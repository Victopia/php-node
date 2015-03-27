<?php
/* Resolver.php | Resolve a path from registered processors. */

namespace framework;

use framework\interfaces\IRequestResolver;

class Resolver {
  private static $resolvers = array();

  /**
   * Use registered resolvers to process target path.
   *
   * A resolver a return a path to further chain the requested resource,
   * optionally process the reuqested resource and may or may not change the
   * requested path afterwards.
   *
   * The resolving chain will continue to work as long as the returned value is
   * a string and not empty, until there are no more resolvers it will return 404.
   */
  public static
  /* String */ function resolve($path) {
    $resolvers = self::retrieveResolvers();

    foreach ( $resolvers as $resolver ) {
      try {
        $res = $resolver->resolve($path);
      }
      catch (exceptions\ResolverException $e) {
        $status = $e->statusCode();

        if ( $status === null ) {
          throw $e;
        }

        return $e->statusCode();
      }

      if ( is_string($res) && $res ) {
        $path = $res;
      }
      // Processed, return true.
      else {
        return true;
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
  /* Boolean */ function registerResolver(IRequestResolver $resolver, $weight = 10) {
    $resolvers = & self::$resolvers;

    $resolver->weight = $weight;

    if ( array_search($resolver, $resolvers) === false ) {
      $resolvers[] = $resolver;

      usort($resolvers, sortsPropDescend('weight'));

      return true;
    }

    return false;
  }

  /**
   * @private
   */
  private static
  /* Array */ function retrieveResolvers() {
    return self::$resolvers;
  }
}
