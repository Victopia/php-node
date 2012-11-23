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
				$ifaces = `ifconfig | expand | cut -c1-8 | sort | uniq -u | awk -F: '{print $1;}'`;
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
	 * Get callee of the current script.
	 */
	static function getCallee($level = 2) {
		$backtrace = debug_backtrace();

		if (count($backtrace) <= $level) {
			$level = count($backtrace) - 1;
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
		return is_array($value) &&
			0 < count($value) &&
			0 !== count(array_diff_key($value, array_keys(array_keys($value))));
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
	 * Wrap an associative array with a new array(), making it iteratable.
	 */
	static function wrapAssoc($item) {
		return self::isAssoc($item) ? array($item) : $item;
	}

	/**
	 * Unwrap an array of primitives, hash-arrays or objects,
	 * and returns the first element.
	 *
	 * Null is returned if input array is empty.
	 */
	static function unwrapAssoc($list) {
		if (is_array($list) && !self::isAssoc($list)) {
			return reset($list);
		}

		return $list;
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
	 * Sanitize the value to be an integer.
	 */
	static function sanitizeInt($value)
	{
		return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
	}

	/**
	 * Sanitize the value to be plain text.
	 */
	static function sanitizeString($value)
	{
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
		if (!is_numeric($input)) {
			return FALSE;
		}

		$strlen = strlen("$input");

		if ($strlen === 2) {
			$time = strptime($input, '%y');

			return $time['tm_year'] + 1900;
		}

		elseif ($strlen === 4) {
			return intval($input);
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
	 * Generate a alphabetically sequencial HTML compatible ID.
	 */
	static function generateHtmlId($prefix = ':') {
		static $idSeq = 0;

		$id = $idSeq++;

		return $prefix . \sprintf('%c%c', floor($id / 25) + 97, $id % 25 + 97);
	}
}