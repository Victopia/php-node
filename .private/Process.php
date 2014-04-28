<?php
/* Process.php | Daemon dequeues and executes processes from the database. */

require_once('scripts/Initialize.php');

$opts = (new optimist())
  ->options('nohup', array(
      'alias' => 'n'
    , 'describe' => 'Force nohup process spawn.'
    ))
  ->options('cron', array(
      'alias' => 'c'
    , 'describe' => 'Indicates the process is invoked from cron job, only affects the log.'
    ))
  ->argv;

// Do periodic cleanup
// Check orphaned process in process table and delete it.
if ( @$opts['cleanup'] ) {
  $res = array_filter(array_map(function($line) {
    if ( preg_match('/^\w+\s+(\d+)/', $line, $matches) ) {
      return (int) $matches[1];
    }

    return null;
  }, explode("\n", `ps aux | grep php`)));

  if ( $res ) {
    $res = core\Database::query('DELETE FROM `'.FRAMEWORK_COLLECTION_PROCESS.'`
      WHERE `pid` IS NOT NULL AND `pid` NOT IN ('.utils::fillArray($res).')', $res);

    $res = $res->rowCount();
  }
  else {
    $res = 0;
  }

  log::write(sprintf('Process cleanup, %d processes removed.', $res));

  die;
}

// use nohup if internal forking is not supported.
if ( !function_exists('pcntl_fork') ) {
  $ret = shell_exec('nohup ' . process::EXEC_PATH . ' --nohup >/dev/null 2>&1 & echo $!');

  if ( !$ret ) {
    log::write('Process cannot be spawn, please review your configuration.', 'Error');
  }

  die;
}
elseif ( @$opts['n'] ) {
  $pid = 0; // fork mimic.
}
else {
  $pid = pcntl_fork();
}

// parent: $forked == false
// child:  $forked == true
$forked = $pid == 0;

// parent will die here.
if ( !$forked ) {
  exit($pid);
}

if ( function_exists('posix_setsid') ) {
  posix_setsid();
}

if ( @$opts['cron'] ) {
  log::write('Cron started process.', 'Information');
}

core\Database::lockTables(array(
    FRAMEWORK_COLLECTION_PROCESS
  , FRAMEWORK_COLLECTION_LOG
  ));

// Get working processes.
$res = Node::get(array(
    NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
  , 'locked' => true
  ));

if ( count($res) >= process::MAX_PROCESS ) {
  log::write('Forking exceed MAX_PROCESS, suicide.', 'Notice');
  die;
}

$res = node::get(array(
    NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
  , 'locked' => false
  ));

// No waiting jobs in queue.
if ( !$res ) {
  $res = node::get(FRAMEWORK_COLLECTION_PROCESS);

  // Not even locked jobs, let's reset the Process ID with truncate.
  if ( !$res ) {
    core\Database::unlockTables();
    core\Database::query('TRUNCATE `' . FRAMEWORK_COLLECTION_PROCESS . '`');
  }

  log::write('No more jobs to do, suicide.', 'Debug');
  die;
}

$process = $res[0];

$process['locked'] = true;
$process['pid'] = getmypid();

Node::set($process);

core\Database::unlockTables();

$path = $process['path'];

log::write("Running process: $path", 'Debug', $pid);

// Kick off the process

$output = null;
$retryCount = 0;
$processSpawn = false;

/* Added @ 3 Sep, 2013
   Explicity release the database connection before spawning concurrent processes.

   A new connection will be created on-demand afterwards.
*/
core\Database::disconnect();

while (!$processSpawn && $retryCount++ < FRAMEWORK_PROCESS_SPAWN_RETRY_COUNT) {
  try {
    $output = `$path 2>&1`;

    $processSpawn = true;
  }
  catch (ErrorException $e) {
    log::write("Error spawning child process, retry count $retryCount.", 'Warning', array(
        'path' => $path
      , 'error' => $e));

    // Wait awhile before retrying.
    usleep(FRAMEWORK_PROCESS_SPAWN_RETRY_INTERVAL * 1000000);
  }
}

// core\Database::reconnect();

if ( !$processSpawn ) {
  log::write('Unable to spawn process, process terminating.', 'Error');
}

unset($process['locked'], $process['path']);

if ( $output !== null ) {
  log::write("Output captured from command line $path:\n" . print_r($output, 1), 'Warning');
}

// Finally, delete the process
core\Database::lockTables(array(
    FRAMEWORK_COLLECTION_PROCESS
  , FRAMEWORK_COLLECTION_LOG
  ));

$res = node::delete($process);

log::write("Deleting finished process, affected rows: $res.", 'Debug', $process);

core\Database::unlockTables();

// Recursive process, fork another child and spawn itself.
if ( !function_exists('pcntl_fork') || pcntl_fork() === 0 ) {
  shell_exec(process::EXEC_PATH . ' >/dev/null &');
}
