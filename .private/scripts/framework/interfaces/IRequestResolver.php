<?php
/*! IPathResolver | Path resolver interface
 *
 *  Resolvers must implements this interface before it can be
 *  added to the resolvers chain in the request gateway.
 */

namespace framework\interfaces;

interface IRequestResolver {

	//--------------------------------------------------
	//
	//  Methods
	//
	//--------------------------------------------------

	/**
	 * Process and prints the layout.
	 *
	 * return FALSE will break the resolver chain.
	 *
	 * return values other than FALSE will chain the value to latter processors.
	 */
	public
	/* Boolean */ function resolve($path);

}