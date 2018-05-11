<?php // Timestamped.php: Models "created_at" and "timestamp" will be automatically updated.

namespace models\abstraction;

use core\Utility as util;

trait Timestamped {

  /**
   * @private
   *
   * Automatically generate timestamp with this format, skip when this value is empty().
   */
  private $_timestampFormat = 'Y-m-d H:i:s.u';

  public function find(array $filter = []) {
    $sorter = &$filter['@sorter'];
    if ( empty($sorter) ) {
      $sorter = [ 'timestamp' => false ];
    }

    return parent::find($filter);
  }

  protected function beforeSave(array &$errors = array()) {
    if ( !empty($this->timestampFormat()) ) {
      $this->timestamp = util::formatDate('Y-m-d\TH:i:s.uO');

      if ( $this->isCreate() ) {
        $this->created_at = $this->timestamp;
      }
    }

    parent::beforeSave($errors);

    return $this;
  }

}
