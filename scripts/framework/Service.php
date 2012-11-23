<?php
/*! Service.php | Cater all web service functions. */

namespace framework;

class Service {

	static function call($service, $method, $parameters = array()) {
		$service = new $service();

		if ($service instanceof \framework\interfaces\IAuthorizable &&
			$service->authorizeMethod($method, $parameters) === FALSE) {
			throw new \framework\exceptions\ResolverException( 401 );
			return NULL;
		}

		$method = new \ReflectionMethod($service, $method);

		return $method->invokeArgs($service, $parameters);
	}

}