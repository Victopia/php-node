<?php
/*! Resolver.php | Request Resolver
 *
 * Resolve a path from registered processors.
 */

namespace framework;

class Resolver {
	private static $resolvers = array();
	
	/**
	 * Use registered resolvers to process target path.
	 *
	 * If no resolvers can handle the provided path, FALSE is returned.
	 */
	public static
	/* String */ function resolve($path) {
		$resolvers = self::retrieveResolvers();
		
		foreach ($resolvers as $resolver) {
			try {
				$res = $resolver->resolve($path);
			}
			catch (exceptions\ResolverException $e) {
				return $e->statusCode();
			}
			
			// Processed, return TRUE.
			if ($res !== FALSE) {
				return TRUE;
			}
		}
		
		// Unresolved, return 404 status code.
		return 404;
	}
	
	/**
	 * Register a resolver.
	 *
	 * @param IRequestResolver $resolver
	 *
	 * @param Integer $weight Weight to determine if a resolver chains before 
	 *                        other, the bigger the earlier.
	 */
	public static
	/* Boolean */ function registerResolver(interfaces\IRequestResolver $resolver, $weight = 10) {
		$resolvers = & self::$resolvers;
		
		$resolver->weight = $weight;
		
		if (array_search($resolver, $resolvers) === FALSE) {
			$resolvers[] = $resolver;
			
			usort($resolvers, 
				create_function('$subject, $object', '
				if ($subject->weight == $object->weight) {
					return 0;
				}
				else {
					return $subject->weight < $object->weight ? 1 : 0;
				}'));
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * @private
	 */
	private static
	/* Array */ function retrieveResolvers() {
		return self::$resolvers;
	}
}