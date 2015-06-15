<?php
/*! IAuthenticator.php | Authenticators for AuthenticationResovler */

namespace framework\interfaces;

use framework\Request;

interface IAuthenticator {

	/**
	 * Takes a Request object as parameter and returns if the authentication is succeed.
	 *
	 * @param {Request} $req The Request object.
	 * @return {boolean} True allows the request context to access, false otherwise.
	 */
	static function authenticate(Request $req);

}
