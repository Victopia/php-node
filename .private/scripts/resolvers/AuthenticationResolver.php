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

  /**
   * @protected
   *
   * Authentication paths.
   */
  protected $paths = array(
      '*' => true
    );

  /**
   * @protected
   *
   * HTTP Status Code to send when access is denied.
   */
  protected $statusCode = 401;

  /**
   * @constructor
   *
   * @param {array} $options[paths] Authentication path mappings, defaults
   *                                { "*": true } to allow everything.
   * @param {int} $options[statusCode] HTTP status code to send when access is
   *                                   denied, defaults to 401 Unauthorized.
   */
  public function __construct(array $options = array()) {
    if ( !empty($options['paths']) ) {
      $this->paths = (array) $options['paths'];
    }

    if ( !empty($options['statusCode']) ) {
      $this->statusCode = (int) $options['statusCode'];
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

    $lastWildcard = @$auth['*'];

    foreach ( $pathNodes as $index => $pathNode ) {
      if ( !util::isAssoc($auth) ) {
        break; // No more definitions, break out.
      }

      if ( isset($auth['*']) ) {
        $lastWildcard = $auth['*'];
      }

      if ( isset($auth[$pathNode]) ) {
        $auth = $auth[$pathNode];
      }
      else {
        unset($auth);
        break;
      }
    }

    if ( !isset($auth) || !is_bool($auth) && (!is_array($auth) || util::isAssoc($auth)) ) {
      if ( empty($lastWildcard) ) {
        throw new FrameworkException('Unable to resolve authentication chain from request URI.');
      }
      else {
        $auth = $lastWildcard;
      }
    }

    unset($pathNodes, $lastWildcard);

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
      $res->status($this->statusCode);
    }

    // TODO: Mark allowed or denied according to the new resolver mechanism.
  }

}
