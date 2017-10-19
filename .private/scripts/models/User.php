<?php /*! User.php | The user data model. */

namespace models;

use core\ContentDecoder;
use core\Database;
use core\Node;
use core\Utility as util;

use authenticators\IsSuperUser;

use framework\System;

use framework\exceptions\ResolverException;

use Intervention\Image\ImageManagerStatic as Image;

class User extends abstraction\UuidModel {

  /**
   * Identifier for Group-User (*:*) relation.
   *
   * Note: Roles are not themselves a model, but plain strings in the relation
   *       table for mappings.
   */
  const GROUP_RELATION = 'Group:User';

  /**
   * Identifier for User-Module (*:*) relation.
   */
  const MODULE_RELATION = 'User:Module';

  /**
   * @protected
   *
   * Prepended to hash for crypt() method to understand which algorithm to use.
   */
  protected $__hashPrefix = '$6$rounds=512$'; // CRYPT_SHA512

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * @protected
   *
   * Create a hash for UNIX crypt(6)
   *
   * Note: Make this alterable, pay attention to key-strenthening.
   */
  protected function hash($username, $password) {
    if ( function_exists('password_hash') ) {
      return password_hash($password, PASSWORD_DEFAULT);
    }
    else {
      $hash = sha1(time() + mt_rand());
      $hash = md5("$username:$hash");
      $hash = substr($hash, 16);
      $hash = "$this->__hashPrefix$hash";
      return crypt($password, $hash);
    }
  }

  public function verifyPassword($password) {
    if ( function_exists('password_verify') ) {
      return password_verify($password, $this->password);
    }
    else if ( strpos($password, $this->__hashPrefix) === 0 ) {
      return crypt($password, $this->password) === $this->password;
    }
    else {
      return FALSE;
    }
  }

  public function isSuperUser() {
    return IsSuperUser::authenticate($this->request());
  }

  public function isRequestUser() {
    $user = @$this->request()->user;

    return $user && $user->identity() == $this->identity();
  }

  //----------------------------------------------------------------------------
  //
  //  Methods: AbstractModel
  //
  //----------------------------------------------------------------------------

  function load($identity = null) {
    if ( $identity == '~' ) {
      return $this->data( $this->request()->user->data() );
    }
    // note; email address as username
    else if ( $identity && strpos($identity, '@') !== false ) {
      $identity = [ 'username' => $identity ];
    }

    return parent::load($identity);
  }

  function populate() {
    if ( !$this->isSuperUser() ) {
      unset($this->password);
    }

    // Cascade name display for user object.
    $names = array_filter(array(
        $this->first_name,
        $this->last_name
      ));

    if ( !$names ) {
      $names[] = $this->username;
    }

    $this->name = implode(' ', $names);

    unset($names);

    // Load groups
    $this->groups = $this->parents(self::GROUP_RELATION);

    return parent::populate();
  }

  function validate() {
    $errors = parent::validate();

    if ( $this->isCreate() ) {
      if ( (new User)->load($this->username)->identity() ) {
        $errors[100] = 'This email has already been registerd.';
      }
      else if (empty($this->password)) {
        $errors[101] = 'Password is required.';
      }
    }
    else if ( !$this->isSuperUser() && !$this->isRequestUser() ) {
      throw new ResolverException(401, 'You are not allowed to edit this user.');
    }

    return $errors;
  }

  /**
   * Hash the user password if not yet.
   */
  protected function beforeSave(array &$errors = array()) {
    // note;dev; hash the heck out of it when algo is unknown.
    $info = password_get_info($this->password);
    if ( !$info['algo'] ) {
      $this->password = $this->hash($this->username, $this->password);
    }
    unset($info);

    // note; Cleanse empty arrays
    foreach ( ['middle_names', 'groups', 'forms'] as $field ) {
      if ( isset($this->$field) && is_array($this->$field) ) {
        $this->$field = array_filter(array_map('trim', $this->$field));

        if ( !$this->$field ) {
          unset($this->$field);
        }
      }
    }

    // note: do not store groups into virtual fields
    if ( !empty($this->groups) && !empty(filter($this->groups)) ) {
      $this->__groups = $this->groups; unset($this->groups);
    }

    $this->timestamp = util::formatDate('Y-m-d H:i:s.u');

    unset($this->name);

    return parent::beforeSave($errors);
  }

  /**
   * Save user groups
   */
  protected function afterSave(array &$result = null) {
    // note; save groups
    if ( !empty($this->__groups) ) {
      $groups = array_filter(array_map(compose('ucwords', 'trim'), (array) $this->__groups));

      if ( $groups != $this->parents(static::GROUP_RELATION) ) {
        $this->parents(static::GROUP_RELATION, $groups, true);
      }

      unset($this->__groups);
    }

    $this->populate();

    return parent::afterSave();
  }

  /**
   * Remove all sessions upon delete.
   */
  protected function afterDelete() {
    Node::delete(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
        'username' => $this->identity()
      ));

    $this->deleteAncestors(static::GROUP_RELATION);

    return parent::afterDelete();
  }

}
