<?php
/*! environment.php
 *
 *  Perform base functionality check, terminate the script if any of these doesn't presence.
 */

//--------------------------------------------------
//
//  Environment definitions
//
//--------------------------------------------------

// Align current directory and REQUEST_URI
if (@$_SERVER['DOCUMENT_ROOT'] && strpos(getcwd(), $_SERVER['DOCUMENT_ROOT']) === 0) {
  $basePath = substr(getcwd(), strlen($_SERVER['DOCUMENT_ROOT']));

  $reqUri = @$_SERVER['REQUEST_URI'];

  if (strpos($reqUri, $basePath) === 0) {
    $_SERVER['REQUEST_URI'] = substr($reqUri, strlen($basePath));
  }

  unset($reqUri);
}

// CRYPT_SHA512
if (CRYPT_SHA512 !== 1) {
	throw new Exception('CRYPT_SHA512 method is not supported, please enable it on your system.');
}

// Starts HTTP session for browsers.
if (!utils::isCLI()) {
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