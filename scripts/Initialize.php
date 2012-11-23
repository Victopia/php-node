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
		die;
	}
}

//--------------------------------------------------
//
//  Functional programming
//
//--------------------------------------------------

require_once('scripts/framework/functions.php');