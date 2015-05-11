<?php
/*! Optimist.php | Port from nodejs optimist https://github.com/substack/node-optimist. */

namespace framework;

use core\Utility;

final class Optimist implements \ArrayAccess {

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  /**
   * @private
   *
   * Stores the alias of different options.
   */
  private $alias = array();

  /**
   * @private
   *
   * Usage wordwrap columns (width).
   */
  private $columns = 75;

  /**
   * @private
   *
   * Default values
   */
  private $defaults = array();

  /**
   * @private
   *
   * Required options
   */
  private $demand = array();

  /**
   * @private
   *
   * Descriptions to different options.
   */
  private $descriptions = array();

  /**
   * @private
   *
   * Auto type-casting to target option.
   */
  private $types = array();

  /**
   * @private
   *
   * Usage message to users when demanded options does not exists.
   */
  private $usage = null;

  /**
   * @type array
   *
   * Parsed arguments.
   */
  private $argv = array();

  /**
   * @private
   *
   * Prevent double processing of argv.
   */
  private $result = null;

  /**
   * @constructor
   *
   * Keep a copy of the global $argv list to allow users make changes to the global one.
   */
  function __construct(array $parameters = null) {
    global $argv;

    if ( $parameters === null ) {
      $this->argv = $argv;
    }
    else {
      $this->argv = $parameters;
    }
  }

  //--------------------------------------------------
  //
  //  Overloading : Getter
  //
  //--------------------------------------------------

  function __get($name) {
    $this->processArgs();

    if ( $name == 'argv' ) {
      return $this->result;
    }
    else {
      return @$this->result[$name];
    }
  }

  function __isset($name) {
    $this->processArgs();

    return isset($this->result[$name]);
  }

  //--------------------------------------------------
  //
  //  Methods : ArrayAccess
  //
  //--------------------------------------------------

  function offsetGet($name) {
    return $this->$name;
  }

  function offsetSet($name, $value) { /* noop */ }

  function offsetExists($name) {
    return isset($this->$name);
  }

  function offsetUnset($name) { /* noop */ }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  /**
   * Set key names as equivalent such that updates to
   * a key will propagate to aliases and vice-versa.
   *
   * Optionally alias() can take an array that maps
   * keys to aliases.
   */
  public function alias($key, $alias = NULL) {
    if ( !is_array($key) ) {
      $key = array($key => $alias);
    }

    // Assign both ways
    foreach ($key as $name => $alias) {
      @$this->alias[$name][] = $alias;
      @$this->alias[$alias][] = $name;
    }

    // Reset the cache
    $this->result = null;

    return $this; // chainable
  }

  /**
   * Set argv[key] to value if no option was specified
   * on process.argv.
   *
   * Optionally defaults() can take an object that maps
   * keys to default values.
   */
  public function defaults($key, $value = NULL) {
    if ( $value !== NULL ) {
      $key = array( $key => $value );
    }

    $this->defaults = $key;

    // Reset the cache
    $this->result = null;

    return $this; // chainable
  }

  /**
   * If key is a string, show the usage information and
   * exit if key wasn't specified in process.argv.
   *
   * If key is a number, demand at least as many
   * non-option arguments, which show up in argv['_'].
   *
   * If key is an Array, demand each element.
   *
   * @param {string} $option Option name to be required.
   * @param {array} $options, Key-Value pair that a key
   *                existance means required, while the
   *                value is a type to be casted into.
   *
   *                Set this to FALSE to cancel the
   *                effect.
   */
  public function demand($key) {
    if ( is_numeric($key) ) {
      $this->demand = $key;
    }
    else {
      if ( !is_array($this->demand) ) {
        $this->demand = array();
      }

      if ( is_string($key) ) {
        $key = array($key => true);
      }

      // $key = Utility::wrapAssoc($key);

      $this->demand = array_merge($this->demand, $key);
    }

    // Reset the cache
    $this->result = null;

    return $this; // chainable
  }

  /**
   * Describe a key for the generated usage information.
   *
   * Optionally describe() can take an object that maps
   * keys to descriptions.
   */
  public function describe($key, $desc) {
    $key = Utility::wrapAssoc($key);

    foreach ($key as $_key) {
      $this->descriptions["$_key"] = $desc;
    }

    // Reset the cache
    $this->result = null;

    return $this; // chainable
  }

  /**
   * Cast key into $type. A generic method precedes
   * boolean() and string().
   */
  public function type($key, $type) {
    $key = Utility::wrapAssoc($key);

    $type = $this->normalizeType($type);

    foreach ($key as $_key) {
      $this->types[$_key] = $type;
    }

    // Reset the cache
    $this->result = null;

    return $this; // chainable
  }

  /**
   * Instead of chaining together .alias().demand().default(),
   * you can specify keys in opt for each of the chainable methods.
   *
   * Optionally options() can take an object that maps
   * keys to opt parameters.
   */
  public function options($key, $opt) {
    $key = Utility::wrapAssoc($key);

    foreach ($key as $_key) {
      foreach ($opt as $option => $value) {
        if ( method_exists($this, $option) ) {
          $this->$option($_key, $value);
        }
      }
    }

    return $this; // chainable
  }

  /**
   * Change the usage notification message.
   *
   * @param {string} $message Message to display when
   *                 required option does not exists.
   */
  public function usage($message) {
    if ( $message ) {
      $this->usage = $message;
    }

    return $this; // chainable
  }

  /**
   * Check that certain conditions are met in the
   * provided arguments.
   *
   * If $func throws or returns false, show the thrown
   * error, usage information, and exit.
   */
  public function check($func) {
    // TODO: What parameters to pass in $func?

    try {
      $ret = $func();
    }
    catch (\Exception $e) {
      $ret = $e->getMessage();
    }

    if ( $ret === false || is_string($ret) ) {
      $this->showError($ret);

      die;
    }

    return $this; // chainable
  }

  /**
   * Interpret key as a boolean. If a non-flag option
   * follows key in process.argv, that string won't
   * get set as the value of key.
   *
   * If key never shows up as a flag in argv, argv[key]
   * will be false.
   *
   * If key is an Array, interpret all the elements
   * as booleans.
   */
  public function boolean($key) {
    $this->type($key, version_compare(PHP_VERSION, '4.2.0', '<') ? 'boolean' : 'bool');

    return $this; // chainable
  }

  /**
   * Tell the parser logic not to interpret key as
   * a number or boolean. This can be useful if you
   * need to preserve leading zeros in an input.
   *
   * If key is an Array, interpret all the elements
   * as strings.
   */
  public function string($key) {
    $this->type($key, 'string');

    return $this; // chainable
  }

  /**
   * Tell the parser logic to cast the options with
   * specified keys as integer.
   *
   * If $key is an Array, interpret all the elements
   * as integers.
   */
  public function integer($key) {
    $this->type($key, version_compare(PHP_VERSION, '4.2.0', '<') ? 'integer' : 'int');

    return $this; // chainable
  }

  /**
   * Tells the parser logic to cast the options with
   * specified keys as decimal numbers.
   *
   * If $key is an array, interpret all the elements
   * as decimal numbers.
   */
  public function float($key) {
    $this->type($key, version_compare(PHP_VERSION, '4.2.0', '<') ? 'double': 'float');

    return $this; // chainable
  }

  /**
   * Format usage output to wrap at $columns many columns.
   */
  public function wrap($columns) {
    $this->columns = $columns;

    return $this; // chainable
  }

  /**
   * Return the generated usage string.
   */
  public function help() {
    return wordwrap($this->usage, (int) $this->columns);
  }

  /**
   * Print the usage data using $func for printing.
   */
  public function showHelp($func = 'error_log') {
    $func($this->help());

    return $this; // chainable
  }

  /**
   * Process a new set of arguments with the same options.
   *
   * @param {array} $args Array of command line argument format.
   */
  public function parse(array $args) {
    $this->argv = $args;

    $this->result = null;

    return $this; // chainable
  }

  //--------------------------------------------------
  //
  //  Private methods
  //
  //--------------------------------------------------

  private function normalizeType($type) {
    switch ($type) {
      case 'int':
      case 'integer':
        $type = 'integer';
        break;

      case 'double':
      case 'float':
      case 'numeric':
        $type = 'float';
        break;
    }

    return $type;
  }

  private function showError($customMessage) {
    echo $this->help();

    $result = array();

    // TODO: Should do alias before anything.
    /* Example:
       > (new optimist())
       >   ->alias('u', 'user')
       >   ->describe('users...')
       >   ->demand('user')
       >   ->argv;

       Will have error output like this:
       > -u, --user    users....
       > --user, -u
       >
       > Missing required options: user

       Alias does not correctly taking reference against each other.
    */

    $options = array_keys($this->descriptions);

    if ( is_array($this->demand) ) {
      $options = array_unique(array_merge($options
        , array_keys($this->demand)));
    }

    $alias = $this->alias;

    foreach ($options as $option) {
      $message = (array) $option;

      if ( isset($alias[$option]) ) {
        $message = array_merge($message, (array) $alias[$option]);
      } unset($alias[$option]);

      $message = array_map(function($key) {
        return strlen("$key") == 1 ? "-$key" : "--$key";
      }, $message);

      $message = implode(', ', $message);

      if ( @$this->descriptions[$option] ) {
        $message.= '  ' . $this->descriptions[$option];
      }

      $result[] = $message;
    } unset($message, $option, $options);

    if ( $result ) {
      echo "\n\nOptions:\n" . implode("\n", $result);

      unset($result);
    }

    if ( $customMessage ) {
      echo "\n\n$customMessage";
    }

    echo "\n\n";
  }

  private function processArgs() {
    if ( $this->result ) {
      return;
    }

    $argv = $this->argv;

    // Remove until the script itself.
    if ( @$_SERVER['PHP_SELF'] && in_array($_SERVER['PHP_SELF'], $argv) ) {
      $argv = array_slice($argv, array_search($_SERVER['PHP_SELF'], $argv) + 1);
    }

    $args = array();

    $currentNode = &$args['_'];

    foreach ($argv as $value) {
      // Match flag style, then assign initial value.
      if ( preg_match('/^\-\-?([\w\.]+)(?:=(.*))?$/', $value, $matches) ) {
        $nodePath = explode('.', $matches[1]);

        $currentNode = &$args[array_shift($nodePath)];

        while ($nodePath) {
          $currentNode = &$currentNode[array_shift($nodePath)];
        }

        $value = isset($matches[3]) ? $matches[3] : TRUE;

        // Value exists
        if ( $currentNode ) {
          if ( !is_array($currentNode) ) {
            $currentNode = array($currentNode);
          }

          $currentNode[] = $value;
        }
        else {
          $currentNode = $value;
        }

        unset($matches, $nodePath);
      }

      // Not -- style flags, see if a previous flag exists.
      // If so, assign to it, otherwise goes to _.
      else {
        if ( isset($currentNode) ) {
          if ( $currentNode === TRUE ) {
            $currentNode = $value;
          }

          // Replace last TRUE in array.
          else {
            array_pop($currentNode);

            $currentNode[] = $value;
          }
        }
        else {
          @$args['_'][] = $value;
        }

        unset($currentNode);
      }
    } unset($value);

    if ( !$args['_'] ) {
      unset($args['_']);
    }

    //------------------------------
    //  Alias
    //------------------------------

    foreach ($this->alias as $key => $alias) {
      // Alias: type
      if ( isset($this->types[$key]) ) {
        $this->types+= array_fill_keys($alias, $this->types[$key]);
      }

      // Alias: defaults
      if ( isset($this->defaults[$key]) ) {
        $this->defaults+= array_fill_keys($alias, $this->defaults[$key]);
      }
    } unset($key, $alias);

    // Alias: value
    foreach ($args as $option => $value) {
      $target = (array) @$this->alias[$option];

      foreach ($target as $alias) {
        $args[$alias] = $value;
      } unset($alias);
    } unset($option, $value, $target);

    if ( @$args['_'] ) {
      foreach($args['_'] as $value) {
        $target = (array) @$this->alias[$value];

        foreach ($target as $alias) {
          $args['_'][] = $alias;
        } unset($alias);
      }

      $args['_'] = array_unique($args['_']);
    } unset($value, $target);

    //------------------------------
    //  Demands
    //------------------------------

    if ( is_numeric($this->demand) ) {
      if ( count((array) @$args['_']) < $this->demand ) {
        $this->showError("It requires at least $this->demand args to run.");

        die;
      }
    }
    else {
      $missingKeys = array();

      foreach ($this->demand as $key => $value) {
        if ( !isset($args[$key]) ) {
          $missingKeys[] = $key;
        }
      }

      if ( $missingKeys ) {
        $this->showError("Missing required options: " . implode(', ', $missingKeys));

        die;
      }
    }

    //------------------------------
    //  Type-casting
    //------------------------------
    $args = Utility::flattenArray($args);

    array_walk($args, function(&$value, $key) {
      if ( isset($this->types[$key]) ) {
        if ( !settype($value, $this->types[$key]) ) {
          $this->showError("Unable to cast $key into type $type.");

          die;
        }
      }
      elseif ( is_numeric($value) ) {
        $value = doubleval($value);
      }
    });

    $args+= (array) $this->defaults;

    return $this->result = Utility::unflattenArray($args);
  }

}
