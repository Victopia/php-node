<?php
/* FrameworkException.php | Exceptions with message resolved with its code from database. */

namespace framework\exceptions;

use framework\Resolver;

/**
 * Framework thrown exceptions, messages are interfaced with front end.
 *
 * Provided messages are discarded when a message is resolved with the
 * error code via Translation class.
 */
class FrameworkException extends \Exception {

  public function __construct($message, $code = 0, \Exception $previous = null) {
    $res = Resolver::getActiveInstance();

    // Resolve message from database, with the exception code.
    if ( $res && $code ) {
      $res = $res->response();
      if ( $res ) {
        $res = $res->__("exception.$code", 'Exception');
        if ( $res ) {
          if ( is_array($message) ) {
            $message = call_user_func_array('sprintf', $message);
          }
        }
      }
    }

    if ( is_array($message) ) {
      $message = implode(' ', $message);
    }

    if ( !$message ) {
      $message = sprintf('Exception #%d', $code);
    }

    parent::__construct($message, $code, $previous);
  }

}
