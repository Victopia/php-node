<?php
/* EventEmitter.php | Basic event emitter class, inspired by nodejs. */

namespace core;

/**
 * EventEmitter class.
 *
 * @author Vicary Archangel <vicary@victopia.org>
 */
class EventEmitter implements interfaces\IEventEmitter {

  private $listeners = array();

  public function on($eventName, $listener) {
    $this->addEventListener($eventName, $listener);
  }

	public function once($eventName, $listener) {
	  $onceHandler = function() use(&$onceHandler, $listener) {
    	call_user_func_array($listener, func_get_args());

    	$this->off($eventName, $onceHandler);
  	};

  	$this->on($eventName, $onceHandler);
	}

	public function off($eventName, $listener = null) {
  	if ($listener) {
    	$this->removeEventListener($eventName, $listener);
  	}
  	else {
    	$this->removeAllListeners($eventName);
  	}
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

    while (false !== ($index = array_search($listener, $this->listeners[$eventName]))) {
      array_splice($this->listeners[$eventName], $index, 1);
    }
  }

  public function removeAllListeners($eventName = null) {
    if ($eventName !== null) {
      unset($this->listeners[$eventName]);
    }
    else {
      $this->listeners = array();
    }
  }

  public function hasEventListener(&$eventName, &$listener) {
    return in_array($listener, $this->listeners[$eventName]);
  }

  public function dispatchEvent($event, $parameters = null) {
    if ($event instanceof Event) {
      $eventName = $event->getType();
    }
    else {
      $eventName = (string) $event;
    }

    $listeners = (array) @$this->listeners[$eventName];

    array_walk($listeners, function($listener) use($parameters) {
      Utility::forceInvoke($listener, $parameters);
    });
  }

}
