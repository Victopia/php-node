<?php
/*! UserContextResolver.php | Identify current user with the current request context. */

namespace resolvers;

use models\User;

use framework\Request;
use framework\Response;
use framework\Process;
use framework\Session;

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
    $req->user = new User();

    // User from CLI
    switch ( $req->client('type') ) {
      case 'cli':
        // Retrieve user context from process data, then CLI argument.
        $userId = (int) Process::get('type');
        if ( !$userId ) {
          $req->cli()->options('u', array(
              'alias' => 'user'
            , 'type' => 'integer'
            , 'describe' => 'Idenitfier of target context user.'
            ));

          $userId = (int) $req->meta('user');
        }

        if ( $userId ) {
          $req->user->load($userId);
        }

        unset($userId);
        break;

      default:
        // Session ID provided, validate it.
        $sid = $req->meta('sid');
        if ( $sid ) {
          $ret = Session::ensure($sid, $req->meta('token'), $req->fingerprint());

          // Session doesn't exist, delete the cookie.
          if ( $ret === false || $ret === Session::ERR_EXPIRED ) {
            $res->cookie('__sid', '', time() - 3600);
          }
          else if ( is_integer($ret) ) {
            switch ( $ret ) {
              // note: System should treat as public user.
              case Session::ERR_INVALID:
                break;
            }
          }
          else {
            // Success, proceed.
            $req->user->load(Session::current('username'));
            unset($req->user->password);
          }
        }
        // When no user is set, add a default user
        else if ( $this->setupSession && !@\core\Node::get('User') ) {
          $req->user->data(
            [ 'id' => 0
            , 'groups' => ['Administrators']
            , 'username' => '__default'
            ]);
        }
        break;
    }
  }

}
