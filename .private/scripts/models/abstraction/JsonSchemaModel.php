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

  //----------------------------------------------------------------------------
  //
  //  Methods: AbstractModel
  //
  //----------------------------------------------------------------------------

  public function validate() {
    $errors = parent::validate();

    // note; remove previous errors to prevent serialization of error objects.
    unset($this->{'$errors'});

    $result = Jsv4::coerce($this->data(), $this->schema());
    if ( $result->valid ) {
      $this->data($result->value);
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
    // note; remove previous errors to prevent serialization of error objects.
    unset($this->{'$errors'});

    $result = Jsv4::coerce($this->data(), $this->schema());
    if ($result->valid) {
      $this->data($result->value);
    }
    else {
      $this->{'$errors'} = $result->errors;
    }

    return parent::populate();
  }

  public function schema($type = 'data') {
    if ( !$this->schema ) {
      $className = substr(strrchr(get_class($this), '\\'), 1);

      $this->schema = util::arrayToObject(
        (array) conf::get("schema.$className")->getContents()
      );

      unset($className);
    }

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

}
