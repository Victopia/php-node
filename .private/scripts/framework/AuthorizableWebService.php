<?php
/*! AuthorizableWebService.php | Base class for web services. */

namespace framework;

abstract class AuthorizableWebService extends WebService implements interfaces\IAuthorizableWebService {

	abstract function authorizeMethod($name, $args = array());

}
