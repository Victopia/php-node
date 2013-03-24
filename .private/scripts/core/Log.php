<?php
/*! Log.php | https://github.com/victopia/PHPNode
 *
 * CAUTION: This class is not production ready, use at own risk.
 */

namespace core;

class Log {

  static function write($message, $type = 'Notice', $context = NULL) {
    // Skip debug logs on production environment.
    if (FRAMEWORK_ENVIRONMENT != 'debug' && $type == 'Debug') {
      return;
    }

    switch ($type) {
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
    $backtrace = \utils::getCallee();

    if (!@$backtrace['file']) {
      $backtrace = array( 'file' => __FILE__, 'line' => 0 );
    }

    $backtrace['file'] = str_replace(getcwd(), '', basename($backtrace['file'], '.php'));

    $message['subject'] = '['.getmypid()."@$backtrace[file]:$backtrace[line]]";
    $message['action'] = @"$backtrace[class]$backtrace[type]$backtrace[function]";

    if ($context !== NULL) {
      $message['context'] = $context;
    }

    $message['type'] = $type;

    $message[NODE_FIELD_COLLECTION] = FRAMEWORK_COLLECTION_LOG;

    return Node::set($message);
  }

  static function sessionWrite($sid, $action, $remarks = NULL) {
    $userId = '0';

    if ($sid !== NULL && \session::ensure($sid)) {
      $userId = \session::currentUser('ID');
    }

    $userId = intval($userId);

    return Node::set(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_LOG
      , 'type' => 'Information'
      , 'subject' => "User#$userId"
      , 'action' => Utility::sanitizeString($action)
      , 'remarks' => $remarks
      ));
  }
}