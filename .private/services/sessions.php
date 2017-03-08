<?php /*! sessions.php | Service to manage login sessions. */

namespace services;

use core\Database;
use core\Node;

use framework\Session;

use framework\exceptions\ServiceException;

/**
 * This class act as a sample service, further demonstrates how to write RESTful functions.
 */
class sessions extends \framework\WebService {

  function validate($username = null, $password = null) {
    if ( !$username ) {
      $username = $this->request()->post('username');
    }

    if ( !$username ) {
      throw new ServiceException('No username provided.');
    }

    if ( !$password ) {
      $password = $this->request()->post('password');
    }

    if ( !$password ) {
      throw new ServiceException('No password provided.');
    }

    return Session::validate($username, $password, $this->request()->fingerprint());
  }

  function ensure($sid = null, $token = null) {
    if ( $sid === null ) {
      $sid = $this->request()->meta('sid');
    }

    return Session::ensure($sid, $token, $this->request()->fingerprint());
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

  function user() {
    return $this->request()->user;
  }

}
