<?php
/*! ContentEncoder.php | Array encoders for the response object. */

namespace core;

class ContentEncoder {

  public static function json($value) {
    // note: binary strings will fuck up the encoding process, remove them.
    $maskBinary = function(&$value) use(&$maskBinary) {
      if ( $value instanceof \JsonSerializable ) {
        $value = $value->jsonSerialize();
      }

      if ( is_object($value) ) {
        foreach ( get_object_vars($value) as $key => $_value ) {
          $maskBinary($value->$key);
        } unset($_value);
      }
      else if ( is_array($value) ) {
        array_walk($value, $maskBinary);
      }
      else if ( is_string($value) && $value && !ctype_print($value) ) {
        $value = '[binary string]';
      }
    };

    $maskBinary($value);

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
