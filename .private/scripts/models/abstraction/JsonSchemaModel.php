<?php /*! JsonSchemaModel.php | Data models that leverages JSON schema. */

namespace models\abstraction;

use core\Database;
use core\Log;
use core\Node;
use core\Utility as util;

use framework\Configuration as conf;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

abstract class JsonSchemaModel extends AbstractRelationModel {

  /**
   * @protected
   *
   * Schema array.
   */
  protected $_schema = array();

  //----------------------------------------------------------------------------
  //
  //  Methods: AbstractModel
  //
  //----------------------------------------------------------------------------

  public function validate() {
    $errors = parent::validate();

    // note; remove previous errors to prevent serialization of error objects.
    unset($this->{'$errors'});

    $this->coerceDateTime("json-schema");

    $data = $this->data();

    $validator = new Validator();
    $validator->validate(
      $data,
      $this->schema(),
      Constraint::CHECK_MODE_TYPE_CAST |
      Constraint::CHECK_MODE_COERCE_TYPES |
      Constraint::CHECK_MODE_APPLY_DEFAULTS
    );

    // $result = Jsv4::coerce($this->data(), $this->schema());
    if ( $validator->isValid() ) {
      $this->data($data);
    }
    else {
      $errors+= array_reduce(
        $validator->getErrors(),
        function($errors, $e) {
          $errors[] = $e;
          return $errors;
        },
        array()
      );
    }

    return $errors;
  }

  protected function populate() {
    // note; remove previous errors to prevent serialization of error objects.
    unset($this->{'$errors'});

    $this->coerceDateTime("json-schema");

    $data = $this->data();

    $validator = new Validator();
    $validator->validate(
      $data,
      $this->schema(),
      Constraint::CHECK_MODE_TYPE_CAST |
      Constraint::CHECK_MODE_COERCE_TYPES |
      Constraint::CHECK_MODE_APPLY_DEFAULTS
    );

    if ( $validator->isValid() ) {
      $this->data($data);
    }
    else {
      $this->{'$errors'} = array_reduce(
        $validator->getErrors(),
        function($errors, $e) {
          $errors[] = $e;
          return $errors;
        },
        array()
      );
    }

    return parent::populate();
  }

  public function schema() {
    if ( !$this->_schema ) {
      $className = substr(strrchr(get_class($this), "\\"), 1);

      $this->_schema = (array) conf::get("schema.$className")->getContents();

      unset($className);
    }

    return $this->_schema;
  }

  protected function beforeSave(array &$errors = array()) {
    parent::beforeSave($errors);

    if ( !$errors ) {
      $this->coerceDateTime("mysql");
    }

    return $this;
  }

  /**
   * SUPER ugly function to convert date-time format between MySQL and JSON Schema.
   */
  protected function coerceDateTime($type) {
    $fields = Node::resolveCollection($this->collectionName());
    $fields = Database::getFields($fields, null, false);
    $fields = filter($fields, compose(
      matches("/(datetime|timestamp)(?:\(\d+\))?/"),
      prop("Type")
    ));
    $fields = array_keys($fields);

    if ( $type === "mysql" ) {
      // Convert RFC3339 back to MySQL DATETIME format.
      foreach ( $fields as $key ) {
        if ( !empty($this->$key) && is_string($this->$key) ) {
          $this->$key = preg_replace(
            "/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(\.\d+)?(Z|([+-]\d{2}):?(\d{2}))$/",
            '$1 $2$3',
            $this->$key
          );
        }
      }
    }
    else if ( $type === "json-schema" ) {
      // Coerce MySQL DATETIME format to RFC3339.
      foreach ( $fields as $key ) {
        if ( !empty($this->$key) && is_string($this->$key) ) {
          $this->$key = preg_replace(
            "/(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(\.\d*)/",
            '$1T$2$3Z',
            $this->$key
          );
        }
      }
    }
  }

}
