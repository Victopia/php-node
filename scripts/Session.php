<?php

// Reserved error code for client use
define('SESSION_ERR_EXPIRED', 0);

// Returned when user and password mismatch
define('SESSION_ERR_MISMATCH', 1);

// Returned when session exists
define('SESSION_ERR_EXISTS', 2);

// Returned when session id provided is invalid.
define('SESSION_ERR_INVALID', 3);

// Returned when user account is suspended by admins
define('SESSION_ERR_SUSPENDED', 4);

class Session
{
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
	 *           3. FALSE on login mismatch.
	 */
	static function validate($username, $password, $overrideExist = false)
	{
		$filter = Array(
			'identifier' => 'User',
			'username' => Utility::sanitizeRegexp($username), 
			'password' => Utility::sanitizeRegexp($password)
		);
		
		$user = Node::get( $filter );
		
		if (!Is_Array($user) || Count($user) == 0) {
			return SESSION_ERR_MISMATCH;
		}
		
		$user = $user[0];
		
		$session = Database::fetchRow('SELECT `sid`, 
			`timestamp` + INTERVAL 30 MINUTE >= CURRENT_TIMESTAMP AS \'valid\' 
			FROM `Sessions` WHERE `UserID` = ?', Array($user['ID']));
		
		// Session exists
		if (Is_Array($session) && $session['sid'] && 
			$session['valid'] && !$overrideExist) {
			return SESSION_ERR_EXISTS;
		}
		
		// Suspended account
		if ( isset($user['suspended']) && $user['suspended'] === TRUE ) {
			return SESSION_ERR_SUSPENDED;
		}
		
		// Can login, generate sid and stores to PHP session.
		$sid = SHA1($username . (microtime(1) * 1000) . $password);
		
		if ( !isset($user['locale']) ) {
			$user['locale'] = 'enUS';
		}
		
		// Composite session object.
		$sessionObject = Array('sid' => $sid, 'locale' => $user['locale']);
		
		// Log the sign in action.
		Log::write(__CLASS__, 'validate', json_encode($sessionObject));
		
		// Inserts the session.
		Database::upsert('Sessions', Array('UserID' => $user['ID'], 'sid' => $sid, 'timestamp' => NULL));
		
		// Updates user timestamp.
		Node::set(Array(
			'identifier' => 'User',
			'ID' => $user['ID']
		), true);
		
		return $sessionObject;
	}
	
	/**
	 *  Logout function, application terminating point.
	 */
	static function invalidate($sid)
	{
		Database::query("DELETE FROM `Sessions` WHERE `sid` = ?", Array($sid));
	}
	
	/**
	 *  Permission ensuring function, and session keep-alive point.
	 *  This function should be called on the initialization stage of every page load.
	 *
	 *  @param $token Optional, decided as a one-time key to have advanced security over AJAX calls.
	 *         This token string should be get from function requestToken.
	 *
	 *  @returns TRUE on access permitted, FALSE otherwise.
	 */
	static function ensure($sid, $token = NULL)
	{
		if (!isset($sid) || !$sid) {
			return SESSION_ERR_INVALID;
		}
		
		$res = Database::select('Sessions', Array('UserID', 'token'), 
			'WHERE `sid` = ? AND `timestamp` + INTERVAL 30 MINUTE >= CURRENT_TIMESTAMP', 
			Array($sid));
		
		/* Session validation. */
		if ($res !== FALSE && Count($res) > 0) {
			/* Token validation. */
			if ($token !== NULL && $token != $res[0]['token']) {
				return FALSE;
			}
			
			/* Session keep-alive update. */
			Database::upsert('Sessions', Array('UserID' => $res[0]['UserID'], 'token' => NULL, 'timestamp' => NULL));
			
			return TRUE;
		}
		
		else {
			return SESSION_ERR_INVALID;
		}
		
		return FALSE;
	}
	
	/**
	 *  Restore a persistant session, updates session timestamp directly to 
	 *  emulate a wake-up action.
	 *
	 *  This function does the same thing as ensure($sid), but didn't check for timeout.
	 *
	 *  @param $sid Session identifier string returned by the validate() method.
	 *
	 *  @returns TRUE on access permitted, false otherwise.
	 */
	static function restore($sid)
	{
		if (!isset($sid) || !$sid) {
			return SESSION_ERR_INVALID;
		}
		
		$res = Database::select('Sessions', Array('UserID', 'token'), 
			'WHERE `sid` = ?', Array($sid));
		
		/* Session validation. */
		if ($res !== FALSE && Count($res) > 0) {
			/* Session keep-alive update. */
			$res = Database::upsert('Sessions', 
				Array('UserID' => $res[0]['UserID'], 'token' => NULL, 'timestamp' => NULL));
			
			if ( $res !== FALSE ) {	
				// Store to PHP session.
				$sid = $sid;
				
				// Retrieve user preferred locale.
				$sessionUser = self::getUser($sid);
				
				return Array('sid' => $sid, 'locale' => $sessionUser['locale']);
			}
		}
		
		else {
			return SESSION_ERR_INVALID;
		}
		
		return FALSE;
	}
	
	/**
	 *  Generate a one-time authentication token string for additional 
	 *  security for AJAX service calls.
	 *
	 *  Each additional call to this function overwrites the token generated last time.
	 *
	 *  @returns One-time token string, or NULL on invalid session.
	 */
	static function generateToken()
	{
		self::ensure();
		
		$token = SHA1($sid . (microtime(1) * 1000));
		
		$res = Database::upsert('Sessions', Array('UserID' => $res, 'token' => $token));
		
		if ( $res === FALSE ) {
			return NULL;
		}
		
		return $token;
	}
	
	/**
	 *  Local user cache.
	 */
	private static $userCache = Array();
	
	/**
	 *  Shortcut function for getting user information.
	 */
	static function getUser($sid)
	{
		if ( isset($userCache[$sid]) ) {
			return $userCache;
		}
		
		$res = 'SELECT `UserID` FROM `Sessions` WHERE `sid` = ?;';
		$res = Database::fetchField($res, Array($sid));
		
		if ( !$res ) {
			return NULL;
		}
		
		$res = Node::get(Array(
			'identifier' => 'User', 
			'ID' => $res
		));
		
		if ($res && Count($res) > 0) {
			return self::$userCache[$sid] = $res[0];
		}
		else {
			unset($userCache[$sid]);
			
			return NULL;
		}
	}
}