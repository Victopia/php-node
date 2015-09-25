<?php
/*! ValidationException.php | An exception that carries a list of validation messages. */

namespace framework\exceptions;

class ValidationException extends \DomainException {

  public function __construct($message = '', $code = 0, array $errors = null, \Excpetion $previous = null) {
    parent::__construct($message, $code, $previous);
    $this->errors = $errors;
  }

  protected $errors;

  public function getErrors() {
    return $this->errors;
  }

}
