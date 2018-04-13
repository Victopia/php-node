<?php /*! JsMinResolver.php | Minify Javascripts on demand. */

namespace resolvers;

use core\Log;

use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

class JsMinResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * Javascript source files directory
   */
  protected $srcPath;

  /**
   * @protected
   *
   * Output directory for minified js
   */
  protected $dstPath = '.';

  /**
   * @protected
   *
   * Request path prefix for minified js
   */
  protected $prefix = '/';

  /**
   * @constructor
   *
   * @param {array} $options Options
   * @param {string|array} $options[source] (Required) directory of Javascript source files.
   * @param {string} $options[output] (Optional) directory for minified Javascript files, web root if omitted.
   */
  public function __construct(array $options) {
    // $options['source'] = (array) @$options['source'];
    if ( empty($options['source']) ||
      array_filter(
        array_map(
          compose('not', funcAnd('is_string', 'is_dir', 'is_readable')),
          (array) $options['source']
          )
        )
      )
    {
      throw new ResolverException(500, sprintf("Source directory %s is invalid.", implode(', ', (array) $options['source'])));
    }

    $this->srcPath = array_map(function($path) {
      if ( !preg_match('/\/$/', $path) ) {
        $path.= '/';
      }

      return $path;
    }, (array) $options['source']);

    if ( !empty($options['output']) ) {
      $this->dstPath = $options['output'];
    }

    if ( !is_string($this->dstPath) || !is_dir($this->dstPath) || !is_writable($this->dstPath) ) {
      throw new ResolverException(500, "Output directory '$this->dstPath' is invalid.");
    }

    if ( $this->dstPath == '.' ) {
      $this->dstPath = '';
    }

    if ( !empty($options['prefix']) ) {
      $this->prefix = $options['prefix'];
    }

    if ( !preg_match('/\/$/', $this->prefix) ) {
      $this->prefix.= '/';
    }
  }

  public function resolve(Request $request, Response $response) {
    $pathInfo = $request->uri('path');

    if ( strpos($pathInfo, $this->prefix . $this->dstPath) !== 0 ) {
      return;
    }
    else {
      $pathInfo = substr($pathInfo, strlen($this->prefix . $this->dstPath));
    }

    $pathInfo = pathinfo($pathInfo);

    // Only serves .min.js requests
    if ( !preg_match('/\.min\.js$/', $pathInfo['basename']) ) {
      return;
    }

    $pathInfo['filename'] = preg_replace('/\.min$/', '', $pathInfo['filename']);

    $_srcPath = "$pathInfo[dirname]/$pathInfo[filename].js";
    $dstPath = "./$this->dstPath$pathInfo[dirname]/$pathInfo[filename].min.js";

    foreach ( $this->srcPath as $srcPath ) {
      $srcPath = preg_replace('/(\/{2,})/', '/', "./$srcPath$_srcPath");

      // compile when: target file not exists, or source is newer
      if ( file_exists($srcPath) && @filemtime($srcPath) > @filemtime($dstPath) ) {
        // empty results are ignored
        $result = (new \MatthiasMullie\Minify\Js($srcPath))->minify();
        if ( $result ) {
          // note; reuse variable $srcPath
          $srcPath = dirname($dstPath);
          if ( (!file_exists($srcPath) && !@mkdir($srcPath, 0770, true)) || !@file_put_contents($dstPath, $result) ) {
            Log::warn('Permission denied, unable to minify Javascript files.');
          }
        }

        break;
      }
    }
  }

}
