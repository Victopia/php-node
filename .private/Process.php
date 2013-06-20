<?php
/* Process.php | Daemon dequeues and executes processes from the database. */

require_once('.private/scripts/Initialize.php');

$opts = (new optimist())
  ->alias('nohup', 'n')
  ->alias('cron', 'c')
  ->argv;

if ( !function_exists('pcntl_fork') ) {
  // try nohup if internal forking is not supported.
  if ( !isset($opts['n']) ) {
    $ret = shell_exec('nohup ' . process::EXEC_PATH . ' --nohup >/dev/null 2>&1 & echo $!');

    if ( !$ret ) {
      log::write('Process cannot be spawn, try configuration.', 'Error');
    }

    die;
  }

  $pid = 0;
}
else {
  $pid = pcntl_fork();
}

// parent: $forked == FALSE
// child:  $forked == TRUE
$forked = $pid == 0;

// parent will die here.
if ( !$forked ) {
  die;
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
  , 'locked' => TRUE
  ));

if ( count($res) >= process::MAX_PROCESS ) {
  log::write('Forking exceed MAX_PROCESS, suicide.', 'Notice');
  die;
}

$res = node::get(array(
    NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
  , 'locked' => FALSE
  ));

// No waiting jobs in queue.
if ( !$res ) {
  $res = node::get(FRAMEWORK_COLLECTION_PROCESS);

  // Not even locked jobs, let's reset the Process ID with truncate.
  if ( !$res ) {
    core\Database::unlockTables();
    core\Database::query('TRUNCATE `' . FRAMEWORK_COLLECTION_PROCESS . '`');
  }

  log::write('No more jobs to do, suicide.', 'Information');
  die;
}

$process = $res[0];

$process['locked'] = TRUE;
$process['pid'] = getmypid();

Node::set($process);

core\Database::unlockTables();

$path = $process['path'];

log::write("Running process: $path", 'Notice', $pid);

// Kick off the process

$output = NULL;
$retryCount = 0;
$processSpawn = FALSE;

while (!$processSpawn && $retryCount++ < FRAMEWORK_PROCESS_SPAWN_RETRY_COUNT) {
  try {
    $output = `$path 2>&1`;

    $processSpawn = TRUE;
  }
  catch (ErrorException $e) {
    log::write("Error spawning child process, retry count $retryCount.", 'Warning', array(
        'path' => $path
      , 'error' => $e));

    // Wait awhile before retrying.
    usleep(FRAMEWORK_PROCESS_SPAWN_RETRY_INTERVAL * 10000);
  }
}

if ( !$processSpawn ) {
  log::write('Unable to spawn process, process terminating.', 'Error');
}

unset($process['locked']);

if ( $output !== NULL ) {
  log::write("Output captured from command line $path:\n" . print_r($output, 1), 'Warning');
}

// Finally, delete the process
core\Database::lockTables(array(
    FRAMEWORK_COLLECTION_PROCESS
  , FRAMEWORK_COLLECTION_LOG
  ));

$res = node::delete($process);

log::write("Deleting finished process, affected rows: $res.", 'Information', $process);

core\Database::unlockTables();

// Recursive process, fork another child and spawn itself.
if ( !function_exists('pcntl_fork') || pcntl_fork() === 0 ) {
  shell_exec(process::EXEC_PATH . ' >/dev/null &');
}