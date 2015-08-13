<?php
/*! AbstractRenderer.php | Base class for all renderers. */

namespace framework\renderers;

use framework\Request;
use framework\Response;

abstract class AbstractRenderer implements IFileRenderer {

  //----------------------------------------------------------------------------
  //
  //  Constructor
  //
  //----------------------------------------------------------------------------

  public function __construct($context = array()) {
    $this->setContext($context);
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   *
   * Target path to include as PHP.
   */
  protected $path;

  public function path($value = null) {
    if ( $value === null ) {
      return $this->path;
    }
    else {
      $this->path = $value;
    }
  }

  /**
   * @private
   *
   * The request context.
   */
  private $request;

  public function request(Request $value = null) {
    if ( $value === null ) {
      return $this->request;
    }
    else {
      $this->request = $value;
    }
  }

  /**
   * @private
   *
   * The response context.
   */
  private $response;

  public function response(Response $value = null) {
    if ( $value === null ) {
      return $this->response;
    }
    else {
      $this->response = $value;
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Set the request and resopnse context of this renderer.
   */
  public function setContext($context = array()) {
    // Defaults to path
    if ( is_string($context) ) {
      $context = array(
          'path' => $context
        );
    }

    if ( !@$context['request'] instanceof Request ) {
      unset($context['request']);
    }

    if ( !@$context['response'] instanceof Response ) {
      unset($context['response']);
    }

    $this->path = @$context['path'];
    $this->request = @$context['request'];
    $this->response = @$context['response'];
  }

  /**
   * Accessible from template files.
   */
  protected function __($key) {
    return $this->response()->__($key);
  }

  /**
   * Renders target file.
   */
  abstract function render($path);

}
