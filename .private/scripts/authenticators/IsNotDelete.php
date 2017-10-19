<?php /* IsNotDelete.php | Allow DELETE requests. */

namespace authenticators;

use framework\Request;

class IsNotDelete implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return $r && $r->method() != 'delete';
  }

}
