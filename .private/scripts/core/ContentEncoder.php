<?php
/*! ContentEncoder.php | Encodes contents for the response output. */

namespace core;

class ContentEncoder {

  /**
   * JSON encode a PHP value.
   *
   * @param {void*} $value Any arbitrary PHP value.
   * @return {string} JSON representation of specified PHP value.
   */
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
      else if ( is_string($value) && $value && !ctype_print($value) && json_encode($value) === false ) {
        $value = '[binary string]';
      }
    };

    $maskBinary($value);

    return json_encode($value);
  }

  /**
   * Serialize PHP value into a string.
   *
   * @param {void*} $value Any arbitrary PHP value.
   * @return {string} Serialized string of specified PHP value.
   */
  public static function serialize($value) {
    return serialize($value);
  }

  /**
   * Encodes PHP array into XML, primitive values are not supported because XML
   * requires a root document (tag).
   *
   * @param {void*} $value Any arbitrary PHP value.
   * @return {string} XML version of specified PHP value.
   */
  public static function xml(array $value) {
    return XMLConverter::toXML($value);
  }

  /**
   * Returns the var_dump() value. This is irriversible or pretty-printed,
   * therefore no decoders will be provided.
   *
   * @param {void*} $value Any arbitrary PHP value.
   * @return {string} var_dump representation of specified PHP value.
   */
  public static function dump($value) {
    ob_start();

    var_dump($value);

    return ob_get_clean();
  }

  /**
   * Returns the var_export() value. eval() will reverse the value, and it is
   * unsafe therefore decoder will not include that.
   *
   * @param {void*} $value Any arbitrary PHP value.
   * @return {string} var_export representation of specified PHP value.
   */
  public static function export($value) {
    return var_export($value, 1);
  }

}
