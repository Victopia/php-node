<?php
/* Service.php | Cater all web service functions. */

namespace framework;

use core\Net;
use core\Utility;

class Service {

  //--------------------------------------------------
  //
  //  Variables
  //
  //--------------------------------------------------

  private static $defaultHttpOptions = array(
    'type' => 'GET'
  );

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  /**
   * This will issue a local request to the server itself.
   *
   * @param $service (String) Typical service request path on this. e.g. "/class/method/param1/param2"
   * @param $options (Array) Optional, will do a GET request without any parameters by default.
   */
  static function redirect($service, $options = array()) {
    $options = self::createRedirectRequest($service, $options);

    $result = null;

    $options['success'] = function($response, $curlOptions) use($service, &$result) {
      // Not 2xx, should be an error.
      if ( $curlOptions['status'] >= 300 ) {
        throw new exceptions\ServiceException("Error making local request to $service, HTTP status: $curlOptions[status].");
      }

      $result = @json_decode($response, true);

      if ( $response && $response !== 'null' && $result === null ) {
        throw new exceptions\ServiceException('Response is not a well-formed JSON string: ' . $response);
      }
    };

    $options['failure'] = function($num, $str, $curlOptions) {
      // This should not occurs, errors are supposed to be thrown as an exception object.
      throw new exceptions\ServiceException("An error occurred when making local service redirection. #$num $str");
    };

    Net::httpRequest($options);

    return $result;
  }

  /**
   * Separated process to create a request object for convenience use in batch for core\Net::httpRequest();
   */
  static function createRedirectRequest($service, $options = array()) {
    if ( is_string($options) ) {
      switch ( strtoupper($options) ) {
        // Request methods, according to RFC2616 section 9.
        case 'GET':
        case 'POST':
        case 'DELETE':
        case 'HEAD':
        case 'OPTIONS':
        case 'PUT':
        case 'TRACE':
        case 'CONNECT':
          $options = array(
            'type' => $options
          );
          break;

        default:
          $options = array(
            'url' => $options
          );
          break;
      }
    }

    if ( strpos($service, 'http') !== 0 ) {
      // prepend '/' if not exists
      if ( strpos($service, '/') !== 0 ) {
        $service = "/$service";
      }

      $service = (@$_SERVER['HTTPS'] ? 'https' : 'http') . '://' . FRAMEWORK_SERVICE_HOSTNAME_LOCAL . $service;
    }

    $options['url'] = $service;

    // Must send this header to determine local redirection.
    @$options['headers'][] = 'Host: ' . FRAMEWORK_SERVICE_HOSTNAME;
    @$options['headers'][] = 'User-Agent: X-PHP';

    @$options['__curlOpts'][CURLOPT_REFERER] = FRAMEWORK_SERVICE_HOSTNAME_LOCAL;

    $options += self::$defaultHttpOptions;

    return $options;
  }

  /**
   * Instantiate target service class and call that method directly.
   *
   * Use this method instead of Service::redirect() to gain performance,
   * or when the current session and cookies are meant to persist, which is
   * not able by local redirection without the PHPSESSID hack.
   */
  static function call($service, $method, $parameters = array(), $options = array()) {
    self::requireService($service);

    /* Modified by Eric @ 21 Dec, 2012
       Uses core\Utility::forceInvoke(), to forcibly invoke regardless of method
       exists. This is to cope with implementations that tries to mimic a real
       RESTful with overloading. i.e. __call().

    $method = new \ReflectionMethod($service, $method);
    return $method->invokeArgs($service, $parameters);

       Modified by Eric @ 9 Jul, 2014
       Mask global request method when making plain calls.
    */
    if ( is_string($options) ) {
      $options = array( 'type' => $options );
    }

    if ( @$options['overrideMethod'] ) {
      $options['type'] = $options['overrideMethod'];
    }

    if ( isset($options['type']) ) {
      $reqMethod = Utility::cascade(@$_SERVER['REQUEST_METHOD'], 'GET');

      $_SERVER['REQUEST_METHOD'] = $options['type'];
    }

    if ( is_array(@$options['data']) ) {
      switch ( strtolower(@$options['type']) ) {
        case 'post':
          $dataRef = array(&$_POST, $_POST);
          break;

        case 'get':
        default:
          $dataRef = array(&$_GET, $_GET);
          break;
      }

      $dataRef[0] = $options['data'];
    }

    $parameters = Utility::wrapAssoc($parameters);

    $service = new $service();

    if (!Utility::isCLI() &&
      $service instanceof \framework\interfaces\IAuthorizableWebService &&
      $service->authorizeMethod($method, $parameters) === false) {
      throw new \framework\exceptions\ResolverException( 401 );
    }

    $ret = Utility::forceInvoke(array($service, $method), $parameters);

    if ( isset($reqMethod) ) {
      $_SERVER['REQUEST_METHOD'] = $reqMethod;
    }

    if ( isset($dataRef) ) {
      $dataRef[0] = $dataRef[1];
    }

    return $ret;
  }

  /**
   * Independent service path resolve mechanism for web services.
   *
   * This is currently isolated from the __autoLoad() method resides in
   * scripts/Initialize.php, this is to avoid (or possibly allows) name
   * conflicts with internal classes.
   *
   * While it is best to avoid using identical names between services and
   * internal classes, it still allows so, but only when used with care.
   */
  static function requireService($service) {
    $servicePath = DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $service) . '.php';

    $servicePath = realpath(FRAMEWORK_PATH_ROOT . FRAMEWORK_PATH_SERVICES . $servicePath);

    if ( !file_exists($servicePath) ) {
      throw new \framework\exceptions\ServiceException('Target service file not found.');
    }

    require_once($servicePath);
  }
}
