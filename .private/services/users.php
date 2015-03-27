<?php
/*! users.php | Service interface to users. */

use core\Node;
use core\Relation;
use core\Utility;
use core\Log;

use eBay\Utility as util;

use framework\Service;
use framework\Session;
use framework\exceptions\ServiceException;

class users implements framework\interfaces\IAuthorizableWebService {

  //--------------------------------------------------
  //
  //  Methods : IAuthorizableWebService
  //
  //--------------------------------------------------

  public /* boolean */
  function authorizeMethod($name, $args = null) {
    if ( Utility::isLocal() || Session::checkStatus(Session::USR_ADMINS) ) {
      return true;
    }

    switch ( $name ) {
      case 'get':
        if ( strcasecmp(@$_SERVER['REQUEST_METHOD'], 'delete') === 0 ) {
          return Session::currentUser('ID') == $args[0];
        }
        return true;

      // Only self is allowed, or it is super user.
      case 'set':
        return @$_POST['ID'] == '~' || @$_POST['ID'] == Session::currentUser('ID');

      default:
        return true;
    }
  }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  public function __call($name, $args = array()) {
    // Get
    if ( count($args) == 0 ) {
      return $this->get($name);
    }
  }

  /**
   * List users, filtered with $_GET parameters.
   * Optionally restrict with List-Range header.
   */
  public /* array */
  function let() {
    $res = (array) filter_input_array(INPUT_GET, array(
        'ID' => array(
            'filter' => FILTER_VALIDATE_INT
          , 'flags' => FILTER_REQUIRE_SCALAR
          )
      , 'username' => FILTER_SANITIZE_STRING
      , 'firstname' => FILTER_SANITIZE_STRING
      , 'lastname' => FILTER_SANITIZE_STRING
      , 'email' => FILTER_SANITIZE_EMAIL
      ));

    $res = array_filter($res) + array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER
      );

    $res = Node::get($res, true, Utility::getListRange());

    $res = array_map(removes('password'), $res);

    return $res;
  }

  /**
   * Get target user with $username provided, if $username is omitted,
   * the current user will be returned.
   */
  public /* array */
  function get($userId = '~') {
    $user = Session::currentUser();

    /* Allows null user context on local processes. */
    if ( $userId === '~' && $user === null ) {
      if ( !Utility::isLocal() ) {
        throw new framework\exceptions\ServiceException('Please login or specify a username.', 1000);
      }
      else {
        return null;
      }
    }

    $filter = is_numeric($userId) ? 'ID' : 'username';

    // User other than session user.
    if ( $userId !== '~' && $userId !== @$user[$filter] ) {
      $user = Node::get(array(
          NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER
        , $filter => $userId
        ));

      $user = Utility::unwrapAssoc($user);
    }

    if ( !Utility::isLocal() ) {
      unset($user['password']);
    }

    if ( !$user ) {
      throw new ServiceException('Specified user does not exist.', 1002);
    }
    else if ( $strcasecmp(@$_SERVER['REQUEST_METHOD'], 'delete') === 0 ) {
      return Node::delete(array(
          NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER
        , $user['ID']
        ));
    }

    return $user;
  }

  /**
   * Updates users' information.
   *
   * Fields that need special catering will be done inside this function,
   * like password, email and others.
   */
  public /* bool */
  function set() {
    $contents = (array) filter_input_array(INPUT_POST, array(
        'ID' => array(
            'filter' => FILTER_SANITIZE_NUMBER_INT
          , 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE
          )
      , 'username' => FILTER_SANITIZE_STRING
      , 'password' => FILTER_SANITIZE_STRING
      , 'status' => FILTER_SANITIZE_NUMBER_INT
      ));

    $contents = array_filter($contents, compose('not', 'is_null'));
    $contents = array_map('trim', $contents);

    if ( !$contents ) {
      throw new ServiceException('No contents to update.', 1003);
    }

    // Update user account
    if ( isset($contents['ID']) ) {
      // Note: Update core accounts - Super users or self.
      // Note: Update sub accounts - Super users, core account or self.
      $user = $this->get($contents['ID']);

      // Password encryption
      if ( @$contents['password'] ) {
        $contents['password'] = $this->hash($user['username'], $contents['password']);
      }

      // Restrict updatable fields
      remove(array('ID', 'username', NODE_FIELD_COLLECTION, 'timestamp'), $contents);

      $contents+= $user;

      unset($user);
    }
    // Create user account
    else {
      // Note: Create core accounts - Super users.
      // Note: Create sub accounts - All users, plus payment validation.
      if ( !@$contents['username'] ) {
        throw new ServiceException('Please provide username.', 1005);
      }
      else if ( $this->get($contents['username']) ) {
        throw new ServiceException('Username already exists.', 1006);
      }

      // Password encryption
      if ( !@$contents['password'] ) {
        throw new ServiceException('Please provide a password.', 1008);
      }
      else {
        $contents['password'] = $this->hash($contents['username'], $contents['password']);
      }
    }

    $contents[NODE_FIELD_COLLECTION] = FRAMEWORK_COLLECTION_USER;

    // Push into database
    $ret = Node::set($contents);

    if ( $ret && is_numeric($ret) ) {
      $contents['ID'] = $ret;
    }

    return $ret;
  }

  //--------------------------------------------------
  //
  //  Private Methods
  //
  //--------------------------------------------------

  /**
   * TODO: Make this alterable, pay attention to key-strenthening.
   */
  protected function hash($username, $password) {
    $hash = sha1(time() + mt_rand());
    $hash = md5("$username:$hash");
    $hash = substr($hash, 16);

    // CRYPT_SHA512
    $hash = '$6$rounds=10000$' . $hash;

    $hash = crypt($password, $hash);

    return $hash;
  }

}
