<?php
/* Utility.php | https://github.com/victopia/php-node */

namespace core;

use framework\Configuration;
use framework\MustacheResource;

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
   * Shorthand access to common filter types.
   */
  static function &commonFilters() {
    static $filters;

    if ( !$filters ) {
      $filters = array(
        'raw' => array(
            'filter' => FILTER_UNSAFE_RAW
          , 'flags' => FILTER_NULL_ON_FAILURE
          )
      , 'rawS' => array(
            'filter' => FILTER_UNSAFE_RAW
          , 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE
          )
      , 'rawA' => array(
            'filter' => FILTER_UNSAFE_RAW
          , 'flags' => FILTER_FORCE_ARRAY | FILTER_NULL_ON_FAILURE
          )
      , 'boolS' => array(
          'filter' => FILTER_VALIDATE_BOOLEAN
        , 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE
        )
      , 'intS' => array(
          'filter' => FILTER_VALIDATE_INT
        , 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE
        )
      , 'intA' => array(
          'filter' => FILTER_VALIDATE_INT
        , 'flags' => FILTER_FORCE_ARRAY | FILTER_NULL_ON_FAILURE
        )
      , 'floatS' => array(
          'filter' => FILTER_VALIDATE_FLOAT
        , 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE
        )
      , 'floatA' => array(
          'filter' => FILTER_VALIDATE_FLOAT
        , 'flags' => FILTER_FORCE_ARRAY | FILTER_NULL_ON_FAILURE
        )
      , 'strS' => array(
          'filter' => FILTER_SANITIZE_STRING
        , 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE | FILTER_FLAG_NO_ENCODE_QUOTES
        )
      , 'strA' => array(
          'filter' => FILTER_SANITIZE_STRING
        , 'flags' => FILTER_FORCE_ARRAY | FILTER_NULL_ON_FAILURE | FILTER_FLAG_NO_ENCODE_QUOTES
        )
      , 'urlS' => array(
          'filter' => FILTER_VALIDATE_URL
        , 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE
        )
      , 'urlA' => array(
          'filter' => FILTER_VALIDATE_URL
        , 'flags' => FILTER_FORCE_ARRAY | FILTER_NULL_ON_FAILURE
        )
      , 'date' => array(
          'filter' => FILTER_CALLBACK
        , 'flags' => FILTER_NULL_ON_FAILURE
        , 'options' => '\core\Utility::validateDateTime'
        )
      , 'dateS' => array(
          'filter' => FILTER_CALLBACK
        , 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE
        , 'options' => '\core\Utility::validateDateTime'
        )
      , 'priceS' => array(
          'filter' => FILTER_CALLBACK
        , 'options' => function($input) {
            return preg_match('/[+-]?\d+(?:\.\d+)?(?:\:\w+)?/', trim(Utility::unwrapAssoc($input))) ? $input : null;
          }
        )
      , 'regex' => function($pattern) {
          return array(
              'filter' => FILTER_CALLBACK
            , 'options' => function($input) use($pattern) {
                return preg_match($pattern, $input) ? $input : null;
              }
            );
        }
      );
    }

    return $filters;
  }

  /**
   * Returns whether the current process is in CLI environment.
   */
  static function isCLI() {
    return php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']);
  }

  /**
   * Gets all network interfaces with an appropriate IPv4 address.
   *
   * Mimic the output of os.networkInterfaces() in node.js.
   */
  static function networkInterfaces() {
    switch ( strtoupper(PHP_OS) ) {
      case 'DARWIN': // MAC OS X
        $res = preg_split('/\n/', @`ifconfig`);
        $res = array_filter(array_map('trim', $res));

        $result = array();

        foreach ( $res as $row ) {
          if ( preg_match('/^(\w+\d+)\:\s+(.+)/', $row, $matches) ) {
            $result['__currentInterface'] = $matches[1];

            $result[$result['__currentInterface']]['__internal'] = false !== strpos($matches[2], 'LOOPBACK');
          }
          else if ( preg_match('/^inet(6)?\s+([^\/\s]+)(?:%.+)?/', $row, $matches) ) {
            $iface = &$result[$result['__currentInterface']];

            @$iface[] = array(
                'address' => $matches[2]
              , 'family' => $matches[1] ? 'IPv6' : 'IPv4'
              , 'internal' => $iface['__internal']
              );

            unset($iface);
          }

          unset($matches);
        } unset($row, $res);

        unset($result['__currentInterface']);

        return array_filter(array_map(compose('array_filter', removes('__internal')), $result));

      case 'LINUX':
        // $ifaces = `ifconfig -a | sed 's/[ \t].*//;/^\(lo\|\)$/d'`;
        // $ifaces = preg_split('/\s+/', $ifaces);
        $res = preg_split('/\n/', @`ip addr`);
        $res = array_filter(array_map('trim', $res));

        $result = array();

        foreach ( $res as $row ) {
          if ( preg_match('/^\d+\:\s+(\w+)/', $row, $matches) ) {
            $result['__currentInterface'] = $matches[1];
          }
          else if ( preg_match('/^link\/(\w+)/', $row, $matches) ) {
            $result[$result['__currentInterface']]['__internal'] = strtolower($matches[1]) == 'loopback';
          }
          else if ( preg_match('/^inet(6)?\s+([^\/]+)(?:\/\d+)?.+\s([\w\d]+)(?:\:\d+)?$/', $row, $matches) ) {
            @$result[$matches[3]][] = array(
                'address' => $matches[2]
              , 'family' => $matches[1] ? 'IPv6' : 'IPv4'
              , 'internal' => Utility::cascade(@$result[$matches[3]]['__internal'], false)
              );
          }

          unset($matches);
        } unset($row, $res);

        unset($result['__currentInterface']);

        return array_filter(array_map(compose('array_filter', removes('__internal')), $result));

      case 'WINNT': // Currently not supported.
      default:
        return array();
    }
  }

  /**
   * Returns whether current request is made by local redirect.
   */
  static function isLocalRedirect() {
    return @$_SERVER['HTTP_REFERER'] == FRAMEWORK_SERVICE_HOSTNAME_LOCAL &&
      @$_SERVER['HTTP_HOST'] == FRAMEWORK_SERVICE_HOSTNAME &&
      @$_SERVER['HTTP_USER_AGENT'] == 'X-PHP';
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
      return false;
    }

    for ( $i=0; $i<5; $i-- ) {
      if ( fgetcsv($file) ) {
        fclose($file);
        return true;
      }
    }

    fclose($file);
    return false;
  }

  /**
   * Determine whether the given string is a well formed URL.
   */
  static function isURL($value) {
    // SCHEME
    $urlregex = '^(?:(?:file|https?|ftps?|php|zlib|data|glob|phar|ssh2|ogg|expect)\:)?\/\/\/?';

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
    $urlregex .= '(\/([a-z0-9%+$ _\-\=~\!\(\),]\.?)+)*\/?';
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
      return null;
    }

    $n = count($args);

    for ( $i=1; $i<$n; $i++ ) {
      foreach ( $args[$i] as $objKey => &$objValue ) {
        foreach ($subject as $subKey => &$subValue) {
          if ( strcasecmp($subKey, $objKey) === 0 ) {
            $subject[$subKey] = $objValue;
            $objValue = null;
            break;
          }
        }

        if ( $objValue !== null ) {
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
    return self::arraySearchIgnoreCase($needle, $haystack) !== false;
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
   * Case-insensitive version of array_search over keys.
   */
  static function arrayKeySearchIgnoreCase($key, $search) {
    $keys = array_keys($search);

    $index = array_search(strtolower($key), array_map('strtolower', $keys));

    if ( $index !== false ) {
      $index = $keys[$index];
    }

    return $index;
  }

  /**
   * Case-insensitive version of array_key_exists.
   */
  static function arrayKeyExistsIgnoreCase($key, $search) {
    return self::arrayKeySearchIgnoreCase($key, $search) !== false;
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
  static function flattenArray(&$input, $delimiter = '.', $flattenNumeric = true) {
    if ( !is_array($input) ) {
      return $input;
    }

    // Could have a single layer solution,
    // can't think of it at the moment so leave it be.
    if ( Utility::isAssoc($input) || $flattenNumeric ) {
      foreach ($input as $key1 => $value) {
        $value = self::flattenArray($value, $delimiter, $flattenNumeric);

        if ( is_array($value) && ($flattenNumeric || Utility::isAssoc($value)) ) {
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

        while ( $keyPath ) {
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
   * Return the first value that is not interpreted as false.
   *
   * This is made for those lazy shits like me, who does
   * (foo || bar || baz) a lot in Javascript.
   *
   * @param {array} $list Array of values to cascade, or it will func_get_args().
   */
  static function cascade($list) {
    if ( func_num_args() > 1 ) {
      $list = func_get_args();
    }

    while ( $list && !($arg = array_shift($list)) );

    return @$arg; // return null when empty array is given.
  }

  /**
   * Create deep array path if any intermediate property does not exists.
   */
  static function &deepRef($path, &$input) {
    if ( !is_array($path) ) {
      $path = explode('.', $path);
    }

    $ref = &$input;

    while ( $path ) {
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
  static function forceInvoke($callable, $parameters = null) {
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
    if ( is_string($callable) && strpos($callable, '::') !== false ) {
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
   * Returns all readable data from a unix socket.
   */
  static function readUnixSocket($address) {
    $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

    if ( !socket_connect($socket, $address) ) {
      return false;
    }

    $result = '';

    while ( true ) {
      $data = @socket_read($socket, 4096);

      if ( false === $data ) {
        return false;
      }
      else if ( !$data ) {
        break;
      }

      $result.= $data;
    }

    socket_close($socket);

    return $result;
  }

  /**
   * This function is very much like the date() function,
   * except it also takes a string which originally needs
   * an extra call to strtotime().
   */
  public static /* string */
  function formatDate($pattern, $date) {
    if ( !is_numeric($date) && $date ) {
      return call_user_func(array(__CLASS__, __FUNCTION__), $pattern, strtotime($date));
    }

    if ( !$date || $date == -1 ) {
      return false;
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
  public static function composeDateString($target, $since = null) {
    if ( $since === null ) {
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
   * @returns true on success, false otherwise.
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
    if ( strptime($value, $format) === false ) {
      return strftime($format, 0);
    }
    else {
      return $value;
    }
  }

  /**
   * Validate if specified input is a parsable date.
   */
  static function validateDateTime($value) {
    return strtotime($value) === false ? false : $value;
  }

  /**
   * Expanding 2 digit year to 4 digits.
   *
   * false will be returned when parse failure.
   */
  static function sanitizeYear($input) {
    if ( !is_int($input-0) ) {
      return false;
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

    return false;
  }

  /**
   * Returns an array of the same length of $input and all it's elements set to $value.
   *
   * Optionally passing $glue will implode the created array with it.
   *
   * @param $input Source array to be counted.
   * @param $value Any PHP value to be filled to the new array.
   * @param $glue (Optional) Cusotmize implode() behavior of the result array, specify false to skip this action and return an array instead.
   *
   * @returns Array of the same length of $input filled with $value,
   *          or an imploded string of the resulting array.
   */
  static function fillArray($input, $value = '?', $glue = ',') {
    $result = array_fill(0, count($input), $value);

    if ( $glue !== false ) {
      $result = implode($glue, $result);
    }

    return $result;
  }

  /**
   * @deprecated
   *
   * Call a WebService internally.
   *
   * @param {string} $service Name of the service.
   * @param {string} $method Name of the service method.
   * @param {array} $parameters Optional, array of parameters passed to the methdod.
   *
   * @return Whatever the method returns, or false in case of method not exists.
   */
  static function callService($service, $method, $parameters = array()) {
    triggerDeprecate('framework\Service::call');

    return \service::call($service, $method, $parameters);
  }

  /**
   * Fix weird array format in _FILES.
   */
  static function filesFix() {
    if ( @$_FILES ) {
      foreach ( $_FILES as &$file ) {
        $output = array();

        foreach ( $file as $fileKey => &$input ) {
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
  static function getInfo($file, $options = null) {
    $finfo = new \finfo($options);

    return $finfo->file($file);
  }

  /**
   * Get offset data from request headers.
   *
   * This HTTP header is a simplified version of the HTTP Range header.
   * List-Range: (\d+)?(-\d+)?
   *
   * Unlike the Range header, only one range is allowed in this header.
   */
  static function getListRange($defaultLength = 20) {
    $range = Request::headers('List-Range');

    if ( !preg_match('/^(\d*)?(?:-(\d*|\*))?$/', trim($range), $matches) ) {
      return null;
    }

    if ( empty($matches[2]) ) {
      list($matches[1], $matches[2]) = array(0, $matches[1]);
    }

    if ( @$matches[2] == '*' ) {
      $matches[2] = $defaultLength;
    }

    if ( $matches[2] == 0 ) {
      return null;
    }

    return array((int) $matches[1], (int) $matches[2]);
  }

  /**
   * Get a MustacheResource object in the context with current server configurations.
   */
  static function getResourceContext($locale = null) {
    static $resource;

    if ( $resource ) {
      return $resource;
    }

    $localeChain = Configuration::get('core.i18n::localeChain');

    if ( !$localeChain ) {
      $localeChain = ['en_US'];
    }

    if ( $locale ) {
      array_unshift($localeChain, $locale);

      $localeChain = array_unique($localeChain);
    }

    $resource = new MustacheResource($localeChain);

    return $resource;
  }

  /**
   * Generate a alphabetically sequencial HTML compatible ID.
   */
  static function generateHtmlId($prefix = ':') {
    static $idSeq = 0;

/*
  0-25 = 97-122 (a-z)
  26-51 = 65-90 (A-Z)
  52 = aa
  53 = ab
  54 = ac
*/

    $id = $idSeq++;

    $res = '';

    while ( $id >= 0 ) {
      $chr = $id % 52;

      if ( $chr < 26 ) {
        $chr += 97;
      }
      else {
        $chr += 65 - 25;
      }

      $res = chr($chr) . $res;

      $id = floor($id / 51);

      if ( $id <= 0 ) {
        break;
      }

      unset($chr);
    }

    return $prefix . $res;
  }
}
