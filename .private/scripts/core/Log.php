<?php
/* Log.php | Shorthand class for writing system logs. */

namespace core;

use framework\Configuration;
use framework\Process;
use framework\Session;
use framework\System;

use Psr\Log\LoggerInterface;

class Log {

  private static $loggers = array();

  static function setLogger(LoggerInterface $logger) {
    self::$loggers[$logger->getName()] = $logger;
  }

  static function getLogger($name = 'default') {
    return @self::$loggers[$name];
  }

  /**
   * Pipe method calls into the logger.
   */
  static function __callStatic($name, $args) {
    return call_user_func_array(array(self::getLogger(), $name), $args);
  }

  static function write($message, $type = 'Notice', $context = null) {
    // Enabled log types
    $types = Configuration::get('log', true);
    if ( is_array($types) ) {
      if ( !in_array($type, $types) ) {
        return;
      }
    }
    else if ( !$types ) {
      return;
    }
    unset($types);

    $message = array('message' => $message);

    // Try to backtrace the callee, performance impact at this point.
    $backtrace = Utility::getCallee(3);

    if ( !@$backtrace['file'] ) {
      $backtrace = array( 'file' => __FILE__, 'line' => 0 );
    }

    $backtrace['file'] = str_replace(getcwd(), '', basename($backtrace['file'], '.php'));

    if ( Session::current() ) {
      $message['subject'] = 'User#' . (int) Session::current('UserID');
    }
    else if ( is_numeric(@Process::get('type')) ) {
      $message['subject'] = 'User@#' . (int) Process::get('type');
    }
    else {
      $message['subject'] = 'Process#' . getmypid();
    }

    $message['subject'] = "$message[subject]@$backtrace[file]:$backtrace[line]";
    $message['action'] = @"$backtrace[class]$backtrace[type]$backtrace[function]";

    $message['type'] = $type;

    if ( Database::isConnected() ) {
      if ( $context !== null ) {
        $message['context'] = $context;
      }

      $message[Node::FIELD_COLLECTION] = FRAMEWORK_COLLECTION_LOG;

      return @Node::set($message);
    }
    else {
      $logContent = $message['message'];

      if ( @$message['context'] ) {
        $logContent.= "\n" . json_encode($message['context']);
      }

      return error_log($logContent, 4);
    }
  }

  /**
   * @deprecated
   *
   * This function will fade out in the next version.
   *
   * Normal logs will now replace pid with User context once a valid session is found.
   */
  static function sessionWrite($sid, $action, $remarks = null) {
    $userId = '0';

    if ( $sid !== null ) {
      Session::ensure($sid);
    }

    $userId = (int) Session::currentUser('ID');

    return Node::set(array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_LOG
      , 'type' => 'Information'
      , 'subject' => "User#$userId"
      , 'action' => Utility::sanitizeString($action)
      , 'remarks' => $remarks
      ));
  }

}
