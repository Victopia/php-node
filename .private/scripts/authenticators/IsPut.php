<?php /* IsPut.php | Allow PUT requests. */

namespace authenticators;

use framework\Request;

class IsPut implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return $r && $r->method() == 'put';
  }

}
