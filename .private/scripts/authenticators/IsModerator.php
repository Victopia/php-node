<?php /* IsModerator.php | Check if requesting user has back end privilege. */

namespace authenticators;

use framework\Request;

class IsModerator implements \framework\interfaces\IAuthenticator {

  static function authenticate(Request $r = null) {
    return IsSuperUser::authenticate($r) || in_array('Moderators', (array) @$r->user['groups']);
  }

}
