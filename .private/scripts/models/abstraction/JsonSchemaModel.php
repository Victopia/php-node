<?php /*! JsonSchemaModel.php | Data models that leverages JSON schema. */

namespace models\abstraction;

use core\Log;
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
  //  Methods
  //
  //----------------------------------------------------------------------------

  protected $_arrayWorkaroundFields = [];

  //----------------------------------------------------------------------------
  //
  //  Methods: AbstractModel
  //
  //----------------------------------------------------------------------------

  public function validate() {
    $errors = parent::validate();

    // note; remove previous errors to prevent serialization of error objects.
    unset($this->{'$errors'});

    $validator = new Validator();

    $data = $this->data();

    $validator->validate(
      $data,
      $this->schema(),
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
          $errors[] = $e['message'];
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

    $validator = new Validator();

    $data = $this->data();

    $validator->validate(
      $data,
      $this->schema(),
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
          $errors[] = $e['message'];
          return $errors;
        },
        array()
      );
    }

    return parent::populate();
  }

  public function schema() {
    if ( !$this->_schema ) {
      $className = substr(strrchr(get_class($this), '\\'), 1);

      $this->_schema = (array) conf::get("schema.$className")->getContents();

      unset($className);
    }

    return $this->_schema;
  }

  protected function beforeSave(array &$errors = array()) {
    // note;workaround; Must remove blank objects because schema form tends to create them.
    foreach ( $this->arrayWorkaroundFields() as $field ) {
      if ( isset($this->$field) && is_array($this->$field) ) {
        $this->$field = filter($this->$field, compose('not', 'blank'));
      }
    }

    parent::beforeSave($errors);

    if ( !$errors ) {
      // note;workaround; Unwraps plain strings in array with object { $: "string" }
      foreach ( $this->arrayWorkaroundFields() as $field ) {
        if ( isset($this->$field) && is_array($this->$field) ) {
          $this->$field = pluck($this->$field, '$');
        }
      }
    }

    return $this;
  }

  protected function afterSave(array &$result = null) {
    // note;workaround; Wrapping plain strings in array with object { $: "string" }
    foreach ( $this->arrayWorkaroundFields() as $field ) {
      if ( isset($this->$field) && is_array($this->$field) ) {
        $this->$field = array_map(wraps('$'), $this->$field);
      }
    }

    return parent::afterSave($result);
  }

}
