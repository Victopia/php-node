<?php
/*! LessResolver.php | When a CSS is requested, compile from LESS source on demand. */

namespace resolvers;

use core\Log;

use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

use lessc;

class LessResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * LESS source files directory.
   */
  protected $srcPath;

  /**
   * @constructor
   *
   * @param {array} $options Options
   * @param {string|array} $options[source] (Required) directory of Javascript source files.
   * @param {string} $options[output] (Optional) directory for minified Javascript files, web root if omitted.
   */
  public function __construct(array $options) {
    $options['source'] = (array) @$options['source'];
    if ( !$options['source'] ||
      array_filter(
        array_map(
          compose('not', funcAnd('is_string', 'is_dir', 'is_readable')),
          $options['source']
          )
        )
      )
    {
      throw new ResolverException('Invalid source directory.');
    }

    $this->srcPath = $options['source'];

    if ( !empty($options['output']) ) {
      if ( !is_string($options['output']) || !is_dir($options['output']) || !is_writable($options['output']) ) {
        throw new ResolverException('Invalid output directory.');
      }

      $this->dstPath = $options['output'];
    }
  }

  public function resolve(Request $request, Response $response) {
    $pathInfo = $request->uri('path');

    // Inex 1 because request URI starts with a slash
    if ( strpos($pathInfo, $this->dstPath) !== 1 ) {
      return;
    }
    else {
      $pathInfo = substr($pathInfo, strlen($this->dstPath) + 1);
    }

    $pathInfo = pathinfo($pathInfo);

    // Only serves .css requests
    if ( $pathInfo['extension'] != 'css' ) {
      return;
    }

    // also takes care of .min requests
    $pathInfo['filename'] = preg_replace('/\.min$/', '', $pathInfo['filename']);

    $_srcPath = "/$pathInfo[dirname]/$pathInfo[filename].less";
    $dstPath = "./$this->dstPath/$pathInfo[dirname]/$pathInfo[filename].css";

    foreach ( $this->srcPath as $srcPath ) {
      $srcPath = "./$srcPath$_srcPath";

      // compile when: target file not exists, or source is newer
      if ( !file_exists($dstPath) || @filemtime($srcPath) > @filemtime($dstPath) ) {
        $result = @trim((new lessc)->compile(file_get_contents($srcPath)));
        // note; write empty file if target exists
        if ( $result || file_exists($dstPath) ) {
          if ( !@file_put_contents($dstPath, $result) ) {
            Log::warn('Permission denied, unable to compile LESS.');
          }
        }

        break;
      }
    }
  }

}
