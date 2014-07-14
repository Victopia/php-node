<?php
/*! TempalteResovler.php \ IRequestResolver
 *
 * Request resolver for Templates.
 *
 * @deprecated
 * This class is going to be revamp with a new logic.
 */

namespace resolvers;

class TemplateResolver implements \framework\interfaces\IRequestResolver {
  private static $mustache;

  private $resouces;

  //----------------------------------------------------------------------
  //
  //  Constructor
  //
  //----------------------------------------------------------------------

  public function __construct($localeChain = 'en_US') {
    if ( !self::$mustache ) {
      self::$mustache = new \Mustache_Engine();
    }

    $this->resources = new \framework\MustacheResource($localeChain);
  }

  //----------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------

  private $directoryIndex = array();

  public function directoryIndex($value = null) {
    if ( is_null($value) ) {
      return $value;
    }
    else {
      $this->directoryIndex = preg_split('/[\s+,;]+/', $value);
    }
  }

  //----------------------------------------------------------------------
	//
	//  Methods: IPathResolver
	//
	//----------------------------------------------------------------------

  public /* Boolean */
  function resolve($path) {
    // Normalize request URL to a relative path
    if ( strpos($path, '.') !== 0 ) {
      $path = ".$path";
    }

    // Mimic directory index
    if ( is_dir($path) ) {
      foreach ( $this->directoryIndex as $dirIndex ) {
        $files = glob($path . DIRECTORY_SEPARATOR . "$dirIndex.*");

        $files = reset($files);

        if ( $files ) {
          $path = realpath($files);

          break;
        }
      }

      unset($dirIndex, $files);
    }

    // Check whether target is a template file

    /* Note @ 11 Feb, 2014
       We support mustache only.
    */
    if ( file_exists($path) ) {
      $ext = pathinfo($path, PATHINFO_EXTENSION);

      switch ( $ext ) {
        case 'mustache':
        case 'html':
          return $this->handle($path);
      }
    }

    return false;
  }

	public /* String */
  function handle($path) {
    echo self::$mustache->render(file_get_contents($path), $this->resources);
	}
}
