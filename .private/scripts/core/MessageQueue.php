<?php
/*! MessageQueue.php | Wrapper class that utilitze msg queue functions. */

/*** CAUTION ***

  This class is experimental, and is using home brew protocols.

  It is experiencing a lot of limitations, and is considered
  not very viable is most cases. This class is simply put aside
  from on-going development.

  If you think of any good use with IPC messaging, feel free to
  let me know and I will consider reworking this.

  Contact me at: Vicary Archangel <vicary@victopia.org>

*/

namespace core;

/**
 * MessageQueue class.
 *
 * Implements simple send, receive and reply functions among processes.
 *
 * This class utilizes the SharedMemory class to store owners and other information.
 */
class MessageQueue {

/* Note by Vicary @ 15 May, 2013

Mechanics:

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

  TODO: The following handshake logic is to be implemented.

  Messages must have a connecting end and receiving end.

  1. Enquiry: (Connect side)
      First character: "\005" (Enquiry)
      Optionally followed by target PID, boardcast message if omitted.

  2. Acknowledge: (Listen side)
      Only character: "\006" (Acknowledgement)
      Listen side must send this, after receiving an enquiry.
      Connect side must receive this before starting the message content.

  3. Sends the message.
      3.1 Starts with a message contains a single character "\002" (Start of text)
      3.2 Data must be segmented into parts not larger than maximum allowed bytes
          in the message queue (msg_qbytes)
      3.3 Transmission ends with a message contains a single character "\003"
          (End of text)

  4. Transmission should not start when no ACK "\006" is received.
      When a timeout is reached in this stage, sender should try to retract the
      enquiry sent.

  5. When timeout is reached during a transmission, sender should try to retract
      all segments of the message sent.

*/

  const PID_MAX = 0x1FFFF;

  const MSG_ENQ = "\005";
  const MSG_ACK = "\006";
  const MSG_HEADER = "\002";
  const MSG_FOOTER = "\003";

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
  private function serializeData($data, $timestamp = NULL) {
    $size = (int) @$this->stat()['msg_qbytes'];

    if ($data instanceof MessageChannel) {
      $data = $data->message;
    }

    $data = serialize($data);

    $data = fopen('data://text/plain;base64,' . base64_encode($data), 'r');

    $result = array(self::MSG_HEADER);

    while ($buffer = fread($data, $size)) {
      // if (strlen($buffer) < $size)

      $result[] = $buffer;
    }

    $result[] = (microtime(1) * 10000) . self::MSG_FOOTER;

    return $result;
  }

  /**
   * @private
   *
   * Unsent messages on timeout or error, should be retracted by the sender.
   */
  private function retractMessage($type) {
    // retract all message segments on timeout,
    // to reserving queue space for other processes.
    while (msg_receive(
        $this->ipc
      , $type
      , $out_msgtype
      , 1            // 1 byte and MSG_NOERROR, just throw all messages of that type away.
      , $data
      , FALSE
      , MSG_IPC_NOWAIT | MSG_NOERROR
      ));
  }

  /**
   * Sends a message to the channel.
   *
   * @param $data Data to be sent, must be serializable by default.
   * @param $options
   *        ['reply'] Target PID to reply to, this will be added
   *                  with PID_MAX before send.
   *        ['timeout'] Seconds to wait before giving up retrying
   *                    the message send.
   *        ['retryInterval'] Seconds to wait between each retry.
   */
  public function send($data, $options = NULL) {
    $options = ((array) $options) + $this->defaultSendOptions;

    $data = $this->serializeData($data, @$options['timestamp']);

    if (is_numeric(@$options['reply'])) {
      $msgtype = intval($options['reply']) + self::PID_MAX;
    }
    else {
      $msgtype = getmypid();
    }

    $options['retryInterval']*= 1000000;

    // Timeout
    if (is_numeric(@$options['timeout'])) {
      $timeout = microtime(1) + doubleval($options['timeout']);
    }
    else {
      $timeout = PHP_INT_MAX;
    }

    // Transmission
    while ($data) {
      $segment = array_shift($data);

      do {
        $ret = @msg_send(
            $this->ipc
          , $msgtype
          , $segment
          , FALSE // serialize
          , FALSE  // blocking
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
            $this->retractMessage($msgtype);

            return FALSE; // return FALSE on error.
          }
        }
      } while (microtime(1) < $timeout);

      $this->retractMessage($msgtype);

      return FALSE; // return FALSE on timeout.
    }

    return TRUE;
  }

  /**
   * Receives a message from the channel.
   *
   * @param $options
   *        ['reply'] Waiting for replies, will listen to self PID
   *                  plus PID_MAX, instead of -PID_MAX.
   *        ['timeout'] Seconds to wait before giving up retrying
   *                    the message send.
   *        ['retryInterval'] Seconds to wait between each retry.
   *        ['maxsize'] Maximum size of target msg, cannot be
   *                    larger than msg_qbytes of stat().
   *        ['truncate'] TRUE to append MSG_NOERROR and trim msg
   *                     bytes exceeded ['maxsize'].
   */
  public function receive($options = NULL) {
    $options = (array) $options + $this->defaultReceiveOptions;

    // Target sender
    if (@$options['reply']) {
      $target = getmypid() + self::PID_MAX;
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

    $options['retryInterval']*= 1000000;

    // Timeout
    if (is_numeric(@$options['timeout'])) {
      $timeout = microtime(1) + doubleval($options['timeout']);
    }
    else {
      $timeout = PHP_INT_MAX;
    }

    // Transmission
    $flags|= MSG_IPC_NOWAIT;

    $buffer = NULL;

    while(1) {
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
          else if (preg_match('/^(\d+)'.self::MSG_FOOTER.'$/', $buffer, $matches)) {
            if (@$options['reply']) {
              $target -= self::PID_MAX;
            }

            return new MessageChannel(unserialize($result), $target, (int) @$matches[1], $this);
          }
          else if ($result !== NULL) { // Only append the message when started, else drop the data.
            $result.= $buffer;
          }

          unset($matches);
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

class MessageChannel {

  private $msgQueue;

  private $sender;

  private $message;

  private $timestamp; // When replying, this will be the same as the message sent.

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($message, $sender = NULL, $timestamp = NULL, $queue = NULL) {
    $this->msgQueue = $queue;
    $this->sender = $sender;
    $this->message = $message;
    $this->timestamp = $timestamp;
  }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  public function reply($data, $options = NULL) {
    $options = (array) $options;

    $options['reply'] = $this->sender;
    $options['timestamp'] = $this->timestamp;

    $this->msgQueue->send($data, $options);
  }

  public function __get($name) {
    switch ($name) {
      case 'sender':
      case 'message':
      case 'timestamp':
        return $this->$name;
        break;
    }
  }

}