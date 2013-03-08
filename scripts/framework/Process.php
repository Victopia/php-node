<?php
/*! Process.php
 *
 *  Perform tasks queued on the database.
 */

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

    if (count($args) == 0 || !self::isExecutable($args[0])) {
      throw new \Exception('[Process] Specified file is invalid or not an executable.', self::ERR_EPERM);
      return FALSE;
    }

    $res = \Node::set(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'status' => NULL
      , 'path' => $command
      ));

    if ($res === FALSE) {
      throw new \Exception('[Process] Unable to enqueue process.', self::ERR_ENQUE);
      return FALSE;
    }

    /* Added by Eric @ 20 Nov, 2012
        Wait until the process is written into database.
    */
    if (is_numeric($res)) {
      $res = array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'ID' => $res
      );

      $retryCount = 0;

      while (!\node::get($res) && $retryCount++ < FRAMEWORK_PROCESS_INSERT_RETRY_COUNT) {
        usleep(FRAMEWORK_DATABASE_ENSURE_WRITE_INTERVAL * 1000000);
      }
    }

    return self::spawnProcess();
  }

  /**
   * Return TRUE if the command is already in queue.
   *
   * @param $requeue Boolean If TRUE, the existing path will be put at the back of the queue.
   */
  public static function
  /* Boolean */ enqueueOnce($command, $requeue = FALSE) {
    $res = \Node::get(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'path' => $command
      ));

    if (count($res) > 0) {
      // throw new \Exception('[Process] Command exists.', self::ERR_EXIST);
      return TRUE;
    }
    else {
      return self::enqueue($command);
    }
  }

  private static function
  /* void */ spawnProcess() {
    if (\utils::isCLI()) {
      // Already in the process, let the recursive fork do it's job.
      return TRUE;
    }

    $res = \node::get(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_PROCESS
      , 'locked' => TRUE
      ));

    if ( !self::isExecutable(self::EXEC_PATH) ) {
      throw new \Exception('[Process] Daemon file not executable, please check permission!');
      return FALSE;
    }

    if ( count($res) < self::MAX_PROCESS ) {
      shell_exec(self::EXEC_PATH . ' >/dev/null &');
      return TRUE;
    }

    return FALSE;
  }

  public static function
  /* Boolean */ isExecutable($command) {
    if (is_executable($command)) {
      return TRUE;
    }
    else {
      $strpos = strpos($command, '/');

      $command = explode(' ', $command, 2);
      $command = $command[0];

      if (($strpos === FALSE || $strpos !== 0) &&
          !preg_match('/^(:?\.\.?\/)/', $command)) {
        $PATH = getenv('PATH');

        if (!$PATH) {
          return FALSE;
        }

        $PATH = explode(':', $PATH);

        foreach ($PATH as $path) {
          if (is_executable("$path/$command")) {
            return TRUE;
          }
        }

        return FALSE;
      }
    }
  }

}