<?php
/*! AssertionException.php | Thrown when assertion fails. */

namespace core\exception;

class AssertionException extends \Exception {

	function __construct($message = "", $code = 0, $previous = NULL) {
		$message = "Assertion fail!\n";

		parent::__construct($message, $code, $previous);
	}

}