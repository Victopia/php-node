<?php
/* IsInternal.php | Authenticates server only processes. */

namespace authenticators;

use framework\Request;

class IsInternal implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return !$r || @$r->__local;
  }

}
