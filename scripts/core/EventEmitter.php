<?php
/*! EventEmitter.php
 *
 *  Basic event emitter class, implemented as the specifications in nodejs.
 */

namespace core;

class EventEmitter implements interfaces\IEventEmitter {

	private $listeners = array();

	public function on($eventName, $listener) {
		$this->addEventListener($eventName, $listener);
	}

	public function addEventListener($eventName, $listener) {
		if (!isset($this->listeners[$eventName])) {
			$this->listeners[$eventName] = array();
		}

		if ($this->hasEventListener($eventName, $listener)) {
			return;
		}

		$this->listeners[$eventName][] = $listener;
	}

	public function removeEventListener($eventName, $listener) {
		if (!isset($this->listeners[$eventName])) {
			return;
		}

		while (FALSE !== ($index = array_search($listener, $this->listeners[$eventName]))) {
			array_splice($this->listeners[$eventName], $index, 1);
		}
	}

	public function removeAllListeners($eventName = NULL) {
		if ($eventName !== NULL) {
			unset($this->listeners[$eventName]);
		}
		else {
			$this->listeners = array();
		}
	}

	public function hasEventListener(&$eventName, &$listener) {
		return in_array($listener, $this->listeners[$eventName]);
	}

	public function dispatchEvent($event, $parameters = NULL) {
		if ($event instanceof Event) {
			$eventName = $event->getType();
		}
		else {
			$eventName = (string) $event;
		}

		$listeners = (array) @$this->listeners[$eventName];

		array_walk($listeners, function($listener) use($parameters) {
			\utils::forceInvoke($listener, $parameters);
		});
	}

}