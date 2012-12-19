<?php
/*! TempalteResovler.php \ IRequestResolver
 *
 *  Request resolver for Templates.
 */

namespace resolvers;

class TemplateResolver implements \framework\interfaces\IRequestResolver {
	//--------------------------------------------------
	//
	//  Methods: IPathResolver
	//
	//--------------------------------------------------
	
	public
	/* Boolean */ function canResolve($path) {
		return file_exists(sprintf(FRAMEWORK_TEMPLATE_PATH, $path));
	}
	
	public 
	/* String */ function resolve($path) {
		$res = sprintf(FRAMEWORK_TEMPLATE_PATH, $path);
		
		if (!file_exists($res)) {
			return FALSE;
		}
		
		$res = file_get_contents($res);
		
		$res = new Template($res);
		
		//TODO: Data/View
		$res = Data::getView(framework\Session::current());
		
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
		return new TemplateResolver();
	}
}