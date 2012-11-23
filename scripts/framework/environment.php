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

// CRYPT_SHA512
if (CRYPT_SHA512 !== 1) {
	throw new Exception('CRYPT_SHA512 method is not supported, please enable it on your system.');
}

// Starts HTTP session for browsers.
if (!utils::isCLI()) {
	session_start();

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