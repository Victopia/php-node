<?php

namespace resolvers;

use framework\Cache;
use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

class CacheResolver implements \framework\interfaces\IRequestResolver {

  //--------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //--------------------------------------------------

  /**
   * Checks whether an update is available.
   */
  public function resolve(Request $request, Response $response) {
    $path = $request->uri('path');
    $hash = $request->param('v');

    $info = Cache::getInfo($path, $hash);
    if ( !$info ) {
      return;
    }

    // Send a bunch of headers

    // 1. Conditional request
    // If-Modified-Since: Match against $info->getMTime();
    // If-None-Match: Match against the md5_file();

    // 2. Normal request
    // Content-Type + charset
    // Content-Length
    // Cache-Control
    // Date
    // Pragma (remove)

    // header('Content-Type: application/json; charset=utf-8', true);
  }

}
