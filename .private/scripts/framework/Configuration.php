<?php
/*! framework\Configuration.php | Configuration accessor utility. */

/* Database schema:

CREATE TABLE IF NOT EXISTS Configurations (
	`key` VARCHAR(255) NOT NULL PRIMARY KEY
, `@contents` LONGTEXT NOT NULL
, FULLTEXT KEY (`@contents`)
) ENGINE=MyISAM;

 */
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

namespace framework;

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

	private $contents = NULL;

	function &getContents() {
		return $this->contents;
	}

	//--------------------------------------------------
	//
	//  Constructor
	//
	//--------------------------------------------------

	function __construct($key, Configuration $parentObject = NULL) {
		// Root objects will get value from database upon creation
		if ($parentObject === NULL) {
			$confObj = array(
				NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_CONFIGURATION
			, '@key' => $key
			);

			if ($res = \Node::get($confObj)) {
				$confObj = $res[0];
			} unset($res);

			unset($confObj['@key'], $confObj[NODE_FIELD_COLLECTION]);
		}
		else {
			$confObj = &$parentObject->__valueOf();
			$confObj = &$confObj[$key];

			$this->parentObject = $parentObject;
		}

		$this->key = &$key;

		$this->contents = &$confObj;
	}

	//--------------------------------------------------
	//
	//  Methods
	//
	//--------------------------------------------------

	// Shorthand accessor
	static function get($key) {
		if (strpos($key, '::') !== FALSE) {
			list($key, $property) = explode('::', $key, 2);

			$key = new Configuration($key);

			return $key[$property];
		}
		else {
			return new Configuration($key);
		}
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
  			NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_CONFIGURATION
  		, '@key' => $confKey
  		);

    if ( !$confObj ) {
      \node::delete($filter);
    }
    else {
		  \node::set($filter + $confObj);
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
		return $this->contents[$offset];
	}

	function offsetSet($offset, $value) {
		$this->$offset = $value;
	}

	function offsetUnset($offset) {
		unset($this->$offset);
	}

}