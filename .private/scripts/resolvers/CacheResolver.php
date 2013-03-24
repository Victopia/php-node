<?php

namespace resolvers;

class CacheResolver implements \framework\interfaces\IRequestResolver {

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($pathPrefix) {
    if (!$pathPrefix) {
      throw new \framework\exceptions\ResolverException('Please provide a proper path prefix for CacheResolver.');
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
    if (!$this->pathPrefix || 0 !== strpos($path, $this->pathPrefix)) {
      return FALSE;
    }

    $path = substr($path, strlen($this->pathPrefix));

    $res = \framework\Cache::get($path);

    if ($res === NULL || $res === FALSE) {
      return FALSE;
    }

    // To be extended ... now defaults to JSON things.

    header('Content-Type: application/json; charset=utf-8', TRUE);

    echo $res;
  }

}