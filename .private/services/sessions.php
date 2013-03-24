<?php
/*! sessions.php | Service for framework Session. */

class sessions implements framework\interfaces\IWebService {
	function validate($username, $password, $overrideExists = FALSE) {
		$res = session::validate($username, $password, $overrideExists);

		if (is_int($res)) {
			switch ($res) {
				case session::ERR_MISMATCH:
					throw new framework\exceptions\ServiceException('Username and password mismatch.', $res);
					break;
				case session::ERR_EXISTS:
					throw new framework\exceptions\ServiceException('Session already exists.', $res);
					break;
			}
		}

		return $res;
	}

	function ensure($sid, $token = NULL) {
		$res = session::ensure($sid, $token);

		if (is_int($res)) {
			switch ($res) {
				case session::ERR_INVALID:
					throw new framework\exceptions\ServiceException('Specified session ID is not valid.', $res);
					break;
				case session::ERR_EXPIRED:
					throw new framework\exceptions\ServiceException('Session has expired, restore it before making other calls.', $res);
					break;
			}
		}

		return $res;
	}

	function current() {
		return (string) session::current();
	}

	function generateToken($sid) {
		return (string) session::generateToken($sid);
	}

	function restore($sid) {
		return session::restore($sid);
	}

	function invalidate($sid = NULL) {
		return session::invalidate($sid);
	}
}