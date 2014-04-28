<?php
/* Process.php | Perform tasks queued on the database. */

namespace framework;

class Process {

  const ERR_EXIST = 1;
  const ERR_EPERM = 2;
  const ERR_ENQUE = 3;

  const MAX_PROCESS = 100;

  // Assume gateway redirection, pwd should always at DOCUMENT_ROOT.
  const EXEC_PATH = '/usr/bin/php .private/Process.php';

  public static /* Boolean */
  function enqueue($command, $spawnProcess = TRUE) {
    $args = explode(' ', $command);

    if ( !$args ) {
      throw new \Exception('[Process] Specified file is invalid or not an executable.', self::ERR_EPERM);
    }

    $res = \Node::set(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'status' => NULL
      , 'path' => $command
      ));

    if ( $res === FALSE ) {
      throw new \Exception('[Process] Unable to enqueue process.', self::ERR_ENQUE);
    }

    /* Added by Vicary @ 20 Nov, 2012
        Wait until the process is written into database.
    */
    if ( is_numeric($res) ) {
      $res = array(
          NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
        , 'ID' => $res
        );

      $retryCount = 0;

      while ( !\node::get($res) && $retryCount++ < FRAMEWORK_PROCESS_INSERT_RETRY_COUNT ) {
        usleep(FRAMEWORK_DATABASE_ENSURE_WRITE_INTERVAL * 1000000);
      }
    }

    if ( !$spawnProcess ) {
      return TRUE;
    }

    $ret = self::spawn();

    if ( $ret ) {
      $res['pid'] = $ret;
    }

    return $res;
  }

  /**
   * Call enqueue when there is no identical process already in queue,
   * otherwise return the process descriptor instead.
   *
   * @param {String} Command to be executed.
   * @param {Boolean} $spawnProcess FALSE to not spawn the queued
   *                                process right away, default TRUE.
   * @param {Boolean} $requeue If TRUE, the existing path will be put
   *                           at the back of the queue.
   * @param {Boolean} $includeActive Specify TRUE to include active
   *                                 processes when considering whether
   *                                 the same process is already in queue.
   */
  public static /* Boolean */
  function enqueueOnce($command, $spawnProcess = TRUE, $requeue = FALSE, $includeActive = FALSE) {
    \core\Database::lockTables(array(
        FRAMEWORK_COLLECTION_LOG
      , FRAMEWORK_COLLECTION_PROCESS
      ));

    $res = array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'path' => $command
      );

    if ( !$includeActive ) {
      $res['locked'] = FALSE;
    }

    $res = \node::get($res);

    /* Quoted by Eric @ 12 Jul, 2013
       Multiple processes could be queued at this moment,
       kill all of them by not unwrapping here.
    */
    // \utils::unwrapAssoc($res);

    if ( $res ) {
      if ( $requeue ) {
        // kill only those has a pid.
        $res = array_filter($res, prop('pid'));

        array_walk($res, compose('Process::kill', prop('ID')));

        \node::delete(array(
            NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
          , 'path' => $command
          , 'locked' => $includeActive
          ));

        return self::enqueue($command, $spawnProcess);
      }

      \core\Database::unlockTables();

      // throw new \Exception('[Process] Command exists.', self::ERR_EXIST);
      return $res;
    }
    else {
      \core\Database::unlockTables();

      return self::enqueue($command, $spawnProcess);
    }
  }

  /**
   * Remove target process from queue.
   *
   * Killing signal defaults to SIGKILL.
   */
  public static /* void */
  function kill($process, $signal = 9) {
    $res = array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      );

    if ( is_numeric($process) ) {
      $res['ID'] = intval($process);
    }
    else {
      $res['path'] = $process;
    }

    $res = \node::get($res);

    if ( $res ) {
      array_walk($res, 'Node::delete');

      \log::write('Killing processes.', 'Debug', $res);

      array_walk($res, function($process) use($signal) {
        if ( @$process['pid'] && function_exists('posix_kill') ) {
          \log::write("Sending SIGKILL to pid $process[pid].", 'Debug');

          posix_kill(-$process['pid'], $signal);
        }
      });
    }
  }

  private static /* void */
  function spawn() {
    $res = \node::get(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'locked' => TRUE
      ));

    if ( count($res) < self::MAX_PROCESS ) {
      return (int) shell_exec(self::EXEC_PATH . ' >/dev/null & echo $?');
    }

    return FALSE;
  }

}
