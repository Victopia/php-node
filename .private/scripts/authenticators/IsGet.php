<?php
/* IsGet.php | Only allow POST requests. */

namespace authenticators;

use framework\Request;

class IsGet implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return $r && $r->method() == 'get';
  }

}
