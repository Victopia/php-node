<?php
/*! UriRewriteResolver.php | Rewrite request with matched URI to target URI. */

namespace resolvers;

use core\Utility as util;

use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

use InvalidArgumentException;

class UriRewriteResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * Arrays of redirection rules, in the format of { source: (string|func), target: (string|func) }.
   */
  protected $rules = array();

  /**
   * @constructor
   *
   * @param {array}  $rules Redirect rules
   * @param {callable|string} $rules[][source] Regex, plain string startsWith() or callback matcher func,
   * @param {string} $rules[][target] String for redirection, can use backreference on regex,
   * @param {?int}   $rules[][method] Redirection status code, or internal by default,
   * @param {?string} $options[source] Base path to match against requests, defaults to root.
   * @param {string|callable} $options[target] Redirects to a static target, or function($request) returns a string;
   */
  public function __construct($rules) {
    // rewrite all URLs
    if ( is_string($rules) ) {
      $rules = array(
          '*' => $rules
        );
    }

    $rules = util::wrapAssoc($rules);

    $this->rules = array_reduce($rules, function($result, $rule) {
      $rule = array_select($rule, array('source', 'target', 'method'));

      // note: make sure source is callback
      if ( is_string($rule['source']) ) {
        // regex
        if ( @preg_match($rule['source'], null) !== false ) {
          $rule['source'] = matches($rule['source']);

          if ( is_string($rule['target']) ) {
            $rule['target'] = compose(
              invokes('uri', array('path')),
              replaces($rule['source'], $rule['target']));
          }
        }
        // plain string
        else if ( !is_callable($rule['source']) ) {
          $rule['source'] = startsWith($rule['source']);

          if ( is_string($rule['target']) ) {
            $rule['target'] = compose(
              invokes('uri', array('path')),
              replaces('/^' . preg_quote($rule['source']) . '/', $rule['target']));
          }
        }
      }

      if ( !is_callable($rule['source']) ) {
        throw new InvalidArgumentException('Source must be string, regex or callable.');
      }

      $result[] = $rule;

      return $result;
    }, array());
  }

  public function resolve(Request $request, Response $response) {
    $path = $request->uri('path');

    foreach ( $this->rules as $rule ) {
      if ( $rule['source']($path) ) {
        if ( is_callable(@$rule['target']) ) {
          $path = $rule['target']($request);
        }
        else {
          $path = @$rule['target'];
        }

        if ( is_numeric(@$rule['method']) ) {
          if ( $path ) {
            $response->redirect($path, array(
                'status' => $rule['method'],
                'secure' => $request->client('secure')
              ));
          }
          else {
            $response->status($rule['method']);
          }
        }
        else {
          $_uri = $request->uri();
          $_uri['path'] = $path;
          $request->setUri($_uri);
        }
        break;
      }
    }
  }

}
