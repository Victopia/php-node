<?php
/*! TestService.php | Sample web service. */

/**
 * This class act as a sample service, further demonstrates how to write RESTful functions.
 *
 * In short:
 * 1. Filename must match class name.
 * 2. Returned values are json encoded then passed to output buffer
 * 3. Output header is always application/json
 * 4. To access this, GET request to http(s)://(your_domain)/services/TestService/echo/(foobar)
 * 5. Does NOT support sub-directories yet.
 */
class TestService extends \framework\WebService {

  function testMethod($input = null) {
    if ( $input !== null ) {
      return $input;
    }
    else {
      return $this->request()->param();
    }
  }

}
