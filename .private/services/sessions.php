<?php
/*! sessions.php | Service to manage login sessions. */

namespace services;

use core\Database;
use core\Node;

use framework\Session;

use framework\exceptions\ServiceException;

/**
 * This class act as a sample service, further demonstrates how to write RESTful functions.
 */
class sessions extends \framework\WebService {

	function validate($username, $password) {
		$session = Node::getOne(array(
				Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
				'username' => Database::escapeValue($username)
			));

		$res = Session::validate($username, $password, $this->request()->fingerprint());
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
		$res = Session::ensure($sid, $token, $this->request()->fingerprint());
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

	function token() {
		return Session::generateToken();
	}

	function invalidate($sid = null) {
		if ( $sid === null ) {
			$sid = $this->request()->meta('sid');
		}

		return Session::invalidate($sid);
	}

}
