<?php /*! AuthenticationResolver.php | Global authentication of all requests. */

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
   * Path Prefix
   */
  protected $prefix = '/';

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

    if ( !empty($options['prefix']) ) {
      $this->prefix = "$options[prefix]";
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

    $pathNodes = ltrim($req->uri('path'), "$this->prefix/");
    $pathNodes = trim($pathNodes, '/');
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
      $authenticator = function($rule) use(&$auth, $req) {
        if ( is_bool($rule) ) {
          return $rule;
        }
        else if ( is_callable($rule) ) {
          return $rule($req);
        }
        else if ( is_string($rule) ) {
          if ( strpos($rule, '/') === false ) {
            $rule = "authenticators\\$rule";
          }

          if ( is_a($rule, 'framework\interfaces\IAuthenticator', true) ) {
            return $rule::authenticate($req);
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
      };

      $auth = array_reduce($auth, function($result, $auth) use(&$authenticator, $req) {
        if ( is_array($auth) && !is_callable($auth) ) {
          $auth = array_reduce($auth, function($result, $auth) use(&$authenticator, $req) {
            return $result && $authenticator($auth);
          }, true);
        }

        return $result || $authenticator($auth);
      }, false);

      unset($authenticator);
    }

    // Boolean
    if ( is_bool($auth) && !$auth ) {
      $res->status($this->statusCode);
    }

    // TODO: Mark allowed or denied according to the new resolver mechanism.
  }

}
