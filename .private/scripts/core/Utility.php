<?php

namespace core;

class Utility {
  /**
   * Returns whether the current process is in CLI environment.
   */
  static function isCLI() {
    return php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']);
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
        // $ifaces = `ifconfig -a | sed 's/[ \t].*//;/^\(lo\|\)$/d'`;
        // $ifaces = preg_split('/\s+/', $ifaces);

        $ifaces = array('en0', 'en1', 'eth0', 'eth1', 'lo');
        return $ifaces;

      case 'WINNT': // Currently not supported.
      default:
        return array();
    }
  }

  /**
   * Parse the command line args into a single array.
   *
   * Logic port from node-optimist, read the docs there
   * https://github.com/substack/node-optimist.
   */
  static function parseOptions($alias = array()) {
    global $argv;

    $args = $argv;

    // Remove until the script itself.
    if (@$_SERVER['PHP_SELF']) {
      while ($args && array_shift($args) != $_SERVER['PHP_SELF']);
    }

    $result = array();

    // Parsing logics:
    // 1. When plain string is placed after option name, place under that name. i.e. --site 0
    // 2. When plain string alone, place under underscore '_'
    // 3. When option name alone, set to TRUE.

    $currentNode = NULL;

    array_walk($args, function($value) use(&$alias, &$result, &$currentNode) {
      if (preg_match('/^\-\-?(\w+)(\[\])?(?:=(.*))?$/', $value, $matches)) {
        $currentNode = $matches[1];

        if (isset($alias[$currentNode])) {
          $currentNode = $alias[$currentNode];
        }

        $target = &$result[$currentNode];
        $value = @$matches[3] !== NULL ? $matches[3] : TRUE;

        if (isset($matches[2])) {
          @$target[] = $value;
        }
        else {
          $target = $value;
        }
      }
      elseif (@$currentNode) {
        $target = &$result[$currentNode];

        if (is_array($target)) {
          // Override last TRUE
          if (end($target) === TRUE) {
            $target = &$target[key($target)];
          }
          else {
            $target = &$target[count($target)];
          }

          $target = $value;
        }
        else {
          $target = $value;

          $currentNode = NULL;
        }
      }
      else {
        @$result['_'][] = $value;
      }
    });

    return $result;
  }

  /**
   * Returns whether current request is made by local redirect.
   */
  static function isLocalRedirect() {
    return @$_SERVER['HTTP_REFERER'] == gethostname();
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

    if (count($backtrace) <= $level) {
      $level = count($backtrace) - 1;
    }

    /* Added by Eric @ 23 Dec, 2012
        This script should:
        1. try its best to search until there is a file, and
        2. stop before framework scripts are reached.
    */
    if (!@$backtrace[$level]['file']) {
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

    if ($level < 0) {
      $level = 0;
    }

    return @$backtrace[$level];
  }

  /**
   * Check whether specified file is somehow in CSV format.
   */
  static function isCSV($file) {
    $file = fopen($file, 'r');

    if (!$file) {
      return FALSE;
    }

    for ($i=0; $i<5; $i--) {
      if (fgetcsv($file)) {
        fclose($file);
        return TRUE;
      }
    }

    fclose($file);
    return FALSE;
  }

  /**
   *  Determine whether the given string is a well formed URL.
   */
  static function isURL($value) {
    // SCHEME
    $urlregex = "^(file|https?|ftps?|php|zlib|data|glob|phar|ssh2|ogg|expect)\:\/\/";

    // USER AND PASS (optional)
    $urlregex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?";

    // HOSTNAME OR IP
    $urlregex .= "[a-z0-9+\$_-]+(\.[a-z0-9+\$_-]+)*"; // http://x = allowed (ex. http://localhost, http://routerlogin)
    //$urlregex .= "[a-z0-9+\$_-]+(\.[a-z0-9+\$_-]+)+"; // http://x.x = minimum
    //$urlregex .= "([a-z0-9+\$_-]+\.)*[a-z0-9+\$_-]{2,3}"; // http://x.xx(x) = minimum
    //use only one of the above

    // PORT (optional)
    $urlregex .= "(\:[0-9]{2,5})?";
    // PATH (optional)
    $urlregex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?";
    // GET Query (optional)
    $urlregex .= "(\?[a-z+&\$_.-][a-z0-9;:@/&%=+\$_.-]*)?";
    // ANCHOR (optional)
    $urlregex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?\$";

    // check
    return (bool) @eregi($urlregex, $value);
  }

  /**
   *  Determine whether an array is associative.
   *
   *  To determine a numeric array, inverse the result of this function.
   */
  static function isAssoc($value) {
    /*
    return is_array($value) && count($value) &&
      count(array_diff_key($value, array_keys(array_keys($value))));
    */

    return is_array($value) && $value &&
      // ALL keys must be numeric to qualify as NOT assoc.
      count(array_filter(array_keys($value), 'is_numeric')) != count($value);
  }

  /**
   *  Case-insensitive version of array_merge.
   *
   *  Character case of the orginal array is preserved.
   */
  static function arrayMergeIgnoreCase(&$subject) {
    $args = func_get_args();

    if (count($args) == 0) {
      return NULL;
    }

    $n = count($args);
    for ( $i=1; $i<$n; $i++ ) {
      foreach ( $args[$i] as $objKey => &$objValue ) {
        foreach ($subject as $subKey => &$subValue) {
          if (strcasecmp($subKey, $objKey) === 0) {
            $subject[$subKey] = $objValue;
            $objValue = NULL;
            break;
          }
        }

        if ($objValue !== NULL) {
          $subject[$objKey] = $objValue;
        }
      }
    }

    return $subject;
  }

  /**
   * Case-insensitve version of in_array.
   */
  static function inArrayIgnoreCase(&$needle, &$haystack) {
    return self::arraySearchIgnoreCase($needle, $haystack) !== FALSE;
  }

  /**
   * Case-insensitive version of array_search.
   */
  static function arraySearchIgnoreCase($needle, &$haystack) {
    $keys = array_map('strtolower', array_keys($haystack));

    return array_search(strtolower($needle), $keys);
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
    if (\utils::isAssoc($input) || $flattenNumeric) {
      foreach ($input as $key1 => $value) {
        $value = self::flattenArray($value, $delimiter, $flattenNumeric);

        if (is_array($value) && ($flattenNumeric || \utils::isAssoc($value))) {
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
    if (!is_array($input)) {
      return $input;
    }

    $result = $input;

    if (self::isAssoc($input)) {
      foreach ($input as $key => $value) {
        $keyPath = explode($delimiter, $key);

        // Skip if it is just the same.
        if ($keyPath == (array) $key) {
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
    if ($list && is_array($list) && !self::isAssoc($list)) {
      return reset($list);
    }

    return $list;
  }

  /**
   * Return the first value that is not interpreted as FALSE.
   *
   * @param $list Array for this, or it will func_get_args().
   */
  static function cascade($list) {
    if (!is_array($list)) {
      $list = func_get_args();
    }

    while ($list && !($arg = array_shift($list)));

    return @$arg; // return NULL when empty array is given.
  }

  /**
   * Create deep array path if any intermediate property does not exists.
   */
  static function deepCreate($path, $input) {
    $ref = &$input;

    foreach ($path as $property) {
      if (!isset($input[$property])) {
        $input[$property] = array();
      }

      $input = &$input[$property];
    }

    return $ref;
  }

  static function forceInvoke($callable, $parameters = NULL) {
    $parameters = (array) $parameters;

    // Normal callable
    if (is_callable($callable)) {
      return call_user_func_array($callable, $parameters);
    }

    // Direct invoke ReflectionFunction
    if ($callable instanceof \ReflectionFunction) {
      return $callable->invokeArgs($parameters);
    }

    // Not callable but is an array, cast it to ReflectionMethod.
    if (is_array($callable)) {
      $method = new \ReflectionMethod($callable[0], $callable[1]);
      $method->setAccessible(true);
      $method->instance = $callable[0];

      $callable = $method;

      unset($method);
    }

    if ($callable instanceof \ReflectionMethod) {
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
    if (!is_numeric($date) && $date) {
      return call_user_func(array(__CLASS__, __FUNCTION__), $pattern, strtotime($date));
    }

    if (!$date || $date == -1) {
      return FALSE;
    }

    return date($pattern, $date);
  }

  /* Added and Quoted by Eric @ 17 Feb, 2013


  /**
   * Make a "in ... (time unit)" string, compared between the input times.
   *
   * @param (uint) $target The target time to be compared against.
   * @param (uint) $since Optional, the relative start time to compare. Defaults to current time.
  public static function composeDateString($target, $since = NULL) {
    if ($since === NULL) {
      $since = time();
    }

    // Already past
    if ($target > $since) {
      return 'expired';
    }

    if ($target > strtotime('+1 year', $since)) {
      $target = $target - $since + EPOACH;
      $target = intval( date('Y', $target) );

      return "in $target " . ($target > 1 ? 'years' : 'year');
    }

    if ($target > strtotime('+1 month', $since)) {
      $target = $target - $since + EPOACH;
      $target = intval( date('n', $target) );

      return "in $target " . ($target > 1 ? 'months' : 'month');
    }

    if ($target > strtotime('+1 week', $since)) {
      $target = $target - $since + EPOACH;
      $target = intval( date('N', $target) );

      return "in $target " . ($target > 1 ? 'weeks' : 'week');
    }

    // return "warn" in 3 days
    if ($target < strtotime('+3 days', $since)) {
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
    if (!preg_match('/^\/.+\/g?i?$/i', $value)) {
      $value = '/' . addslashes($value) .'/';
    }

    return $value;
  }

  /**
   *  Try parsing the value as XML string.
   *
   *  @returns TRUE on success, FALSE otherwise.
   */
  static function sanitizeXML($value) {
    libxml_use_internal_errors(true);

    $doc = new DOMDocument('1.0', 'utf-8');
    $doc->loadXML($xml);

    $errors = libxml_get_errors();

    // Allow error levels 1 and 2
    if (empty($errors) || $errors[0]->level < 3) {
      return $value;
    }

    return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
  }

  /**
   *  Try sanitizing the value as date.
   *
   *  A date of zero timestamp will be returned on invalid.
   */
  static function sanitizeDate($value, $format = '%Y-%m-%d')
  {
    if (strptime($value, $format) === FALSE)
    {
      return strftime($format, 0);
    }
    else
    {
      return $value;
    }
  }

  /**
   *  Expanding 2 digit year to 4 digits.
   *
   *  FALSE will be returned when parse failure.
   */
  static function sanitizeYear($input) {
    if (!is_int($input-0)) {
      return FALSE;
    }

    $strlen = strlen("$input");

    if ($strlen === 2) {
      $time = strptime($input, '%y');

      return $time['tm_year'] + 1900;
    }

    elseif ($strlen === 4) {
      $time = strptime($input, '%Y');

      return $time['tm_year'] + 1900;
    }

    return FALSE;
  }

  /**
   *  Returns an array of the same length of $input and all it's elements set to $value.
   *
   *  Optionally passing $glue will implode the created array with it.
   *
   *  @param $input Source array to be counted.
   *  @param $value Any PHP value to be filled to the new array.
   *  @param $glue (Optional) Cusotmize implode() behavior of the result array, specify FALSE to skip this action and return an array instead.
   *
   *  @returns Array of the same length of $input filled with $value,
   *           or an imploded string of the resulting array.
   */
  static function fillArray($input, $value = '?', $glue = ',') {
    $result = array_fill(0, count($input), $value);

    if ($glue !== FALSE) {
      $result = implode($glue, $result);
    }

    return $result;
  }

  /**
   * Call a WebService internally.
   *
   * @param $service Name of the service.
   * @param $method Name of the service method.
   * @param $parameters Array of parameters passed to the methdod.
   *
   * @return Whatever the method returns, or FALSE in case of method not exists.
   */
  static function callService($service, $method, $parameters = Array()) {
    return \service::call($service, $method, $parameters);
  }

  /**
   * Fix weird array format in _FILES.
   */
  static function filesFix() {
    if ( isset($_FILES) ) {
      foreach ($_FILES as $key => &$file) {
        if ( is_array($file['name']) ) {
          $result = Array();

          foreach ($file['name'] as $index => $name) {
            $result[$index] = Array(
              'name' => $name,
              'type' => $file['type'][$index],
              'tmp_name' => $file['tmp_name'][$index],
              'error' => $file['error'][$index],
              'size' => $file['size'][$index]
            );
          }

          $file = $result;
        }
      }
    }
  }

  /**
   * Fix default namespace bug of SimpleXML.
   */
  static function namespaceFix(&$xml) {
    $ns = $xml->getDocNamespaces();

    if (isset($ns[''])) {
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