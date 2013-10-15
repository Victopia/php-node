<?php
/* Request.php | Helper class that parses useful information from the current HTTP request. */

namespace core;

class Request {

  public static /* array */
  function headers($name = NULL) {
    if ( function_exists('getallheaders') ) {
      $headers = getallheaders();
    }
    else {
      $headers = array();

      foreach ($_SERVER as $key => $value) {
        if ( strpos($key, 'HTTP_') === 0 ) {
          $key = substr($key, 5);
          $key = implode('-', array_map('ucfirst', explode('-', $key)));

          $headers[$key] = $value;
        }
      }
    }

    if ( $name === NULL ) {
      return $headers;
    }
    else {
      $index = Utility::arrayKeySearchIgnoreCase($name, $headers);

      return $index === FALSE ? NULL : $headers[$index];
    }
  }

}