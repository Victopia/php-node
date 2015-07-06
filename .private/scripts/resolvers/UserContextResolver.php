<?php
/*! UserContextResolver.php | Identify current user with the current request context. */

namespace resolvers;

use framework\Request;
use framework\Response;
use framework\Process;

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
    $req->user = new \models\Users();

    // User from CLI
    switch ( $req->client('type') ) {
      case 'cli':
        // Retrieve user context from process data, then CLI argument.
        $userId = (int) Process::get('userId');
        if ( !$userId ) {
          $req->cli()->options('u', array(
              'alias' => 'user'
            , 'type' => 'integer'
            , 'describe' => 'Idenitfier of target context user.'
            ));

          $userId = (int) $req->param('user');
        }

        if ( $userId ) {
          $req->user->load($userId);
        }

        unset($userId);
        break;

      default:
        // Session ID provided, validate it.
        $sid = $req->param('__sid');
        if ( $sid ) {
          $res = Session::ensure($sid, $req->param('__token'));
          if ( $res === false || $res === Session::ERR_EXPIRED ) {
            // Session doesn't exists, delete exsting cookie.
            $res->cookie('__sid', '', time() - 3600);
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
            $req->user->load(Session::current('UserID'));
          }
        }
        // When no user is set, add a default user
        else if ( $this->setupSession && !@\core\Node::get('User') ) {
          $req->user->data(
            [ 'id' => 0
            , 'groups' => ['Administrators']
            , 'username' => 'default'
            ]);
        }
        break;
    }
  }

}
