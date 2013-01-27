<?php
//------------------------------------------------------------
// Initialize.php
//
// Define general methods and setup global usage classes.
//------------------------------------------------------------

// Global system constants
require_once('scripts/framework/constants.php');

// Sets default Timezone
date_default_timezone_set('Asia/Hong_Kong');

// Setup class autoloading on-demand.
function __autoload($name)
{
	// Namespace fix
	$name = str_replace('\\', '/', $name);

	// Look up current folder
	if (file_exists("./$name.php")) {
		require_once("./$name.php");
	}

	// Loop up script folder
	else {
		$scriptName = dirname(__FILE__);
		$scriptName = realpath("$scriptName/../scripts/$name.php");

		if (file_exists($scriptName)) {
			require_once($scriptName);
		}

		// Then look for services
		else {
			$scriptName = dirname(__FILE__);
			$serviceName = realpath("$scriptName/../services/$name.php");

			if (file_exists($serviceName)) {
				require_once($serviceName);
			}
		}
	}
}

// Database options
$options = new core\DatabaseOptions(
	'mysql', null, null,
	'cometolist', 'cometolist', 'RBdukzzw8KfBoQndzGhn'
);

$options->driverOptions = Array(
		PDO::ATTR_PERSISTENT => true
	, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
	, PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
	);

core\Database::setOptions($options);

unset($options);

// Ensure base functionalities
require_once('scripts/framework/environment.php');

//--------------------------------------------------
//
//  eBay related settings
//
//--------------------------------------------------

// eBay site by custom request header
/*
if (@$_REQUEST['HEADERS']['X-EBAY-API-SITEID']) {
	EBayAPI::site(intval($_REQUEST['HEADERS']['X-EBAY-API-SITEID']));
}
*/

//--------------------------------------------------
//
//  Global functions
//
//--------------------------------------------------

function authorize($status, $rejectTarget = '/Login') {
	if ($status && session::checkStatus($status) === FALSE) {
		// Already logged in, redirect to restricted.
		if (session::currentUser()) {
			$rejectTarget = '/tool/user/restricted';
		}

		if ($_SERVER['REQUEST_URI']) {
			$rejectTarget.= "?returnUrl=$_SERVER[REQUEST_URI]";
		}

		redirect("$rejectTarget");
	}
}

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
    // FastCGI and CGI expects Status: instead of HTTP/1.0 for status code.
    $statusPrefix = FALSE !== strpos(@$_SERVER['GATEWAY_INTERFACE'], 'CGI') ? 'Status:' : 'HTTP/1.0';

    switch ($response) {
      case 200:
        header("$statusPrefix 200 OK", true, 200);
        return;
      case 304: // CAUTION: Do not create an errordoc for this code.
        header("$statusPrefix 304 Not Modified", true, 304);
        die;
      case 400: // TODO: Use this when the URI is unrecognizable.
        header("$statusPrefix 400 Bad Request", true, 400);
        break;
      case 401:
        header("$statusPrefix 401 Unauthorized", true, 401);
        break;
      case 403:
        header("$statusPrefix 403 Forbidden", true, 403);
        break;
      case 404: // TODO: Use this when file resolver can resolve the URI, but no file actually exists.
        header("$statusPrefix 404 Not Found", true, 404);
        break;
      case 405:
        header("$statusPrefix 405 Method Not Allowed", true, 405);
        break;
      case 412:
        header("$statusPrefix 412 Precondition Failed", true, 412);
        break;
      case 500:
        header("$statusPrefix 500 Internal Server Error", true, 500);
        break;
      case 501:
        header("$statusPrefix 501 Not Implemented", true, 501);
        break;
      case 503:
        header("$statusPrefix 503 Service Unavailable", true, 503);
        break;
    }

    unset($statusPrefix);

    $response = "assets/errordocs/$response.php";

    if (is_file($response)) {
      include($response);
    }
  }

  // Further HTML has no meaning when a redirect header is present, exit.
  die;
}

//--------------------------------------------------
//
//  Functional programming
//
//--------------------------------------------------

require_once('scripts/framework/functions.php');