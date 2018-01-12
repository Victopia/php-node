<?php
namespace models\abstraction;

use authenticators\IsLoggedIn;
use authenticators\IsModerator;

use core\Database;

/**
 * UserView appends user identity the collection name [table_name]@[user_uuid].
 *
 * This is intended for views that restricts data access to the requesting user.
 */
abstract class UserView extends UuidModel {

  function collectionName() {
    if ( !$this->_collectionName ) {
      $req = $this->request();

      $this->_collectionName = parent::collectionName();

      if ( IsLoggedIn::authenticate($req) ) {
        $table = $this->_collectionName . '@' . $req->user->identity();

        if ( Database::hasTable($table) ) {
          $this->_collectionName = $table;
        }

        unset($table);
      }

      unset($req);
    }

    return $this->_collectionName;
  }

}
