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

class User extends abstraction\JsonSchemaModel {

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

  /**
   * @constructor
   */
  public function __construct($data = null) {
    // note; read schema from this file.
    static $schema;
    if ( !$schema ) {
      $schema = ContentDecoder::json(file_get_contents(__FILE__, false, null, __COMPILER_HALT_OFFSET__), 0);
    }

    $this->schema = $schema;

    parent::__construct($data);
  }

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

  /**
   * Cascading name display from user data.
   */
  public function name() {
    $names = array_filter(array(
        $this->first_name,
        $this->last_name
      ));

    if ( !$names ) {
      $names[] = $this->username;
    }

    return implode(' ', $names);
  }

  //----------------------------------------------------------------------------
  //
  //  Properties : AbstractModel
  //
  //----------------------------------------------------------------------------

  protected $_primaryKey = 'uuid';

  public function identity($value = null) {
    if ( $value === null ) {
      return util::unpackUuid(parent::identity());
    }
    else {
      return parent::identity(util::packUuid($value));
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Methods: AbstractModel
  //
  //----------------------------------------------------------------------------

  function load($identity) {
    if ( $identity == '~' ) {
      return $this->data( $this->__request->user->data() );
    }
    else {
      // note; email address as username
      if ( strpos($identity, '@') !== false ) {
        $identity = array( 'username' => $identity );
      }
      else {
        $identity = util::packUuid($identity);
      }

      return parent::load($identity);
    }
  }

  function find(array $filter = array()) {
    $identity = &$filter[$this->primaryKey()];
    if ( $identity ) {
      if ( is_array($identity) ) {
        $identity = array_map('core\Utility::packUuid', $identity);
      }
      else {
        $identity = util::packUuid($identity);
      }
    }
    else {
      unset($filter[$this->primaryKey()]);
    }
    unset($identity);

    return parent::find($filter);
  }

  function afterLoad() {
    if ( !$this->__isSuperUser ) {
      unset($this->password);
    }

    // unpack uuid
    $this->uuid = util::unpackUuid($this->uuid);

    // Load groups
    $this->groups = $this->parents(static::GROUP_RELATION);

    return parent::afterLoad();
  }

  function validate(array &$errors = array()) {
    if ( $this->isCreate() ) {
      if ( (new User)->load($this->username)->identity() ) {
        $errors[100] = 'This email has already been registerd.';
      }
    }
    else if ( !$this->__isSuperUser && @$this->__request->user->id != $this->id ) {
      throw new ResolverException(401, 'You are not allowed to edit this user.');
    }
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

    // note; assign UUID upon creation
    if ( $this->isCreate() ) {
      // note; loop until we find a unique uuid
      do {
        $this->identity(Database::fetchField("SELECT UNHEX(REPLACE(UUID(), '-', ''))"));
      }
      while (
        $this->find(array(
            $this->primaryKey() => $this->identity()
          ))
        );
    }

    parent::beforeSave($errors);

    if ( !$errors ) {
      if ( isset($this->uuid) ) {
        $this->uuid = util::packUuid($this->uuid);
      }
    }

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
   * @protected
   *
   * Pack UUID for delete filters.
   */
  function beforeDelete(array &$filter = array()) {
    if ( isset($filter[$this->primaryKey()]) ) {
      $filter[$this->primaryKey()] = util::packUuid($filter[$this->primaryKey()]);
    }

    return $this;
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

__halt_compiler();

{
  "data": {
    "$schema": "http://json-schema.org/draft-04/schema#",
    "id": "/serivce/_/Users/schema",
    "type": "object",
    "title": "User",
    "description": "Users for the prefw system.",
    "properties": {
      "uuid": {
        "type": "string",
        "minLength": 32,
        "maxLength": 32,
        "pattern": "^[a-fA-F0-9]{32}$",
        "description": "User instance identity key."
      },
      "username": {
        "description": "Unique identifier for the user acconut",
        "type": "string",
        "format": "email",
        "pattern": "^\\S+@\\S+$",
        "minLength": 4,
        "maxLength": 254,
        "uniqueItems": true
      },
      "password": {
        "description": "Password is encrypted after save.",
        "type": "string",
        "minLength": 8,
        "maxLength": 255
      },
      "first_name": {
        "description": "First name of the user",
        "type": "string"
      },
      "middle_names": {
        "description": "Middle names of the user, multiple middle names supported",
        "type": "array",
        "minItems": 1,
        "items": {
          "type": "string"
        }
      },
      "last_name": {
        "description": "Last name of the user",
        "type": "string"
      },
      "birthday": {
        "description": "Date, or possibly time, of the birth of the user.",
        "type": "string",
        "format": "date-time"
      },
      "groups": {
        "type": "array",
        "description": "Groups this user is assigned.",
        "items": {
          "type": "string",
          "minLength": 2,
          "maxLength": 255
        },
        "uniqueItems": true
      }
    },
    "required": ["username", "password"]
  },
  "form": [
    {
      "key": "username",
      "description": "Email address to uniquely identifies the user."
    },
    "password",
    "groups"
  ]
}
