<?php
/*! framework\functions.php | Implements functional programming. */

//--------------------------------------------------
//
//  Functional functions
//
//--------------------------------------------------

function compose() {
	$funcs = func_get_args();

  return function() use($funcs) {
    $args = func_get_args();

    for ($i = count($funcs); isset($funcs[--$i]);) {
      $args = array(call_user_func_array($funcs[$i], $args));
    }

    return $args[0];
  };
}

function partial() {
	$part = func_get_args();
	$func = array_shift($part);

	return function() use($func, $part) {
		$args = array_merge($part, func_get_args());

		return call_user_func($func, $args);
	};
}

function prop($name) {
	return function ($object) use($name) {
		return @$object[$name];
	};
}

function propEquals($prop, $value, $strict = FALSE) {
	return propIn($prop, (array) $value, $strict);
}

function propIn($prop, array $values, $strict = FALSE) {
	return function($object) use($prop, $values, $strict) {
		return in_array($object[$prop], $values, $strict);
	};
}

function func($name, array $args = array()) {
	return function ($object) use($name, $args) {
		return call_user_func(array($object, $name), $args);
	};
}

function funcEquals($name, $value, array $args = array(), $strict = FALSE) {
	return funcIn($name, (array) $value, $args, $strict);
}

function funcIn($name, array $values, array $args = array(), $strict = FALSE) {
	return function($object) use($name, $values, $args, $strict) {
		$ret = call_user_func_array($object, $name, $args);

		return in_array($ret, $values, $strict);
	};
}

function remove($name) {
	// This enables these two patterns:
	// 1. remove($field1, $field2)
	// 2. remove(array($field1, $field2))
	if (!is_array($name)) {
		$name = func_get_args();
	}

	return function($object) use($name) {
		$name = (array) $name;
		array_walk($name, function($name) use(&$object) { unset($object[$name]); });
		return $object;
	};
}

//--------------------------------------------------
//
//  String ops
//
//--------------------------------------------------

function prepend($prefix) {
	return function($object) use($prefix) {
		return "$prefix$object";
	};
}

function append($suffix) {
	return function($object) use($suffix) {
		return "$object$suffix";
	};
}

//--------------------------------------------------
//
//  Array ops
//
//--------------------------------------------------

/**
 * Factory of functions that wrap provided $item with an array under property $name.
 */
function wrap($name) {
	return function ($item) {
		return array($name => $item);
	};
}

/**
 * Transform hash arrays into a numeric array in [key, value] pairs.
 */
function pairs($list) {
	$result = array();

	foreach ($list as $key => $value) {
		$result[] = array($key, $value);
	}

	return $result;
}

/**
 * Transform key-value pairs into hash arrays.
 */
function object($list) {
	$result = array();

	foreach ($list as $value) {
		$result[$value[0]] = @$value[1];
	}

	return $result;
}

//--------------------------------------------------
//
//  shim
//
//--------------------------------------------------

if (!function_exists('array_select')) {
	function array_select($list, $keys) {
		$result = array();

		foreach ($list as $key => &$value) {
			if (in_array($key, $keys, TRUE)) {
				$result[$key] = &$value;
			}

			// Remove the copied value to save memory.
			unset($list[$key]);
		}

		return $result;
	}
}

if (!function_exists('array_filter_keys')) {
	function array_filter_keys($list, $func) {
		return array_select($list, array_filter(array_keys($list), $func));
	}
}

//--------------------------------------------------
//
//  partials
//
//--------------------------------------------------

function mapdef($func, $list) {
	return array_map($func, array_filter($list));
}

function seldef($func, $list) {
	array_select($func, array_filter($list));
}

//--------------------------------------------------
//
//  Directory
//
//--------------------------------------------------

function isdir() {
	return function($file) {
		return is_dir($file);
	};
}

function isfile() {
	return function($file) {
		return is_file($file);
	};
}

function isexecutable() {
	return function($file) {
		return is_executable($file);
	};
}