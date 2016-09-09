<?php
/*! ModelCollection.php | Traversable collection class for database cursors. */

namespace models\abstraction;

use core\Node;
use core\Utility as util;

class ModelCollection implements \Iterator, \ArrayAccess, \Countable, \JsonSerializable {

  /**
   * Model class name
   */
  protected $modelClass;

  /**
   * Node collection class
   */
  protected $collection = null;

  /**
   * Current wrapped model class
   */
  protected $current = null;

  /**
   * @constructor
   */
  public function __construct($modelClass, $filter) {
    if ( !class_exists($modelClass) ) {
      throw new \Exception("Class $modelClass does not exists.");
    }

    $this->modelClass = $modelClass;
    $this->collection = new Node($filter);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : Iterator
  //
  //----------------------------------------------------------------------------

  public function current() {
    if ( !$this->current && $this->collection->valid() ) {
      $this->current = $this->createModel(
        $this->collection->current()
      );
    }

    return $this->current;
  }

  public function key() {
    return $this->collection->key();
  }

  public function next() {
    $this->current = null;
    $this->collection->next();

    return $this;
  }

  public function rewind() {
    $this->current = null;
    $this->collection->rewind();

    return $this;
  }

  public function valid() {
    return $this->collection->valid();
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : ArrayAccess
  //
  //----------------------------------------------------------------------------

  public function offsetExists($offset) {
    return isset($this->collection[$offset]);
  }

  public function offsetGet($offset) {
    $data = $this->collection[$offset];
    if ( $data ) {
      return $this->createModel($data);
    }
  }

  public function offsetSet($offset, $value) {
    $this->collection[$offset] = $value;
  }

  public function offsetUnset($offset) {
    unset($this->collection[$offset]);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : Countable
  //
  //----------------------------------------------------------------------------

  public function count() {
    return $this->collection->count();
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : JsonSerializable
  //
  //----------------------------------------------------------------------------

  public function jsonSerialize() {
    return $this->toArray();
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  protected function createModel($data) {
    $model = new $this->modelClass(
      $this->collection->current()
    );

    if ( isset($this->__request) ) {
      $model->__request = $this->__request;
    }

    if ( isset($this->__response) ) {
      $model->__response = $this->__response;
    }

    util::forceInvoke(array($model, 'afterLoad'));

    return $model;
  }

  /**
   * Reload underlying result set for updated data.
   *
   * @return {ModelCollection} Chainable.
   */
  public function reload() {
    $this->collection->reload();

    return $this;
  }

  /**
   * Fetch the whole result and return as an array.
   */
  public function toArray() {
    $result = [];

    foreach ($this as $value) {
      $result[] = $value;
    }

    return $result;
  }
}
