<?php
/*! User.php | The user data model. */

namespace models;

use core\ContentDecoder;
use core\Database;
use core\Node;
use core\Utility as util;

use authenticators\IsAdministrator;
use authenticators\IsInternal;

use framework\exceptions\ResolverException;

class User extends abstraction\UuidModel {

  /**
   * Identifier for User-Role (1:*) relation.
   *
   * Note: Roles are not themselves a model, but plain strings in the relation
   *       table for mappings.
   */
  const GROUP_RELATION = 'Group:User';

  /**
   * @protected
   *
   * Prepended to hash for crypt() method to understand which algorithm to use.
   */
  protected $__hashPrefix = '$6$rounds=10000$'; // CRYPT_SHA512

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
    $hash = sha1(time() + mt_rand());
    $hash = md5("$username:$hash");
    $hash = substr($hash, 16);
    $hash = "$this->__hashPrefix$hash";
    return crypt($password, $hash);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  public function __create() {
    return $this;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods: AbstractModel
  //
  //----------------------------------------------------------------------------

  function load($identity = null) {
    if ( $identity == '~' ) {
      return $this->data( $this->__request->user->data() );
    }
    // note; email address as username
    else if ( $identity && strpos($identity, '@') !== false ) {
      $identity = [ 'username' => $identity ];
    }

    return parent::load($identity);
  }

  function populate() {
    if ( !$this->__isSuperUser ) {
      unset($this->password);
    }

    // Load groups
    $this->groups = $this->parents(static::GROUP_RELATION);

    // Cascade name display for user object.
    $names = array_filter(array(
        $this->first_name,
        $this->last_name
      ));

    if ( !$names ) {
      $names[] = $this->username;
    }

    $this->name = implode(' ', $names);

    return parent::populate();
  }

  function validate() {
    $errors = parent::validate();

    if ( $this->isCreate() ) {
      if ( (new User)->load($this->username)->identity() ) {
        $errors[100] = 'This email has already been registerd.';
      }
    }
    else if ( !$this->__isSuperUser && @$this->__request->user->id != $this->id ) {
      throw new ResolverException(401, 'You are not allowed to edit this user.');
    }

    return $errors;
  }

  /**
   * Hash the user password if not yet.
   */
  function beforeSave(array &$errors = array()) {
    $password = $this->password;
    if ( strpos($password, $this->__hashPrefix) !== 0 ) {
      $this->password = $this->hash($this->username, $password);
    }
    unset($password);

    // note: do not store groups into virtual fields
    if ( !empty($this->groups) ) {
      $this->__groups = $this->groups; unset($this->groups);
    }

    $this->timestamp = util::formatDate('Y-m-d H:i:s.u');

    parent::beforeSave($errors);

    return $this;
  }

  /**
   * Save user groups
   */
  function afterSave(array &$result = null) {
    // save groups
    if ( !empty($this->__groups) ) {
      $groups = array_map('ucwords', (array) $this->__groups);

      if ( $groups != $this->parents(static::GROUP_RELATION) ) {
        $this->parents(static::GROUP_RELATION, $groups, true);
      }

      // note: put it back for data consistency
      $this->groups = $groups;

      unset($this->__groups);
    }

    return parent::afterSave();
  }

  /**
   * Remove all sessions upon delete.
   */
  function afterDelete() {
    Node::delete(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
        'username' => $this->identity()
      ));

    $this->deleteAncestors(static::GROUP_RELATION);

    return parent::afterDelete();
  }

}
