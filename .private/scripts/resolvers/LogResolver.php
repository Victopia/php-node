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
		// Debug access log
		if ( System::environment() == 'debug' ) {
			$message = $request->client('version') . ' ' . strtoupper($request->method()) . ' ' . $request->uri('path');

		  Log::write($message, 'Debug', array_filter(array(
		      'origin' =>  $request->client('referer')
		    , 'userAgent' => util::cascade(@$request->client('userAgent'), 'Unknown')
		    , 'timeElapsed' => round(microtime(1) - $request->timestamp(), 4) . ' secs'
		    )));
		}
	}

}
