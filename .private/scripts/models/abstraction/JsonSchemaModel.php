<?php /*! JsonSchemaModel.php | Data models that leverages JSON schema. */

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
  public function __construct($data = null, $context = array()) {
    if ( !$this->schema ) {
      $collectionName = $this->collectionName();

      if ( strpos($collectionName, '\\') !== false ) {
        $collectionName = substr(strrchr($collectionName, '\\'), 1);
      }

      $this->schema = util::arrayToObject((array) conf::get("schema.$collectionName")->getContents());

      unset($collectionName);
    }

    parent::__construct($data, $context);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  protected $_arrayWorkaroundFields = [];

  // note; Prevent array fields from wrapping multiple times during save().
  private $isSaving = false;

  //----------------------------------------------------------------------------
  //
  //  Methods: AbstractModel
  //
  //----------------------------------------------------------------------------

  public function validate() {
    $errors = parent::validate();

    // note; remove previous errors to prevent serialization of error objects.
    unset($this->{'$errors'});

    $result = Jsv4::coerce($this->data, $this->schema());
    if ( $result->valid ) {
      $this->data = $result->value;
    }
    else {
      $errors+= array_reduce(
        $result->errors,
        function($errors, $e) {
          $errors[$e->getCode()] = $e->dataPath . ': ' . $e->getMessage();
          return $errors;
        },
        array()
      );
    }

    return $errors;
  }

  protected function populate() {
    if ( !$this->isSaving ) {
      // note;workaround; Wrapping plain strings in array with object { $: "string" }
      foreach ( $this->arrayWorkaroundFields() as $field ) {
        if ( isset($this->$field) && is_array($this->$field) ) {
          $this->$field = array_map(wraps('$'), $this->$field);
        }
      }
    }

    // note; remove previous errors to prevent serialization of error objects.
    unset($this->{'$errors'});

    $result = Jsv4::coerce($this->data, $this->schema());
    if ($result->valid) {
      $this->data = $result->value;
    }
    else {
      $this->{'$errors'} = $result->errors;
    }

    return parent::populate();
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

    return $ret;
  }

  protected function beforeSave(array &$errors = array()) {
    $this->isSaving = true;

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
    $this->isSaving = false;

    // note;workaround; Wrapping plain strings in array with object { $: "string" }
    foreach ( $this->arrayWorkaroundFields() as $field ) {
      if ( isset($this->$field) && is_array($this->$field) ) {
        $this->$field = array_map(wraps('$'), $this->$field);
      }
    }

    return parent::afterSave($result);
  }

}
