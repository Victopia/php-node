<?php
/*! WebServiceResolver.php \ IRequestResovler
 *
 *  Eliminate the needs of service gateway.
 *
 *  CAUTION: This class calls global function
 *           redirect() and terminates request
 *           directly when client requests for
 *           unauthorized services.
 */

namespace resolvers;

class WebServiceResolver implements \framework\interfaces\IRequestResolver {
	//--------------------------------------------------
	//
	//  Methods: IPathResolver
	//
	//--------------------------------------------------

	public
	/* String */ function resolve($path) {
		$path = urldecode($path);

		// Resolve target service and apply appropiate parameters
		preg_match('/\/services\/([^\/]+)\/([^\/\?,]+)(\/[^\?]+)?/', $path, $matches);

		// Chain off to 404 instead of the original "501 Method Not Allowed".
		if (count($matches) < 3) {
			return FALSE;
		}

		$classname = '\\' . $matches[1];
		$function = $matches[2];

		if (!class_exists($classname)) {
			return FALSE;
		}

		$instance = new $classname();

		if (!method_exists($classname, $function) && !is_callable($instance, $function)) {
			throw new \framework\exceptions\ResolverException( 501 );
		}

		if (isset($matches[3])) {
			$parameters = explode('/', substr($matches[3], 1));
		}
		else {
			$parameters = Array();
		}

		unset($matches);

		// Access log
		\log::write("WebService: $classname->$function, parameters: " . print_r($parameters, 1), 'Access');

		// Shooto!
		$response = \service::call($classname, $function, $parameters);

		unset($instance); unset($function); unset($parameters);

		header('Content-Type: application/json; charset=utf-8', true);

		// JSON encode the result and response to client.
		echo json_encode($response);
	}

	//--------------------------------------------------
	//
	//  Methods: Serializable
	//
	//--------------------------------------------------

	public
	/* String */ function serialize() {
		return serialize($this);
	}

	public
	/* void */ function unserialize($serial) {
		return unserialize($serial);
	}
}