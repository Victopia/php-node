<?php
/*! StatusDocumentResolver.php | Standard contents according to HTTP response status code. */

namespace resolvers;

use framework\Request;
use framework\Response;

class StatusDocumentResolver implements \framework\interfaces\IRequestResolver {

	/**
	 * @private
 	 */
	protected $basepath;

	public function __construct($path) {
		if ( !is_readable($path) || !is_dir($path) ) {
			throw new ResolverException('Error document path must be a readable directory.');
		}

		$this->basepath = $path;
	}

	public function resolve(Request $request, Response $response) {
		// No more successful resolve should occur at this point.
		if ( !$response->status() ) {
			$response->status(404);
		}

		// Check if docment of target status and mime type exists.
		switch ( $response->header('Content-Type') ) {
			case 'application/xhtml+xml':
			case 'text/html':
			default:
				break;

			case 'application/json':
				$ext = 'json';
				break;

			case 'application/xml':
			case 'text/xml':
				$ext = 'xml';
				break;
		}

		$basename = $this->basepath . DIRECTORY_SEPARATOR . $response->status();
		if ( isset($ext) && file_exists("$basename.$ext") ) {
			readfile("$basename.$ext");
		}
		// Fall back to PHP
		else if ( file_exists("$basename.php") ) {
			include("$basename.php");
		}
		// Fall back to HTML
		else if ( file_exists("$basename.html") ) {
			readfile("$basename.html");
		}
	}

}
