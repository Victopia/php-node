<?php
/*! StatusDocumentResolver.php | Standard contents according to HTTP response status code. */

namespace resolvers;

use framework\Request;
use framework\Response;

use framework\renderers\IncludeRenderer;

use framework\exceptions\ResolverException;

class StatusDocumentResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @constructor
   */
  public function __construct($options) {
    if ( empty($options['prefix']) || !is_readable($options['prefix']) || !is_dir($options['prefix']) ) {
      throw new ResolverException('Error document path must be a readable directory.');
    }
    else {
      $this->pathPrefix($options['prefix']);
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  //------------------------------
  //  pathPrefix
  //------------------------------
  protected $pathPrefix = '/';

  /**
   * Target path to serve.
   */
  public function pathPrefix($value = null) {
    $pathPrefix = $this->pathPrefix;

    if ( $value !== null ) {
      $this->pathPrefix = './' . trim(trim($value), '/');
    }

    return $pathPrefix;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  public function resolve(Request $request, Response $response) {
    // No more successful resolve should occur at this point.
    if ( !$response->status() ) {
      $response->status(404);
    }

    if ( $response->body() || is_array($response->body()) ) {
      return;
    }

    // Check if docment of target status and mime type exists.
    switch ( $response->header('Content-Type') ) {
      case 'application/xhtml+xml':
      case 'text/html':
      default:
        $ext = 'html';
        break;

      case 'application/json':
        $ext = 'json';
        break;

      case 'application/xml':
      case 'text/xml':
        $ext = 'xml';
        break;
    }

    $basename = $this->pathPrefix() . '/' . $response->status();
    if ( isset($ext) && file_exists("$basename.$ext") ) {
      readfile("$basename.$ext");
    }
    // Fall back to PHP
    else if ( file_exists("$basename.php") ) {
      $context = array(
          'request' => $request
        , 'response' => $response
        );

      (new IncludeRenderer($context))->render("$basename.php");
    }
    // Fall back to HTML
    else if ( $ext != 'html' && file_exists("$basename.html") ) {
      readfile("$basename.html");
    }
  }

}
