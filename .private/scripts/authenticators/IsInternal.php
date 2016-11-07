<?php /* IsInternal.php | Authenticates server only processes. */

namespace authenticators;

use framework\Configuration as conf;
use framework\Request;
use framework\System;

class IsInternal implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return
      !$r ||
      (
        $r->client('host') &&
        $r->client('host') == System::getHostname($r->client('secure')? 'secure': 'default')
      ) ||
      in_array(
        $r->client('address'),
        (array) conf::get('system::localhosts', '127.0.0.1')
      );
  }

}
