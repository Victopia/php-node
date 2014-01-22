<?php
/*! users.php | Service interface to users. */

class users implements framework\interfaces\IAuthorizableWebService {

  //--------------------------------------------------
  //
  //  Methods : IAuthorizableWebService
  //
  //--------------------------------------------------

  public function
  /* boolean */ authorizeMethod($name, $args = null) {
    switch ($name) {
      case 'create':
        return session::checkStatus(session::USR_ADMINS);

      // Only self is allowed, or it is super user.
      case 'set':
        return @$args[0] == '~' ^ utils::isLocal();

      case 'token':
        return @$args[1] || utils::isLocal() || session::current();

      default:
        return true;
    }
  }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  /**
   * Get target user with $username provided, if $username is omitted,
   * the current user will be returned.
   */
  public /* array */
  function get($userId = '~') {
    $user = session::currentUser();

    /* Allows null user context on local processes. */
    if ( $userId === '~' && $user === null ) {
      if ( !utils::isLocal() ) {
        throw new framework\exceptions\ServiceException('Please login or specify a username.', 1000);
      }
      else {
        return null;
      }
    }

    $filter = is_numeric($userId) ? 'ID' : 'username';

    // User other than session user.
    if ($userId !== '~' && $userId !== @$user[$filter]) {
      $user = node::get(array(
          NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER
        , $filter => $userId
        ));

      $user = utils::unwrapAssoc($user);
    }

    if ($user && !utils::isCLI()) {
      unset($user['password']);
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
  function set($userId = '~', $contents = null) {
    if ($contents === null) {
      if ($_POST) {
        $contents = $_POST;
      }
      else {
        return false;
      }
    }

    $user = $this->get($userId);

    // Restrict updatable fields
    remove(array('ID', 'username', NODE_FIELD_COLLECTION, 'timestamp'), $contents);

    // Cater special fields.

    // - Password
    if (@$contents['password']) {
      $contents['password'] = $this->hash($user['username'], $contents['password']);
    }

    $user = array_filter($contents + $user, compose('not', 'is_null'));

    // Update user
    return node::set($user);
  }

  /**
   * Get server data associated with the current user.
   *
   * The $_POST data should be stored under the property name 'data',
   * in order to avoid the fucking '.' to '_' replacement.
   *
   * Goddamn PHP parser!
   */
  public function
  /* array */ data($name = null, $userId = '~') {
    $user = $this->get($userId);

    if ((@$_POST['data'] || @$_SERVER['REQUEST_METHOD'] == 'DELETE') && $name === null) {
      throw new framework\exceptions\ServiceException('You must specify the name to modify.');
    }

    // Set
    if (@$_SERVER['REQUEST_METHOD'] !== 'DELETE' && @$_POST['data']) {
      if ($name !== null && (session::checkStatus(session::USR_ADMINS) || \utils::isLocal())) {
        $data = array( $name => $_POST['data'] );

        // flatten the data with '::'.
        $data = utils::flattenArray($data, '::', false);

        array_walk($data, function($data, $key) use($user, &$result) {
          $result[] = array(
              NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER_DATA
            , 'UserID' => $user['ID']
            , 'name' => $key
            , 'value' => json_encode($data)
            );

          // Delete lower leveled data to prevent collision.
          node::delete(array(
              NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER_DATA
            , 'UserID' => $user['ID']
            , 'name' => '/^'.preg_quote($key).'.+/'
            ));
        });

        // Also delete the exact match when there is more than one updates,
        // as this is very likely a lower-level data is going to be inserted.
        node::delete(array(
            NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER_DATA
          , 'UserID' => $user['ID']
          , 'name' => $name
          ));

        return node::set($result);
      }
      else {
        throw new framework\exceptions\ResolverException(405);
      }
    }

    $res = array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER_DATA
      , 'UserID' => $user['ID']
      );

    if ($name) {
      $res['name'] = '/^'.preg_quote($name).'/';
    }

    // Get
    $res = array_map(function($item) {
      $item['value'] = json_decode($item['value'], true);

      return $item;
    }, node::get($res));

    if ($name !== null) {
      if ($res) {
        // Delete
        if (@$_SERVER['REQUEST_METHOD'] == 'DELETE') {
          $res = utils::wrapAssoc($res);

          $affectedRows = 0;

          foreach ($res as $row) {
            unset($row['value']);

            $affectedRows+= node::delete($row);
          }

          return $affectedRows;
        }
      }
      else {
        return null;
      }
    }

    $res = array_combine(
        array_map(prop('name'), $res)
      , array_map(prop('value'), $res)
      );

    $res = utils::unflattenArray($res, '::');

    // Named queries.
    if ($name !== null) {
      $name = explode('::', $name);

      while ($name) {
        $res = @$res[array_shift($name)];
      }
    }

    return $res ? $res : null;
  }

  /**
   * Retrieve tokens assciated with target user.
   */
  public function
  /* array */ token($name = null, $formatDates = true, $userId = '~') {
    return service::call('tokens', $name === null ? 'let' : 'get', array($name, $formatDates, $userId));
  }

  public function
  /* boolean */ create($username, $password, $customFields = array()) {
    $hash = $this->hash($username, $password);

    $res = node::get(array(
        NODE_FIELD_COLLECTION => 'User'
      , 'username' => $username
      ));

    if (count($res) > 0) {
      throw new Exception('User already exists.');
    }

    $res = array(
        NODE_FIELD_COLLECTION => 'User'
      , 'status' => session::USR_NORMAL
      , 'username' => $username
      , 'password' => $hash
      );

    $res = array_merge($customFields, $res);

    return node::set($res);
  }

  //--------------------------------------------------
  //
  //  Private Methods
  //
  //--------------------------------------------------

  /**
   * TODO: Make this alterable, pay attention to key-strenthening.
   */
  private function hash($username, $password) {
    $hash = sha1(time() + mt_rand());
    $hash = md5("$username:$hash");
    $hash = substr($hash, 16);

    // CRYPT_SHA512
    $hash = '$6$rounds=10000$' . $hash;

    $hash = crypt($password, $hash);

    return $hash;
  }
}
