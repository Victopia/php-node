<?php
/* Process.php | Perform tasks queued on the database. */

namespace framework;

use core\ContentEncoder;
use core\Database;
use core\Log;
use core\Node;
use core\Utility as util;

use Cron\CronExpression;

use framework\exceptions\ProcessException;

class Process {

  //----------------------------------------------------------------------------
  //
  //  Constants
  //
  //----------------------------------------------------------------------------

  const ERR_SUPRT = 1;
  const ERR_EPERM = 2;
  const ERR_ENQUE = 3;
  const ERR_SPAWN = 4;
  const ERR_CEXPR = 5;

  // Assume gateway redirection, pwd should always at DOCUMENT_ROOT
  const EXEC_PATH = 'php .private/scripts/Process.php';

  /**
   * Maximum server capacity.
   *
   * Workers will stop capturing processes when the sum of capacity in active
   * processes exceed this limit.
   */
  const MAXIMUM_CAPACITY = 100;

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  private static $defaultOptions = array(
      '$spawn' => true
    , '$singleton' => false
    , '$requeue' => false
    , '$type' => 'system'
    , '$weight' => 100
    , '$capacity' => 5
    );

  /**
   * Retry number when workers try to capture for a process.
   */
  public static $spawnCaptureCount = 5;

  /**
   * Delay in seconds between capture retry of a worker.
   */
  public static $spawnCaptureInterval = .4;

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Put the specified command into process queue, optionally spawn a daemon to run it.
   *
   * @param {string} $command Command line to be run by the daemon.
   *
   * @param {array} $options Array with the following properties:
   *                $options[$spawn] {bool} Whether to spawn a worker daemon immediately, default true.
   *                $options[$singleton] {bool} Whether to skip the queuing when there is already an exact same command
   *                                           in the process list, default false.
   *                $options[$requeue] {bool} True to remove any previous inactive identical commands before pushing into
   *                                         queue, default false.
   *                $options[$kill] {int} When provided, a signal to be sent to all active identical commands.
   *                $options[$type] {string} Identifier of command queue groups, commands will be drawn and run randomly
   *                                        among groups, one at a time, by the daemon.
   *                $options[$weight] {int} The likeliness of a command to be drawn within the same group, default 1.
   *                $options[$capacity] {float} Percentage of occupation within the same group. To limit maximum active
   *                                           processes within the same group to be 10, set this to 0.1. Default 0.2.
   *                $options[$env] {array} Associative array of properties that will be available when target command starts.
   *                $options[...] Any other values will be set into the process object, which will be accessible by spawn
   *                              processes with Process::get() method.
   */
  public static /* array */
  function enqueue($command, $options = array()) {
    // For backward-compatibility, this parameter is originally $spawnProcess.
    if ( is_bool($options) ) {
      $options = array(
          '$spawn' => $options
        );
    }
    else {
      $options = (array) $options;
    }

    $options = array_filter($options, compose('not', 'is_null'));

    $options+= self::$defaultOptions;

    $process = array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'command' => $command
      ) + array_select($options, array_filter(array_keys($options), compose('not', startsWith('$'))));

    // Remove identical inactive commands
    if ( $options['$requeue'] ) {
      Node::delete(array(
          Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
        , 'command' => $command
        , 'pid' => null
        ));
    }

    // Sends the specified signal to all active identical commands
    if ( is_int(@$options['$kill']) ) {
      if ( !function_exists('posix_kill') ) {
        throw new ProcessException('Platform does not support posix_kill command.', ERR_SUPRT);
      }

      $activeProcesses = Node::get(array(
          Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
        , 'command' => $command
        , 'pid' => '!=null'
        ));

      foreach ( $activeProcesses as $process ) {
        posix_kill($process['pid'], $options['$kill']);
      }

      unset($activeProcesses);
    }

    // Only pushes the command into queue when there are no identical process.
    if ( $options['$singleton'] ) {
      $identicalProcesses = Node::get(array(
          Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
        , 'command' => $command
        ));

      // Process object will be updated
      if ( $identicalProcesses ) {
        $process['id'] = $identicalProcesses[0]['id'];
      }

      unset($identicalProcesses);
    }

    // Copy process related fields.
    foreach ( ['type', 'weight', 'capacity'] as $field ) {
      if ( isset($options["$$field"]) ) {
        $process[$field] = $options["$$field"];
      }
    }

    // Default start time to now
    if ( empty($process['start_time']) || !strtotime($process['start_time']) ) {
      $process['start_time'] = date('c');
    }

    // Push or updates target process.
    $res = Node::set($process);

    if ( $res === false ) {
      throw new ProcessException('Unable to enqueue process.', self::ERR_ENQUE);
    }

    if ( is_numeric($res) ) {
      $process['id'] = $res;
    }

    unset($res);

    $env = (array) @$options['$env'];

    if ( $env ) {
      $env = array('env' => ContentEncoder::json($env));
    }

    unset($options['$env']);

    // Only spawn a worker if target process is not already working.
    if ( @$options['$spawn'] && !@$process['pid'] && !self::spawnWorker($env) ) {
      throw new ProcessException('Unable to spawn daemon worker.', self::ERR_SPAWN);
    }

    return $process;
  }

  /**
   * This function is for backward-compatibility.
   */
  public static /* array */
  function enqueueOnce($command, $spawnProcess = true, $requeue = false, $includeActive = false) {
    $options = array(
        '$singleton' => true
      , '$spawn' => $spawnProcess
      , '$requeue' => $requeue
      );

    if ( $includeActive ) {
      $options['$kill'] = 9; // SIGKILL
    }

    return self::enqueue($command, $options);
  }

  /**
   * Sends a signal to target process with kill.
   */
  public static /* boolean */
  function kill($procId, $signal) {
    $proc = Node::get(array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'id' => (int) $procId
      ));

    $proc = util::unwrapAssoc($proc);

    if ( !@$proc['pid'] ) {
      return false;
    }

    return posix_kill($proc['pid'], $signal);
  }

  /**
   * Puts a command into recursive schduled task, this will be enqueued by
   * cron spawned workers for the next single process.
   *
   * @param {string} $name Unique identifier for this schdule task.
   * @param {string} $expr Cron expression for the schdule.
   * @param {string} $command The command to run.
   * @param {?array} $options This is identical to the option array in enqueue()
   *                          but also includes properties starts with dollar sign.
   */
  public static function schedule($name, $expr, $command, $options = array()) {
    $schedules = Node::get(array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS_SCHEDULE
      , 'name' => $name
      ));
    if ( $schedules ) {
      throw new ProcessException('Schedule with the same name already exists.', self::ERR_ENQUE);
    }

    if ( !CronExpression::isValidExpression($expr) ) {
      throw new ProcessException('Expression is not in valid cron format.', self::ERR_CEXPR);
    }

    return Node::set(array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS_SCHEDULE
      , 'name' => $name
      , 'schedule' => $expr
      , 'command' => $command
      ) + $options);
  }

  /**
   * Remove a scheduled process with specified name.
   */
  public static function unschedule($name) {
    return Node::delete(array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS_SCHEDULE
      , 'name' => $name
      ));
  }

  /**
   * Spawns a daemon worker, this is called automatically when the spawn options is true upon calling enqueue.
   */
  public static /* void */
  function spawnWorker($env = null) {
    $proc = array(
        ['pipe', 'r']
      , ['pipe', 'w']
      , ['pipe', 'w']
      );

    if ( !util::isAssoc($env) ) {
      $env = null;
    }

    $proc = proc_open(self::EXEC_PATH, $proc, $pipes, getcwd(), $env, array(
        'suppress_errors' => true
      , 'bypass_shell' => true
      ));

    if ( $proc === false ) {
      return false;
    }

    $stat = proc_get_status($proc);

    return $stat['pid'];
  }

  /**
   * @private
   *
   * Data cache for current process.
   *
   * Note: Cache can be save to use because only the process itself can update its own data.
   */
  protected static $_processData;

  /**
   * Retrieve process related info by specified property $name.
   *
   * @param {string} $name Target property in process object, omit this to get the whole object.
   */
  public static /* mixed */
  function get($name = null) {
    if ( constant('PHP_SAPI') != 'cli' || !function_exists('posix_getppid') ) {
      return null;
    }

    $processData = &self::$_processData;
    if ( !$processData ) {
      $processData = Node::get(array(
          Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
        , 'pid' => [getmypid(), posix_getppid()] // Both processes should be alive and no collision should ever exists.
        ));

      $processData = util::unwrapAssoc($processData);
    }

    if ( is_null($name) ) {
      return $processData;
    }
    else {
      return @$processData[$name];
    }
  }

  /**
   * Updates process related info of specified property $name.
   *
   * Note: Updates to real table properties are ignored, as they are requried
   * by the process framework.
   */
  public static /* boolean */
  function set($name, $value) {
    Database::lockTables(FRAMEWORK_COLLECTION_PROCESS, FRAMEWORK_COLLECTION_LOG);

    $res = self::get();

    if ( !$res ) {
      Database::unlockTables();
      return false;
    }

    $readOnlyFields = ['id', 'command', 'type', 'weight', 'pid', 'timestamp'];

    if ( in_array($name, $readOnlyFields) ) {
      Database::unlockTables();
      return false;
    }

    if ( is_null($value) ) {
      unset($res[$name]);
    }
    else {
      $res[$name] = $value;
    }

    unset($res['timestamp']);

    $ret = Node::set($res);

    Database::unlockTables();

    // Clear data cache
    self::$_processData = null;

    return $ret;
  }
}
