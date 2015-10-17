<?php
/* IsAdministrator.php | Authenticates administrators access. */

namespace authenticators;

use framework\Request;

class IsAdministrator implements \framework\interfaces\IAuthenticator {

	static function authenticate(Request $r) {
		return in_array('Administrators', (array) @$r->user['groups']);
	}

}
