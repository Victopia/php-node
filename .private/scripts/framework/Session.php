<?php
/*! Session.php | Manage login sessions. */

/*! System flow:
 * - 1.  Client attemp to login
 * - 2.  Server returns session info of that user.
 * - 2.1 Server returns static::ERR_MISMATCH if login failure.
 * - 2.2 Server returns static::ERR_EXISTS if an existing session hasn't been invalidated.
 * - 3.  Client stores the session id.
 * - 4.  Services should call Session::ensure($sid) in most requests.
 *       Client is responsible to requests along with session ID.
 * - 4.1 Client can explicitly request a one-time request token for extended security.
 *       If this token is not provided in next request, Session::ensure() will fail.
 *       If such token is lost, the client must then request another token to proceed
 *       further actions.
 * - 5.  Client can leave the session alone without invalidation,
 *       sessions are expiring in 30mins of inactivity be default.
 *       (Inactivity means Session::ensure() not being called)
 * - 6.  When a client is back and want to revive an expired sessoin, use Session::restore().
 * - 7.  Server will return session info along with that user.
 * - 7.1 Server returns static::ERR_INVALID if session ID is not found.
 */

namespace framework;

use core\Database;
use core\Node;
use core\Log;
use core\Utility as util;

use models\User;
use models\TaskInstance;

class Session {

  //------------------------------
  //  Constants
  //------------------------------

  // Returned when extended security is requested, and current session is expired
  const ERR_EXPIRED       = 0;

  // Returned when user and password mismatch
  const ERR_MISMATCH      = 1;

  // Returned when session exists
  const ERR_EXISTS        = 2;

  // Returned when session id provided is invalid.
  const ERR_INVALID       = 3;

  // Returned when one-time token mismatch.
  const ERR_TOKEN_INVALID = 4;

  // Session expire time relative to current time.
  const EXPIRE_TIME = '-30 min';

  // Sessions this old will be deleted.
  const DELETE_TIME = '-1 week';

  /**
   * @private
   *
   * Session ID is stored upon successful calls to validate(), ensure(), or restore().
   */
  private static $currentSession = null;

  /**
   * Login function, application commencement point.
   *
   * @param $username Username of the user.
   * @param $password SHA1 hash of the password.
   * @param $fingerprint Fingerprint extracted from current request, identifies
   *                     a requesting host as uniquely as possible.
   *
   * @return Possible return values are:
   *         1. Session identifier string on success,
   *         2. Empty string on session exists without override, or
   *         3. false on login mismatch.
   */
  static function validate($username, $password, $fingerprint = null) {
    // Search by username
    $user = (new User)->load($username);
    if ( !$user->identity() ) {
      return static::ERR_MISMATCH;
    }

    // Password crypt matching
    if ( crypt($password, $user->password) !== $user->password ) {
      return static::ERR_MISMATCH;
    }

    // Can login, generate sid and stores to PHP session.
    $session = array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
        'sid' => Database::fetchField("SELECT UNHEX(REPLACE(UUID(), '-', ''))"),
        'username' => $user->identity(),
      );

    if ( $fingerprint ) {
      $session['fingerprint'] = $fingerprint;
    }

    // Store session into database
    Node::set($session);

    // Reference to current session
    static::$currentSession = $session;

    // Log the sign in action.
    Log::debug('Session validated', $session);

    return util::unpackUuid($session['sid']);
  }

  /**
   * Logout function, application terminating point.
   */
  static function invalidate($sid = null) {
    if ( $sid === null ) {
      $sid = static::$currentSession['sid'];
    }

    $sid = util::packUuid($sid);

    $session = Node::getOne(array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
        'sid' => $sid
      ));

    Database::query('DELETE FROM `'.FRAMEWORK_COLLECTION_SESSION.'` WHERE `sid` = ?', array($sid));

    Log::info(sprintf('Session invalidated', $session));

    /* Clear reference */
    static::$currentSession = null;
  }

  /**
   * Permission ensuring function, and session keep-alive point.
   * This function should be called on the initialization stage of every page load.
   *
   * CAUTION: When $token is specified, extended security is performed on the current session.
   *          Current session can expire with constant Session::ERR_EXPIRED after 30 minutes of inactivity.
   *
   * @param $token Optional, decided as a one-time key to have advanced security over AJAX calls.
   *        This token string should be get from function requestToken.
   *
   * @return true on access permitted, false otherwise.
   */
  static function ensure($sid, $token = null, $fingerprint = null) {
    if ( !$sid ) {
      return static::ERR_INVALID;
    }

    $res = Node::getOne(array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
        'sid' => util::packUuid($sid),
        'fingerprint' => $fingerprint
      ));

    if ( !$res ) {
      return static::ERR_INVALID;
    }

    // One-time token mismatch
    if ( ($token || $res['token']) && util::packUuid($token) != $res['token'] ) {
      return false;
    }

    // Session expired
    if ( strtotime($res['timestamp']) < strtotime(static::EXPIRE_TIME) ) {
      return static::ERR_EXPIRED;
    }

    unset($res['timestamp'], $res['token']);

    // Update timestamp
    Node::set($res);

    static::$currentSession = $res;

    return true;
  }

  /**
   * Generate a one-time authentication token string for additional
   * security for AJAX service calls.
   *
   * Each additional call to this function overwrites the token generated last time.
   *
   * @return One-time token string, or null on invalid session.
   */
  static function generateToken($sid = null) {
    $res = static::ensure($sid);
    if ( $res !== true ) {
      return $res;
    }

    $res = &static::$currentSession;

    $res['token'] = Database::fetchField("SELECT UNHEX(REPLACE(UUID(),'-',''));");

    unset($res['timestamp']);

    if ( Node::set($res) === false ) {
      return null;
    }

    return util::unpackUuid($res['token']);
  }

  /**
   * Get current session ID.
   *
   * This will only be set on first call of validate(), ensure() or restore().
   */
  static function current($property = null) {
    $session = static::$currentSession;
    if ( $property === null ) {
      return $session;
    }
    else {
      return @$session[$property];
    }
  }

}
