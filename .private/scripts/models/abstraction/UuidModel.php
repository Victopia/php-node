<?php
/*! UuidModel.php | Data models that uses uuid as identifiers. */

namespace models\abstraction;

use core\Database;
use core\Utility as util;

use framework\Configuration as conf;
use framework\exceptions\FrameworkException;

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

  protected function beforeLoad(array &$filter) {
    $filter = $this->packUuid($filter);

    return parent::beforeLoad($filter);
  }

  function populate() {
    $this->{$this->primaryKey()} = $this->identity();

    return parent::populate();
  }

  function find(array $filter = []) {
    $filter = $this->packUuid($filter);

    return parent::find($filter);
  }

  protected function beforeSave(array &$errors = array()) {
    $key = $this->primaryKey();

    if ( $this->isCreate() ) {
      // note; loop until we find a unique uuid
      do {
        $this->$key = Database::fetchField("SELECT LOWER(REPLACE(UUID(), '-', ''))");
      }
      while ($this->find([ $key => $this->$key ])->count());
    }

    $ret = parent::beforeSave($errors);

    $this->$key = util::packUuid($this->$key);

    return $ret;
  }

  /**
   * Packs UUID for filters.
   */
  protected function beforeDelete(array &$filter = []) {
    $filter = $this->packUuid($filter);

    return parent::beforeDelete($filter);
  }

  protected function packUuid(array $filter = []) {
    $key = $this->primaryKey();

    if ( isset($filter[$key]) ) {
      $filter[$key] = util::packUuid($filter[$key]);
    }

    return $filter;
  }

}
