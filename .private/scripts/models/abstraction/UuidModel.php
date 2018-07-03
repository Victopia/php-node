<?php /*! UuidModel.php | Data models that uses uuid as identifiers. */

namespace models\abstraction;

use core\Database;
use core\Utility as util;

use framework\Configuration as conf;
use framework\exceptions\FrameworkException;

use Ramsey\Uuid\Uuid;

abstract class UuidModel extends JsonSchemaModel {

  //----------------------------------------------------------------------------
  //
  //  Properties : AbstractModel
  //
  //----------------------------------------------------------------------------

  protected $_primaryKey = 'uuid';

  public function identity($value = null) {
    if ( $value === null ) {
      return util::unpackUuid(parent::identity());
    }
    else {
      return parent::identity(util::packUuid($value));
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Methods: AbstractModel
  //
  //----------------------------------------------------------------------------

  function find(array $filter = []) {
    foreach ( $this->binaryFields() as $binaryField ) {
      if ( isset($filter[$binaryField]) && (is_string($filter[$binaryField]) || is_array($filter[$binaryField])) ) {
        $filter[$binaryField] = array_map('core\Utility::packUuid', (array) $filter[$binaryField]);
      }
    }

    return parent::find($filter);
  }

  function validate() {
    $isCreate = !$this->identity();
    if ( $isCreate ) {
      $this->generateUuid();
    }

    $errors = parent::validate();

    if ( $isCreate ) {
      unset($this->{$this->primaryKey()});
    }

    return $errors;
  }

  protected function beforeLoad(array &$filter) {
    foreach ( $this->binaryFields() as $binaryField ) {
      if ( isset($filter[$binaryField]) ) {
        $filter[$binaryField] = array_map('core\Utility::packUuid', (array) $filter[$binaryField]);
      }
    }

    return parent::beforeLoad($filter);
  }

  protected function populate() {
    foreach ( $this->binaryFields() as $binaryField ) {
      if ( isset($this->$binaryField) ) {
        $this->$binaryField = util::unpackUuid($this->$binaryField);
      }
    }

    return parent::populate();
  }

  protected function beforeSave(array &$errors = array()) {
    if ( $this->isCreate() && !$this->identity() ) {
      $this->generateUuid();
    }

    foreach ( $this->binaryFields() as $binaryField ) {
      if ( isset($this->$binaryField) ) {
        $this->$binaryField = util::unpackUuid($this->$binaryField);
      }
    }

    $ret = parent::beforeSave($errors);

    foreach ( $this->binaryFields() as $binaryField ) {
      if ( isset($this->$binaryField) ) {
        $this->$binaryField = util::packUuid($this->$binaryField);
      }
    }

    return $ret;
  }

  protected function afterSave(array &$result = null) {
    foreach ( $this->binaryFields() as $binaryField ) {
      if ( isset($this->$binaryField) ) {
        $this->$binaryField = util::unpackUuid($this->$binaryField);
      }
    }

    return parent::afterSave($result);
  }

  /**
   * Packs UUID for filters.
   */
  protected function beforeDelete(array &$filter = []) {
    foreach ( $this->binaryFields() as $binaryField ) {
      if ( isset($filter[$binaryField]) ) {
        $filter[$binaryField] = array_map('core\Utility::packUuid', (array) $filter[$binaryField]);
      }
    }

    return parent::beforeDelete($filter);
  }

  protected function binaryFields() {
    return [ $this->primaryKey() ];
  }

  protected function generateUuid($forceRenew = false) {
    if ( !$forceRenew && (!$this->isCreate() || $this->identity()) ) {
      return;
    }

    $key = $this->primaryKey();

    $this->$key = str_replace('-', '', Uuid::uuid4());
  }

}
