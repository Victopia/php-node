<?php
/* MarkdownResolver.php | Handles markdown files. */

/*! Note @ 14 May, 2015
 *  Stuffing everything into FileResolver may seems a bit clumpsy, should break
 *  down handling of different kinds of file into multiple resolvers.
 */

namespace resolvers;

use Parsedown;

use framework\Request;
use framework\Response;

class MarkdownResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @private
   *
   * The serving directory of physical files.
   */
  protected $basePath;

  /**
   * @constructor
   *
   * @param {?string} $basePath Specify where the markdown files are located in,
   *                            relative to the request url. Defaults to current
   *                            working directory.
   */
  function __construct($basePath = '.') {
    $this->basePath = realpath($basePath);
  }

  function resolve(Request $request, Response $response) {
    $pathname = $this->basePath . $request->uri('path');
    switch ( pathinfo($pathname, PATHINFO_EXTENSION) ) {
      case 'md':
      case 'mdown':
        break;

      default:
        return;
    }

    if ( is_file($pathname) ) {
      $text = file_get_contents($pathname);
      $text = (new Parsedown)->text($text);
      $response->send($text, 200);
    }
  }

}
