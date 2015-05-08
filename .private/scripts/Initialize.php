<?php
/*! Initialize.php | Define general methods and setup global usage classes. */

use core\Node;

use framework\Resolver;
use framework\Session;
use framework\Service;
use framework\System;

// Global system constants
require_once(__DIR__ . '/framework/constants.php');

// Sets default Timezone
date_default_timezone_set('Asia/Hong_Kong');

// Setup class autoloading on-demand.
function __autoload($name) {
  // Namespace path fix
  $name = str_replace('\\', DS, ltrim($name, '\\'));

  // Classname path fix
  $name = dirname($name) . DS . str_replace('_', DS, basename($name));

  // Look up current folder
  if ( file_exists("./$name.php") ) {
    require_once("./$name.php");
  }

  // Loop up script folder
  else {
    $lookupPaths = array(
        __DIR__ // Script files, meant to be included on initialize.
      );

    foreach ( $lookupPaths as $lookupPath ) {
      $lookupPath = "$lookupPath/$name.php";
      if ( file_exists($lookupPath) ) {
        require_once($lookupPath);
      }
    }
  }
}

// Error & Exception handling.
framework\ExceptionsHandler::setHandlers();

//--------------------------------------------------
//
//  Database initialization
//
//--------------------------------------------------

/*! TODO @ 8 May, 2015
 *  DatabaseOptions will be replaced by IDatabaseAdapter interface, and
 *  PdoMySQLAdapter will be made as default and sample class.
 *
 *  The new interface will abide to the pattern of the MongoDB API, like find(),
 *  findOne() and so on.
 *
 *  Database class will act as a wrapper for the designated adapter.
 */

/*! Uncomment this section and config database connection.

$options = new core\DatabaseOptions(
  'mysql', '127.0.0.1', null, 'database', 'username', 'password'
);

$options->driverOptions = Array(
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
  , PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
  );

core\Database::setOptions($options);

unset($options);
*/

// Ensure basic functionalities
require_once(__DIR__ . '/framework/environment.php');

//------------------------------------------------------------------------------
//
//  Functional programming
//
//------------------------------------------------------------------------------

require_once(__DIR__ . '/framework/functions.php');
