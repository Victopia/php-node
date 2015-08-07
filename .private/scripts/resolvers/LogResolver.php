<?php
/*! LogResolver.php | Generate system logs for all requests. */

namespace resolvers;

use core\Log;
use core\Utility as util;

use framework\Request;
use framework\Response;
use framework\System;

/*! Note
 *  This should also takes care of logs originally produced by WebServiceResolver.
 */

class LogResolver implements \framework\interfaces\IRequestResolver {

	public function resolve(Request $request, Response $response) {
		global $argv;

		// Debug access log
		if ( System::environment() == 'debug' ) {
			switch ( $request->client('type') ) {
				case 'cli':
					$message = implode(' ', array(
							$request->client('type'),
							$request->uri(),
						));

					break;

				default:
					$message = implode(' ', array(
							$request->client('version'),
							strtoupper($request->method()),
							$request->uri('path'),
						));

				  @Log::debug($message, array_filter(array(
				      'origin' =>  $request->client('referer')
				    , 'userAgent' => util::cascade(@$request->client('userAgent'), 'Unknown')
				    , 'timeElapsed' => round(microtime(1) - $request->timestamp(), 4) . ' secs'
				    )));
					break;
			}
		}
	}

}
