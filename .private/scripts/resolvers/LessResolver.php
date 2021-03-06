<?php /*! LessResolver.php | When a CSS is requested, compile from LESS source on demand. */

namespace resolvers;

use core\Log;

use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

use Less_Parser;

class LessResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * LESS source files directory.
   */
  protected $srcPath;

  /**
   * @protected
   *
   * Output directory for minified js
   */
  protected $dstPath = '.';

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
      throw new ResolverException("Source directory '$options[source]' is invalid.");
    }

    $this->srcPath = array_map(function($path) {
      if ( !preg_match('/\/$/', $path) ) {
        $path.= '/';
      }

      return $path;
    }, $options['source']);

    if ( !empty($options['output']) ) {
      $this->dstPath = $options['output'];
    }

    if ( !is_string($this->dstPath) || !is_dir($this->dstPath) || !is_writable($this->dstPath) ) {
      throw new ResolverException("Output directory '$this->dstPath' is invalid.");
    }

    if ( $this->dstPath == '.' ) {
      $this->dstPath = '';
    }
  }

  public function resolve(Request $request, Response $response) {
    $pathInfo = $request->uri('path');

    if ( strpos($pathInfo, $this->dstPath) !== 0 ) {
      return;
    }
    else {
      $pathInfo = substr($pathInfo, strlen($this->dstPath));
    }

    $pathInfo = pathinfo($pathInfo);

    // Only serves .css requests
    if ( @$pathInfo['extension'] != 'css' ) {
      return;
    }

    $options = [
      'cache_dir' => '/tmp'
    ];

    $buildPath = compose(
      partial('implode', DIRECTORY_SEPARATOR),
      'filter',
      partial('explode', DIRECTORY_SEPARATOR)
    );

    // Request for minified version.
    if ( preg_match('/\.min$/', $pathInfo['filename']) ) {
      $options['compress'] = true;

      $_srcPath = preg_replace('/\.min$/', '', $pathInfo['filename']);
    }
    else {
      $_srcPath = $pathInfo['filename'];
    }

    $_srcPath = $buildPath("$pathInfo[dirname]/$_srcPath.less");
    $dstPath = $buildPath("$this->dstPath$pathInfo[dirname]/$pathInfo[filename].css");

    foreach ( $this->srcPath as $srcPath ) {
      $srcPath = $buildPath("./$srcPath/$_srcPath");

      // compile when: target file not exists, or source is newer
      if ( file_exists($srcPath) && !file_exists($dstPath) || @filemtime($srcPath) > @filemtime($dstPath) ) {
        $parser = new Less_Parser($options);
        $parser->parseFile($srcPath);
        $result = $parser->getCss();
        unset($parser);

        if ( $result ) {
          $srcPath = dirname($dstPath);
          if ( !file_exists($srcPath) && !@mkdir($srcPath, 0770, true) || !@file_put_contents($dstPath, $result) ) {
            Log::warn('Permission denied, unable to write LESS output.');
          }
        }

        break;
      }
    }
  }

}
