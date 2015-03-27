<?php
/* FrameworkException.php | Exceptions with message resolved with its code from database. */

namespace framework\exceptions;

use core\Utility;

/**
 * Framework thrown exceptions, messages are interfaced with front end.
 *
 * Provided messages are discarded when a message is resolved with the
 * error code via Resource class.
 */
class FrameworkException extends \Exception {

  public function __construct($message, $code = 0, \Exception $previous = null) {
    // Resolve message from database, with the exception code.
    if ( $code ) {
      $resource = Utility::getResourceContext();

      $resource = (string) $resource->{'exception.'.$code};

      if ( $resource ) {
        $message = (array) $message;

        array_unshift($message, $resource);

        $message = call_user_func_array('sprintf', $message);
      }
    }

    if ( !$message ) {
      $message = sprintf('Exception #%d', $code);
    }

    parent::__construct($message, $code, $previous);
  }

}
