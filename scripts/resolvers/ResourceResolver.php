<?php
/*! ReosurceResolver.php \ IRequestResolver
 *
 *  Database resource from requesting path.
 *
 *  Path format: /@Image/12
 */

namespace resolvers;

class ResourceResolver implements \framework\interfaces\IRequestResolver {
	//--------------------------------------------------
	//
	//  Methods: IPathResolver
	//
	//--------------------------------------------------

	public
	/* String */ function resolve($path) {
		$res = sprintf(FRAMEWORK_TEMPLATE_PATH, $path);

		if (!file_exists($res)) {
			return FALSE;
		}

		$res = file_get_contents($res);

		$res = new Template($res);

		//TODO: Data/View
		$res = Data::getView(\framework\Session::current());

		$res->render();
	}

	//--------------------------------------------------
	//
	//  Methods: Serializable
	//
	//--------------------------------------------------

	public
	/* String */ function serialize() {
		return NULL;
	}

	public
	/* void */ function unserialize($serial) {
		return new ResourceResolver();
	}
}