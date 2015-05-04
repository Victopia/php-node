<?php
/*! WebService.php | Base class for web services. */

namespace framework;

abstract class WebService implements interfaces\IWebService {

	protected function request() {
		return $this->request;
	}

	protected function response() {
		return $this->response;
	}

	protected function userContext() {
		return @$this->request()->user;
	}

	protected function userIsAdmin() {
		$user = (array) $this->userContext();

		return in_array('Administrators', (array) @$user['groups']);
	}

	protected function isLocal() {
		return (bool) @$this->request()->isLocal;
	}

	/**
	 * @private
   *
	 * Request context, uses private on purpose to prevent subclasses from changing it.
	 */
	private $request;

	/**
	 * @private
	 *
	 * Response context, uses private on purpose to prevent subclasses from changing it.
	 */
	private $response;

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
	public function __invoke() {
		$this->request()->header('Content-Type', 'application/json; charset=utf-8');

		$args = func_get_args();
		$method = $this->request()->method();
		switch ( $method ) {
			case 'get':
				if ( !$args ) {
					$method = 'let';
				}
				else {
					$method = 'get';
				}
				break;
		}

		if ( method_exists($this, $method) ) {
			return call_user_func_array([$this, $method], $args);
		}
		else {
			$this->response(501); // Not implemented
		}
	}

}
