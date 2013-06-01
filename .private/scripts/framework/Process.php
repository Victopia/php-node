<?php
/* Process.php | Perform tasks queued on the database. */

namespace framework;

class Process {

  const ERR_EXIST = 1;
  const ERR_EPERM = 2;
  const ERR_ENQUE = 3;

  const MAX_PROCESS = 20;

  // Assume gateway redirection, pwd should always at DOCUMENT_ROOT.
  const EXEC_PATH = 'php .private/Process.php';

  public static function
  /* Boolean */ enqueue($command) {
    $args = explode(' ', $command);

    if ( count($args) == 0 || !self::isExecutable($args[0]) ) {
      throw new \Exception('[Process] Specified file is invalid or not an executable.', self::ERR_EPERM);
      return FALSE;
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

    return self::spawn() ? $res : FALSE;
  }

  /**
   * Return TRUE if the command is already in queue.
   *
   * @param $requeue Boolean If TRUE, the existing path will be put at the back of the queue.
   */
  public static function
  /* Boolean */ enqueueOnce($command, $requeue = FALSE) {
    $res = \node::get(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'path' => $command
      ));

    if ( $res ) {
      if ( $requeue ) {
        \node::delete(array(
            NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
          , 'path' => $command
          , 'locked' => FALSE
          ));

        return self::enqueue($command);
      }

      // throw new \Exception('[Process] Command exists.', self::ERR_EXIST);
      return TRUE;
    }
    else {
      return self::enqueue($command);
    }
  }

  /**
   * Remove target process from queue.
   */
  public static function
  /* void */ kill($process) {
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
      node::delete($res);

      if ( @$res['pid'] ) {
        posix_kill($res['pid'], SIGKILL);
      }
    }
  }

  private static function
  /* void */ spawn() {
    if (\utils::isCLI()) {
      // Already in the process, let the recursive fork do it's job.
      return TRUE;
    }

    $res = \node::get(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'locked' => TRUE
      ));

    if ( count($res) < self::MAX_PROCESS ) {
      shell_exec(self::EXEC_PATH . ' >/dev/null &');
      return TRUE;
    }

    return FALSE;
  }

}