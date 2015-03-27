<?php
/* Process.php | Daemon dequeues and executes processes from the database. */

require_once('scripts/Initialize.php');

use core\Database;
use core\Log;
use core\Node;
use core\Utility;

use framework\ProcessPool;
use framework\Configuration;
use framework\Process;

use framework\exceptions\ProcessException;

$opts = (new framework\Optimist)
  ->options('nohup', array(
      'alias' => 'n'
    , 'describe' => 'Force nohup process spawn.'
    ))
  ->options('cron', array(
      'alias' => 'c'
    , 'describe' => 'Indicates the process is invoked from cron job, only affects the log.'
    ))
  ->argv;

// Process cleanup
  if ( @$opts['cleanup'] ) {
    $affectedRows = 0;

    // Check orphaned process in process table and delete it.
    $pids = array_filter(array_map(function($line) {
      if ( preg_match('/^\w+\s+(\d+)/', $line, $matches) ) {
        return (int) $matches[1];
      }

      return null;
    }, explode("\n", `ps aux | grep 'php\\|node'`)));

    if ( $pids ) {
      $res = Database::query('DELETE FROM `'.FRAMEWORK_COLLECTION_PROCESS.'`
        WHERE `type` != \'permanent\' AND `pid` IS NOT NULL AND `pid` NOT IN ('.Utility::fillArray($pids).')', $pids);

      if ( $res ) {
        $affectedRows+= $res->rowCount();
      }

      $res = Database::query('UPDATE `'.FRAMEWORK_COLLECTION_PROCESS.'` SET `pid` = NULL
        WHERE `type` = \'permanent\' AND `pid` IS NOT NULL AND `pid` NOT IN ('.Utility::fillArray($pids).')', $pids);

      if ( $res ) {
        $affectedRows+= $res->rowCount();
      }
    }

    unset($res, $pids);

    // $res = Database::query('DELETE FROM `'.FRAMEWORK_COLLECTION_PROCESS.'`
    //   WhERE `timestamp` < CURRENT_TIMESTAMP - INTERVAL 30 MIN');

    // $affectedRows+= $res->rowCount();

    if ( $affectedRows ) {
      Log::write(sprintf('Process cleanup, %d processes removed.', $affectedRows));
    }

    die;
  }

// Forks then exits the parent.
  // Use nohup if internal forking is not supported.
  if ( !function_exists('pcntl_fork') ) {
    $ret = shell_exec('nohup ' . Process::EXEC_PATH . ' --nohup >/dev/null 2>&1 & echo $!');

    if ( !$ret ) {
      Log::write('Process cannot be spawn, please review your configuration.', 'Error');
    }

    die;
  }
  elseif ( @$opts['n'] ) {
    $pid = 0; // fork mimic
  }
  else {
    $pid = pcntl_fork();
  }

  // parent: $forked == false
  // child:  $forked == true
  $forked = $pid == 0;

  unset($pid);

  // parent will die here
  if ( !$forked ) {
    exit(1);
  }

// Avoid forking connection crash, renew the connection.
  Database::disconnect();

// Logs for cron processes
  if ( @$opts['cron'] ) {
    Log::write('Cron started process.', 'Debug');
  }

// Get maximum allowed connections before table lock.
  $capLimit = (int) Configuration::get('core.Net::maxConnections');

  if ( !$capLimit ) {
    $capLimit = FRAMEWORK_PROCESS_MAXIMUM_CAPACITY;
  }

// Start transaction before lock tables.
  Database::beginTransaction();

// Pick next awaiting process
  Database::locKTables([
    FRAMEWORK_COLLECTION_PROCESS . ' READ'
  ]);

  $res = (int) Database::fetchField('SELECT IFNULL(SUM(`capacity`), 0) as capacity
    FROM `' . FRAMEWORK_COLLECTION_PROCESS . '`
    WHERE `pid` IS NOT NULL');

  Database::unlockTables(true);

  if ( $res >= $capLimit ) {
    Log::write('Active processes has occupied maximum server capacity, daemon exits.', 'Debug');

    Database::rollback();

    die;
  }

  unset($res, $capLimit);

  Database::lockTables(array(
      FRAMEWORK_COLLECTION_PROCESS . ' LOW_PRIORITY WRITE'
    , FRAMEWORK_COLLECTION_PROCESS . ' as `active` LOW_PRIORITY WRITE'
    , FRAMEWORK_COLLECTION_PROCESS . ' as `inactive` LOW_PRIORITY WRITE'
    ));

  $process = Database::fetchRow('SELECT `inactive`.* FROM `'. FRAMEWORK_COLLECTION_PROCESS .'` as `inactive`
    LEFT JOIN ( SELECT `type`, SUM(`capacity`) as `occupation` FROM `' . FRAMEWORK_COLLECTION_PROCESS . '`
        WHERE `pid` IS NOT NULL GROUP BY `type` ) as `active`
      ON `active`.`type` = `inactive`.`type`
    WHERE `inactive`.`timestamp` <= CURRENT_TIMESTAMP
      AND `start_time` <= CURRENT_TIMESTAMP
      AND `inactive`.`pid` IS NULL
    ORDER BY `occupation`, `weight` DESC, `ID`
    LIMIT 1;');

  // No waiting jobs in queue.
  if ( !$process ) {
    Database::unlockTables(true);

    Database::rollback();

    Log::write('No more jobs to do, suicide.', 'Debug');

    die;
  }

  $processContents = (array) json_decode($process[NODE_FIELD_VIRTUAL], 1);

  unset($process[NODE_FIELD_VIRTUAL]);

  $process+= $processContents;

  unset($processContents);

  $res = Database::query('UPDATE `' . FRAMEWORK_COLLECTION_PROCESS . '` SET `pid` = ?
    WHERE `ID` = ? AND `pid` IS NULL LIMIT 1',
    [ getmypid(), $process['ID'] ]);

// Commit transaction
  Database::unlockTables(true);

  Database::commit();

  if ( $res->rowCount() < 1 ) {
    Log::write('Unable to update process pid, worker exits.');

    die;
  }
  else {
    $process['pid'] = getmypid();
    $process[NODE_FIELD_COLLECTION] = FRAMEWORK_COLLECTION_PROCESS;
  }

// Check if $env specified in option
  if ( @$_SERVER['env'] ) {
    $_SERVER['env'] = json_decode($_SERVER['env'], 1);
  }

// More debug logs
  Log::write("Executed process: $process[command]", 'Debug');

// Spawn process and retrieve the pid
  $proc = false;

  do {
    $proc = proc_open($process['command'], array(
        array('pipe', 'r')
      , array('pipe', 'w')
      , array('pipe', 'e')
      ), $pipes, null, @$_SERVER['env']);

    if ( $proc ) {
      break;
    }
    else {
      usleep(FRAMEWORK_PROCESS_SPAWN_RETRY_INTERVAL);
    }
  }
  while ( ++$retryCount < FRAMEWORK_PROCESS_SPAWN_RETRY_COUNT );

  if ( !$proc ) {
    throw new ProcessException('Unable to spawn process, please check error logs.');
  }

// Log the process output if available
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);

  if ( "$stdout$stderr" ) {
    Log::write(sprintf('Output captured from command line: %s', $process['command']),
      $stderr ? 'Error' : 'Information', array_filter(array('stdout' => $stdout, 'stderr' => $stderr)));
  }

  unset($stdout, $stderr);

// Handles cleanup after process exit
  switch ( strtolower($process['type']) ) {
    // Permanent processes will be restarted upon death
    case 'permanent':
      core\Database::query('UPDATE `'.FRAMEWORK_COLLECTION_PROCESS.'` SET `pid` = NULL WHERE `ID` = ?', $process['ID']);

      Log::write('Permanent process died, clearing pid.', 'Debug', [$res, $process]);
      break;

    // Deletes the process object upon exit
    default:
      $process = array_select($process, array(NODE_FIELD_COLLECTION, 'ID', /*'type', 'weight', 'capacity', */'pid'));

      $res = Node::delete($process);

      Log::write("Deleting finished process, affected rows: $res.", 'Debug', [$res, $process]);

      ProcessPool::SplitProcessCommmand2($process['ID'], $process['pid']);

      break;
  }

// Recursive process, spawn another worker.
  Process::spawnWorker(@$_SERVER['env']);
