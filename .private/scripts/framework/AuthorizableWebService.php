<?php
/*! AuthorizableWebService.php | Base class for web services. */

namespace framework;

abstract class AuthorizableWebService extends WebService implements interfaces\IAuthorizableWebService {

  abstract function authorizeMethod($name, $args = array());

  public function __invoke($method = null) {
    $args = func_get_args();
    $method = $this->resolveMethodName($args);
    if ( !$this->authorizeMethod($method, $args) ) {
      $this->response()->status(401); // Forbidden
    }
    else {
      return call_user_func_array('parent::__invoke', func_get_args());
    }
  }

}
