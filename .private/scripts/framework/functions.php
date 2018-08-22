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

/* Note @ 13 Nov, 2013

   Functional iterators is way more complicated with flow control,
   it should be listenable, chainable iteration object instead of
   compose() based, single dimension approach.

   Common options:
   invokes: Invokes when target is callable and this value is true.
   pointer: Reads and writes with references "&" instead of values.

*/

use core\Utility;

//--------------------------------------------------
//
//  Functional functions
//
//--------------------------------------------------

function compose($funcs) {
  if ( func_num_args() > 1 ) {
    $funcs = func_get_args();
  }
  else {
    $funcs = (array) $funcs;
  }

  return function() use($funcs) {
    $args = func_get_args();

    for ( $i = count($funcs); isset($funcs[--$i]); ) {
      $args = array(call_user_func_array($funcs[$i], $args));
    }

    return $args[0];
  };
}

function identity($input) {
  return $input;
}

function partial() {
  return call_user_func_array('unshiftsArg', func_get_args());
}

function pushesArg() {
  $part = func_get_args();
  $func = array_shift($part);

  return function() use($func, $part) {
    $args = array_merge(func_get_args(), $part);

    return call_user_func_array($func, $args);
  };
}

function unshiftsArg() {
  $part = func_get_args();
  $func = array_shift($part);

  return function() use($func, $part) {
    $args = array_merge($part, func_get_args());

    return call_user_func_array($func, $args);
  };
}

function funcAnd() {
  $funcs = func_get_args();
  return function($input) use($funcs) {
    return array_reduce($funcs, function($result, $func) use($input) {
      if ( !$result ) {
        return $result;
      }

      if ( is_callable($func) ) {
        $func = $func($input);
      }

      return $result && $func;
    }, true);
  };
};

function funcOr() {
  $funcs = func_get_args();
  return function($input) use($funcs) {
    return array_reduce($funcs, function($result, $func) use($input) {
      if ( $result ) {
        return $result;
      }

      if ( is_callable($func) ) {
        $func = $func($input);
      }

      return $result || $func;
    }, false);
  };
};

function not($value) {
  return !$value;
}

function is($value, $strict = false) {
  if ( $strict ) {
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

function isNot($value, $strict = false) {
  return compose('not', is($value, $strict));
}

function in($values, $strict = false) {
  $values = (array) $values;

  return function($input) use($values, $strict) {
    return in_array($input, $values, $strict);
  };
}

function notIn($values, $strict = false) {
  return compose('not', in($values, $strict));
}

function has($needle, $strict = false) {
  return function($input) use($needle, $strict) {
    return in_array($needle, (array) $input, $strict);
  };
}

function prop($name) {
  if ( is_array($name) ) {
    $name = array_map(
      function($name) {
        return prop($name);
      },
      array_reverse($name)
    );

    return compose($name);
  }

  return function ($object) use($name) {
    if ( is_object($object) ) {
      return @$object->$name;
    }
    else {
      return @$object[$name];
    }
  };
}

function pluck($list, $prop) {
  return map($list, prop($prop));
}

function plucks($prop) {
  return function($list) use($prop) {
    return pluck($list, $prop);
  };
}

function diffs($value) {
  return function($list) use($value) {
    if ( is_callable($value) ) {
      $value = $value();
    }

    return array_diff($list, $value);
  };
}

function func($name) {
  return function($object) use($name) {
    if ( is_callable(array($object, $name)) ) {
      return call_user_func(array($object, $name));
    }

    return @$object[$name];
  };
}

function propIs($prop, $value, $strict = false) {
  return propIn($prop, array($value), $strict);
}

function propIsNot($prop, $value, $strict = false) {
  return compose('not', propIs($prop, $value, $strict));
}

function propIn($prop, array $values, $strict = false) {
  return compose(in($values, $strict), prop($prop));
}

function propInNot($prop, array $values, $strict = false) {
  return compose('not', propIn($prop, $values, $strict));
}

/**
 * This differs from propIn() only when target property
 * is an array, this returns true when at least one of
 * the contents in targert property matches $values,
 * while propIn() does full array equality comparison.
 *
 * @param {string} $prop Target property.
 * @param {array} $values Array of values to match against.
 * @param {bool} $strict Whether to perform a strict comparison or not.
 *
 * @returns {Closure} A function that returns true on
 *                    at least one matches, false othereise.
 */
function propHas($prop, array $values, $strict = false) {
  return function($object) use($prop, $values, $strict) {
    $prop = Utility::wrapAssoc(@$object[$prop]);

    $prop = array_map(function($prop) use($values, $strict) {
      return in_array($prop, $values, $strict);
    }, $prop);

    return in_array(true, $prop, true);
  };
}

function invokes($name, array $args = array()) {
  return function ($object) use($name, $args) {
    if ( Utility::isAssoc($object) ) {
      $func = @$object[$name];
    }
    else if ( method_exists($object, $name) ) {
      $func = array($object, $name);
    }
    else if ( isset($object->$name) && is_callable($object->$name) ) {
      $func = $object->$name;
    }
    else {
      if ( is_object($object) ) {
        $object = get_class($object);
      }

      if ( is_array($object) ) {
        $object = 'input array';
      }

      trigger_error("No callable $name() found in $object.", E_USER_WARNING);

      unset($object);
    }

    return call_user_func_array($func, $args);
  };
}

function funcEquals($name, $value, array $args = array(), $strict = false) {
  return funcIn($name, array($value), $args, $strict);
}

function funcIn($name, array $values, array $args = array(), $strict = false) {
  return function($object) use($name, $values, $args, $strict) {
    $ret = call_user_func_array($object, $name, $args);

    return in_array($ret, $values, $strict);
  };
}

/**
 * Walks arrays and objects, optionally recursively.
 */
function walk(&$input, $callback, $deep = false) {
  if ( !is_array($input) && !is_object($input) ) {
    throw new \InvalidArgumentException(sprintf('walk() expects parameter 1 to be array or object, %s given.', gettype($input)));
  }

  if ( $deep ) {
    $walker = function(&$value, $key, &$parent) use(&$walker, &$callback) {
      if ( is_array($value) ) {
        foreach ( $value as $k => &$v ) {
          $walker($v, $k, $value);
        }

        $callback($value, $key, $parent);
      }
      else if ( $value instanceof \Traversable ) {
        foreach ( $value as $k => $v ) {
          $walker($v, $k, $value);
        }

        $callback($value, $key, $parent);
      }
      else if ( is_object($value) ) {
        $_value = get_object_vars($value);
        foreach ( $_value as $k => $v ) {
          $walker($v, $k, $_value);
        }
        $value = (object) $_value;
        unset($_value);

        $callback($value, $key, $parent);
      }
      else {
        $callback($value, $key, $parent);
      }
    };
  }
  else {
    $walker = function(&$value) use(&$callback) {
      if ( is_array($value) ) {
        foreach ( $value as $k => &$v ) {
          $callback($v, $k, $value);
        }
      }
      else if ($value instanceof \Traversable) {
        foreach ( $value as $k => $v ) {
          $callback($v, $k, $value);
        }
      }
      else if ( is_object($value) ) {
        $_value = get_object_vars($value);
        foreach ( $_value as $k => $v ) {
          $callback($v, $k, $_value);
        }
        $value = (object) $_value;
        unset($_value);
      }
      else {
        return $callback($value, null, $value);
      }
    };
  }

  $walker($input, null, $input);
}

/**
 * Object compatible version of array_reduce which also gives the key to callback.
 *
 * @param {array|object} $input The thing to be iterated.
 * @param {callable} A callback with signature function($result, $value, $key); for iteration.
 * @param {void*} Can be any value which will be passed as the $result parameter in the first iteration.
 */
function reduce($input, $callback, $initial = null) {
  walk($input, function($value, $key, $input) use(&$initial, &$callback) {
    $initial = call_user_func($callback, $initial, $value, $key, $input);
  });

  return $initial;
}

/**
 * Object compatible version of array_filter.
 */
function filter($input, $callback = null, $deep = false) {
  if ( $input === null ) {
    return $input;
  }

  if ( !is_array($input) && !is_object($input) ) {
    throw new \InvalidArgumentException(sprintf('filter() expects parameter 1 to be array or object, %s given.', gettype($input)));
  }

  if ( $callback === null ) {
    $callback = compose('not', 'blank');
  }

  walk($input, function($value, $key, &$parent) use(&$callback) {
    if ( !$callback($value, $key, $parent) ) {
      remove($key, $parent);
    }
  }, $deep);

  return $input;
}

/**
 * Modified array_map to support ArrayAccess and Traversable interfaces.
 */
function map($input, $callback) {
  if ( is_scalar($input) && !($input instanceof \Traversable) ) {
    $input = [$input];
  }

  if ( $input instanceof \Traversable || is_array($input) ) {
    $result = [];
    foreach ( $input as $key => $value ) {
      $result[$key] = call_user_func($callback, $value, $key, $input);
    }
    return $result;
  }
  else {
    throw new \InvalidArgumentException('Supplied input must be an array or Traversable.');
  }
}

/**
 * Variation of empty() function that also counts for object emptiness (stdClass).
 */
function blank($value) {
  if ( is_object($value) && $value instanceof stdClass ) {
    $value = get_object_vars($value);
  }

  return empty($value);
}

function remove($names, &$object) {
  $names = (array) $names;

  foreach ($names as $name) {
    if ( is_object($object) ) {
      unset($object->$name);
    }
    else {
      unset($object[$name]);
    }
  }
}

/**
 * Return a subset of the supplied array where items match supplied filter.
 */
function find(array $input, $filter) {
  return filter($input, function($item) use(&$filter) {
    return (array) array_select($item, array_keys($filter)) === (array) $filter;
  });
}

/**
 * Return the first matching item of supplied array.
 */
function &findOne(array &$input, array $filter) {
  $result = null;

  foreach ( $input as &$item ) {
    if ( (array) select($item, array_keys($filter)) == $filter ) {
      $result = $item;
      break;
    }
  }

  return $result;
}

//--------------------------------------------------
//
//  Comparer
//
//--------------------------------------------------

function sortsAscend($subject, $object, $strict = false) {
  if ( $strict ) {
    if ( $subject === $object ) {
      return 0;
    }
  }
  else {
    if ( $subject == $object ) {
      return 0;
    }
  }

  if ( is_numeric($subject) ) {
    $subject = doubleval($subject);
  }

  if ( is_numeric($object) ) {
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

    case 'null':
    default:
      throw new Exception('null or unknown type comparison is not supported.');
      return 0;
  }
}

function sortsDescend($subject, $object, $strict = false) {
  $ret = sortsAscend($subject, $object, $strict);

  if ( $ret >= 1 ) {
    return -1;
  }

  if ( $ret <= -1 ) {
    return 1;
  }

  return 0;
}

function sortsPropAscend($name, $strict = false) {
  $name = prop($name);

  return function($subject, $object) use($name, $strict) {
    return sortsAscend($name($subject), $name($object), $strict);
  };
}

function sortsPropDescend($name, $strict = false) {
  $name = prop($name);

  return function($subject, $object) use($name, $strict) {
    return sortsDescend($name($subject), $name($object), $strict);
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

function prepends($prefix, $prop = null) {
  if ( $prop === null ) {
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
  if ( is_string($suffix) ) {
    return "$object$suffix";
  }
  else {
    return $object + $suffix;
  }
}

function appends($suffix, $prop = null) {
  if ( $prop === null ) {
    return function($object) use($suffix) {
      return append($suffix, $object);
    };
  }
  else {
    return function($object) use($suffix, $prop) {
      if ( is_object($object) ) {
        $object->$prop = append($suffix, @$object->$prop);
      }
      else {
        $object[$prop] = append($suffix, @$object[$prop]);
      }

      return $object;
    };
  }
}

function assigns($value, $prop = null) {
  // setter pattern
  if ( $prop === null ) {
    return function($object) use($value) {
      if ( is_callable($value) ) {
        $value = $value($object);
      }

      return $value;
    };
  }
  // normal assignment
  else {
    return function($object) use($prop, $value) {
      if ( is_callable($value) ) {
        $value = $value($object);
      }

      if ( is_object($object) ) {
        $object->$prop = $value;
      }
      else {
        $object[$prop] = $value;
      }

      return $object;
    };
  }
}

// Numeric values
function eq($value, $strict = false) {
  return is($value, $strict);
}

function gt($value) {
  return function($input) use($value) {
    return $input > $value;
  };
}

function gte($value) {
  return function($input) use($value) {
    return $input >= $value;
  };
}

function lt($value) {
  return function($input) use($value) {
    return $input < $value;
  };
}

function lte($value) {
  return function($input) use($value) {
    return $input <= $value;
  };
}

function adds($value) {
  return function($input) use($value) {
    return $input + $value;
  };
}

function subtracts($value) {
  return function($input) use($value) {
    return $input + $value;
  };
}

function subtractsBy($value) {
  return function($input) use($value) {
    return $value - $input;
  };
}

function multiplies($value) {
  return function($input) use($value) {
    return $input * $value;
  };
}

function divides($value) {
  return function($input) use($value) {
    return $input / $value;
  };
}

function dividesBy($value) {
  return function($input) use($value) {
    return $value / $input;
  };
}

// String values
function replaces($pattern, $replacement) {
  return function($string) use($pattern, $replacement) {
    return preg_replace($pattern, $replacement, $string);
  };
}

function matches($pattern) {
  return function($string) use($pattern) {
    return preg_match($pattern, $string);
  };
}

function startsWith($prefixes, $ignoreCase = false) {
  $prefixes = (array) $prefixes;

  if ( $ignoreCase ) {
    return function($input) use($prefixes) {
      return (bool) array_filter($prefixes, function($prefix) use($input) {
        return stripos($input, $prefix) === 0;
      });
    };
  }
  else {
    return function($input) use($prefixes) {
      return (bool) array_filter($prefixes, function($prefix) use($input) {
        return strpos($input, $prefix) === 0;
      });
    };
  }
}

function containsWith($strings, $ignoreCase = false) {
  $strings = (array) $strings;

  if ( $ignoreCase ) {
    return function($input) use($strings) {
      return (bool) array_filter($strings, function($string) use($input) {
        return stripos($input, $string) !== false;
      });
    };
  }
  else {
    return function($input) use($strings) {
      return (bool) array_filter($strings, function($string) use($input) {
        return strpos($input, $string) !== false;
      });
    };
  }
}

function endsWith($suffixes, $ignoreCase = false) {
  $suffixes = (array) $suffixes;

  if ( $ignoreCase ) {
    return function($input) use($suffixes) {
      return (bool) array_filter($suffixes, function($suffix) use($input) {
        return strcasecmp(substr($input, -strlen($suffix)), $suffix) === 0;
      });
    };
  }
  else {
    return function($input) use($suffixes) {
      return (bool) array_filter($suffixes, function($suffix) use($input) {
        return strcmp(substr($input, -strlen($suffix)), $suffix) === 0;
      });
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
 *
 * @param {?callable} $callback
 * @param {?int} $flag
 */
function filters() {
  $args = func_get_args();
  return function($input) use(&$args) {
    return call_user_func_array(
      'filter',
      array_merge(array($input), $args)
    );
  };
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
 * This function allows these two patterns:
 * 1. remove($field1, $field2)
 * 2. remove(array($field1, $field2))
 */
function removes($name) {
  if ( !is_array($name) ) {
    $name = func_get_args();
  }

  return function($object) use($name) {
    remove($name, $object);
    return $object;
  };
}

/**
 * Factory of array_map($list, $callback);
 */
function maps($callback) {
  return function($list) use($callback) {
    return map($list, $callback);
  };
}

/**
 * Factory of functions that wrap provided $item with an array under property $name.
 */
function wraps($name) {
  return function ($item) use($name) {
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

// PHP equalivent of Javascript Array.prototype.every()
if ( !function_exists('array_every') ) {
  function array_every(array $array, callable $callback) {
    foreach ( $array as $key => $item ) {
      if ( !$callback($item, $key, $array) ) {
        return false;
      }
    }

    return true;
  }
}

// PHP equalivent of Javascript Array.prototype.some()
if ( !function_exists('array_some') ) {
  function array_some(array $array, callable $callback) {
    foreach ( $array as $key => $item ) {
      if ( $callback($item, $key, $array) ) {
        return true;
      }
    }

    return false;
  }
}

// A version of sort() that it returns the result instead.
if ( !function_exists('array_sort') ) {
  function array_sort(array $list) {
    $args = func_get_args();
    $args[0] = &$list;
    call_user_func_array('sort', $args);
    return $args[0];
  };
}

if ( !function_exists('array_select') ) {
  /**
   * @param array $list Array to be selected.
   * @param array $keys Array of keys to select.
   */
  function array_select(array $list, array $keys): array {
    return select($list, $keys);
  }
}

/**
 * Builds an array without unspecified keys from an array or object.
 *
 * @param array|object $value Array of object to be selected.
 * @param array $keys The keys to be included in exists.
 * @return array|object An array containing the specified keys.
 */
function select($value, array $keys) {
  if ( !is_array($value) && !is_object($value) ) {
    return $value;
  }

  $type = gettype($value);

  $value = filter($value, function($value, $key) use($keys) {
    return in_array($key, $keys);
  });

  settype($value, $type);

  return $value;
}

if ( !function_exists('array_remove') ) {
  function array_remove(&$list, $item, $strict = false) {
    $items = (array) $item;

    $hasRemoved = false;

    foreach ($items as $item) {
      while (false !== ($index = array_search($item, $list, $strict))) {
        $hasRemoved = true;

        array_splice($list, $index, 1);
      }
    }

    return $hasRemoved;
  }
}

if ( !function_exists('array_remove_keys') ) {
  function array_remove_keys(&$list, $keys) {
    $keys = (array) $keys;

    foreach ( $keys as $key ) {
      unset($list[$key]);
    }
  }
}

if ( !function_exists('array_mapdef') ) {
  function array_mapdef() {
    return call_user_func_array('mapdef', func_get_args());
  }
}

if ( !function_exists('array_seldef') ) {
  function array_seldef() {
    return call_user_func_array('seldef', func_get_args());
  }
}

if ( !function_exists('array_filter_keys') ) {
  function array_filter_keys($list, /* callable */ $func) {
    return array_select($list, array_filter(array_keys($list), $func));
  }
}

//--------------------------------------------------
//
//  partials
//
//--------------------------------------------------

function mapsdef($callback, $filter = null) {
  return function($input) use(&$callback, &$filter) {
    return mapdef($input, $callback, $filter);
  };
}

function mapdef($list, /* callable */ $callback, /* callable */ $filter = null, $keep_index = false) {
  if ( $filter === null ) {
    $filter = filters(compose('not', 'blank'));
  }
  else {
    $filter = filters($filter);
  }

  $λ = compose($filter, maps($callback));

  if ( $keep_index ) {
    return $λ($list);
  }
  else {
    return array_values($λ($list));
  }
}

function seldef(array $keys, $list, /* callable */ $filter = null) {
  if ( $filter === null ) {
    $filter = filters();
  }
  else {
    $filter = filters($filter);
  }

  $function = compose($filter, selects($callback));

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

function unlinks($path) {
  return function() use($path) {
    @unlink($path);
  };
}
