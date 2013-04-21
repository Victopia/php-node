<?php
/*! framework\functions.php | Implements functional programming. */

/* Note by Eric @ 10 Dec, 2012

    Follow a simple rule: Use verbs as names for function factories
    (functions that return functions).

    e.g. remove($name, $object) removes the property right away, while
         removes($name) will return a closure that takes $object as
         the only argument, and then invokes remove($name, $object)
         with the $name provided.

*/

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

function funcAnd($inputA, $inputB) {
  return function($input) use($inputA, $inputB) {
    if (is_callable($inputA)) {
      $inputA = $inputA($input);
    }

    if (is_callable($inputB)) {
      $inputB = $inputB($input);
    }

    return $inputA && $inputB;
  };
};

function funcOr($inputA, $inputB) {
  return function($input) use($inputA, $inputB) {
    if (is_callable($inputA)) {
      $inputA = $inputA($input);
    }

    if (is_callable($inputB)) {
      $inputB = $inputB($input);
    }

    return $inputA || $inputB;
  };
};

function not($value) {
  return !$value;
}

function is($value, $strict = FALSE) {
  if ($strict) {
    return function($input) use($value) {
      return $input === $value;
    };
  }
  else {
    return function($input) use($value) {
      return $input == $value;
    };
  }
}

function isNot($value, $strict = FALSE) {
  return compose('not', is($value, $strict));
}

function prop($name) {
	return function ($object) use($name) {
		return @$object[$name];
	};
}

function propIs($prop, $value, $strict = FALSE) {
	return propIn($prop, (array) $value, $strict);
}

function propIsNot($prop, $value, $strict = FALSE) {
  return compose('not', propIs($prop, $value, $strict));
}

function propIn($prop, array $values, $strict = FALSE) {
	return function($object) use($prop, $values, $strict) {
		return in_array($object[$prop], $values, $strict);
	};
}

function propInNot($prop, array $values, $strict = FALSE) {
  return compose('not', propIn($prop, $values, $strict));
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

function remove($names, &$object) {
  $names = (array) $names;

  foreach ($names as $name) {
    unset($object[$name]);
  }
}

/**
 * This function allows these two patterns:
 * 1. remove($field1, $field2)
 * 2. remove(array($field1, $field2))
 */
function removes($name) {
	if (!is_array($name)) {
		$name = func_get_args();
	}

	return function($object) use($name) {
	  remove($name, $object);
	  return $object;
	};
}

//--------------------------------------------------
//
//  Comparer
//
//--------------------------------------------------

function sortsAscend($subject, $object, $strict = FALSE) {
  if ($strict) {
    if ($subject === $object) {
      return 0;
    }
  }
  else {
    if ($subject == $object) {
      return 0;
    }
  }

  if (is_numeric($subject)) {
    $subject = doubleval($subject);
  }

  if (is_numeric($object)) {
    $object = doubleval($object);
  }

  switch (gettype($subject)) {
    case 'boolean':
      return $subject ? 1 : -1;

    case 'integer':
    case 'double':
      return $subject > $object ? 1 : -1;

    case 'string':
      return $strict ? strcasecmp($subject, $object) : strcmp($subject, $object);

    case 'array':
      return $subject > $object ? 1 : -1;

    case 'object':
      throw new Exception('Object type comparison is not supported.');
      return 0;

    case 'resource':
      throw new Exception('Resource type comparison is not supported.');
      return 0;

    case 'NULL':
    default:
      throw new Exception('NULL or unknown type comparison is not supported.');
      return 0;
  }
}

function sortsDescend($subject, $object, $strict = FALSE) {
  $ret = sortsAscend($subject, $object, $strict);

  if ($ret >= 1) {
    return -1;
  }

  if ($ret <= -1) {
    return 1;
  }

  return 0;
}

function sortsPropAscend($name, $strict = FALSE) {
  return function($subject, $object) use($name, $strict) {
    return sortsAscend(@$subject[$name], @$object[$name], $strict);
  };
}

function sortsPropDescend($name, $strict = FALSE) {
  return function($subject, $object) use($name, $strict) {
    return sortsDescend(@$subject[$name], @$object[$name], $strict);
  };
}

//--------------------------------------------------
//
//  String ops
//
//--------------------------------------------------

function prepend($prefix, $object) {
  return "$prefix$object";
}

function prepends($prefix, $prop = NULL) {
  if ($prop === NULL) {
    return function($object) use($prefix) {
      return prepend($prefix, $object);
    };
  }
  else {
    return function($object) use($prefix, $prop) {
      @$object[$prop] = prepend($prefix, @$object[$prop]);

      return $object;
    };
  }
}

function append($suffix, $object) {
  return "$object$suffix";
}

function appends($suffix, $prop = NULL) {
  if ($prop === NULL) {
  	return function($object) use($suffix) {
  		return append($suffix, $object);
  	};
  }
  else {
    return function($object) use($suffix, $prop) {
      @$object[$prop] = append($suffix, @$object[$prop]);

      return $object;
    };
  }
}

//--------------------------------------------------
//
//  Array ops
//
//--------------------------------------------------

/**
 * Factory of array_filter($list, $filter);
 */
function filters(/* callable */ $filter = NULL) {
  if (is_null($filter)) {
    return function(array $list) {
      return array_filter($list);
    };
  }
  else {
    return function(array $list) use($filter) {
      return array_filter($list, $filter);
    };
  }
}

/**
 * Factory of array_select($list, $keys);
 */
function selects($keys) {
  $keys = (array) $keys;

  return function($list) use($keys) {
    return array_select($list, $keys);
  };
}

/**
 * Factory of array_map($list, $callback);
 */
function maps(/* callable */ $callback) {
  return function($list) use($callback) {
    return array_map($callback, $list);
  };
}

/**
 * Factory of functions that wrap provided $item with an array under property $name.
 */
function wraps($name) {
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
	function array_select($list, array $keys) {
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

if (!function_exists('array_remove')) {
  function array_remove(&$list, $item, $strict = FALSE) {
    $items = (array) $item;

    $hasRemoved = FALSE;

    foreach ($items as $item) {
      while (FALSE !== ($index = array_search($item, $list, $strict))) {
        $hasRemoved = TRUE;

        array_splice($list, $index, 1);
      }
    }

    return $hasRemoved;
  }
}

if (!function_exists('array_mapdef')) {
  function array_mapdef() {
    return call_user_func_array('mapdef', func_get_args());
  }
}

if (!function_exists('array_seldef')) {
  function array_seldef() {
    return call_user_func_array('seldef', func_get_args());
  }
}

if (!function_exists('array_filter_keys')) {
	function array_filter_keys($list, /* callable */ $func) {
		return array_select($list, array_filter(array_keys($list), $func));
	}
}

//--------------------------------------------------
//
//  partials
//
//--------------------------------------------------

function mapdef(/* callable */ $callback, $list, /* callable */ $filter = NULL) {
  $function = compose(
      filters($filter)
    , maps($callback)
    );

  return $function($list);
}

function seldef(array $keys, $list, /* callable */ $filter = NULL) {
  $function = compose(
      filters($filter)
    , selects($keys)
    );

  return $function($list);
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