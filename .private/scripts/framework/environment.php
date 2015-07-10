<?php
/*! environment.php | Perform base functionality check, terminate the script if any of these doesn't presence. */

use core\Utility;

use framework\Configuration as conf;
use framework\System;

//--------------------------------------------------
//
//  Environment definitions
//
//--------------------------------------------------

// CRYPT_SHA512
if ( !constant('CRYPT_SHA512') ) {
  throw new Exception('CRYPT_SHA512 method is not supported, please enable it on your system.');
}

// Sets default Timezone, defaults to development locale (Hong Kong).
date_default_timezone_set(
  conf::get('system.locale::timezone', 'Asia/Hong_Kong')
  );

// Allow more nesting for functional programming.
ini_set('xdebug.max_nesting_level', 1000);

if ( constant('PHP_SAPI') == 'cli' ) {
  // Turn on garbage collection
  gc_enable();
}
else {
  // Starts HTTP session for browsers.
  session_start();
}

//--------------------------------------------------
//
//  Compatibility
//
//--------------------------------------------------

if ( !function_exists('curl_file_create') ) {
  function curl_file_create($path, $type = null, $name = null) {
    $ret = "@$path";

    if ( $type ) {
      $ret.= ";type=$type";
    }

    return $ret;
  }
}

if ( !function_exists('http_build_url') ) {
  function http_build_url($url = null, $parts = array()) {
    if ( is_array($url) ) {
      $parts = $url;
      $url = null;
    }

    if ( $url ) {
      $url = parse_url($url);
    }

    $url = $parts + (array) $url;

    if ( empty($url['scheme']) ) {
      $url['scheme'] = 'http://';
    }
    else if ( substr($url['scheme'], -3) != '://' ) {
      $url['scheme'].= '://';
    }

    // Build the url according to parts.
    if ( !empty($url['user']) ) {
      if ( !empty($url['pass']) ) {
        $url['user'] = "$url[user]:";
        $url['pass'] = "$url[pass]@";
      }
      else {
        $url['user'] = "$url[user]@";
      }
    }

    if ( empty($url['host']) ) {
      $url['host'] = System::getHostname();
    }

    if ( !empty($url['port']) && $url['port'] != 80 ) {
      $url['port'] = ":$url[port]";
    }
    else {
      unset($url['port']);
    }

    if ( substr(@$url['path'], 0, 1) != '/' ) {
      $url['path'] = @"/$url[path]";
    }

    if ( isset($url['query']) ) {
      if ( is_array($url['query']) ) {
        $url['query'] = http_build_query($url['query']);
      }

      if ( $url['query'] && $url['query'][0] != '?' ) {
        $url['query'] = "?$url[query]";
      }
    }

    if ( !empty($url['fragment']) && $url['fragment'][0] != '#' ) {
      $url['fragment'] = "#$url[fragment]";
    }

    return @"$url[scheme]$url[user]$url[pass]$url[host]$url[port]$url[path]$url[query]$url[fragment]";
  }
}

//--------------------------------------------------
//
//  Development cycle functions
//
//--------------------------------------------------

function triggerDeprecate($successor = '') {
  $message = Utility::getCallee();

  $message = implode('::', array_filter([@$message['class'], @$message['function']]));

  $message = "Function $message() has been deprecated";

  if ($successor) {
    $message.= ", use its successor $successor instead";
  }

  trigger_error("$message.", E_USER_DEPRECATED);
}
