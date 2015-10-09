<?php
/*! ValidationException.php | An exception that carries a list of validation messages. */

namespace framework\exceptions;

class ValidationException extends \DomainException {

  public function __construct(array $errors = null, $message = '', $code = 0, \Excpetion $previous = null) {
    parent::__construct($message, $code, $previous);
    $this->errors = $errors;
  }

  /**
   * @protected
   *
   * Validation errors.
   */
  protected $errors = array();

  public function getErrors() {
    return $this->errors;
  }

}
