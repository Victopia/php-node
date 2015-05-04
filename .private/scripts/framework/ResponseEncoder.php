<?php
/*! ResponseEncoder.php | Array encoders for the response object. */

namespace framework;

class ResponseEncoder {

  public static function json($value) {
    return json_encode($value);
  }

  public static function jsonp($value) {
    $value = self::json($value);

    $callback = @$_REQUEST['JSONP_CALLBACK_NAME'];
    if ( !$callback ) {
      $callback = 'callback';
    }

    if ( !empty($_REQUEST[$callback]) ) {
      $value = "$_REQUEST[$callback]($value)";
    }

    return $value;
  }

  public static function serialize($value) {
    return serialize($value);
  }

  public static function dump($value) {
    ob_start();

    var_dump($value);

    return ob_get_clean();
  }

  public static function xml($value) {
    return \core\XMLConverter::toXML($value);
  }

}
