<?php
/*! WebServiceResolver.php \ IRequestResovler
 *
 *  Eliminate the needs of service gateway.
 *
 *  CAUTION: This class calls global function
 *           redirect() and terminates request
 *           directly when client requests for
 *           unauthorized services.
 */

namespace resolvers;

class WebServiceResolver implements \framework\interfaces\IRequestResolver {

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($pathPrefix) {
    if (!$pathPrefix) {
      throw new \framework\exceptions\ResolverException('Please provide a proper path prefix for ResourceResolver.');
    }

    $this->pathPrefix = $pathPrefix;
  }

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  private $pathPrefix = NULL;

  //--------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //--------------------------------------------------

  public
  /* String */ function resolve($path) {
    // Request URI must start with the specified path prefix. e.g. /:resource/.
    if (!$this->pathPrefix || 0 !== strpos($path, $this->pathPrefix)) {
      return FALSE;
    }

    $path = substr($path, strlen($this->pathPrefix));

    $path = urldecode($path);

    // Resolve target service and apply appropiate parameters
    preg_match('/^([^\/]+)\/([^\/\?,]+)(\/[^\?]+)?/', $path, $matches);

    // Chain off to 404 instead of the original "501 Method Not Allowed".
    if (count($matches) < 3) {
      return FALSE;
    }

    $classname = '\\' . $matches[1];
    $function = $matches[2];

    \service::requireService($classname);

    if (!class_exists($classname)) {
      return FALSE;
    }

    $instance = new $classname();

    // Why instanceof fails on interfaces? God fucking knows!
    if (!is_a($instance, 'framework\interfaces\IWebService')) {
      return FALSE;
    }

    if (!method_exists($classname, $function) && !is_callable(array($instance, $function))) {
      throw new \framework\exceptions\ResolverException( 501 );
    }

    if (isset($matches[3])) {
      $parameters = explode('/', substr($matches[3], 1));
    }
    else {
      $parameters = array();
    }

    unset($matches);

    // Allow constants and primitive values on REST requests
    $parameters = array_map(function($value) {
      if (preg_match('/^\:([\d\w]+)$/', $value, $matches)) {
        if (strcasecmp(@$matches[1], 'true') == 0) {
          $value = TRUE;
        }
        elseif (strcasecmp(@$matches[1], 'false') == 0) {
          $value = FALSE;
        }
        elseif (strcasecmp(@$matches[1], 'null') == 0) {
          $value = NULL;
        }
        elseif (!defined(@$matches[1])) {
          throw new \framework\exceptions\ResolverException('Error resolving web service parameters, undefined constant ' . $matches[1]);
        }
        else {
          $value = constant(@$matches[1]);
        }
      }

      return $value;
    }, $parameters);

    // Allow constants and primitive values on $_GET parameters,
    // map on both keys and values.

    /* Note by Eric @ 14 Feb, 2013
       CAUTION: Should really prevent NODE_FIELD_RAWQUERY, serious security problem!
                These constant things are originally designed for an OR search upon
                eBay items.
    */
    array_walk($_GET, function(&$value, $key) {
      if (is_array($value)) {
        return;
      }

      if (preg_match('/^\:([\d\w+]+)$/', $value, $matches)) {
        if (strcasecmp(@$matches[1], 'true') == 0) {
          $value = TRUE;
        }
        elseif (strcasecmp(@$matches[1], 'false') == 0) {
          $value = FALSE;
        }
        elseif (strcasecmp(@$matches[1], 'null') == 0) {
          $value = NULL;
        }
        elseif (!defined(@$matches[1])) {
          throw new \framework\exceptions\ResolverException('Error resolving web service parameters, undefined constant ' . $matches[1]);
        }
        else {
          $value = constant(@$matches[1]);
        }
      }

      if (preg_match('/^\:([\d\w+]+)$/', $key, $matches)) {
        unset($_GET[$key]);

        if (!defined(@$matches[1])) {
          throw new \framework\exceptions\ResolverException('Error resolving web service parameters, undefined constant ' . $matches[1]);
        }
        else {
          $_GET[constant(@$matches[1])] = $value;
        }
      }
    });

    // Shooto!
    $response = \service::call($classname, $function, $parameters);

    // Access log
    \log::write("[WebService] $classname->$function", 'Access', array_filter(array(
        'parameters' => $parameters
      , 'response' => $response
      )));

    // unset($instance); unset($function); unset($parameters);

    // Return nothing when the service function returns nothing,
    // this favors file download and other non-JSON outputs.
    if ($response !== NULL) {
      header('Content-Type: application/json; charset=utf-8', true);

      // JSON encode the result and response to client.
      /* Note by Eric @ 11 Dec, 2012
          Do not use JSON_NUMERIC_CHECK option, this might corrupt
          values. Should ensure numeric on insertion instead.
      */
      $response = json_encode($response);

      // JSONP request, do it so.
      if (@$_GET['callback']) {
        $response = "$_GET[callback]($response)";
      }

      echo $response;
    }
  }

}