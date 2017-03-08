<?php /*! CssMinResolver.php | Minify CSS on demand. */

namespace resolvers;

use core\Log;

use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

use CssMin;

class CssMinResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * SCSS source files directory.
   */
  protected $srcPath;

  /**
   * @protected
   *
   * Output directory of the compiled CSS.
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

    if ( !empty($options['prefix']) ) {
      $this->prefix = $options['prefix'];
    }

    if ( !preg_match('/\/$/', $this->prefix) ) {
      $this->prefix.= '/';
    }
  }

  public function resolve(Request $request, Response $response) {
    $pathInfo = $request->uri('path');

    // Index 1 because request URI starts with a slash
    if ( strpos($pathInfo, $this->prefix . $this->dstPath) !== 0 ) {
      return;
    }
    else {
      $pathInfo = substr($pathInfo, strlen($this->prefix . $this->dstPath));
    }

    $pathInfo = pathinfo($pathInfo);

    // Only serves .min.css requests
    if ( !preg_match('/\.min\.css$/', $pathInfo['basename']) ) {
      return;
    }

    $pathInfo['filename'] = preg_replace('/\.min$/', '', $pathInfo['filename']);

    $_srcPath = "$pathInfo[dirname]/$pathInfo[filename].css";
    $dstPath = "./$this->dstPath/$pathInfo[dirname]/$pathInfo[filename].min.css";

    $break = false;

    foreach ( $this->srcPath as $srcPath ) {
      $srcPath = "/$srcPath$_srcPath";

      // note;dev; Response outputBuffer will mess up parent OB in some PHP versions, don't rely on that.
      \core\Net::httpRequest(
        [ 'url' => $response->createLink($srcPath)
        , 'success' => function($response, $options) use($srcPath, $dstPath, &$break) {
            $res = new \framework\Response();

            foreach ( array_filter(preg_split('/\r?\n/', @$options['response']['headers'])) as $value ) {
              $res->header($value);
            }

            $res = @strtotime($res->header('Last-Modified'));

            if ( $options['response']['status'] < 300 ) {
              if ( empty($res) || $res > @filemtime($dstPath) ) {
                $res = trim(@CssMin::minify($response));
                $srcPath = ".$srcPath";
                if ( $res && (!file_exists($srcPath) && !@mkdir($srcPath, 0770, true)) || !@file_put_contents($dstPath, $res) ) {
                  Log::warn('Permission denied, unable to minify CSS.');
                }
              }

              $break = true;
            }
          }
        , 'failure' => function($num, $str) use($srcPath, &$break) {
            \core\Log::warning("[CssMin] Error reading source file in $srcPath.");

            $break = true;
          }
        ]
      );

      if ( $break ) {
        break;
      }
    }
  }

}
