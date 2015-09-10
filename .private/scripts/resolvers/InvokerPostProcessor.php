<?php
/*! InvokerPostProcessor.php | Invokes specified method from the output object. */

namespace resolvers;

use core\Log;

use framework\Request;
use framework\Response;

use framework\exceptions\FrameworkException;

class InvokerPostProcessor implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * Map from request param name to actual callables.
   */
  protected $funcMap;

  /**
   * @constructor
   *
   * @param {array} $funcMap Key-Value pairs mapping between param names and callables,
   *                         callables must return another callable that takes the
   *                         request body as the only parameter. i.e.
   *                         `function(...) { return function($body) {} }`
   */
  function __construct(array $funcMap) {
    $this->funcMap = $funcMap;
  }

  function resolve(Request $request, Response $response) {
    if ( $response->status() != 200 ) {
      return; // only normal response will be processed
    }

    foreach ( (array) $request->meta('output') as $outputFilter ) {
      // format: func([param1[,param2[,param3 ...]]])
      if ( preg_match('/(\w+)\(([\w\s,]+)\)/', $outputFilter, $matches) ) {
        $func = @$this->funcMap[$matches[1]];
        if ( is_callable($func) ) {
          $func = call_user_func_array(
            $func,
            explode(',', $matches[2]) // note: preserve whitespace
            );

          if ( is_callable($func) ) {
            $_handler = set_error_handler(function($num, $str, $file = null, $line = null, $ctx = null) use($response) {
              if ( !error_reporting() ) {
                return;
              }

              Log::warning($str, $ctx);

              $response->clearHeaders();
              $response->header('Content-Type: text/plain');
              $response->send('An error occurred during post process, please check API usage.', 500);
              die;
            }, E_ALL);

            $response->send($func($response->body()));

            set_error_handler($_handler);
          }
        }
      }
    }
  }

}
