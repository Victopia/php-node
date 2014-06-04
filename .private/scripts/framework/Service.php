<?php
/* Service.php | Cater all web service functions. */

namespace framework;

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
   * @deprecated
   *
   * The original localRequest has been renamed into redirect()
   * as it also supports external requests, acting more like
   * a shorthand of core/Net module now.
   *
   * This function exists for backward compatibility.
   */
  static function localRequest($service, $httpOptions = array()) {
    triggerDeprecate('redirect');

    return self::redirect($service, $httpOptions);
  }

  /**
   * @beta
   *
   * This will issue a local request to the server itself.
   *
   * @param $service (String) Typical service request path on this. e.g. "/class/method/param1/param2"
   * @param $options (Array) Optional, will do a GET request without any parameters by default.
   */
  static function redirect($service, $options = array()) {
    $result = null;

    if ( is_string($options) ) {
      switch (strtoupper($options)) {
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

      $service = (@$_SERVER['HTTPS'] ? 'https' : 'http') . '://' . FRAMEWORK_SERVICE_HOSTNAME . $service;
    }

    $options['url'] = $service;

    // Must send this header to determine local redirection.
    @$options['headers'][] = 'Host: ' . FRAMEWORK_SERVICE_HOSTNAME;
    @$options['headers'][] = 'User-Agent: X-PHP';

    @$options['__curlOpts'][CURLOPT_REFERER] = FRAMEWORK_SERVICE_HOSTNAME;

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

    $options += self::$defaultHttpOptions;

    \core\Net::httpRequest($options);

    return $result;
  }

  /**
   * Instantiate target service class and call that method directly.
   *
   * Use this method instead of Service::redirect() to gain performance,
   * or when the current session and cookies are meant to persist, which is
   * not able by local redirection without the PHPSESSID hack.
   */
  static function call($service, $method, $parameters = array()) {
    self::requireService($service);

    $service = new $service();

    if (!\utils::isCLI() &&
      $service instanceof \framework\interfaces\IAuthorizableWebService &&
      $service->authorizeMethod($method, $parameters) === false) {
      throw new \framework\exceptions\ResolverException( 401 );
    }

    /* Modified by Eric @ 21 Dec, 2012
        Uses core\Utility::forceInvoke(), to forcibly invoke
        regardless of method exists. This is to cope with
        implementations that tries to mimic a real RESTful
        with overloading. i.e. __call()

    $method = new \ReflectionMethod($service, $method);
    return $method->invokeArgs($service, $parameters);
    */
    return \utils::forceInvoke(array($service, $method), $parameters);
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
