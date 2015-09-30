<?php
/*! ContentDecoder.php | Parse encoded contents into PHP values. */

namespace core;

class ContentDecoder {

  /**
   * JSON decodes a string, this function strips comments.
   */
  public static function json($value, $assoc = true, $depth = 512, $options = 0) {
    // Compress script: single line comments
    $value = preg_replace('/\/\/.*/', '', $value);
    // Compress value: multiline comments
    $value = preg_replace('/\/\*.*\*\//', '', $value);

    return json_decode($value, $assoc, $depth, $options);
  }

  /**
   * Unserialize a value.
   */
  public static function unserialize($value) {
    return unserialize($value);
  }

  /**
   * Parse XML string into PHP value.
   */
  public static function xml($value) {
    return XMLConverter::fromXML($value);
  }

}
