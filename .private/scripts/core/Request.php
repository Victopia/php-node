<?php
/* Request.php | Helper class that parses useful information from the current HTTP request. */

namespace core;

class Request {

  public static /* array */
  function headers($name = NULL) {
    /* Request headers should not change during
       execution period, cache them to improve
       performance.
    */
    static $headers = NULL;

    if ( $headers === NULL ) {
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
    }

    if ( $name === NULL ) {
      return $headers;
    }
    else {
      $index = Utility::arrayKeySearchIgnoreCase($name, $headers);

      return $index === FALSE ? NULL : $headers[$index];
    }
  }

  public static /* string */
  function locale($locale = null) {
    static $_locale = 'en_US';

    if ( empty($locale) ) {
      return $_locale;
    }
    else {
      $_locale = $locale;
    }
  }

}
