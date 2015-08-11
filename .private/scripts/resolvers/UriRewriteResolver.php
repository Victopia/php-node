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
  protected $sourceFunc = null;

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
   * @param {?string} $options[source] Base path to match against requests, defaults to root.
   * @param {string|callable} $options[target] Redirects to a static target, or function($request) returns a string;
   */
  public function __construct(array $options) {
    if ( !empty($options['source']) ) {
      if ( is_string($options['source']) ) {
        // Regex
        if ( @preg_match($options['source'], null) !== false ) {
          $options['source'] = matches($options['source']);
        }
        // Not a function name, plain string.
        else if ( !is_callable($options['source']) ) {
          $options['source'] = startsWith($options['source']);
        }
      }

      // Callable
      if ( is_callable($options['source']) ) {
        $this->sourceFunc = $options['source'];
      }
      else {
        throw new InvalidArgumentException('Source must be string, regex or callable.');
      }
    }
    else {
      $this->sourceFunc = matches('/.*/');
    }

    if ( empty($options['target']) || (!is_string($options['target']) && !is_callable($options['target'])) ) {
      throw new InvalidArgumentException('target must be string or callable.');
    }

    $this->target = $options['target'];
  }

  public function resolve(Request $request, Response $response) {
    $sourceFunc = $this->sourceFunc;
    if ( $sourceFunc($request->uri('path')) ) {
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
