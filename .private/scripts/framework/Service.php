<?php
/*! Service.php | Cater all web service functions. */

namespace framework;

class Service {

  //--------------------------------------------------
  //
  //  Variables
  //
  //--------------------------------------------------

  private static $defaultHttpOptions = array(
    'type' => 'GET'
  , 'headers' => array(
      "Host: {gethostname()}"
    )
  );

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  /**
   * @beta
   *
   * This will issue a local request to the server itself.
   *
   * @param $service (String) Typical service request path on this. e.g. "/class/method/param1/param2"
   * @param $httpOptions (Array) Optional, will do a GET request without any parameters by default.
   */
  static function localRequest($service, $httpOptions = array()) {
    $result = NULL;

    // remove prefix '/'
    if (strpos($service, '/') !== 0) {
      $service = "/$service";
    }

    if (is_string($httpOptions)) {
      switch (strtoupper($httpOptions)) {
        // Request methods, according to RFC2616 section 9.
        case 'GET':
        case 'POST':
        case 'DELETE':
        case 'HEAD':
        case 'OPTIONS':
        case 'PUT':
        case 'TRACE':
        case 'CONNECT':
          $httpOptions = array(
            'type' => $httpOptions
          );
          break;

        default:
          $httpOptions = array(
            'url' => $httpOptions
          );
          break;
      }
    }

    $httpOptions['url'] = (@$_SERVER['HTTPS'] ? 'https' : 'http') . '://' . gethostname() . $service;

    $httpOptions['__curlOpts'] = array(
      CURLOPT_REFERER => gethostname()
    );

    $httpOptions['callbacks'] = array(
      'success' => function($response, $curlOptions) use($service, &$result) {
        // Not 2xx, should be an error.
        if ($curlOptions['status'] >= 300) {
          throw new exceptions\ServiceException("Error making local request to $service, HTTP status: $curlOptions[status].");
        }

        $result = json_decode($response, TRUE);
      }
    , 'failure' => function($num, $str, $curlOptions) {
        // This should not occurs, errors are supposed to be thrown as an exception object.
        throw new exceptions\ServiceException("An error occurred when making local service redirection. #$num $str");
      }
    );

    $httpOptions += self::$defaultHttpOptions;

    \core\Net::httpRequest($httpOptions);

    return $result;
  }

  /**
   * Instantiate target service class and call that method directly.
   *
   * Use this method instead of Service::localRequest() to gain performance,
   * or when the current session and cookies are meant to persist, which is
   * not able by local redirection without the PHPSESSID hack.
   */
  static function call($service, $method, $parameters = array()) {
    self::requireService($service);

    $service = new $service();

    if (!\utils::isCLI() &&
      $service instanceof \framework\interfaces\IAuthorizableWebService &&
      $service->authorizeMethod($method, $parameters) === FALSE) {
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

    if (!file_exists($servicePath)) {
      throw new \framework\exceptions\ServiceException('Target service file not found.');
    }

    require_once($servicePath);
  }
}