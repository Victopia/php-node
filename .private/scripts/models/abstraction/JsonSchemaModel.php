<?php
/*! JsonSchemaModel.php | Data models that leverages JSON schema. */

namespace models\abstraction;

use core\Log;
use core\Utility as util;

use framework\Configuration as conf;

use Jsv4;

abstract class JsonSchemaModel extends AbstractRelationModel {

  /**
   * @protected
   *
   * Schema array.
   */
  protected $schema = array();

  /**
   * @constructor
   *
   * Try to read from configuration files if schema is not already set.
   */
  public function __construct($data = null) {
    if ( !$this->schema ) {
      $className = get_class($this);

      if ( strpos($className, '\\') !== false ) {
        $className = substr(strrchr($className, '\\'), 1);
      }

      $this->schema = util::arrayToObject((array) conf::get("schema.$className")->getContents());

      unset($className);
    }

    parent::__construct($data);
  }

  public function validate() {
    $errors = parent::validate();

    $result = Jsv4::coerce(filter($this->data), $this->schema());
    if ( $result->valid ) {
      $this->data = $result->value;
    }
    else {
      $errors = array_merge(
        $errors,
        array_reduce(
          $result->errors,
          function($errors, $e) {
            $errors[$e->getCode()] = $e->dataPath . ': ' . $e->getMessage();
            return $errors;
          },
          array()
        )
      );
    }

    return $errors;
  }

  public function schema($type = 'data') {
    switch ( $type ) {
      case 'form':
        $default = array('*');
        break;

      default:
        $default = null;
        break;
    }

    $ret = @$this->schema->$type;
    if ( !$ret ) {
      $ret = $default;
    }

    if ( !$ret && isset($this->__response) ) {
      $this->__response->status(404);
    }

    return $ret;
  }

  protected function afterLoad() {
    parent::afterLoad();

    $result = Jsv4::coerce($this->data, $this->schema());
    if ($result->valid) {
      $this->data = $result->value;
    }

    return $this;
  }

}
