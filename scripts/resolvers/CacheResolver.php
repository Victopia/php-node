<?php

namespace resolvers;

class CacheResolver implements \framework\interfaces\IRequestResolver {
	//--------------------------------------------------
	//
	//  Methods: IPathResolver
	//
	//--------------------------------------------------
	
	/**
	 * Checks whether an update is available.
	 */
	public
	/* Boolean */ function resolve($path) {
		$res = \framework\Cache::get($path);
		
		if ($res === NULL || $res === FALSE) {
			return FALSE;
		}
		
		include($res);
	}
	
	//--------------------------------------------------
	//
	//  Methods: Serializable
	//
	//--------------------------------------------------
	
	public
	/* String */ function serialize() {
		return serialize($this);
	}
	
	public
	/* void */ function unserialize($serial) {
		return unserialize($serial);
	}
}