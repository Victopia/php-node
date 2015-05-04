<?php
/*! IRequestResolver | Path resolver interface */

namespace framework\interfaces;

use framework\Request;
use framework\Response;

/**
 *  Resolvers must implements this interface before it can be
 *  added to the resolvers chain in the request gateway.
 */
interface IRequestResolver {

	//--------------------------------------------------
	//
	//  Methods
	//
	//--------------------------------------------------

	/**
	 * Process and prints the layout.
	 *
	 * return false will break the resolver chain.
	 *
	 * return values other than false will chain the value to latter processors.
	 */
	public /* mixed */ function resolve(Request $request, Response $response);

}
