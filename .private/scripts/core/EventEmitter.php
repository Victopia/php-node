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

  public function off($eventName, $listener = NULL) {
    if ($listener === NULL) {
      $this->removeAllListeners($eventName);
    }
    else {
      $this->removeEventListener($eventName, $listener);
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
      Utility::forceInvoke($listener, $parameters);
    });
  }

}