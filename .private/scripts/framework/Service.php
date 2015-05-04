<?php
/* Service.php | Cater all web service functions. */

namespace framework;

use core\Net;
use core\Utility;

use framework\exceptions\ServiceException;

class Service {

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  private static $defaultOptions = array(
    'type' => 'get'
  );

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Use the new resolving mechanism to call for local services.
   */
  static function call($service, $method, $parameters = array(), $options = array()) {
    self::requireService($service);

    if ( is_string($options) ) {
      $options = array( 'type' => $options );
    }

    $options = (array) $options + self::$defaultOptions;

    $options['uri'] = array(
        'scheme' => (bool) @$options['secure']? 'https': 'http'
      , 'host' => System::getHostname()
      , 'path' => "/service/$service/$method/" . implode('/', array_map('urlencode', (array) $parameters))
      );

    // Customizable Resolver
    if ( @$options['resolver'] instanceof Resolver ) {
      $serviceResolver = $options['resolver'];
    }
    else {
      $serviceResolver = Resolver::getActiveInstance();
    }
    unset($options['resolver']);

    if ( @$options['response'] instanceof Response ) {
      $serviceResponse = $options['response'];
    }
    else {
      $serviceResponse = new Response();
    }
    unset($options['response']);

    $serviceRequest = new Request($options);

    // Explicitly force this request to be local.
    $serviceRequest->isLocal = true;

    return $serviceRequest->send($serviceResolver, $serviceResponse);
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
   *
   * @param {string} $service FQCL of target service class.
   */
  static function requireService($service) {
    $service = array_merge(
      explode(DS, System::getRoot('service')),
      array_filter(explode('\\', "$service.php")));

    $service = implode(DS, $service);

    // $servicePath = DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $service) . '.php';
    // $servicePath = realpath(FRAMEWORK_PATH_ROOT . FRAMEWORK_PATH_SERVICES . $servicePath);

    if ( !file_exists($service) ) {
      throw new ServiceException('Target service file not found.');
    }

    require_once($service);
  }
}
