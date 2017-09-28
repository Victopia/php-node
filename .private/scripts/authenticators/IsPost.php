<?php /* IsPost.php | Allow POST requests. */

namespace authenticators;

use framework\Request;

class IsPost implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return $r && $r->method() == 'post';
  }

}
