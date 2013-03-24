<?php
/* users.php | RESTful interface to users. */

class users implements framework\interfaces\IAuthorizableWebService {
  public function
  /* boolean */ authorizeMethod($name, $args = NULL) {
    switch ($name) {
      case 'create':
        return session::checkStatus(session::USR_ADMINS);

      case 'token':
        return @$args[1] || utils::isLocal() || session::current();

      default:
        return TRUE;
    }
  }

  /**
   * Get target user with $username provided, if $username is omitted,
   * the current user will be returned.
   */
  public function
  /* array */ get($userId = '~') {
    $user = session::currentUser();

    if ($userId === '~' && $user === NULL) {
      throw new Exception('Please login or specify a username.');
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
   * Get server data associated with the current user.
   *
   * The $_POST data should be stored under the property name 'data',
   * in order to avoid the fucking '.' to '_' replacement.
   *
   * Goddamn PHP parser!
   */
  public function
  /* array */ data($name = NULL, $userId = '~') {
    $user = $this->get($userId);

    if ((@$_POST['data'] || @$_SERVER['REQUEST_METHOD'] == 'DELETE') && $name === NULL) {
      throw new framework\exceptions\ServiceException('You must specify the name to modify.');
    }

    // Set
    if (@$_SERVER['REQUEST_METHOD'] !== 'DELETE' && @$_POST['data']) {
      if ($name !== NULL && (session::checkStatus(session::USR_ADMINS) || \utils::isLocal())) {
        $data = array( $name => $_POST['data'] );

        // flatten the data with '::'.
        $data = utils::flattenArray($data, '::', FALSE);

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

    if ($name !== NULL) {
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
        return NULL;
      }
    }

    $res = array_combine(
        array_map(prop('name'), $res)
      , array_map(prop('value'), $res)
      );

    $res = utils::unflattenArray($res, '::');

    // Named queries.
    if ($name !== NULL) {
      $name = explode('::', $name);

      while ($name) {
        $res = @$res[array_shift($name)];
      }
    }

    return $res ? $res : NULL;
  }

  /**
   * Retrieve tokens assciated with target user.
   */
  public function
  /* array */ token($name = NULL, $formatDates = TRUE, $userId = '~') {
    return service::call('tokens', $name === NULL ? 'let' : 'get', array($name, $formatDates, $userId));
  }

  public function
  /* boolean */ create($username, $password, $customFields = array()) {
    return func_get_args();
  }

  //--------------------------------------------------
  //
  //  Message related
  //
  //--------------------------------------------------

  public function
  /* array */ message($username = '~') {
    $user = $this->get($username);

    // Update message stack
    if ($_POST) {
      /*core\Database::lockTables(array(
          FRAMEWORK_COLLECTION_LOG
        , FRAMEWORK_COLLECTION_USER
        , FRAMEWORK_COLLECTION_SESSION
        ));
      */

      $messages = \utils::wrapAssoc((array) @$_POST['messages']);

      $res = FALSE;

      if (isset($_POST['clearall'])) {
        $user = node::get(array(
            NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_USER
          , 'ID' => $user['ID']
          ));

        $user = utils::unwrapAssoc($user);

        unset($user['messages']);

        $res = node::set($user);
      }
      elseif ($messages) {
        array_walk($messages, function($contents) use(&$user) {
          @$user['messages'][$contents['code']] = array(
              'contents' => $contents['message']
            , 'count' => intval(@$message['count']) + 1
            );
        });

        $res = node::set($user, TRUE);
      }

      // core\Database::unlockTables();

      return $res;
    }

    // return messages
    return (array) @$user['messages'];
  }

}