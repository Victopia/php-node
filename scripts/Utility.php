<?php

class Utility
{
	/**
	 *  Determine whether an array is associative.
	 *
	 *  To determine a numeric array, inverse the result of this function.
	 */
	static function isAssociative($value)
	{
		return Is_Array($value) && (
			0 === count($value) || 
			0 !== count(Array_Diff_Key($value, Array_Keys(Array_Keys($value))))
			);
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
	static function sanitizeRegexp($value)
	{
		if (!preg_match('/^\/.+\/g?i?$/i', $value))
		{
			$value = '/' . addslashes($value) .'/';
		}
		
		return $value;
	}
	
	/**
	 *  Try parsing the value as XML string.
	 *
	 *  @returns TRUE on success, FALSE otherwise.
	 */
	static function sanitizeXML($value)
	{
		libxml_use_internal_errors(true);

		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->loadXML($xml);

		$errors = libxml_get_errors();
		if (empty($errors))
		{
			return $value;
		}

		$error = $errors[ 0 ];
		if ($error->level < 3)
		{
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
	 *  Sanitize an array of rules, and removes invalid elements.
	 *
	 *  This function uses TRUE as the subject of the comparison string.
	 *
	 *  @param $rules An array of rules or a single rule.
	 *
	 *  @returns Array of rules with valid PHP code.
	 */
	static function sanitizeRules($rules)
	{
		if (self::isAssociative($rules))
		{
			$rules = Array($rules);
		}
		
		foreach ($rules as $index => $rule)
		{
			if (!isset($rule['Rule_ID']) || 
				!isset($rule['condition']) || 
				@eval("TRUE $rule[condition]") === FALSE)
			{
				unset($rules[$index]);
			}
		}
		
		return $rules;
	}
	
	/**
	 * Fix weird array format in _FILES.
	 */
	static function filesFix()
	{
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
}