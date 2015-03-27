<?php
/*! sessions.php | Service for framework Session. */

use framework\Session;

/**
 * This class act as a sample service, further demonstrates how to write RESTful functions.
 */
class sessions implements framework\interfaces\IWebService {
	function validate($username, $password, $overrideExists = false) {
		$res = Session::validate($username, $password, $overrideExists);

		if ( is_int($res) ) {
			switch ($res) {
				case Session::ERR_MISMATCH:
					throw new framework\exceptions\ServiceException('Username and password mismatch.', $res);
					break;
				case Session::ERR_EXISTS:
					throw new framework\exceptions\ServiceException('Session already exists.', $res);
					break;
			}
		}

		return $res;
	}

	function ensure($sid, $token = null) {
		$res = Session::ensure($sid, $token);

		if (is_int($res)) {
			switch ($res) {
				case Session::ERR_INVALID:
					throw new framework\exceptions\ServiceException('Specified session ID is not valid.', $res);
					break;

				case Session::ERR_EXPIRED:
					throw new framework\exceptions\ServiceException('Session has expired, restore it before making other calls.', $res);
					break;
			}
		}

		return $res;
	}

	function current() {
		return (string) Session::current();
	}

	function generateToken($sid) {
		return (string) Session::generateToken($sid);
	}

	function restore($sid) {
		return Session::restore($sid);
	}

	function invalidate($sid = NULL) {
		return Session::invalidate($sid);
	}
}
