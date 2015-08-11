<?php
/*! UriRewriteResolver.php | Rewrite request with matched URI to target URI. */

namespace resolvers;

use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

use InvalidArgumentException;

class UriRewriteResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * Base path to match against requests
   */
  protected $pattern = '/.*/';

  /**
   * @protected
   *
   * Redirect target, string or callable.
   */
  protected $target;

  /**
   * @constructor
   *
   * @param {array} $options
   * @param {?string} $options[pattern] Base path to match against requests, defaults to root.
   * @param {string|callable} $options[target] Redirects to a static target, or function($request) returns a string;
   */
  public function __construct(array $options) {
    if ( !empty($options['pattern']) ) {
      if ( @preg_match($options['pattern'], null) === false ) {
        throw new InvalidArgumentException('pattern must be valid regex expression.');
      }

      $this->pattern = $options['pattern'];
    }

    if ( empty($options['target']) || (!is_string($options['target']) && !is_callable($options['target'])) ) {
      throw new InvalidArgumentException('target must be string or callable.');
    }

    $this->target = $options['target'];
  }

  public function resolve(Request $request, Response $response) {
    if ( preg_match($this->pattern, $request->uri('path')) ) {
      $target = $this->target;
      if ( is_callable($this->target) ) {
        $target = $this->target($request);
      }

      if ( is_string($target) ) {
        $uri = $request->uri();
        $uri['path'] = $target;
        $request->setUri($uri);
      }
    }
  }

}
