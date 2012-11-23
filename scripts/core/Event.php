<?php
/*! Event.php
 *
 *  Basic event class.
 */

class Event {
	
	//--------------------------------------------------
	//
	//  Constructor
	//
	//--------------------------------------------------
	
	public function __construct($type) {
		$this->type = $type;
	}
	
	//--------------------------------------------------
	//
	//  Properties
	//
	//--------------------------------------------------
	
	private $type = NULL;
	
	public function getType() {
		return $this->type;
	}
	
}