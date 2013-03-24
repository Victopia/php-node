<?php

namespace framework\exceptions;

class ResolverException extends GeneralException {

	//--------------------------------------------------
	//
	//  Constructor
	//
	//--------------------------------------------------
	public function __construct($statusCode, $message = "", $code = 0, Exception $previous = NULL) {
		$this->statusCode = $statusCode;

		$message = \message::get(APP_TOOL, FNC_TOOL_EXCEPTION, MSG_EXCEPTION_RESOLVER);

		$message = sprintf($message, $statusCode);

		parent::__construct($message, $code, $previous);
	}

	//--------------------------------------------------
	//
	//  Properties
	//
	//--------------------------------------------------

	private $statusCode;

	public function statusCode() {
		return $this->statusCode;
	}

}