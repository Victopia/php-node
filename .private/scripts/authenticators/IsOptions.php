<?php /* IsOption.php | Allow OPTION requests. */

namespace authenticators;

use framework\Request;

class IsOption implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return $r && $r->method() == 'options';
  }

}
