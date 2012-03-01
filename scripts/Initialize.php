<?php
//------------------------------------------------------------
// Initialize.php
// 
// Define general methods and setup global usage classes.
//------------------------------------------------------------

// Global system constants

// Table name for Data class
define('NODE_TABLENAME', 'Nodes');
// Table name for Node hirarchy relations.
define('RELATION_TABLENAME', 'NodeRelations');
// Table name for Log class
define('LOG_TABLENAME', 'Log');
// Row limit for each data fetch, be careful on setting this.
// Required system resources will change exponentially.
define('DATA_FETCHSIZE', '100');

// Starts HTTP session
session_start();

// Sets default Timezone
date_default_timezone_set('Asia/Hong_Kong');

// Setup class autoloading on-demand.
function __autoload($name)
{
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
$options = new DatabaseOptions(
	null, null, null, 'dev',
	'dev', 'dev01'
);

$options->driverOptions = Array(
	PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
	PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
);

Database::setOptions($options);

unset($options);