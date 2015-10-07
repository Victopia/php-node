<?php
/* Process.php | Daemon dequeues and executes processes from the database. */

require_once(__DIR__ . '/Initialize.php');

use core\ContentDecoder;
use core\Database;
use core\Log;
use core\Node;
use core\Utility;

use Cron\CronExpression;

use framework\Configuration as conf;
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
      // Delete normal orphan processes
      $res = Database::query('DELETE FROM `'.FRAMEWORK_COLLECTION_PROCESS.'`
        WHERE `type` NOT IN (\'permanent\', \'cron\')
          AND `pid` IS NOT NULL
          AND `pid` NOT IN ('.Utility::fillArray($pids).')', $pids);
      if ( $res ) {
        $affectedRows+= $res->rowCount();
      }

      // Delete cron process only when current time is ahead of start_time
      $res = Database::query('DELETE FROM `'.FRAMEWORK_COLLECTION_PROCESS.'`
        WHERE `type` = \'cron\'
          AND `pid` = 0
          AND `pid` NOT IN ('.Utility::fillArray($pids).')', $pids);
      if ( $res ) {
        $affectedRows+= $res->rowCount();
      }

      // Clear pid of dead permanent process
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
      Log::debug(sprintf('Process cleanup, %d processes removed.', $affectedRows));
    }

    die;
  }

// Cron processes
  if ( @$opts['cron'] ) {
    Log::debug('Cron started process.');

    $scheduler = function($schedule) {
      $nextTime = CronExpression::factory($schedule['schedule'])->getNextRunDate()->format('Y-m-d H:i:s');

      /*! Note
       *  The purpose of schedule_name alias is to use a less common name, and
       *  leave the "name" property open for in-process use.
       */
      $schedules = Node::get(array(
          Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
        , 'start_time' => $nextTime
        , 'command' => $schedule['command']
        , 'schedule_name' => $schedule['name']
        ));
      if ( !$schedules ) {
        $schedule =
          [ 'command' => $schedule['command']
          , 'start_time' => $nextTime
          , 'schedule_name' => $schedule['name']
          , '$type' => 'cron'
          , '$spawn' => false
          ];

        Log::debug('Scheduling new process', $schedule);

        Process::enqueue($schedule['command'], $schedule);
      }
    };

    // Configuration based crontab
    array_map($scheduler, (array) conf::get('crontab::schedules'));
  }

// Avoid forking connection crash, renew the connection.
  Database::disconnect();

// Forks then exits the parent.
  // Use nohup if internal forking is not supported.
  if ( !function_exists('pcntl_fork') ) {
    $ret = shell_exec('nohup ' . Process::EXEC_PATH . ' --nohup >/dev/null 2>&1 & echo $!');

    if ( !$ret ) {
      Log::error('Process cannot be spawn, please review your configuration.');
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

  // unset($pid);

  // parent will die here
  if ( !$forked ) {
    exit(1);
  }

// Avoid forking connection crash, renew the connection.
  Database::disconnect();

// Get maximum allowed connections before table lock.
  $capLimit = (int) @conf::get('system::crontab.process_limit');
  if ( !$capLimit ) {
    $capLimit = Process::MAXIMUM_CAPACITY;
  }

// Start transaction before lock tables.
  Database::beginTransaction();

// Pick next awaiting process
  Database::locKTables([
    FRAMEWORK_COLLECTION_PROCESS . ' READ'
  ]);

  $res = (int) Database::fetchField('SELECT IFNULL(SUM(`capacity`), 0) as occupation
    FROM `' . FRAMEWORK_COLLECTION_PROCESS . '`
    WHERE `pid` IS NOT NULL AND `pid` > 0');

  Database::unlockTables(true);

  if ( $res >= $capLimit ) {
    Log::debug('Active processes has occupied maximum server capacity, daemon exits.');

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
    ORDER BY `occupation`, `weight` DESC, `id`
    LIMIT 1;');

  // No waiting jobs in queue.
  if ( !$process ) {
    Database::unlockTables(true);

    Database::rollback();

    Log::debug('No more jobs to do, suicide.');

    die;
  }

  $processContents = (array) ContentDecoder::json($process[Node::FIELD_VIRTUAL], 1);

  unset($process[Node::FIELD_VIRTUAL]);

  $process+= $processContents;

  unset($processContents);

  $res = Database::query('UPDATE `' . FRAMEWORK_COLLECTION_PROCESS . '` SET `pid` = ?
    WHERE `id` = ? AND `pid` IS NULL LIMIT 1',
    [ getmypid(), $process['id'] ]);

// Commit transaction
  Database::unlockTables(true);

  Database::commit();

  if ( $res->rowCount() < 1 ) {
    Log::warning('Unable to update process pid, worker exits.');

    die;
  }
  else {
    $process['pid'] = getmypid();
    $process[Node::FIELD_COLLECTION] = FRAMEWORK_COLLECTION_PROCESS;
  }

// Check if $env specified in option
  if ( @$_SERVER['env'] ) {
    $_SERVER['env'] = ContentDecoder::json($_SERVER['env'], 1);
  }

// More debug logs
  Log::debug("Execute process: $process[command]");

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
      usleep(Process::$spawnCaptureInterval * 1000000);
    }
  }
  while ( ++$retryCount < Process::$spawnCaptureCount );

  if ( !$proc ) {
    throw new ProcessException('Unable to spawn process, please check error logs.');
  }

// Log the process output if available
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);

  if ( "$stdout$stderr" ) {
    $method = $stderr ? 'error' : 'info';

    Log::$method(sprintf('Output captured from command line: %s', $process['command']),
      array_filter(array('stdout' => $stdout, 'stderr' => $stderr)));

    unset($method);
  }

  unset($stdout, $stderr);

// Handles cleanup after process exit
  switch ( strtolower($process['type']) ) {
    // Permanent processes will be restarted upon death
    case 'permanent':
      core\Database::query('UPDATE `'.FRAMEWORK_COLLECTION_PROCESS.'` SET `pid` = NULL WHERE `id` = ?', $process['id']);

      Log::debug('Permanent process died, clearing pid.', [$res, $process]);
      break;

    // Sets pid to 0, prevents it fire again and double enqueue of the same time slot.
    case 'cron':
      core\Database::query('UPDATE `'.FRAMEWORK_COLLECTION_PROCESS.'` SET `pid` = 0 WHERE `id` = ?', $process['id']);
      break;

    // Deletes the process object upon exit
    default:
      $process = array_select($process, array(Node::FIELD_COLLECTION, 'id', /*'type', 'weight', 'capacity', */'pid'));

      $res = Node::delete($process);

      Log::debug("Deleting finished process, affected rows: $res.", [$res, $process]);

      break;
  }

// Recursive process, spawn another worker.
  Process::spawnWorker(@$_SERVER['env']);
