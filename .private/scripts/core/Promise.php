<?php
/* Promise.php | An implementation of Promise object in jQuery. */

namespace core;

class Promise {

	//--------------------------------------------------
	//
	//  Constants
	//
	//--------------------------------------------------

	const STATE_NORMAL   = 0;
	const STATE_RESOLVED = 1;
	const STATE_REJECTED = 2;

	//--------------------------------------------------
	//
	//  Variables
	//
	//--------------------------------------------------

	private $progressCallbacks = array();

	private $resolvedCallbacks = array();

	private $resolvedArgs = array();

	private $rejectedCallbacks = array();

	private $rejectedArgs = array();

	private $alwaysCallbacks   = array();

	private $state = 0;

	//--------------------------------------------------
	//
	//  Methods
	//
	//--------------------------------------------------

	public function progress($callback) {
		if ($this->state === self::STATE_NORMAL) {
			$this->progressCallbacks[] = $callback;
		}

		return $this; // chainable
	}

	public function done($callback) {
		if ($this->state === self::STATE_RESOLVED) {
			Utility::forceInvoke($callback, $this->resolvedArgs);
		}
		else {
			$this->resolvedCallbacks[] = $callback;
		}

		return $this; // chainable
	}

	public function fail($callback) {
		if ($this->state === self::STATE_REJECTED) {
			Utility::forceInvoke($callback, $this->rejectedArgs);
		}
		else {
			$this->rejectedCallbacks[] = $callback;
		}

		return $this; // chainable
	}

	public function always($callback) {
		if ($this->state === self::STATE_RESOLVED) {
			Utility::forceInvoke($callback, $this->resolvedArgs);
		}
		elseif ($this->state === self::STATE_REJECTED) {
			Utility::forceInvoke($callback, $this->rejectedArgs);
		}
		else {
			$this->alwaysCallbacks[] = $callback;
		}

		return $this; // chainable
	}

	public function then($doneCallback, $failCallback) {
		$this->done($doneCallback);
		$this->fail($failCallback);

		return $this; // chainable
	}

}
