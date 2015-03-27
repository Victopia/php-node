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

function compose() {
  $funcs = func_get_args();

  return function() use($funcs) {
    $args = func_get_args();

    for ( $i = count($funcs); isset($funcs[--$i]); ) {
      $args = array(call_user_func_array($funcs[$i], $args));
    }

    return $args[0];
  };
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

function funcAnd($inputA, $inputB) {
  return function($input) use($inputA, $inputB) {
    if ( is_callable($inputA) ) {
      $inputA = $inputA($input);
    }

    if ( is_callable($inputB) ) {
      $inputB = $inputB($input);
    }

    return $inputA && $inputB;
  };
};

function funcOr($inputA, $inputB) {
  return function($input) use($inputA, $inputB) {
    if ( is_callable($inputA) ) {
      $inputA = $inputA($input);
    }

    if ( is_callable($inputB) ) {
      $inputB = $inputB($input);
    }

    return $inputA || $inputB;
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
  return function ($object) use($name) {
    return @$object[$name];
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
  return propIn($prop, (array) $value, $strict);
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
    if ( is_array($object) ) {
      $func = @$object[$name];
    }
    else {
      $func = array($object, $name);
    }

    return call_user_func_array($func, $args);
  };
}

function funcEquals($name, $value, array $args = array(), $strict = false) {
  return funcIn($name, (array) $value, $args, $strict);
}

function funcIn($name, array $values, array $args = array(), $strict = false) {
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
  if ( !is_array($name) ) {
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
  return function($subject, $object) use($name, $strict) {
    if ( is_object($subject) ) {
      $subject = @$subject->$name;
    }
    else {
      $subject = @$subject[$name];
    }

    if ( is_object($object) ) {
      $object = @$object->$name;
    }
    else {
      $object = @$object[$name];
    }

    return sortsAscend($subject, $object, $strict);
  };
}

function sortsPropDescend($name, $strict = false) {
  return function($subject, $object) use($name, $strict) {
    if ( is_object($subject) ) {
      $subject = @$subject->$name;
    }
    else {
      $subject = @$subject[$name];
    }

    if ( is_object($object) ) {
      $object = @$object->$name;
    }
    else {
      $object = @$object[$name];
    }

    return sortsDescend($subject, $object, $strict);
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

  else if ( is_array($suffix) ) {
    return $suffix + $object; // $suffix takes precedence.
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
      @$object[$prop] = append($suffix, @$object[$prop]);

      return $object;
    };
  }
}

function assigns($value, $prop = null) {
  if ( $prop === null ) {
    return function($object) use($value) {
      if ( is_callable($value) ) {
        $value = $value($object);
      }

      return $value;
    };
  }
  else {
    return function($object) use($prop, $value) {
      if ( is_callable($value) ) {
        $value = $value($object);
      }

      $object[$prop] = $value;

      return $object;
    };
  }
}

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
 */
function filters(/* callable */ $filter = null) {
  if ( is_null($filter) ) {
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

// PHP equalivent of Javascript Array.prototype.every()
if ( !function_exists('array_every') ) {
  function array_every(array $array, callable $callback) {
    foreach ( $array as $item ) {
      if ( !$callback($item) ) {
        return false;
      }
    }

    return true;
  }
}

// PHP equalivent of Javascript Array.prototype.some()
if ( !function_exists('array_some') ) {
  function array_some(array $array, callable $callback) {
    foreach ( $array as $item ) {
      if ( $callback($item) ) {
        return true;
      }
    }

    return false;
  }
}

if ( !function_exists('array_select') ) {
  function array_select($list, array $keys) {
    $result = array();

    foreach ( $keys as $key ) {
      if ( array_key_exists($key, $list) ) {
        $result[$key] = $list[$key];
      }
    }

    return $result;
  }
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

function mapdef($list, /* callable */ $callback, /* callable */ $filter = null) {
  $function = compose(
      filters($filter)
    , maps($callback)
    );

  return $function($list);
}

function seldef(array $keys, $list, /* callable */ $filter = null) {
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
