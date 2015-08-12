<?php
/*! WebService.php | Base class for web services. */

namespace framework;

abstract class WebService implements interfaces\IWebService {

	//----------------------------------------------------------------------------
	//
	//  Properties
	//
	//----------------------------------------------------------------------------

	/**
	 * @private
   *
	 * Request context, uses private on purpose to prevent subclasses from changing it.
	 */
	private $request;

	protected function request() {
		return $this->request;
	}

	/**
	 * @private
	 *
	 * Response context, uses private on purpose to prevent subclasses from changing it.
	 */
	private $response;

	protected function response() {
		return $this->response;
	}

	//----------------------------------------------------------------------------
	//
	//  Methods
	//
	//----------------------------------------------------------------------------

	/**
	 * @constructor
	 *
	 * The new WebService class must be called with request and response context.
	 */
	public function __construct(Request $request, Response $response) {
		$this->request = $request;
		$this->response = $response;
	}

	/**
	 * New service classes are allowed to be invoked directly, functions of request
	 * methods are then called accordingly.
	 *
	 * If no such method exists, a 501 Not Implemented response will be thrown.
	 */
	public function __invoke($method = null) {
		$args = func_get_args();

		$method = $this->resolveMethodName($args);
		if ( method_exists($this, $method) ) {
			$this->request()->header('Content-Type', 'application/json; charset=utf-8');

			return call_user_func_array([$this, $method], $args);
		}
		else {
			$this->response(501); // Not implemented
		}
	}

	protected function resolveMethodName(&$args = array()) {
		if ( isset($args[0]) && method_exists($this, $args[0]) ) {
			return array_shift($args);
		}

		// Default method depending on request method
		switch ( $this->request()->method() ) {
			case 'get':
				if ( !$args ) {
					return 'let';
				}
				return 'get';

			case 'post':
				return 'set';
		}

		return $this->request()->method();
	}

	protected function userContext() {
		return @$this->request()->user;
	}

	protected function isLocal() {
		return (bool) @$this->request()->__local;
	}

}
