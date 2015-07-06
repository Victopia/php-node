<?php
/*! Users.php | The user data model. */

namespace models;

class Users extends abstraction\JsonSchemaModel {

  /**
   * @private
   *
   * Create a hash for UNIX crypt(6)
   *
   * Note: Make this alterable, pay attention to key-strenthening.
   */
  protected function hash($username, $password) {
    $hash = sha1(time() + mt_rand());
    $hash = md5("$username:$hash");
    $hash = substr($hash, 16);
    $hash = '$6$rounds=10000$' . $hash; // CRYPT_SHA512
    $hash = crypt($password, $hash);
    return $hash;
  }

}
