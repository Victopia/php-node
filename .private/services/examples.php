<?php
/*! examples.php | Sample web service. */

namespace services;

/**
 * This class act as a sample service, further demonstrates how to write RESTful functions.
 *
 * In short:
 * 1. Filename must match class name.
 * 2. Returned values are json encoded then passed to output buffer
 * 3. Output header is always application/json
 * 4. To access this, GET request to http(s)://(your_domain)/services/examples/(method)/(param)
 * 5. Does NOT support sub-directories yet.
 */
class examples extends \framework\WebService {

  function hello() {
    return 'Hello world!';
  }

  function ping() {
    return 'pong!';
  }

  /**
   * This method has two behaviors.
   *
   * 1. URL "examples/test/foo" will return ["foo"]
   * 2. URL "examples/test?foo=bar" will return {"foo":"bar"}
   *
   * @return {array} All given parameters as an array, or request params otherwise.
   */
  function test() {
    if ( func_num_args() ) {
      // Simple echo method
      return func_get_args();
    }
    else {
      // Type casting ensures the encoded JSON is object, even if it is empty.
      return (object) $this->request()->param();
    }
  }

}
