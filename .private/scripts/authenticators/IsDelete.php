<?php /* IsDelete.php | Allow DELETE requests. */

namespace authenticators;

use framework\Request;

class IsDelete implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return $r && $r->method() == 'delete';
  }

}
