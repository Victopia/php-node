<?php
/* IEventEmitter.php | Base interface for event handling implementations. */

namespace core\interfaces;

/**
 * IEventEmitter interface.
 *
 * @author Vicary Archagnel <vicary@victopia.org>
 */
interface IEventEmitter {

	/**
	 * Push the specified listener to the stack of specified event.
	 *
	 * The same callback could be passed in multiple times, and it
	 * will be fired with times accordingly.
	 */
	public function
	/* void */ addEventListener($eventName, $listener);

	/**
	 * Shorthand of addEventListener().
	 */
	public function
	/* void */ on($eventName, $listener);

	/**
	 * Adds a one time listener for the event. This listener is
	 * invoked only the next time the event is fired, after
	 * which it is removed.
	 */
	public function
	/* void */ once($eventName, $listener);

  /**
   * Shorthand of removeEventListener().
   */
  public function
  /* void */ off($eventName, $listener = NULL);

	/**
	 * Remove specified listener from the callback stack if exists.
	 */
	public function
	/* void */ removeEventListener($eventName, $listener);

	/**
	 * Remove all event listeners, or of a specific event.
	 */
	public function
	/* void */ removeAllListeners($eventName = NULL);

	/**
	 * Remove target or all event listeners.
	 *
	 * Shorthand for removeEventListener and removeAllListeners.
	 */
  public function
  /* void */ off($eventName, $listener = NULL);

	/**
	 * Returns TRUE when this object has listeners attached,
	 * FALSE otherwise.
	 */
	public function
	/* boolean */ hasEventListener(&$eventName, &$listener);

	/**
	 * This method should trigger all registered
	 * listener callbacks of the specified type of event.
	 *
	 * Optionally it takes parameters which will pass
	 * along with the event call.
	 *
	 * * Optimally all of the extra data should be passed
	 * inside the event object instead of extra parameters.
	 */
	public function
	/* void */ dispatchEvent($event, $arguments = NULL);

}