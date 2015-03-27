<?php

namespace resolvers;

use framework\Cache;

use framework\exceptions\ResolverException;

class CacheResolver implements \framework\interfaces\IRequestResolver {

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($pathPrefix) {
    if ( !$pathPrefix ) {
      throw new ResolverException('Please provide a proper path prefix for CacheResolver.');
    }

    $this->pathPrefix = $pathPrefix;
  }

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  private $pathPrefix;

  //--------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //--------------------------------------------------

  /**
   * Checks whether an update is available.
   */
  public
  /* Boolean */ function resolve($path) {
    // Request URI must start with the specified path prefix. e.g. /:resource/.
    if ( !$this->pathPrefix || 0 !== strpos($path, $this->pathPrefix) ) {
      return $path;
    }

    $path = substr($path, strlen($this->pathPrefix));

    $res = Cache::get($path);

    if ( $res === null || $res === false ) {
      return $path;
    }

    // To be extended ... now defaults to JSON things.

    header('Content-Type: application/json; charset=utf-8', true);

    echo $res;
  }

}
