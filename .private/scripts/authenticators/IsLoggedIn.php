<?php /* IsLoggedIn.php | Authenticates administrators access. */

namespace authenticators;

use framework\Request;

class IsLoggedIn implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r) {
    return isset($r->user);
  }

}
