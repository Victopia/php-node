<?php
/*! JsonpResolver.php | Pipe appropriate JSONP header into repsonse for output. */

namespace resolvers;

use framework\Request;
use framework\Response;

class JsonpResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * Default JSONP callback name when it is not specified.
   */
  protected $defaultCallback;

  /**
   * @constuctor
   */
  public function __construct(array $options = array()) {
    if ( !empty($options['defaultCallback']) ) {
      $this->defaultCallback = $options['defaultCallback'];
    }
  }

  /**
   * Add appropriate response header for response class to output.
   */
  public function resolve(Request $req, Response $res) {
    $callback = $req->param('JSONP_CALLBACK_NAME');
    if ( !$callback ) {
      $callback = $this->defaultCallback;
    }

    if ( $callback && $req->param($callback) ) {
      $res->header('X-JSONP-CALLBACK', $req->param($callback));
    }
  }

}
