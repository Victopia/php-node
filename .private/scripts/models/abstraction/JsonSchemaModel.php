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
    parent::__construct($data);
  }

  public function validate(array &$errors = array()) {
    if ( !Jsv4::isValid($this->data, $this->schema()) ) {
      // try to coerce on initial failure
      $result = Jsv4::coerce($this->data, $this->schema());
      if ( $result->valid ) {
        $this->data = $result->value;
      }
      // return errors if exists
      else {
        $errors = array_merge(util::objectToArray($result->errors));
      }
    }

    return parent::validate();
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

  function afterLoad() {
    parent::afterLoad();

    $result = Jsv4::coerce($this->data, $this->schema());
    if ( $result->valid ) {
      $this->data = $result->value;
    }
  }

}
