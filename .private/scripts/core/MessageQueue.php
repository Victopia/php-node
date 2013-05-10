<?php
/*! MessageQueue.php | Wrapper class that utilitze msg queue functions. */

namespace core;

/**
 * MessageQueue class.
 *
 * Implements simple send, receive and reply functions among processes.
 *
 * This class utilizes the SharedMemory class to store owners and other information.
 */
class MessageQueue {

/* Mechanics:

1. Messages sent to public will assign their own process ID as $msgtype.
2. Messages with a specific recipient will have target process ID added with 0x1FFFF (131071).
3. Messages larger then msg_qbytes will be broken down to segments.
4. Message segments will be wrapped into arrays, and then recontruct seamlessly in receive().
5. Clients will listen to it's own process ID plus 0x1FFFF (131071).
6. Clients will send with their own process ID.
7. Servers will listen to -0x1FFFF (131071).
8. Servers will reply with target's process ID plus 0x1FFFF (131071).

CAUTION: This might not be compatible to systems with maximum process ID
        larger than 131071, but it should work on most machines.

*/

  const PID_MAX = 0x1FFFF;

  const MSG_HEADER = ' ';

  const MSG_FOOTER = "\004";

  private $ipcFile;

  private $ipc;

  private $autoRemove = FALSE;

  protected $defaultSendOptions = array(
      'retryInterval' => 0.2 // retry sending every 0.2 second
    );

  protected $defaultReceiveOptions = array(
      'truncate' => FALSE    // truncate message or fail the receive
    , 'retryInterval' => 0.2 // retry sending every 0.2 second
    );

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($channel, $autoRemove = FALSE) {
    $this->ipcFile = sys_get_temp_dir() . "/$channel.ipc";

    @touch($this->ipcFile);

    $this->ipc = ftok($this->ipcFile, 'm'); // m for msg

    $this->ipc = msg_get_queue($this->ipc);

    $this->autoRemove = $autoRemove;
  }

  //--------------------------------------------------
  //
  //  Destructor
  //
  //--------------------------------------------------

  public function __destruct() {
    if ($this->autoRemove) {
      $this->destroy();
    }
  }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  /**
   * @private
   *
   * Check whether this process is the queue owner.
   *
  private function isOwner() {
    return $this->shm->mopid == getmypid();
  }
  */

  /**
   * @private
   *
   * Segmenting data and then wraps it.
   *
   * Mechanics:
   * 1. Data is serialized in PHP's way
   * 2. Starts with a space character as a single message
   * 3. Ends with an <EOT> character \u0004 as a single message
   */
  private function serializeData($data) {
    $size = (int) @$this->stat()['msg_qbytes'];

    $data = serialize($data);

    $data = fopen('data://text/plain;base64,' . base64_encode($data), 'r');

    $result = array(self::MSG_HEADER);

    while ($buffer = fread($data, $size)) {
      // if (strlen($buffer) < $size)

      $result[] = $buffer;
    }

    $result[] = self::MSG_FOOTER;

    return $result;
  }

  /**
   * Sends a message to the channel.
   *
   * @param $data Data to be sent, must be serializable by default.
   * @param $options
   *        ['timeout'] Seconds to wait before giving up retrying the message send.
   *        ['retryInterval'] Seconds to wait between each retry.
   */
  public function send($data, $options = NULL) {
    $options = (array) $options + $this->defaultSendOptions;

    $data = $this->serializeData($data);

    // Timeout
    if (is_numeric(@$options['timeout'])) {
      $options['retryInterval']*= 10000;

      while ($data) {
        $segment = array_shift($data);

        $timeout = microtime(1) + doubleval($options['timeout']);

        do {
          $ret = msg_send(
              $this->ipc
            , getmypid()
            , $segment
            , FALSE // serialize
            , TRUE  // blocking
            , $errCode
            );

          if ($ret) {
            continue 2;
          }
          else {
            if ($errCode === MSG_EAGAIN) {
              usleep($options['retryInterval']);
            }
            else {
              // return $errCode; // return other errors, as of PHP 5.4 there are no "other errors" exists.

              return FALSE; // return FALSE on error.
            }
          }
        } while (microtime(1) < $timeout);

        return FALSE; // return FALSE on timeout.
      }

      return TRUE;
    }
    else {
      while ($data) {
        $segment = array_shift($data);

        $ret = msg_send(
            $this->ipc
          , getmypid()
          , $segment
          , FALSE // serialize
          , TRUE  // blocking
          , $errCode
          );

        if (!$ret) {
          return $ret;
        }
      }

      return TRUE;
    }
  }

  /**
   * Receives a message from the channel.
   */
  public function receive($options = NULL) {
    $options = (array) $options + $this->defaultReceiveOptions;

    // Target sender
    if (is_numeric(@$options['target'])) {
      $target = intval($options['target']);
    }
    else {
      $target = -self::PID_MAX;
    }

    $maxsize = (int) @$this->stat()['msg_qbytes'];

    if (!@$options['maxsize'] || $options['maxsize'] > $maxsize) {
      $options['maxsize'] = $maxsize;
    }

    unset($maxsize);

    $flags = $options['truncate'] ? MSG_NOERROR : 0;

    $result = NULL; // NULL means message not started.

    // Timeout
    if (is_numeric(@$options['timeout'])) {
      $options['retryInterval']*= 10000;

      $flags|= MSG_IPC_NOWAIT;

      $buffer = NULL;

      do {
        $timeout = microtime(1) + doubleval($options['timeout']);

        do {
          $ret = msg_receive($this->ipc, $target, $msgtype, (int) $options['maxsize'], $buffer, FALSE, $flags, $errCode);

          if ($ret) {
            if ($buffer === self::MSG_HEADER) {
              // Got a message and is listening to boardcast, listen only to this sender from now on.
              if ($target === -self::PID_MAX) {
                $target = $msgtype;
              }

              $result = '';
            }
            else if ($buffer === self::MSG_FOOTER) {
              return unserialize($result);
            }
            else if ($result !== NULL) { // Only append the message when started, else drop the data.
              $result.= $buffer;
            }
          }
          else {
            if ($errCode === MSG_ENOMSG) {
              usleep($options['retryInterval']);
            }
            else {
              // throw new exceptions\CoreException('Unable to read from msg queue.', $errCode);

              return FALSE; // return FALSE on error.
            }
          }
        } while (microtime(1) < $timeout);

        return FALSE; // return FALSE on timeout.
      } while(1);
    }
    else {
      $buffer = NULL;

      while (1) {
        $ret = msg_receive($this->ipc, $target, $msgtype, (int) $options['maxsize'], $buffer, FALSE, $flags);

        if ($ret) {
          if ($buffer === self::MSG_HEADER) {
            // Got a message and is listening to boardcast, listen only to this sender from now on.
            if ($target === -self::PID_MAX) {
              $target = $msgtype;
            }

            $result = '';
          }
          else if ($buffer === self::MSG_FOOTER) {
            return unserialize($result);
          }
          else if ($result !== NULL) { // Only append the message when started, else drop the data.
            $result.= $buffer;
          }
        }
        else {
          throw new exceptions\CoreException('Unable to read from msg queue.', $errCode);
        }
      }
    }
  }

  public function stat() {
    return msg_stat_queue($this->ipc);
  }

  public function destroy() {
    msg_remove_queue($this->ipc);

    @unlink($this->ipcFile);
  }

}