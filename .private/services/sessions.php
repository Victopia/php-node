<?php
/*! sessions.php | Service to manage login sessions. */

use framework\Session;

use framework\exceptions\ServiceException;

/**
 * This class act as a sample service, further demonstrates how to write RESTful functions.
 */
class sessions implements framework\interfaces\IWebService {
	function validate($username, $password, $overrideExists = false) {
		$res = Session::validate($username, $password, $overrideExists);
		if ( is_int($res) ) {
			switch ( $res ) {
				case Session::ERR_MISMATCH:
					throw new ServiceException('Username and password mismatch.', $res);
					break;

				case Session::ERR_EXISTS:
					throw new ServiceException('Session already exists.', $res);
					break;
			}
		}

		return $res;
	}

	function ensure($sid, $token = null) {
		$res = Session::ensure($sid, $token);
		if ( is_int($res) ) {
			switch ( $res ) {
				case Session::ERR_INVALID:
					throw new ServiceException('Specified session ID is not valid.', $res);
					break;

				case Session::ERR_EXPIRED:
					throw new ServiceException('Session has expired, restore it before making other calls.', $res);
					break;
			}
		}

		return $res;
	}

	function current() {
		return Session::current();
	}

	function generateToken() {
		return Session::generateToken();
	}

	function restore($sid = null) {
		if ( $sid === null ) {
			$sid = $this->request()->param('__sid');
		}

		return Session::restore($sid);
	}

	function invalidate() {
		return Session::invalidate($sid);
	}
}
