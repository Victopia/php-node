<?php /*! Session.php | Manage login sessions. */

/*! System flow:
 * - 1.  Client attemp to login
 * - 2.  Server returns session info of that user.
 * - 2.1 Server returns static::ERR_MISMATCH if login failure.
 * - 2.2 Server returns static::ERR_EXISTS if an existing session hasn't been invalidated.
 * - 3.  Client stores the session id.
 * - 4.  Services should call Session::ensure($session_id) in most requests.
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

use models\users;

use Ramsey\Uuid\Uuid;

class Session {

  //----------------------------------------------------------------------------
  //
  //  Constants
  //
  //----------------------------------------------------------------------------

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
  const EXPIRE_TIME = "+30 min";

  // Sessions this old will be deleted.
  const DELETE_TIME = "-1 week";

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   *
   * Session ID is stored upon successful calls to validate(), ensure(), or restore().
   */
  private static $currentSession = null;

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

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Acquire a session from specified user UUID.
   *
   * @param models\user $user Target user model.
   * @param string $fingerprint Client fingerprint for multiple login support,
   *               omitting this implies single sign-on mode.
   * @return array Acquired session object.
   *               + [uuid] Session identity.
   *               + [user_uuid] User identity.
   *               + [expire_at] Expiration time in RFC3339 format.
   * @throws framework\exceptions\FrameworkException When in single sign-on mode,
   *         throws when another session already exists.
   */
  static function fromUser(user $user, $fingerprint = null) {
    if ( !$user->identity() ) {
      throw new framework\exceptions\FrameworkException("Invalid user provided.", static::ERR_INVALID);
    }

    $fingerprint = strtolower(trim($fingerprint));

    $session =
      [ Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION
      , "user_uuid" => util::packUuid($user->identity())
      ];

    if ( $fingerprint ) {
      $session["fingerprint"] = util::packUuid($fingerprint);
    }

    $res = Node::getOne($session);

    if ( $fingerprint === null && $res ) {
      throw new exceptions\FrameworkException(
        "Session already exists.",
        static::ERR_EXISTS,
        null,
        [ "uuid" => util::unpackUuid($res["uuid"]) ]
      );
    }

    if ( $res ) {
      $session = $res;
    }
    else {
      $session["uuid"] = util::packUuid(preg_replace("/-/", "", Uuid::uuid4()));
    }

    $session["timestamp"] = util::formatDate("Y-m-d H:i:s.u");

    Node::set($session);

    $session = [
      "uuid" => util::unpackUuid($session["uuid"]),
      "user_uuid" => util::unpackUuid($session["user_uuid"]),
      "expire_at" => util::formatDate("c", $session["timestamp"] . static::EXPIRE_TIME)
    ];

    static::$currentSession = $session;

    Log::debug("Session validated.", $session);

    return $session;
  }

  /**
   * Acquire user session from external integrations such as OAuth, OIDC... etc.
   *
   * @param string $platform google, facebook, wechat, instragram ... etc.
   * @param string $identity User's unique identity from target platform, usually open id.
   * @param string $secret Optional. Secret from target platform.
   * @param string $fingerprint Optional. Uniquely identifies the connecting
   *                            client, identical fingerprints will override
   *                            existing sessions. Ommiting this implies single
   *                            sign-on mode.
   * @return array The acquired session.
   * @throws framework\exceptions\FrameworkException Invalid credentials provided.
   */
  static function fromIntegration($platform, $identity, $secret = "", $fingerprint = null) {
    $user = (new user(null, [ "request"=> null ]))->loadByIntegration($platform, $identity, $secret);

    if ( !$user->identity() ) {
      throw new exceptions\FrameworkException("Invalid credentials.", static::ERR_MISMATCH);
    }

    return static::fromUser($user->identity(), $fingerprint);
  }

  /**
   * Acquire user session from local username and password.
   *
   * @param string $username Username of the user signing in.
   * @param string $password Password of the user signing in.
   * @param string $fingerprint Optional. Uniquely identifies the connecting
   *                            client, identical fingerprints will override
   *                            existing sessions. Ommiting this implies single
   *                            sign-on mode.
   * @return array The acquired session.
   * @throws framework\exceptions\FrameworkException Invalid credentials provided.
   */
  static function fromCredential($username, $password, $fingerprint = null) {
    $user = (new user(null, [ "request"=> null ]))->load($username);

    if ( !$user->identity() || !$user->verifyPassword($password) ) {
      throw new exceptions\FrameworkException("Invalid credentials.", static::ERR_MISMATCH);
    }

    return static::fromUser($user, $fingerprint);
  }

  /**
   * Alias of fromCredentials().
   * @see Session::fromCredentials();
   */
  static function validate($username, $password, $fingerprint = null) {
    return static::fromCredential($username, $password, $fingerprint);
  }

  /**
   * Logout function, application terminating point.
   */
  static function invalidate($session_id = null) {
    if ( $session_id === null ) {
      $session_id = static::current("uuid");
    }

    $session_id = util::packUuid($session_id);

    $session = Node::getOne(array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
        "uuid" => $session_id
      ));

    $res = Database::query("DELETE FROM `".FRAMEWORK_COLLECTION_SESSION."` WHERE `uuid` = ?", array($session_id));

    if ( $res->rowCount() ) {
      Log::info(sprintf("Session invalidated", $session));

      /* Clear reference */
      static::$currentSession = null;
    }

    return (bool) $res->rowCount();
  }

  /**
   * Logout all sessions of specified user.
   */
  static function invalidateUser($username) {
    $user = (new user)->load($username);

    if ( $user->identity() ) {
      Database::query("DELETE FROM `".FRAMEWORK_COLLECTION_SESSION."` WHERE `user_uuid` = ?", array($user->identity()));
    }
  }

  /**
   * Permission ensuring function, and session keep-alive point.
   * This function should be called on the initialization stage of every page load.
   *
   * CAUTION: When $token is specified, extended security is performed on the current session.
   *          Current session can expire with constant Session::ERR_EXPIRED after 30 minutes of inactivity.
   *
   * @return boolean true on success, false otherwise.
   */
  static function ensure($uuid, $token = null, $fingerprint = null) {
    $session = Node::getOne([
      Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
      "uuid" => util::packUuid($uuid),
      "fingerprint" => $fingerprint
    ]);

    if ( !$session ) {
      throw new exceptions\FrameworkException("Invalid session identifier.", static::ERR_INVALID);
    }

    // Session expired
    if ( strtotime($session["timestamp"] . static::EXPIRE_TIME) < time() ) {
      throw new exceptions\FrameworkException("Session expired, please login again.", static::ERR_EXPIRED);
      return false;
    }

    $session["token"] = null;
    $session["timestamp"] = util::formatDate("Y-m-d H:i:s.u");

    // Update timestamp
    Node::set($session);

    $session = [
      "uuid" => util::unpackUuid($session["uuid"]),
      "user_uuid" => util::unpackUuid($session["user_uuid"]),
      "expire_at" => util::formatDate("c", $session["timestamp"] . static::EXPIRE_TIME)
    ];

    static::$currentSession = $session;

    return $session;
  }

  /**
   * Generate a one-time authentication token string for additional
   * security for AJAX service calls.
   *
   * Each additional call to this function overwrites the token generated last time.
   *
   * @return string One-time token string, or null on invalid session.
   */
  static function generateToken($session_id = null) {
    $res = static::ensure($session_id);
    if ( $res !== true ) {
      return $res;
    }

    $res = &static::$currentSession;

    $res["token"] = Database::fetchField("SELECT UNHEX(REPLACE(UUID(),'-',''));");
    $res["timestamp"] = util::formatDate("Y-m-d H:i:s.u");

    if ( Node::set($res) === false ) {
      return null;
    }

    return util::unpackUuid($res["token"]);
  }

  /**
   * Returns user object associated with current session.
   */
  static function getUser() {
    return (new user)->load(static::current("user_uuid"));
  }

}
