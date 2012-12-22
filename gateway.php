<?php
/*! Gateway.php
 *
 *  Starting point of all URI access.
 */

//--------------------------------------------------
//
//  Initialization
//
//--------------------------------------------------
require_once('scripts/Initialize.php');

//------------------------------
//  Environment variables
//------------------------------
foreach ($_SERVER as $key => $value) {
	if ($key == 'REDIRECT_URL') continue;

	$res = preg_replace('/^REDIRECT_/', '', $key, -1, $count);

	if ($count > 0) {
		unset($_SERVER[$key]);

		$_SERVER[$res] = $value;
	}
}

if (isset($_SERVER['HTTP_STATUS'])) {
	redirect(intval($_SERVER['HTTP_STATUS']));
}

// Error & Exception handling.
framework\Exceptions::setHandlers();

//------------------------------
//  Session & Authentication
//------------------------------
$sid = NULL;

// Follow variables_order, i.e. POST > GET > COOKIES.
if (isset($_REQUEST['sid'])) {
	$sid = $_REQUEST['sid'];
}
else
if (isset($_COOKIE['sid'])) {
	$sid = $_COOKIE['sid'];
}
// Discouraged usage but still, check for PHP sessions.
// elseif (isset($_SESSION['sid'])) { $sid = $_SESSION['sid']; }

// Session ID provided, validate it.
if ($sid !== NULL) {
	$res = session::ensure($sid);

	if ($res === FALSE) {
		// Session doesn't exists, delete exsting cookie.
		setcookie('sid', '', time() - 3600, '/');
	}
	else if (is_integer($res)) {
		switch ($res) {
			// Reserved error code for client use
			case session::ERR_EXPIRED:
				redirect('notice/user/expired');
				break;
			// Treat as public user.
			case session::ERR_INVALID:
				break;
		}
	}
	else {
		// Success, proceed.
	}
}

unset($sid, $_POST['sid'], $_GET['sid'], $_REQUEST['sid'], $_COOKIE['sid']);

//--------------------------------------------------
//
//  Request handling
//
//--------------------------------------------------

//------------------------------
//  Access log
//------------------------------
if (FRAMEWORK_ENVIRONMENT == 'debug') {
  $accessTime = microtime(1);
}

//------------------------------
//  Resolve request URI
//------------------------------

$resolver = 'framework\Resolver';

// Web Services
$resolver::registerResolver(new resolvers\WebServiceResolver(), 60);

// Cache resolver
$resolver::registerResolver(new resolvers\CacheResolver(), 50);

// Template resolver
$resolver::registerResolver(new resolvers\TemplateResolver(), 40);

// Database resources
$resolver::registerResolver(new resolvers\ResourceResolver(), 30);

// External URL
$resolver::registerResolver(new resolvers\ExternalResolver(), 20);

// Physical file resolver ( Directory Indexing )
$fileResolver = new resolvers\FileResolver();

$fileResolver->directoryIndex('Home index');

$fileResolver->cacheExclusions('htm html php');

$resolver::registerResolver($fileResolver, 10);

// Perform resolving
$response = $resolver::resolve($_SERVER['REQUEST_URI']);

unset($resolver);

//------------------------------
//  Access log
//------------------------------
if (FRAMEWORK_ENVIRONMENT == 'debug') {
  log::write("$_SERVER[REQUEST_METHOD] $_SERVER[REQUEST_URI]", 'Access', array(
    'timeElapsed' => round(microtime(1) - $accessTime, 4) . ' secs'
  ));

  unset($accessTime);
}

// HTTP status code returned
if (is_integer($response)) {
	redirect($response);
}

//--------------------------------------------------
//
//  Functions
//
//--------------------------------------------------

function redirect($response) {
	if (is_string($response)) {
		$self = $_SERVER['REQUEST_URI'];

		$dest = dirname($_SERVER['REQUEST_URI']) . $response;

		if ($self && $dest && $self == $dest) {
			return;
		}

		header("Location: $response", true);
	}
	else if (is_integer($response)) {
		switch ($response) {
			case 304: // CAUTION: Do not create an errordoc for this code.
				header('HTTP/1.0 304 Not Modified', true, 304);
				die;
			case 400: // TODO: Use this when the URI is unrecognizable.
				header('HTTP/1.0 400 Bad Request', true, 400);
				break;
			case 401:
				header('HTTP/1.0 401 Unauthorized', true, 401);
				break;
			case 403:
				header('HTTP/1.0 403 Forbidden', true, 403);
				break;
			case 404: // TODO: Use this when file resolver can resolve the URI, but no file actually exists.
				header('HTTP/1.0 404 Not Found', true, 404);
				break;
			case 405:
				header('HTTP/1.0 405 Method Not Allowed', true, 405);
				break;
			case 500:
				header('HTTP/1.0 500 Internal Server Error', true, 500);
				break;
			case 501:
				header('HTTP/1.0 501 Not Implemented', true, 501);
				break;
			case 503:
				header('HTTP/1.0 503 Service Unavailable', true, 503);
				break;
		}

		$response = "assets/errordocs/$response.php";

		if (is_file($response)) {
			include($response);
		}
	}

	die;
}