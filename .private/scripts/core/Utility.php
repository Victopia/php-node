<?php
/* Utility.php | https://github.com/victopia/php-node */

namespace core;

/**
 * Utility class.
 *
 * This is a collection of handy utility methods,
 * read the code for hidden gems.
 *
 * Mostly snippets here that is not big enough
 * (either size or functionality) for a standalone
 * class.
 *
 * @author Vicary Archangel <vicary@victopia.org>
 */
class Utility {
  /**
   * Returns whether the current process is in CLI environment.
   */
  static function isCLI() {
    return php_sapi_name() == 'cli'/* && empty($_SERVER['REMOTE_ADDR'])*/;
  }

  /**
   * Gets all network interfaces with an appropriate IPv4 address.
   */
  static function letIfaces() {
    switch (strtoupper(PHP_OS)) {
      case 'DARWIN': // MAC OS X
        $ifaces = @`ifconfig | expand | cut -c1-8 | sort | uniq -u | awk -F: '{print $1;}'`;
        $ifaces = preg_split('/\s+/', $ifaces);
        return $ifaces;

      case 'LINUX':
        $ifaces = `ifconfig -a | sed 's/[ \t].*//;/^\(lo\|\)$/d'`;
        $ifaces = preg_split('/\s+/', $ifaces);
        return $ifaces;

      case 'WINNT': // Currently not supported.
      default:
        return array();
    }
  }

  /**
   * Returns whether current request is made by local redirect.
   */
  static function isLocalRedirect() {
    return @$_SERVER['HTTP_REFERER'] == FRAMEWORK_SERVICE_HOSTNAME;
  }

  /**
   * Shorthand function for either isCLI() or isLocalRedirect().
   *
   * Service methods should normally allow this no matter what,
   * unless user accessible data range must be enforced. Functions
   * should then implement a way to indicate target user.
   */
  static function isLocal() {
    return self::isCLI() || self::isLocalRedirect();
  }

  /**
   * Get callee of the current script.
   */
  static function getCallee($level = 2) {
    $backtrace = debug_backtrace();

    if ( count($backtrace) <= $level ) {
      $level = count($backtrace) - 1;
    }

    /* Added by Vicary @ 23 Dec, 2012
        This script should:
        1. try its best to search until there is a file, and
        2. stop before framework scripts are reached.
    */
    if ( !@$backtrace[$level]['file'] ) {
      for ( $level = count($backtrace);
            $level-- && !@$backtrace[$level]['file'];
          );
    }

    // Framework scripts are:
    // - resolvers
    // to be added ...
    while (strrchr(dirname(@$backtrace[$level]['file']), '/') == '/resolvers') {
      $level--;
    }

    if ( $level < 0 ) {
      $level = 0;
    }

    return @$backtrace[$level];
  }

  /**
   * Check whether specified file is somehow in CSV format.
   */
  static function isCSV($file) {
    $file = fopen($file, 'r');

    if ( !$file ) {
      return FALSE;
    }

    for ( $i=0; $i<5; $i-- ) {
      if ( fgetcsv($file) ) {
        fclose($file);
        return TRUE;
      }
    }

    fclose($file);
    return FALSE;
  }

  /**
   * Determine whether the given string is a well formed URL.
   */
  static function isURL($value) {
    // SCHEME
    $urlregex = '^(file|https?|ftps?|php|zlib|data|glob|phar|ssh2|ogg|expect)\:\/\/';

    // USER AND PASS (optional)
    $urlregex .= '([a-z0-9+!*(),;?&=$_.-]+(\:[a-z0-9+!*(),;?&=$_.-]+)?@)?';

    // HOSTNAME OR IP
    $urlregex .= '[a-z0-9+$_-]+(\.[a-z0-9+$_-]+)*'; // http://x = allowed (ex. http://localhost, http://routerlogin)
    //$urlregex .= '[a-z0-9+$_-]+(\.[a-z0-9+$_-]+)+'; // http://x.x = minimum
    //$urlregex .= '([a-z0-9+$_-]+\.)*[a-z0-9+$_-]{2,3}'; // http://x.xx(x) = minimum
    //use only one of the above

    // PORT (optional)
    $urlregex .= '(\:[0-9]{2,5})?';
    // PATH (optional)
    $urlregex .= '(\/([a-z0-9+$_\-\=~\!\(\),]\.?)+)*\/?';
    // GET Query (optional)
    $urlregex .= '(\?[a-z+&$_.-][a-z0-9;:@\/&%=+$_.-]*)?';
    // ANCHOR (optional)
    $urlregex .= '(#[a-z_.-][a-z0-9+$_.-]*)?$';

    // check
    return (bool) preg_match("/$urlregex/i", (string) $value);
  }

  /**
   * Determine whether an array is associative.
   *
   * To determine a numeric array, inverse the result of this function.
   */
  static function isAssoc($value) {
    /* This is the original version found somewhere in the internet,
       keeping it to respect the author.

       Problem is numeric arrays with inconsecutive keys will return
       as associative, might be a desired outcome when doing json_encode,
       but this led to a not very descriptive function name.

    return is_array($value) && count($value) &&
      count(array_diff_key($value, array_keys(array_keys($value))));
    */

    return is_array($value) && $value &&
      // All keys must be numeric to qualify as NOT assoc.
      array_filter(array_keys($value), compose('not', 'is_numeric'));
  }

  /**
   * Wrap an associative array with a new array(), making it iteratable.
   */
  static function wrapAssoc($item) {
    return !is_array($item) || self::isAssoc($item) ? array($item) : $item;
  }

  /**
   * Unwrap an array of primitives, hash-arrays or objects,
   * and returns the first element.
   *
   * Null is returned if input array is empty.
   */
  static function unwrapAssoc($list) {
    if ( $list && is_array($list) && !self::isAssoc($list) ) {
      return reset($list);
    }

    return $list;
  }

  /**
   * Case-insensitive version of array_merge.
   *
   * Character case of the orginal array is preserved.
   */
  static function arrayMergeIgnoreCase(&$subject) {
    $args = func_get_args();

    if ( !$args ) {
      return NULL;
    }

    $n = count($args);
    for ( $i=1; $i<$n; $i++ ) {
      foreach ( $args[$i] as $objKey => &$objValue ) {
        foreach ($subject as $subKey => &$subValue) {
          if ( strcasecmp($subKey, $objKey) === 0 ) {
            $subject[$subKey] = $objValue;
            $objValue = NULL;
            break;
          }
        }

        if ( $objValue !== NULL ) {
          $subject[$objKey] = $objValue;
        }
      }
    }

    return $subject;
  }

  /**
   *
   */
  static function arrayDiffIgnoreCase() {
    $arrays = array_map(function($input) {
      return array_map('strtolower', array_filter($input, 'is_string')) + $input;
    }, func_get_args());

    return call_user_func_array('array_diff', $arrays);
  }

  /**
   * Case-insensitve version of in_array.
   */
  static function inArrayIgnoreCase($needle, $haystack) {
    return self::arraySearchIgnoreCase($needle, $haystack) !== FALSE;
  }

  /**
   * Case-insensitive version of array_search.
   */
  static function arraySearchIgnoreCase($needle, $haystack) {
    // Only lower case strings, then adding non-string key-value pairs back.
    $haystack = array_map('strtolower', array_filter($haystack, 'is_string')) + $haystack;

    return array_search(strtolower($needle), $haystack);
  }

  /**
   * Case-insensitive version of array_key_exists.
   */
  static function arrayKeyExistsIgnoreCase($key, $search) {
    $keys = array_map('strtolower', array_keys($search));

    return array_key_exists(strtolower($key), $keys);
  }

  /**
   * Deep conversion from stdClass (object) to array.
   */
  static function objectToArray($data) {
    if ( is_object($data) ) {
      $data = get_object_vars($data);
    }

    if ( is_array($data) ) {
      return array_map(array(__CLASS__, __FUNCTION__), $data);
    }

    return $data;
  }

  /**
   * Flatten an array, concatenating keys with specified delimiter.
   */
  static function flattenArray(&$input, $delimiter = '.', $flattenNumeric = TRUE) {
    if (!is_array($input)) {
      return $input;
    }

    // Could have a single layer solution,
    // can't think of it at the moment so leave it be.
    if ( \utils::isAssoc($input) || $flattenNumeric ) {
      foreach ($input as $key1 => $value) {
        $value = self::flattenArray($value, $delimiter, $flattenNumeric);

        if ( is_array($value) && ($flattenNumeric || \utils::isAssoc($value)) ) {
          foreach ($value as $key2 => $val) {
            $input["$key1$delimiter$key2"] = $val;
          }

          unset($input[$key1]);
        }
      }
    }

    return $input;
  }

  /**
   * Reconstruct an array by breaking the keys with $delimiter, reverse of flattenArray().
   */
  static function unflattenArray($input, $delimiter = '.') {
    if ( !is_array($input) ) {
      return $input;
    }

    $result = $input;

    if ( self::isAssoc($input) ) {
      foreach ($input as $key => $value) {
        $keyPath = explode($delimiter, $key);

        // Skip if it is just the same.
        if ( $keyPath == (array) $key ) {
          continue;
        }

        $valueNode = &$result;

        while ($keyPath) {
          $valueNode = &$valueNode[array_shift($keyPath)];
        }

        // Saves memory
        unset($input[$key], $result[$key]);

        $valueNode = $value;
      }
    }

    return $result;
  }

  /**
   * Return the first value that is not interpreted as FALSE.
   *
   * This is made for those lazy shits like me, who does
   * (foo || bar || baz) a lot in Javascript.
   *
   * @param {array} $list Array of values to cascade, or it will func_get_args().
   */
  static function cascade($list) {
    if ( !is_array($list) ) {
      $list = func_get_args();
    }

    while ($list && !($arg = array_shift($list)));

    return @$arg; // return NULL when empty array is given.
  }

  /**
   * Create deep array path if any intermediate property does not exists.
   */
  static function &deepRef($path, &$input) {
    if ( !is_array($path) ) {
      $path = explode('.', $path);
    }

    $ref = &$input;

    while ($path) {
      $ref = &$ref[array_shift($path)];
    }

    return $ref;
  }

  /**
   * Value version of deepRef.
   */
  static function deepVal($path, $input) {
    return self::deepRef($path, $input);
  }

  /**
   * Invoke target function or method, regardless the declaration
   * modifier (private, protected or public).
   *
   * This is achieved by the Reflection model of PHP.
   */
  static function forceInvoke($callable, $parameters = NULL) {
    $parameters = (array) $parameters;

    // Normal callable
    if ( is_callable($callable) ) {
      return call_user_func_array($callable, $parameters);
    }

    // Direct invoke ReflectionFunction
    if ( $callable instanceof \ReflectionFunction ) {
      return $callable->invokeArgs($parameters);
    }

    // "class::method" static thing
    if ( is_string($callable) && strpos($callable, '::') !== FALSE ) {
      $callable = explode('::', $callable);
    }

    // Not callable but is an array, cast it to ReflectionMethod.
    if ( is_array($callable) ) {
      $method = new \ReflectionMethod($callable[0], $callable[1]);
      $method->setAccessible(true);

      if ( is_object($callable[0]) ) {
        $method->instance = $callable[0];
      }

      $callable = $method;

      unset($method);
    }

    if ( $callable instanceof \ReflectionMethod ) {
      return $callable->invokeArgs(@$callable->instance, $parameters);
    }
  }

  /**
   * This function is very much like the date() function,
   * except it also takes a string which originally needs
   * an extra call to strtotime().
   */
  public static function
  /* string */ formatDate($pattern, $date) {
    if ( !is_numeric($date) && $date ) {
      return call_user_func(array(__CLASS__, __FUNCTION__), $pattern, strtotime($date));
    }

    if ( !$date || $date == -1 ) {
      return FALSE;
    }

    return date($pattern, $date);
  }

  /* Added and Quoted by Vicary @ 17 Feb, 2013
     Not very useful, should be a standalone duration class, or even resource bundle
     for this kind of thing.

  /**
   * Make a "in ... (time unit)" string, compared between the input times.
   *
   * @param (uint) $target The target time to be compared against.
   * @param (uint) $since Optional, the relative start time to compare. Defaults to current time.
  public static function composeDateString($target, $since = NULL) {
    if ( $since === NULL ) {
      $since = time();
    }

    // Already past
    if ( $target > $since ) {
      return 'expired';
    }

    if ( $target > strtotime('+1 year', $since) ) {
      $target = $target - $since + EPOACH;
      $target = intval( date('Y', $target) );

      return "in $target " . ($target > 1 ? 'years' : 'year');
    }

    if ( $target > strtotime('+1 month', $since) ) {
      $target = $target - $since + EPOACH;
      $target = intval( date('n', $target) );

      return "in $target " . ($target > 1 ? 'months' : 'month');
    }

    if ( $target > strtotime('+1 week', $since) ) {
      $target = $target - $since + EPOACH;
      $target = intval( date('N', $target) );

      return "in $target " . ($target > 1 ? 'weeks' : 'week');
    }

    // return "warn" in 3 days
    if ( $target < strtotime('+3 days', $since) ) {
      return 'warn';
    }

    $target = $target - $since + EPOACH;
    $target = intval( date('j', $target) );

    return "in $target " . ($tmp > 1 ? 'days' : 'day');
  }
  */

  /**
   * Sanitize the value to be an integer.
   */
  static function sanitizeInt($value) {
    return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
  }

  /**
   * Sanitize the value to be plain text.
   */
  static function sanitizeString($value) {
    return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
  }

  /**
   * Sanitize the value to be an Regexp.
   */
  static function sanitizeRegexp($value) {
    if ( !preg_match('/^\/.+\/g?i?$/i', $value) ) {
      $value = '/' . addslashes($value) .'/';
    }

    return $value;
  }

  /**
   * Try parsing the value as XML string.
   *
   * @returns TRUE on success, FALSE otherwise.
   */
  static function sanitizeXML($value) {
    libxml_use_internal_errors(true);

    $doc = new DOMDocument('1.0', 'utf-8');
    $doc->loadXML($xml);

    $errors = libxml_get_errors();

    // Allow error levels 1 and 2
    if ( empty($errors) || $errors[0]->level < 3 ) {
      return $value;
    }

    return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
  }

  /**
   * Try sanitizing the value as date.
   *
   * A date of zero timestamp will be returned on invalid.
   */
  static function sanitizeDate($value, $format = '%Y-%m-%d') {
    if ( strptime($value, $format) === FALSE ) {
      return strftime($format, 0);
    }
    else {
      return $value;
    }
  }

  /**
   * Expanding 2 digit year to 4 digits.
   *
   * FALSE will be returned when parse failure.
   */
  static function sanitizeYear($input) {
    if ( !is_int($input-0) ) {
      return FALSE;
    }

    $strlen = strlen("$input");

    if ( $strlen === 2 ) {
      $time = strptime($input, '%y');

      return $time['tm_year'] + 1900;
    }

    else if ( $strlen === 4 ) {
      $time = strptime($input, '%Y');

      return $time['tm_year'] + 1900;
    }

    return FALSE;
  }

  /**
   * Returns an array of the same length of $input and all it's elements set to $value.
   *
   * Optionally passing $glue will implode the created array with it.
   *
   * @param $input Source array to be counted.
   * @param $value Any PHP value to be filled to the new array.
   * @param $glue (Optional) Cusotmize implode() behavior of the result array, specify FALSE to skip this action and return an array instead.
   *
   * @returns Array of the same length of $input filled with $value,
   *          or an imploded string of the resulting array.
   */
  static function fillArray($input, $value = '?', $glue = ',') {
    $result = array_fill(0, count($input), $value);

    if ( $glue !== FALSE ) {
      $result = implode($glue, $result);
    }

    return $result;
  }

  /**
   * Call a WebService internally.
   *
   * @param {string} $service Name of the service.
   * @param {string} $method Name of the service method.
   * @param {array} $parameters Optional, array of parameters passed to the methdod.
   *
   * @return Whatever the method returns, or FALSE in case of method not exists.
   */
  static function callService($service, $method, $parameters = array()) {
    return \service::call($service, $method, $parameters);
  }

  /**
   * Fix weird array format in _FILES.
   */
  static function filesFix() {
    if ( @$_FILES ) {
      foreach ($_FILES as &$file){
        $output = array();

        foreach ($file as $fileKey => &$input) {
          $recursor = function($input, &$output) use(&$fileKey, &$recursor) {
            if ( is_array($input) ) {
              foreach ( $input as $key => $value ) {
                $recursor($value, $output[$key]);
              }
            }
            else {
              $output[$fileKey] = $input;
            }
          };

          $recursor($input, $output);
        }

        $file = $output;
      }
    }
  }

  /**
   * Fix default namespace bug of SimpleXML.
   */
  static function namespaceFix(&$xml) {
    $ns = $xml->getDocNamespaces();

    if ( isset($ns['']) ) {
      $xml->registerXPathNamespace('_', $ns['']);
    }
  }

  /**
   * Get file info with the finfo class.
   */
  static function getInfo($file, $options = NULL) {
    $finfo = new \finfo($options);

    return $finfo->file($file);
  }

  /**
   * Generate a alphabetically sequencial HTML compatible ID.
   */
  static function generateHtmlId($prefix = ':') {
    static $idSeq = 0;

    $id = $idSeq++;

    return $prefix . \sprintf('%c%c', floor($id / 25) + 97, $id % 25 + 97);
  }
}