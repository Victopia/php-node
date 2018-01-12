<?php /*! AbstractModel.php | Abstraction the data model structure. */

namespace models\abstraction;

use Reflection;
use ReflectionMethod;

use core\Database;
use core\EventEmitter;
use core\Node;
use core\Utility as util;

use framework\Resolver;

use framework\exceptions\ValidationException;

/**
 * Base class for all data models.
 *
 * Data access will be catered by ArrayAccess, and Magic Methods __get, __set, __isset and __unset.
 *
 * Property access will be served by __invoke() with the following rules:
 * 1. Public properties are directly accessible, this will bypass all generic methods.
 * 2. Properties starting with an underscore will be treated as read-only.
 *    i.e. `protected $_foo;` can be accessed via `$obj->foo()`;
 * 3. Normal properties will take precedence of 2) and reads with the same way as 2).
 * 4. Normal properties can also be written with `$obj->foo($value);` which is chainable.
 */
abstract class AbstractModel implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable {

  /**
   * @constructor
   *
   * Instantiate the model with specified data.
   *
   * @param {?array} $data Designated data to be set into this model object.
   */
  function __construct($data = null, $context = array()) {
    if ( $data instanceof self ) {
      $data = $data->data();
    }

    $this->data($data);

    $res = Resolver::getActiveInstance();

    if ( array_key_exists('request', $context) ) {
      $this->_request = $context['request'];
    }
    else if ( $res ) {
      $this->_request = $res->request();
    }

    if ( array_key_exists('response', $context) ) {
      $this->_response = $context['response'];
    }
    else if ( $res ) {
      $this->_response = $res->response();
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   *
   * (Read-only) Reqeust context.
   */
  protected $_request;

  /**
   * @private
   *
   * (Read-only) Response context.
   */
  protected $_response;

  /**
   * @private
   *
   * (Read-only) Collection name of this data model
   */
  protected $_collectionName;

  function collectionName() {
    // Note: Inheriting classes can define the property to override this.
    if ( !$this->_collectionName ) {
      $this->_collectionName = explode('\\', get_class($this));
      $this->_collectionName = end($this->_collectionName);
    }

    return $this->_collectionName;
  }

  /**
   * @private
   *
   * (Read-only) Primary key
   */
  protected $_primaryKey = 'id';

  /**
   * Accessor to data value under the name of the primaryKey property.
   *
   * @param {?int|string} $value Identity key to replace, omit this to read.
   * @return {int|string|AbstractModel} Identity key when read, $this when write.
   */
  function identity($value = null) {
    if ( $value === null ) {
      // note; This check would prevent __get() from creating a null value in data array.
      if ( isset($this[$this->_primaryKey]) ) {
        return $this[$this->_primaryKey];
      }
    }
    else {
      $this[$this->_primaryKey] = $value;
      return $this; // chainable
    }
  }

  /**
   * @private
   *
   * The data content.
   */
  protected $data;

  /**
   * Retrieves and/or replaces internal data of current model.
   *
   * For additive updates please refer to appendData() or prependData().
   *
   * @param {?array|object} $value The data to be replaced.
   * @return {array|AbstractModel} Data when read, $this when write.
   */
  function data($value = null) {
    if ( $value === null ) {
      if ( !$this->data ) {
        $this->data = new \stdClass;
      }

      return $this->data;
    }
    else {
      $this->data = new \stdClass;

      $value = (array) $value;

      if ( isset($value[Node::FIELD_COLLECTION]) && $value[Node::FIELD_COLLECTION] == static::collectionName() ) {
        unset($value[Node::FIELD_COLLECTION]);
      }

      // note: let __set() do the job.
      foreach ( $value as $key => $val ) {
        $this->$key = util::arrayToObject($val);
      }

      return $this;
    }
  }

  /**
   * Append data to current model data, this is equivalent to using `+` operator
   * with arrays, inexisting keys will be added to current data.
   *
   * @param {array|object} $data Array of data to be appended.
   * @return {AbstractModel} Chainable
   */
  function appendData($data) {
    if ( $data instanceof self ) {
      $data = $data->data;
    }

    return $this->data((array) $this->data + (array) $data);
  }

  /**
   * Add data on top of current model data, this differs from appendData() in a
   * way that the specified data takes precedence.
   *
   * @param {array|object} $data Arrray of data to be prepended.
   * @return {AbstractModel} Chainable
   */
  function prependData($data) {
    if ( $data instanceof self ) {
      $data = $data->data;
    }

    return $this->data((array) $data + (array) $this->data);
  }

  /**
   * @private
   *
   * Indicates whether this model is in the middle of creation, this is set to
   * true when saving a model without an identity value or with an identity of
   * inexistance.
   */
  private $_isCreate = false;

  //----------------------------------------------------------------------------
  //
  //  Overloading
  //
  //----------------------------------------------------------------------------

  /**
   * Property names starts with "@" or "__" will be treated directly as object
   * properties and does not proxy into internal data.
   */
  function &__get($name) {
    if ( strpos($name, '__') !== 0 ) {
      if ( blank($this->data) ) {
        // note;dev; variable for reference returning.
        $null = NULL;

        return $null;
      }
      else {
        return $this->data->$name;
      }
    }

    if ( strpos($name, '__is') === 0 ) {
      $auth = '\authenticators\\Is' . ucfirst(substr($name, 4));

      // "__isSuperUser" into "__is_super_user"
      $name = preg_replace('/(?<!^)([A-Z])/', '_\\1', $name);

      if ( class_exists($auth) ) {
        // note; Make a variable for reference returning.
        $result = call_user_func("$auth::authenticate", $this->request());

        return $result;
      }

      unset($auth);
    }

    return $this->$name;
  }

  /**
   * Property names starts with "@" or "__" will be assigned directly to the
   * class instance instead of internal data array, this enables some dynamic
   * contextual assignment and avoid polluting the data.
   */
  function __set($name, $value) {
    if ( strpos($name, '__') !== 0 ) {
      if ( !$this->data ) {
        $this->data = new \stdClass;
      }

      $this->data->$name = $value;
    }
    else {
      $this->$name = $value;
    }
  }

  function __isset($name) {
    return isset($this->data->$name);
  }

  function __unset($name) {
    unset($this->data->$name);
  }

  /**
   * Generic accessors
   */
  function __call($name, $args) {
    // Note: public methods are called directly, we don't need to take care of methods here.

    // Special methods are ignored
    if ( $name[0] == '_' ) {
      // Internal force invoke. i.e. __afterLoad() -> afterLoad()
      if ( preg_match('/^__([^_]+)$/', $name, $matches) ) {
        return $this->{$matches[1]}($args);
      }
      else {
        throw new \BadMethodCallException('Method does not exist.');
      }
    }

    // Read-Write properties takes precedence
    if ( isset($this->$name) ) {
      if ( !$args ) {
        return $this->data->$name;
      }

      if ( count($args) == 1 ) {
        $this->$name = $args[0];

        return $this;
      }
    }
    // Read-only properties
    else if ( property_exists($this, "_$name") ) {
      return $this->{"_$name"};
    }

    throw new \BadMethodCallException(sprintf('Method %s::%s() does not exists.', get_called_class(), $name));
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : ArrayAccess
  //
  //----------------------------------------------------------------------------

  function offsetGet($offset) {
    return $this->$offset;
  }

  function offsetSet($offset, $value) {
    $this->$offset = $value;
  }

  function offsetExists($offset) {
    return isset($this->$offset);
  }

  function offsetUnset($offset) {
    unset($this->$offset);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : IteratorAggregate
  //
  //----------------------------------------------------------------------------

  function getIterator() {
    return new \ArrayIterator($this->data);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : Countable
  //
  //----------------------------------------------------------------------------

  function count() {
    return count((array) $this->data);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : JsonSerializable
  //
  //----------------------------------------------------------------------------

  function jsonSerialize() {
    return $this->data;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Validate current data inside this model.
   *
   * Data models should implement this method to validate before save, database
   * exceptions are not supposed to be thrown directly.
   *
   * @return {array} Array of errors.
   */
  function validate() {
    return [];
  }

  /**
   * Get a list of data models from the collection.
   */
  function find(array $filter = array()) {
    if ( $filter && !util::isAssoc($filter) ) {
      $filter = [ $this->_primaryKey => $filter ];
    }

    $filter[Node::FIELD_COLLECTION] = static::collectionName();

    $collection = new ModelCollection(get_called_class(), $filter, [
      'request' => $this->request(),
      'response' => $this->response()
    ]);

    return $collection;
  }

  /**
   * Static shorthand of find.
   */
  static function search(array $filter = array()) {
    return (new static)->find($filter);
  }

  /**
   * Loads data into current intance with specified $entityId from collection.
   *
   * @param {array|string|number} $filter Scalar types will be treated as identity,
   *                                      array types will be used as is.
   */
  function load($identity = null) {
    if ( !$identity ) {
      $identity = $this->identity();
    }

    if ( !$identity ) {
      return $this;
    }

    if ( ctype_print($identity) ) {
      $identity = Database::escapeValue($identity);
    }

    $filter = [ Node::FIELD_COLLECTION => static::collectionName() ];

    if ( is_scalar($identity) ) {
      $filter[$this->_primaryKey] = $identity;
    }
    else if ( is_array($identity)) {
      $filter+= $identity;
    }

    $this->beforeLoad($filter);

    if ( $filter !== false ) {
      $data = (array) Node::getOne($filter);
    }

    if ( !empty($data) ) {
      $this->data($data);
      $this->afterLoad();
    }

    return $this;
  }

  /**
   * Saves the current data into database.
   *
   * @param {&array} $result Optional, if omitted exceptions will be thrown.
   *                 $result[errors] Array of validation errors.3
   *                 $result[success] True on succeed, otherwise not set.
   */
  function save(&$result = null) {
    $this->_isCreate = !$this->identity();

    $errors = [];

    if ( $result !== null ) {
      $result = [];
      $_result = &$result;
    }

    $_result = [ 'success' => false ];

    $errors = [];
    $this->beforeSave($errors);
    if ( $errors ) {
      $_result['errors'] = $errors;

      if ( $result === null ) {
        throw new ValidationException($errors, 'Invalid model data.', 0);
      }
    }
    else {
      try {
        // note; Conflicts here. Virutal fields would love to skip nulls, but real fields would not.
        $res = util::objectToArray($this->data);

        $res[Node::FIELD_COLLECTION] = static::collectionName();

        $res = Node::set($res);

        if ( is_numeric($res) ) {
          $_result['action'] = 'insert';

          // Primary keys other than auto_increment will return 0, but
          // auto_increment fields which are not primary key will also returned
          // here.
          if ( $res ) {
            $_res = Node::resolveCollection(static::collectionName());
            $_res = Database::getFields($_res, 'PRI', false);
            if ( strpos(@"$_res[Extra]", 'auto_increment') !== false ) {
              $this->identity($res);
            }
            unset($_res);
          }
        }
        else if ( $res ) {
          $_result['action'] = 'update';
        }

        $_result['success'] = true;
      }
      catch (\PDOException $e) {
        if ( $result === null ) {
          throw $e;
        }

        $_result['error'] = $e->getMessage();
        $_result['code'] = $e->getCode();
        unset($_result['action']);
      }

      // Load again to reflect database level changes
      if ( $_result['success'] ) {
        $this->load();
        $this->afterSave($result);
      }
    }

    $this->_isCreate = false;

    return $this;
  }

  /**
   * Delete this model from database.
   */
  function delete(&$isDeleted = false) {
    $filter =
      [ Node::FIELD_COLLECTION => static::collectionName()
      , '@limits' => 1
      ];

    if ( $this->identity() ) {
      $filter[$this->_primaryKey] = $this->identity();
    }
    else {
      $filter+= util::objectToArray($this->data());
    }

    $this->beforeDelete($filter);
    if ( $filter !== false ) {
      $isDeleted = (bool) Node::delete($filter);

      if ( $isDeleted ) {
        $this->afterDelete();
      }
    }

    return $this;
  }

  /**
   * Populate computed values from existing fields.
   */
  protected function populate() {
    return $this;
  }

  /**
   * Called before model is loaded, performing modifications to the filter
   * before passing down to Node::get() or Node::getOne().
   *
   * @param {=array} $filter The filter array passed down to Node::get().
   * @return {AbstractModel} Chainable.
   */
  protected function beforeLoad(array &$filter) {
    return $this;
  }

  /**
   * Called after the model is loaded.
   *
   * @return {AbstractModel} Chainable.
   */
  protected function afterLoad() {
    return $this->populate();
  }

  /**
   * Called before the model is being saved, performing last chance modifications
   * before passing down to Node::set().
   *
   * @param {=array} $errors This will contain all validation errors.
   * @return {AbstractModel} Chainable.
   */
  protected function beforeSave(array &$errors = array()) {
    $errors+= (array) $this->validate();
    return $this;
  }

  /**
   * Called after the model is safed.
   *
   * @return {AbstractModel} Chainable.
   */
  protected function afterSave(array &$result = null) {
    return $this;
  }

  /**
   * Called before the model is being deleted, performaing last change modifications
   * before passing down to Node::delete().
   *
   * @param {=array} $filter The filter array passed down to Node::delete().
   * @return {AbstractModel} Chainable.
   */
  protected function beforeDelete(array &$filter = array()) {
    return $this;
  }

  /**
   * Called after model deletion.
   *
   * @return {AbstractModel} Chainable.
   */
  protected function afterDelete() {
    return $this;
  }

}
