<?php
/* TempalteResovler.php | Renders template files with specified renderer and options. */

namespace resolvers;

use core\Utility;

class TemplateResolver implements \framework\interfaces\IRequestResolver {
  private $handlers = array();

  //----------------------------------------------------------------------
  //
  //  Constructor
  //
  //----------------------------------------------------------------------

  /**
   * @param $handlers An array of template handler options, with the following format:
   *                 [{ render: // Template rendering function: function($template, $resource);
   *                  , extensions: // File extensions to match against handling templates.
   *                  }]
   */
  public function __construct($handlers = array()) {
    $handlers = Utility::wrapAssoc($handlers);

    foreach ( $handlers as &$handler ) {
      if ( !is_callable($handler['render']) ) {
        throw new FrameworkException('Please specify a valid render function.');
      }

      if ( !@$handler['extensions'] ) {
        throw new FrameworkException('Please specify file extensions this handler handles.');
      }

      if ( is_string($handler['extensions']) ) {
        $handler['extensions'] = preg_split('/\s+/', $handler['extensions']);
      }

      $handler['extensions'] = Utility::wrapAssoc($handler['extensions']);
    }

    $this->handlers = $handlers;
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

  public /* Boolean */ function resolve($path) {
    // Not a regular request uri.
    if ( strpos($path, '/') !== 0 ) {
      return $path;
    }

    // Normalize request URL to a relative path
    if ( strpos($path, '.') !== 0 ) {
      $res = ".$path";
    }

    // Mimic directory index
    if ( is_dir($res) ) {
      foreach ( $this->directoryIndex as $dirIndex ) {
        $files = glob($res . DIRECTORY_SEPARATOR . "$dirIndex.*");

        $files = reset($files);

        if ( $files ) {
          $res = realpath($files);

          break;
        }
      }

      unset($dirIndex, $files);
    }

    // extensionless
    $ext = pathinfo($res, PATHINFO_EXTENSION);
    $ext = strtolower($ext);

    if ( !$ext ) {
      foreach ( $this->handlers as $handler ) {
        // var_dump($handler); die;
        foreach ( (array) $handler['extensions'] as $extension ) {
          if ( file_exists("$res.$extension") ) {
            $res = "$res.$extension";

            break 2;
          }
        }

        unset($extension);
      }

      unset($handler);
    }

    // Check whether we handles target template file.
    if ( file_exists($res) ) {
      $ext = pathinfo($res, PATHINFO_EXTENSION);
      $ext = strtolower($ext);

      foreach ( $this->handlers as $handler ) {
        if ( in_array($ext, $handler['extensions']) ) {
          echo $handler['render']($res);

          return;
        }
      }
    }

    return $path;
  }
}
