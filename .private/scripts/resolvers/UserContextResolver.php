<?php
/*! UserContextResolver.php | Identify current user with the current request context. */

namespace resolvers;

use framework\Request;
use framework\Response;

/**
 * Assigns a user context to the request object when a user is authenticated
 * with the request context.
 *
 * This replaces the use of framework\Session::current() thing.
 */
class UserContextResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @private
   *
   * When true and no user presents in the database,
   * sets current user as a temporary user with admin rights.
   */
  protected $setupSession = false;

  public function __construct($options = array()) {
    $this->setupSession = (bool) @$options['setup'];
  }

  public function resolve(Request $req, Response $res) {
    // Internally specified user context
    if ( isset($req->user) ) {
      return;
    }

    // Session ID provided, validate it.
    $sid = $req->param('__sid');
    if ( $sid ) {
      $res = Session::ensure($sid, $req->param('__token'));
      if ( $res === false || $res === Session::ERR_EXPIRED ) {
        // Session doesn't exists, delete exsting cookie.
        setcookie('__sid', '', time() - 3600);
      }
      else if ( is_integer($res) ) {
        switch ( $res ) {
          // Treat as public user.
          case Session::ERR_INVALID:
            break;
        }
      }
      else {
        // Success, proceed.
        $req->user = Session::currentUser();
      }
    }
    // When no user is set, add a default user
    else if ( $this->setupSession && !@\core\Node::get('User') ) {
      $req->user =
        [ 'ID' => 0
        , 'groups' => ['Administrators']
        , 'username' => 'default'
        ];
    }
  }

}
