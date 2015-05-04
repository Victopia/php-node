<?php
/*! WebServiceResolver.php \ IRequestResovler | Resolves service API requests. */

namespace resolvers;

use core\Log;
use core\Utility;

use framework\Request;
use framework\Response;
use framework\Service;
use framework\System;

use framework\exceptions\ResolverException;

class WebServiceResolver implements \framework\interfaces\IRequestResolver {

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  private $pathPrefix;

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($pathPrefix) {
    if ( !$pathPrefix ) {
      throw new ResolverException('Please provide a proper path prefix for ResourceResolver.');
    }

    $this->pathPrefix = $pathPrefix;
  }

  //--------------------------------------------------
  //
  //  Methods: IRequestResolver
  //
  //--------------------------------------------------

  public /* String */
  function resolve(Request $request, Response $response) {
    $path = $request->uri('path');

    // Request URI must start with the specified path prefix. e.g. /:resource/.
    if ( !$this->pathPrefix || 0 !== strpos($path, $this->pathPrefix) ) {
      return;
    }

    $res = substr($path, strlen($this->pathPrefix));
    $res = urldecode($res);

    // Resolve target service and apply appropiate parameters
    preg_match('/^([^\/]+)(?:\/([^\/\?,]+))?(\/[^\?]+)?\/?/', $res, $matches);

    // Chain off to 404 instead of the original "501 Method Not Allowed".
    if ( count($matches) < 2 ) {
      return;
    }

    $classname = '\\' . $matches[1];
    $function = @$matches[2];

    Service::requireService($classname);
    if ( !class_exists($classname) ) {
      return;
    }

    if ( is_a($classname, 'framework\WebService', true) ) {
      $instance = new $classname($request, $response);
    }
    else {
      $instance = new $classname();
    }

    if ( !($instance instanceof \framework\interfaces\IWebService) ) {
      return;
    }

    // If the class is invokeable, it takes precedence.
    if ( is_callable($instance) ) {
      if ( $function ) {
        @$matches[3] = "/$function/$matches[3]";
      }

      $function = '~';
    }
    else
    if ( !method_exists($classname, $function) && !is_callable(array($instance, $function)) ) {
      $response->status(501);
      return; // 404
    }

    if ( isset($matches[3]) ) {
      $parameters = explode('/', substr($matches[3], 1));
    }
    else {
      $parameters = array();
    }

    unset($matches);

    // Allow intelligible primitive values on REST requests
    $parameters = array_map(function($value) {
      if ( preg_match('/^\:([\d\w]+)$/', $value, $matches) ) {
        if ( strcasecmp(@$matches[1], 'true') == 0 ) {
          $value = true;
        }
        else if ( strcasecmp(@$matches[1], 'false') == 0 ) {
          $value = false;
        }
        else if ( strcasecmp(@$matches[1], 'null') == 0 ) {
          $value = null;
        }
      }

      return $value;
    }, $parameters);

    // Allow constants and primitive values on $_GET parameters,
    // map on both keys and values.

    /*! Note by Eric @ 14 Feb, 2013
     *  CAUTION: Should really prevent Node::FIELD_RAWQUERY, serious security problem!
     *           These constant things are originally designed for an OR search upon
     *           eBay items.
     */
    array_walk($_GET, function(&$value, $key) {
      if ( is_array($value) ) {
        return;
      }

      if ( preg_match('/^\:([\d\w+]+)$/', $value, $matches) ) {
        if ( strcasecmp(@$matches[1], 'true') == 0 ) {
          $value = true;
        }
        elseif ( strcasecmp(@$matches[1], 'false') == 0 ) {
          $value = false;
        }
        elseif ( strcasecmp(@$matches[1], 'null') == 0 ) {
          $value = null;
        }
        elseif ( !defined(@$matches[1]) ) {
          throw new ResolverException('Error resolving web service parameters, undefined constant ' . $matches[1]);
        }
        else {
          $value = constant(@$matches[1]);
        }
      }

      if ( preg_match('/^\:([\d\w+]+)$/', $key, $matches) ) {
        unset($_GET[$key]);

        if ( !defined(@$matches[1]) ) {
          throw new ResolverException('Error resolving web service parameters, undefined constant ' . $matches[1]);
        }
        else {
          $_GET[constant(@$matches[1])] = $value;
        }
      }
    });

    if ($request->client('type') != 'cli' &&
      $instance instanceof \framework\interfaces\IAuthorizableWebService &&
      !$instance instanceof \framework\AuthorizableWebService &&
      $instance->authorizeMethod($function, $parameters) === false) {
      $response->status(401);
      return;
    }

    // Access log
    if ( System::environment() == 'debug' || !Utility::isLocal() ) {
      Log::write(sprintf('[WebService] %s %s->%s', $request->method(), $classname, $function),
        'Access', array_filter(array('parameters' => $parameters)));
    }

    // Shooto!
    $serviceResponse = call_user_func_array($function == '~' ? $instance : [$instance, $function], $parameters);

    // Return nothing when the service function returns nothing,
    // this favors file download and non-JSON outputs.
    if ( $serviceResponse !== null ) {
      // JSON encode the result and response to client.
      $response->header('Content-Type', 'application/json');
      $response->send($serviceResponse); // This sets repsonse code to 200
    }
    else if ( !$response->status() ) {
      $response->status(204);
    }
  }

}
