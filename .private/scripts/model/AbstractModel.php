<?php
/*! AbstractModel.php | Abstraction the data model structure. */

namespace model;

use core\EventEmitter;
use core\Node;
use core\Utility as util;

use framework\exceptions\FrameworkException;

/**
 * Base class for all data models.
 */
abstract class AbstractModel implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable {

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   *
   * Collection name of this data model
   */
  protected $collectionName;

  function collectionName() {
    return $this->collectionName;
  }

  /**
   * @private
   *
   * Primary key
   */
  protected $primaryKey = 'ID';

  function primaryKey() {
    return $this->primaryKey;
  }

  function identity($value = null) {
    if ( $value === null ) {
      return $this[$this->primaryKey];
    }
    else {
      $this[$this->primaryKey] = $value;
    }
  }

  /**
   * @private
   *
   * The data content.
   */
  protected $data = array();

  /**
   * Retrieves or updates data of current model.
   */
  function data($data = null) {
    if ( $data === null ) {
      return $this->data;
    }
    else {
      $this->data = array_filter_keys((array) $data, compose('not', startsWith('@')));
    }
  }

  /**
   * @private
   *
   * Indicates whether this model is in the middle of creation, this is set to
   * true when saving a model without an identity value or with an identity of
   * inexistance.
   */
  private $isCreate = false;

  /**
   * (read-only) Check if the model is now in the middle of creation save.
   */
  protected function isCreate() {
    return $this->isCreate;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : ArrayAccess
  //
  //----------------------------------------------------------------------------

  function offsetExists($offset) {
    return isset($this->data[$offset]);
  }

  function offsetGet($offset) {
    return @$this->data[$offset];
  }

  function offsetSet($offset, $value) {
    if ( $offset[0] != '@' ) {
      $this->data[$offset] = $value;
    }
  }

  function offsetUnset($offset) {
    unset($this->data);
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
    return count($this->data);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : JsonSerializable
  //
  //----------------------------------------------------------------------------

  function jsonSerialize() {
    return (object) $this->data;
  }

  //----------------------------------------------------------------------------
  //
  //  Overloading
  //
  //----------------------------------------------------------------------------

  function __get($name) {
    return $this->data[$name];
  }

  function __set($name, $value) {
    $this->data[$name] = $value;
  }

  function __isset($name) {
    return isset($this->data[$name]);
  }

  function __unset($name) {
    unset($this->data[$name]);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Instantiate the model with specified data.
   *
   * @param {?array} $data Designated data to be set into this model object.
   */
  function __construct($data = null) {
    $this->data($data);

    if ( !$this->collectionName ) {
      $this->collectionName = explode('\\', get_called_class());
      $this->collectionName = end($this->collectionName);
    }
  }

  /**
   * Validate current data inside this model.
   *
   * Data models should implement this method to validate before save, database
   * exceptions are not supposed to be thrown directly.
   *
   * @return {array} An array of errors.
   */
  abstract function validate();

  /**
   * Get a list of data models from the collection.
   */
  function get($filter = array()) {
    if ( $filter && !util::isAssoc($filter) ) {
      $filter = array(
          $this->primaryKey => $filter
        );
    }

    $filter[Node::FIELD_COLLECTION] = $this->collectionName;

    $collection = array();
    Node::getAsync($filter, function($data) use(&$collection) {
      // create a new instance for retrieved data
      $model = get_called_class();
      $model = new $model($data);

      // force invoke internal function
      util::forceInvoke(array($model, 'afterLoad'));

      $collection[] = $model;
    });

    return $collection;
  }

  /**
   * Loads data into current intance with specified $entityId from collection.
   *
   * @param {array|string|number} $filter Scalar types will be treated as identity,
   *                                      array types will be used as is.
   */
  function load($identity) {
    $filter = array(
        Node::FIELD_COLLECTION => $this->collectionName
      , $this->primaryKey => $identity
      );

    if ( is_scalar($identity) ) {
      $filter[$this->primaryKey] = $identity;
    }
    else if ( is_array($identity)) {
      $filter+= $identity;
    }

    $filter = $this->beforeLoad($filter);
    if ( $filter !== false ) {
      $this->data((array) @Node::getOne($filter));

      $this->afterLoad();
    }

    return $this;
  }

  /**
   * Saves the current data into database.
   */
  function save(&$isCreated = false) {
    $this->isCreate = !$this->identity() && !$this->get($this->identity());

    if ( $this->beforeSave() !== false ) {
      $res = Node::set([Node::FIELD_COLLECTION => $this->collectionName] + $this->data);
      if ( is_numeric($res) ) {
        $isCreated = true;
        $this->identity($res);
      }

      // Load again to reflect database level changes
      $this->load($this->identity());

      $this->afterSave();
    }

    $this->isCreate = false;

    return $this;
  }

  /**
   * Delete this model from database.
   */
  function delete(&$isDeleted = false) {
    $filter =
      [ Node::FIELD_COLLECTION => $this->collectionName
      , '@limit' => 1
      ] + $this->data;

    $filter = $this->beforeDelete($filter);
    if ( $filter !== false ) {
      $isDeleted = (bool) Node::delete($filter);

      $this->afterDelete();
    }

    return $this;
  }

  /**
   * Called before model is loaded, performing modifications to the filter
   * before passing down to Node::get() or Node::getOne().
   *
   * @param {array} $filter The filter array created from the current model.
   * @return {array|boolean} The filter to be passed down to Node::get(), or
   *                         return false to prevent the model from loading.
   */
  protected function beforeLoad($filter) {
    return $filter;
  }

  /**
   * Called after the model is loaded.
   */
  protected function afterLoad() { /* noop */ }

  /**
   * Called before the model is being saved, performing last chance modifications
   * before passing down to Node::set().
   *
   * @return {?bool} Explicitly return false to prevent the save action.
   */
  protected function beforeSave() {
    if ( $this->validate() !== true ) {
      return false;
    }
  }

  /**
   * Called after the model is safed.
   */
  protected function afterSave() { /* noop */ }

  /**
   * Called before the model is being deleted, performaing last change modifications
   * before passing down to Node::delete().
   *
   * @param {array} $filter The filter array created from the current model.
   * @return {array|boolean} The filter to be passed down to Node::delete(), or
   *                         return false to deny this action.
   */
  protected function beforeDelete($filter) {
    return $filter;
  }

  /**
   * Called after model deletion.
   */
  protected function afterDelete() { /* noop */ }

}
