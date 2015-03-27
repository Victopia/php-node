<?php
/*! Session.php | https://github.com/victopia/PHPNode
 *
 * Manage login sessions.
 *
 * System flow:
 * - 1.  Client attemp to login
 * - 2.  Server returns session info of that user.
 * - 2.1 Server returns self::ERR_MISMATCH if login failure.
 * - 2.2 Server returns self::ERR_EXISTS if an existing session hasn't been invalidated.
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
 * - 7.1 Server returns self::ERR_INVALID if session ID is not found.
 *
 */

namespace framework;

use core\Database;
use core\Node;
use core\Log;

class Session {
  //------------------------------
  //  Error constants
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

  //------------------------------
  //  User status, OR safe
  //------------------------------
  // 00000001: Public users
  const USR_PUBLIC   = 0b000001;

  // 00000010: Normal authorized users
  const USR_NORMAL   = 0b000010;

  // 00000110: Administrators ( 4 | 2 = 6, Normal right included )
  const USR_ADMINS   = 0b000110;

  // 00001000: Users temporary locked
  const USR_LOCKED   = 0b001000;

  // 00010000: Suspended users
  const USR_BANNED   = 0b010000;

  // 00100000: Users Not Verify
  const USR_INACTIVE = 0b100000;

  // Session ID is stored when validate(), ensure(), or
  // restore() is called without failure.
  private static $currentSession = null;

  /**
   *  Login function, application commencement point.
   *
   *  @param $username Username of the user.
   *  @param $password SHA1 hash of the password.
   *  @param $overrideExist Specify whether to override existing session.
   *         i.e. User not logged out or logged in from elsewhere.
   *
   *  @returns Possible return values are:
   *           1. Session identifier string on success,
   *           2. Empty string on session exists without override, or
   *           3. false on login mismatch.
   */
  static function validate($username, $password, $overrideExist = false) {
    // Username
    $res = array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER
      , 'username' => $username
      );

    $res = Node::get($res);

    if ( !is_array($res) || count($res) == 0 ) {
      return self::ERR_MISMATCH;
    }

    $res = $res[0];

    // Password crypt matching
    if ( crypt($password, $res['password']) !== $res['password'] ) {
      return self::ERR_MISMATCH;
    }

    $session = Database::fetchRow('SELECT `sid`,
      `timestamp` + INTERVAL 30 MINUTE >= CURRENT_TIMESTAMP AS \'valid\'
      FROM `'.FRAMEWORK_COLLECTION_SESSION.'` WHERE `UserID` = ?', Array($res['ID']));

    // Session exists and not overriding, return error.
    if ( is_array($session) && $session['valid'] ) {
      if ( $overrideExist === false ) {
        return self::ERR_EXISTS;
      }
      else {
        // Notify existing users
        \eBay\Utility::sendUserMessage($username, array(
            'session.invalidate' => microtime(1)
          ));
      }
    }

    // Can login, generate sid and stores to PHP session.
    $sid = sha1($username . (microtime(1) * 1000) . $password);

    // Update "Last Login Time" by Steven (2014-09-02)
    $res["lastLogin"] = date("Y-m-d");

    Node::set($res);

    // Inserts the session.
    Database::upsert(FRAMEWORK_COLLECTION_SESSION, array(
        'UserID' => $res['ID'],
        'sid' => $sid,
        'timestamp' => null
      ));

    /* Reference to current session. */
    self::$currentSession = $sid;

    // Log the sign in action.
    Log::write('Session validated.', 'Information', $sid);

    return $sid;
  }

  /**
   *  Logout function, application terminating point.
   */
  static function invalidate($sid = null) {
    if ( $sid === null ) {
      $sid = self::$currentSession;
    }

    Log::write('Session invalidate.', 'Information', $sid);

    Database::query('DELETE FROM `'.FRAMEWORK_COLLECTION_SESSION.'` WHERE `sid` = ?', array($sid));

    /* Clear reference */
    self::$currentSession = null;
  }

  /**
   *  Permission ensuring function, and session keep-alive point.
   *  This function should be called on the initialization stage of every page load.
   *
   *  @param $token Optional, decided as a one-time key to have advanced security over AJAX calls.
   *         This token string should be get from function requestToken.
   *
   *  CAUTION: When specified, extended security is performed on the current session.
   *           Current session can expire with constant Session::ERR_EXPIRED after 30 minutes of inactivity.
   *
   *  @returns true on access permitted, false otherwise.
   */
  static function ensure($sid, $token = null) {
    if ( !$sid ) {
      return self::ERR_INVALID;
    }

    $res = Database::fetchRow('SELECT `UserID`, `token`,
      `timestamp` + INTERVAL 30 MINUTE >= CURRENT_TIMESTAMP as \'valid\'
      FROM `'.FRAMEWORK_COLLECTION_SESSION.'` WHERE `sid` = ?', array($sid));

    // Session validation
    if ( $res ) {
      // Token validation
      if ( ($token || $res['token']) && $token != $res['token'] ) {
        return false;
      }

      if ( !$res['valid'] ) {
        return self::ERR_EXPIRED;
      }

      /* Session keep-alive update. */
      Database::upsert(FRAMEWORK_COLLECTION_SESSION, array(
        'UserID' => $res['UserID'],
        'token' => null,
        'timestamp' => null
      ));

      /* Reference to current session. */
      self::$currentSession = $sid;

      return true;
    }

    else {
      return self::ERR_INVALID;
    }

    return false;
  }

  /**
   *  Restore a persistant session, updates session timestamp directly to
   *  emulate a wake-up action.
   *
   *  This function does the same thing as ensure($sid), but didn't check for timeout.
   *
   *  @param $sid Session identifier string returned by the validate() method.
   *
   *  @returns true on access permitted, false otherwise.
   */
  static function restore($sid) {
    if ( !isset($sid) || !$sid ) {
      return self::ERR_INVALID;
    }

    $res = Database::select(FRAMEWORK_COLLECTION_SESSION
      , array('UserID', 'token')
      , 'WHERE `sid` = ?', array($sid));

    /* Session validation. */
    if ( $res !== false && count($res) > 0 ) {
      /* Session keep-alive update. */
      $res = Database::upsert(FRAMEWORK_COLLECTION_SESSION, array(
          'UserID' => $res[0]['UserID']
        , 'token' => null
        , 'timestamp' => null
        ));

      if ( $res !== false ) {
        // Store to PHP session.
        $sid = $sid;

        // Retrieve current user.
        $sessionUser = self::getUser($sid);

        // Reference to current session.
        self::$currentSession = $sid;

        return $sid;
      }
    }

    else {
      return self::ERR_INVALID;
    }

    return false;
  }

  /**
   *  Generate a one-time authentication token string for additional
   *  security for AJAX service calls.
   *
   *  Each additional call to this function overwrites the token generated last time.
   *
   *  @returns One-time token string, or null on invalid session.
   */
  static function generateToken($sid) {
    self::ensure($sid);

    $token = SHA1($sid . (microtime(1) * 1000));

    $res = Database::upsert(FRAMEWORK_COLLECTION_SESSION, array('UserID' => $res, 'token' => $token));

    if ( $res === false ) {
      return null;
    }

    return $token;
  }

  /**
   *  Get current session ID.
   *
   *  This will only be set on first call of validate(), ensure() or restore().
   */
  static function current($property = null) {
    $res = self::$currentSession;

    if ( is_null($property) ) {
      return $res;
    }

    else {
      $res = array(
          NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION
        , 'sid' => self::$currentSession
        );
      $res = Node::get($res);

      if ( count($res) == 0 ) {
        return null;
      }

      return @$res[0][$property];
    }
  }

  /**
   *  Check if current user has specified status.
   *
   *  See constants with USR_ prefix.
   */
  static function checkStatus($status) {
    $res = self::currentUser();

    if ( !$res ) {
      return $status === self::USR_PUBLIC;
    }

    $res = @$res['status'];

    return ($res & $status) === $status;
  }

  /**
   *  Shortcut function for getting user information.
   */
  static function currentUser($property = null) {
    $sid = self::$currentSession;

    if ( !$sid ) {
      return null;
    }

    $res = 'SELECT `UserID` FROM `'.FRAMEWORK_COLLECTION_SESSION.'` WHERE `sid` = ?;';
    $res = Database::fetchField($res, array($sid));

    if ( !$res ) {
      return null;
    }

    $res = array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER
      , 'ID' => $res
      );
    $res = Node::get($res);

    if ( $res ) {
      if ( $property !== null ) {
        return @$res[0][$property];
      }
      else {
        return $res[0];
      }
    }
    else {
      return null;
    }
  }
}
