<?php
/*! IAuthorizableWebService.php | Provides an interface for gateway to query a method is allowed for access or not. */

namespace framework\interfaces;

interface IAuthorizableWebService extends IWebService {

  /**
   * Service gateway will enquire this method for
   * access control, http code 405 Forbidden will
   * be returned to user if this method return FALSE.
   */
  function authorizeMethod($name, $args = null);

}
