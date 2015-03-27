<?php

namespace framework\exceptions;

class ResolverException extends FrameworkException {

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------
  public function __construct($statusCode, $message = "", $code = 0, Exception $previous = NULL) {
    if (is_numeric($statusCode)) {
      $this->statusCode = $statusCode;
    }
    else {
      // Force-cast to string message
      $message = @"$statusCode";
    }

    // TODO: Use `Resource` class to get a predefined locale-based message.

    parent::__construct($message, $code, $previous);
  }

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  private $statusCode = NULL;

  public function statusCode() {
    return $this->statusCode;
  }

}
