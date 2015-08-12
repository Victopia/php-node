<?php
/* Service.php | Cater all web service functions. */

namespace framework;

use core\Net;
use core\Utility;

use framework\Configuration as conf;

use framework\exceptions\ServiceException;

class Service {

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  private static $defaultOptions = array(
    'method' => 'get'
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
    $hostname = System::getHostname('service');
    if ( !$hostname ) {
      throw new ServiceException('Service hostname undefined.');
    }

    if ( is_string($options) ) {
      $options = array( 'method' => $options );
    }

    if ( isset($options['type']) && empty($options['method']) ) {
      $options['method'] = $options['type'];
    }

    $options = (array) $options + self::$defaultOptions;

    $prefix = conf::get('web::resolvers.service.prefix', '/service');
    $options['uri'] = array(
        'scheme' => (bool) @$options['secure']? 'https': 'http'
      , 'host' => $hostname
      , 'path' => "$prefix/$service/$method/" . implode('/', array_map('urlencode', (array) $parameters))
      );
    unset($prefix);

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
    $serviceRequest->__local = true;

    return $serviceRequest->send($serviceResolver, $serviceResponse);
  }

}
