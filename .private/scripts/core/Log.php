<?php
/* Log.php | Shorthand class for writing system logs. */

namespace core;

use framework\Process;
use framework\Session;
use framework\System;

class Log {
  static function write($message, $type = 'Notice', $context = null) {
    // Skip debug logs on production environment.
    if ( System::environment() != 'debug' && $type == 'Debug' ) {
      return;
    }

    switch ( $type ) {
      case 'Access':
      case 'Information':
      case 'Notice':
      case 'Debug':
        $message = array('remarks' => $message);
        break;
      default:
        $message = array('reason' => $message);
    }

    // Try to backtrace the callee, performance impact at this point.
    $backtrace = Utility::getCallee(3);

    if ( !@$backtrace['file'] ) {
      $backtrace = array( 'file' => __FILE__, 'line' => 0 );
    }

    $backtrace['file'] = str_replace(getcwd(), '', basename($backtrace['file'], '.php'));

    if ( Session::current() ) {
      $message['subject'] = 'User#' . (int) Session::currentUser('ID');
    }
    else if ( is_numeric(Process::get('type')) ) {
      $message['subject'] = 'User@#' . (int) Process::get('type');
    }
    else {
      $message['subject'] = 'Process#' . getmypid();
    }

    $message['subject'] = "[$message[subject]@$backtrace[file]:$backtrace[line]]";
    $message['action'] = @"$backtrace[class]$backtrace[type]$backtrace[function]";

    if ( $context !== null ) {
      $message['context'] = $context;
    }

    $message['type'] = $type;

    $message[Node::FIELD_COLLECTION] = FRAMEWORK_COLLECTION_LOG;

    return @Node::set($message);
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
