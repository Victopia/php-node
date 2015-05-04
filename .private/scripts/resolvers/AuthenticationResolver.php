<?php
/*! AuthenticationResolver.php | Global authentication of all requests. */

namespace resolvers;

use core\Utility as util;

use framework\Configuration as conf;
use framework\Request;
use framework\Response;

/**
 * Perform authentication according to Configuration table settings.
 *
 * Default rule storage is in 'auth.paths'.
 */
class AuthenticationResolver implements \framework\interfaces\IRequestResolver {

  const CONFIG_IDENTIFIER = 'auth.paths';

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
    $conf = (array) @conf::get(self::CONFIG_IDENTIFIER);

    foreach ( explode('/', $req->uri('path')) as $pathNode ) {
      if ( isset($conf[$pathNode]) ) {
        $conf = $conf[$pathNode];
      }
      else if ( isset($conf['*']) ) {
        $conf = $conf['*'];
      }
      else {
        $conf = (array) $conf;
        if ( !util::isAssoc($conf) ) {
          $auth = array_reduce($conf, function($result, $auth) {
            if ( !$result ) {
              return $result;
            }

            if ( is_callable($auth) ) {
              $auth = $auth($path);
            }

            return $result && $auth;
          }, true);

          if ( !$auth ) {
            return;
          }
        }

        break;
      }
    }

    // Defaults to only allow users with admin rights
    if ( !isset($req->user) || !in_array('Administrators', $req->user['groups']) ) {
      $res->status(401);
    }
    // TODO: Mark allowed or denied according to the new resolver mechanism.
  }

}
