<?php
/* Resolver.php | Resolve a path from registered processors. */

namespace framework;

class Resolver {

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   */
  protected static $activeInstances = array();

  /**
   * Retrieve the resolver instance currently active from calling run().
   */
  public static function getActiveInstance() {
    return end(self::$activeInstances);
  }

  /**
   * @private
   */
  protected $request;

  public function request() {
    return $this->request;
  }

  /**
   * @private
   */
  protected $response;

  public function response() {
    return $this->response;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Initiates the resolve-response mechanism with current context.
   */
  public /* void */ function run(Request $request = null, Response $response = null) {
    $this->request = &$request;
    if ( $request === null ) {
      $request = new Request($this);
    }

    $this->response = &$response;
    if ( $response === null ) {
      $response = new Response();
    }

    array_push(self::$activeInstances, $this);

    $this->resolve();

    array_pop(self::$activeInstances);
  }

  /**
   * @private
   */
  protected $resolvers = array();

  /**
   * Register a resolver.
   *
   * @param IRequestResolver $resolver
   *
   * @param Integer $weight Weight to determine if a resolver chains before
   *                        other, the bigger the earlier.
   */
  public /* boolean */ function registerResolver(interfaces\IRequestResolver $resolver, $weight = 10) {
    $resolvers = &$this->resolvers;

    $resolver->weight = $weight;

    if ( array_search($resolver, $resolvers) === false ) {
      $resolvers[] = $resolver;

      usort($resolvers, sortsPropDescend('weight'));

      return true;
    }

    return false;
  }

  /**
   * Use registered resolvers to process request.
   *
   * A resolver a return a path to further chain the requested resource,
   * optionally process the reuqested resource and may or may not change the
   * requested path afterwards.
   *
   * The resolving chain will continue to work as long as the returned value is
   * a string and not empty, until there are no more resolvers it will return 404.
   */
  private /* void */ function resolve() {
    $request = $this->request;
    $response = $this->response;
    foreach ( $this->resolvers as $resolver ) {
      try {
        $resolver->resolve($request, $response);
      }
      catch (exceptions\ResolverException $e) {
        $status = $e->statusCode();

        if ( $status === null ) {
          throw $e;
        }

        $response->status($e->statusCode());

        return; // Only way to break the handler chain.
      }
    }
  }

}
