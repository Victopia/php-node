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
      if ( preg_match('/(\w+)\(([\w\s,]*)\)/', $outputFilter, $matches) ) {
        $func = @$this->funcMap[$matches[1]];
        if ( is_callable($func) ) {
          if ( @$matches[2] ) {
            $func = call_user_func_array(
              $func,
              explode(',', $matches[2]) // note: preserve whitespace
              );
          }

          if ( is_callable($func) ) {
            try {
              $response->send(
                call_user_func_array($func, array($response->body()))
                );
            }
            catch(\Exception $e) {
              Log::error(sprintf('[InvokerPostProcessor] Error calling %s(): %s @ %s:%d',
                $matches[1], $e->getMessage(), basename($e->getFile()), $e->getLine()), $e->getTrace());

              $response->send(array(
                  'error' => $e->getMessage(),
                  'code' => $e->getCode()
                ), 500);
            }
          }
        }
      }
    }
  }

}
