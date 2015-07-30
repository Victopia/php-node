<?php
/*! JsonSchemaModel.php | Data models that leverages JSON schema. */

namespace models\abstraction;

use core\Log;
use core\Utility as util;

use framework\Configuration as conf;

use Jsv4;

abstract class JsonSchemaModel extends AbstractRelationModel {

  public function validate(&$errors = array()) {
    $schema = $this->schema();

    if ( !Jsv4::isValid($this->data, $schema) ) {
      // try to coerce on initial failure
      $result = Jsv4::coerce($this->data, $schema);
      if ( $result->value ) {
        $this->data = $result->value;
      }

      // return errors if exists
      if ( !empty($result->errors) ) {
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

    return conf::get("{$this->collectionName()}.model::$type", $default);
  }

}
