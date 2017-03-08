<?php /* NodeModel.php | Node model class for generic collections. */

namespace models;

use core\Node;
use core\Utility as util;

use framework\exceptions\FrameworkException;

/**
 * Disposable keys which will be cleared after a certain period.
 *
 * Error code: 9xx
 */
class NodeModel extends abstraction\UuidModel {

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  function __construct($collection, $data = null) {
    // note; ModelCollection will feed data here, but at least we need ['@collection'] to work.
    if ( is_array($collection) && $data === null ) {
      if ( empty($collection[Node::FIELD_COLLECTION]) ) {
        throw new FrameworkException('Collection name is missing.');
      }
      else {
        $data = $collection;
        $collection = $data[Node::FIELD_COLLECTION];
      }
    }

    $this->_collectionName = $collection;

    parent::__construct($data);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : AbstractModel
  //
  //----------------------------------------------------------------------------

  protected function beforeSave(array &$errors = array()) {
    $this->timestamp = util::formatDate('Y-m-d H:i:s.u');

    return parent::beforeSave($errors);
  }

}