<?php
/*! Schemas.php | Defines JSON Schemas for other JsonSchemaModels to use. */

namespace models;

use framework\Configuration as conf;

use framework\exceptions\ServiceException;

class Schemas extends abstraction\JsonSchemaModel {

  protected $confObj;

  /**
   * @constructor
   */
  public function __construct($data = null, $identity = null) {
    if ( empty($identity) ) {
      throw new ServiceException('Configuration key cannot be null, please provide $identity.');
    }

    $this->confObj = (new conf(trim($identity) . '.model'))->schema;
  }

  //----------------------------------------------------------------------------
  //
  //  CRUD Operations
  //
  //----------------------------------------------------------------------------

  /**
   * No list functions, always returns an empty array.
   */
  public function get($filters = array()) {
    return [];
  }

  /**
   * Retrieves target model object from configurations
   */
  public function load($identity) {
    return $this->confObj->getContents();
  }

  /**
   * Save the defined
   */
  public function save(&$result = array()) {
    $errors = $this->beforeSave();
    if ( $errors ) {
      $result['errors'] = $errors;
    }
    else {
      $recursion = function($node, $conf) use(&$recursion) {
        if ( is_array($node) ) {
          foreach ( $node as $key => $value ) {
            if ( is_array($value) ) {
              $recursion($value, $conf->$key);
            }
            else {
              $conf->$key = $value;
            }
          }
        }
        else {
          // Cannot set anything with no key.
        }
      };

      $recursion($this->data, $this->confObj);

      unset($recursion, $data);

      $result['success'] = true;
    }

    return $this;
  }

  public function delete(&$isDeleted = false) {
    /*! Note @ 23 Jun, 2015
     *  Iterator does not work properly when keys are unset inside iteration,
     *  must get all keys before looping.
     */
    $keys = array_keys($this->confObj->getContents());
    foreach ( $keys as $key ) {
      unset($this->confObj->$key);
    }

    $isDeleted = true;

    return $this;
  }

  //----------------------------------------------------------------------------
  //
  //  Json Schema related methods
  //
  //----------------------------------------------------------------------------

  public function schema() {
    static $schema;
    if ( !$schema ) {
      $retriever = new \JsonSchema\Uri\UriRetriever;

      $schema = $retriever->retrieve('http://json-schema.org/hyper-schema');

      (new \JsonSchema\RefResolver($retriever))->resolve($schema);

      unset($retriever);
    }

    return $schema;
  }

  public function form() {
    return array('*');
  }

}
