<?php
/* IsSuperUser.php | Check if requesting user has super privilege. */

namespace authenticators;

use framework\Request;

class IsSuperUser implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return !$r || IsAdministrator::authenticate($r) || IsInternal::authenticate($r);
  }

}
