<?php
/* Deferred.php | An implementation of jQuery deferred object. */

namespace core;

/**
 * An implementation of jQuery deferred object.
 *
 * Difference between Promise and deferred object
 * is the accessibility of state changing methods.
 *
 * @author Vicary Archangel <vicary@victopia.org>
 */
class Deferred {

	//--------------------------------------------------
	//
	//  Constructor
	//
	//--------------------------------------------------

	public function __construct() {
		$this->promiseObject = new Promise($this);
	}

	//--------------------------------------------------
	//
	//  Properties
	//
	//--------------------------------------------------

	private $promiseObject = NULL;

	/**
	 * Read only internal state.
	 */
	public function state() {
		return $this->getPromise('state');
	}

	//--------------------------------------------------
	//
	//  Methods
	//
	//--------------------------------------------------

	public function promise() {
		return $this->promiseObject;
	}

	public function resolve() {
		$promiseObject = $this->promiseObject;
		$funcArgs = func_get_args();
		$callbacks = $this->getPromise('resolvedCallbacks');

		if ($this->state() !== Promise::STATE_NORMAL) {
			return;
		}

		$this->setPromise('resolvedArgs', $funcArgs);
		$this->setPromise('state', Promise::STATE_RESOLVED);

		array_walk($callbacks, function($callback) use($funcArgs) {
			Utility::forceInvoke($callback, $funcArgs);
		});

		$this->invokeAlways();
	}

	public function reject() {
		$promiseObject = $this->promiseObject;
		$funcArgs = func_get_args();
		$callbacks = $this->getPromise('rejectedCallbacks');

		if ($this->state() !== Promise::STATE_NORMAL) {
			return;
		}

		$this->setPromise('rejectedArgs', $funcArgs);
		$this->setPromise('state', Promise::STATE_REJECTED);

		array_walk($callbacks, function($callback) use($funcArgs) {
			Utility::forceInvoke($callback, $funcArgs);
		});

		$this->invokeAlways();
	}

	public function notify() {
		$promiseObject = $this->promiseObject;
		$callbacks = $this->getPromise('progressCallbacks');
		$funcArgs = func_get_args();

		if ($this->state() !== Promise::STATE_NORMAL) {
			return;
		}

		array_walk($callbacks, function($callback) use($funcArgs) {
			Utility::forceInvoke($callback, $funcArgs);
		});
	}

	// Expose methods from internal Promise object.

	public function progress($callback) {
		return $this->promiseObject->progress($callback);
	}

	public function done($callback) {
		return $this->promiseObject->done($callback);
	}

	public function fail($callback) {
		return $this->promiseObject->fail($callback);
	}

	public function always($callback) {
		return $this->promiseObject->always($callback);
	}

	//--------------------------------------------------
	//
	//  Private methods
	//
	//--------------------------------------------------

	private function invokeAlways() {
		$promiseObject = $this->promiseObject;
		$callbacks = $this->getPromise('alwaysCallbacks');

		array_walk($callbacks, function($callback) {
			Utility::forceInvoke($callback);
		});

		// Remove progress listeners to save memory.
		$this->setPromise('progressCallbacks', NULL);
		$this->setPromise('resolvedCallbacks', NULL);
		$this->setPromise('rejectedCallbacks', NULL);
	}

	//--------------------------------------------------
	//
	//  Static methods
	//
	//--------------------------------------------------

	private function getProperty($name) {
		$ref = new \ReflectionClass($this->promiseObject);

		$ref = $ref->getProperty($name);

		$ref->setAccessible(TRUE);

		return $ref;
	}

	private function getPromise($name) {
		$ref = $this->getProperty($name);

		return $ref->getValue($this->promiseObject);
	}

	private function setPromise($name, $value) {
		$ref = $this->getProperty($name);

		$ref->setValue($this->promiseObject, $value);
	}

}
