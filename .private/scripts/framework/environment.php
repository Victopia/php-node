<?php
/* environment.php | Perform base functionality check, terminate the script if any of these doesn't presence. */

use core\Utility;

//--------------------------------------------------
//
//  Environment definitions
//
//--------------------------------------------------

// Align current directory and REQUEST_URI,
// this approach does not cater any virtual directories, kinda bad.
if (isset($_SERVER['REQUEST_URI']) && realpath(FRAMEWORK_PATH_VIRTUAL)) {
  $virtualPath = realpath('/' . FRAMEWORK_PATH_VIRTUAL);

  if (strpos($_SERVER['REQUEST_URI'], $virtualPath) === 0) {
    $_SERVER['REQUEST_URI'] = '/' . substr($_SERVER['REQUEST_URI'], count($virtualPath));
  }

  unset($virtualPath);
}

// CRYPT_SHA512
if (CRYPT_SHA512 !== 1) {
  throw new Exception('CRYPT_SHA512 method is not supported, please enable it on your system.');
}

if ( Utility::isCLI() ) {
  // Allow more nesting for functional programming.
  ini_set('xdebug.max_nesting_level', 50000);

  // Turn on garbage collection
  gc_enable();
}

// Starts HTTP session for browsers.
else {
  session_start();

  if (function_exists('getallheaders')) {
    // Parse custom headers
    $_REQUEST['HEADERS'] = array();

    foreach(getallheaders() as $key => $value) {
      if (preg_match(FRAMEWORK_CUSTOM_HEADER_PATTERN, $key)) {
        $_REQUEST['HEADERS'][$key] = $value;
      }
    } unset($key, $value);

    if (!$_REQUEST['HEADERS']) {
      unset($_REQUEST['HEADERS']);
    }
  }
}

//--------------------------------------------------
//
//  Compatibility
//
//--------------------------------------------------

if ( !function_exists('curl_file_create') ) {
  function curl_file_create($path, $type = NULL, $name = NULL) {
    $ret = "@$path";

    if ( $type ) {
      $ret.= ";type=$type";
    }

    return $ret;
  }
}

//--------------------------------------------------
//
//  Development cycle functions
//
//--------------------------------------------------

function triggerDeprecate($successor = '') {
  $message = Utility::getCallee();

  $message = "Function $message[class]::$message[function]() has been deprecated";

  if ($successor) {
    $message.= ", use its successor $successor() instead";
  }

  trigger_error("$message.", E_USER_DEPRECATED);
}
