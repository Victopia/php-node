<?php
/*! ReosurceResolver.php \ IRequestResolver
 *
 *  Database resource from requesting path.
 *
 *  Path format: /@Image/12
 */

namespace resolvers;

class ResourceResolver implements \framework\interfaces\IRequestResolver {

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($pathPrefix) {
    if (!$pathPrefix) {
      throw new \framework\exceptions\ResolverException('Please provide a proper path prefix for ResourceResolver.');
    }

    $this->pathPrefix = $pathPrefix;
  }

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  private $pathPrefix = NULL;

  //--------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //--------------------------------------------------

  public
  /* String */ function resolve($path) {
    // Request URI must start with the specified path prefix. e.g. /:resource/.
    if (!$this->pathPrefix || 0 !== strpos($path, $this->pathPrefix)) {
      return FALSE;
    }

    $path = substr($path, strlen($this->pathPrefix));

    echo $path;
  }

}