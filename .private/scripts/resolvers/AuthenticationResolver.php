<?php
/*! AuthenticationResolver.php | Global authentication of all requests. */

namespace resolvers;

use core\Utility as util;

use framework\Configuration as conf;
use framework\Request;
use framework\Response;

use framework\exceptions\FrameworkException;

/**
 * Perform authentication according to Configuration table settings.
 *
 * Default rule storage is in 'auth.paths'.
 */
class AuthenticationResolver implements \framework\interfaces\IRequestResolver {

  const CONFIG_IDENTIFIER = 'auth.paths';

  /**
   * @protected
   *
   * Authentication paths.
   */
  protected $paths = array(
      '*' => true
    );

  /**
   * @constructor
   *
   * @param {array} $options[paths] Authentication path mappings, defaults
   *                                { "*": true } to allow everything.
   */
  public function __construct(array $options = array()) {
    if ( empty($options['paths']) ) {
      throw new FrameworkException('Authentication paths must be specified in $options.');
    }
    else {
      $this->paths = (array) $options['paths'];
    }
  }

  /*! Example Usage:
   *  { "service":
   *    { "users":
   *      { "*": true          // Allow all methods
   *      , "set": "Session"   // Requires an active session (logged in)
   *      , "create": "Admins" // Requires administrators
   *      }
   *    }
   *  }
   */
  public function resolve(Request $req, Response $res) {
    $auth = $this->paths;

    $pathNodes = trim($req->uri('path'), '/');
    if ( $pathNodes ) {
      $pathNodes = explode('/', $pathNodes);
    }
    else {
      $pathNodes = [ '/' ];
    }

    foreach ( $pathNodes as $pathNode ) {
      if ( isset($auth[$pathNode]) ) {
        $auth = $auth[$pathNode];
      }
      else if ( isset($auth['*']) ) {
        $auth = $auth['*'];
        break; // break out on wildcards
      }
    }
    unset($pathNodes);

    // Type checking to make sure something has been picked from the foreach loop.
    if ( !is_bool($auth) && (!is_array($auth) || util::isAssoc($auth)) ) {
      throw new FrameworkException('Invalid authentication format, must be ' .
        'boolean or array of authenticators.');
    }

    // Numeric array
    if ( is_array($auth) && !util::isAssoc($auth) ) {
      $auth = array_reduce($auth, function($result, $auth) use($req) {
        if ( !$result ) {
          return $result;
        }

        if ( is_callable($auth) ) {
          $auth = $auth($req);
        }
        else if ( is_string($auth) ) {
          if ( strpos($auth, '/') === false ) {
            $auth = "authenticators\\$auth";
          }

          if ( is_a($auth, 'framework\interfaces\IAuthenticator', true) ) {
            $result = $result && $auth::authenticate($req);
          }
          else {
            throw new FrameworkException('Unknown authenticator type, must be ' .
              'instance of IAuthenticator or callable.');
          }
        }
        else {
          throw new FrameworkException('Unknown authenticator type, must be ' .
            'instance of IAuthenticator or callable.');
        }

        return $result && $auth;
      }, true);
    }

    // Boolean
    if ( is_bool($auth) && !$auth ) {
      $res->status(401);
    }

    // TODO: Mark allowed or denied according to the new resolver mechanism.
  }

}
