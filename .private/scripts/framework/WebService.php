<?php
/*! WebService.php | Base class for web services. */

namespace framework;

abstract class WebService implements interfaces\IWebService {

	protected function request() {
		return Resolver::getActiveInstance()->request();
	}

	protected function repsonse() {
		return Resolver::getActiveInstance()->response();
	}

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

		return call_user_func_array([$this, $method], $args);
	}

}
