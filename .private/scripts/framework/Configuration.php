<?php
/*! framework\Configuration.php | Configuration accessor utility. */

namespace framework;

use core\Node;

/* Usage:

	Similar as SimpleXMLElement.

	// Read configuration values by key
	$conf = new framework\Configuration('core.Net');
	$conf = framework\Configuration::get('core.Net');

	// Get a specific configuration property
	$conf->$property->__valueOf();
	$conf[$property];

	// Set new value to configuration
	$conf->$property = $value; // __set
	$conf[$property] = $value; // offsetSet -> __set

	// Delete a specific configuration value
	unset($conf->$property);  // __unset
	unset($conf[$property]);  // offsetUnset -> __unset

	// The difference between object accessor "->" and array accessors "[]"
	// is that object accessor "->" will return an instance of Configuration
	// object, enabling deep array structure. i.e.

	$conf->foo->bar->baz = $value;

	// Resulting a JSON object like this:
	{ "foo": { "bar": { "baz": $value } } }

*/

class Configuration implements \Iterator, \ArrayAccess {

	//--------------------------------------------------
	//
	//  Properties
	//
	//--------------------------------------------------

	private $parentObject = NULL;

	function getParentObject() {
		return $this->parentObject;
	}

	//------------------------------
	//  key
	//------------------------------

	private $key = NULL;

	function getKey() {
		return $this->key;
	}

	//------------------------------
	//  contents
	//------------------------------

	private $contents = null;

	function &getContents() {
		return $this->contents;
	}

	//--------------------------------------------------
	//
	//  Constructor
	//
	//--------------------------------------------------

	function __construct($key, Configuration $parentObject = NULL) {
	  $this->key = &$key;
		$this->parentObject = $parentObject;

		$this->update();
	}

	//--------------------------------------------------
	//
	//  Methods
	//
	//--------------------------------------------------

	// Shorthand accessor
	static function get($key, $defaultValue = null) {
		if ( strpos($key, '::') !== false ) {
			list($key, $property) = explode('::', $key, 2);

			$key = new Configuration($key);

			if ( isset($key[$property]) ) {
				return $key[$property];
			}
			else {
				return $defaultValue;
			}
		}
		else {
			return new Configuration($key);
		}
	}

  /**
   * Query contents with current configuration key from the database,
   * and update the local stored value.
   */
  function update() {
    // Root objects will get value from database upon creation
		if ( $this->parentObject === null ) {
			$confObj = array(
				Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_CONFIGURATION
			, '@key' => $this->key
			);

			$res = (array) @Node::get($confObj);

			if ( isset($res) ) {
				$confObj = @$res[0];
			}

			unset($res, $confObj['@key'], $confObj[Node::FIELD_COLLECTION]);
		}
		else {
			$confObj = &$this->parentObject->__valueOf();
			$confObj = &$confObj[$this->key];
		}

		$this->contents = &$confObj;
  }

	function &__get($name) {
		// We don't need to create the data row yet.
		// Node::set will be called when ConfigurationInstance::__set is invoked.

		$confObj = new Configuration($name, $this);

		return $confObj;
	}

	function __set($name, $value) {
		$confObj = &$this->contents;

		$confObj[$name] = $value;

		$this->setContents();
	}

	function __unset($name) {
		$confObj = &$this->contents;

		unset($confObj[$name]);

		$this->setContents();
	}

	function __isset($name) {
		return isset($this->contents);
	}

	function &__valueOf() {
		return $this->contents;
	}

	function __toString() {
		return "$this->contents";
	}

	//------------------------------
	//  private: getContents
	//------------------------------

	private function setContents() {
		// TODO: Trace upwards until root for the object.

		$cObj = $this;

		while ($pObj = $cObj->getParentObject())
			$cObj = $pObj;

		$confObj = $cObj->getContents();
		$confKey = $cObj->getKey();

    $filter = array(
  			Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_CONFIGURATION
  		, '@key' => $confKey
  		);

    if ( !$confObj ) {
      Node::delete($filter);
    }
    else {
		  Node::set($filter + $confObj);
		}
	}

	//--------------------------------------------------
	//
	//  Methods : Iterator
	//
	//--------------------------------------------------

	function current() {
		return current($this->contents);
	}

	function key() {
		return key($this->contents);
	}

	function next() {
		return next($this->contents);
	}

	function rewind() {
		reset($this->contents);
	}

	function valid() {
		return isset($this->contents[$this->key()]);
	}

	//--------------------------------------------------
	//
	//  Methods: ArrayAccess
	//
	//--------------------------------------------------

	function offsetExists($offset) {
		return isset($this->contents[$offset]);
	}

	function offsetGet($offset) {
		return @$this->contents[$offset];
	}

	function offsetSet($offset, $value) {
		$this->$offset = $value;
	}

	function offsetUnset($offset) {
		unset($this->$offset);
	}

}
