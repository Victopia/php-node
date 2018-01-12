<?php /*! Initialize.php | Define general methods and setup global usage classes. */

use core\Node;
use core\Log;

use framework\Configuration as conf;
use framework\Resolver;
use framework\Session;
use framework\Service;
use framework\System;

use framework\log\processors\BacktraceProcessor;
use framework\log\processors\SessionProcessor;
use framework\log\processors\ProcessProcessor;
use framework\log\handlers\NodeHandler;

use Monolog\Logger;
use Monolog\Handler\NullHandler;

//------------------------------------------------------------------------------
//
//  Bootstrapping
//
//------------------------------------------------------------------------------

require_once(__DIR__ . '/framework/System.php');

// Backward compatible autoloading
if ( !function_exists('spl_autoload_register') ) {
  $tmp = tmpfile();

  fwrite($tmp, '<?php function __autoload($name) { System::__autoload($name); }');

  $info = stream_get_meta_data($tmp);

  if ( is_readable(@$info['uri']) ) {
    require_once($info['uri']);
  }

  fclose($tmp);

  unset($info, $tmp);
}

// Autoloaders and module prefixes are setup here.
System::bootstrap();

// Error & Exception handling.
framework\ExceptionsHandler::setHandlers();

// Call this one to check if enviroment is valid.
System::environment();

// Global system constants
require_once(__DIR__ . '/framework/constants.php');

// Functional programming
require_once(__DIR__ . '/framework/functions.php');

//------------------------------------------------------------------------------
//
//  Database initialization
//
//------------------------------------------------------------------------------

/*! TODO @ 8 May, 2015
 *  DatabaseOptions will be replaced by IDatabaseAdapter interface, and
 *  PdoMySQLAdapter will be made as default and sample class.
 *
 *  The new interface will abide to the pattern of the MongoDB API, like find(),
 *  findOne() and so on.
 *
 *  Database class will act as a wrapper for the designated adapter.
 */

core\Database::setOptions(
  core\DatabaseOptions::fromArray(
    (array) conf::get('system::database.default')
  )
);

//------------------------------------------------------------------------------
//
//  Logger initialization
//
//------------------------------------------------------------------------------

/*! Note @ 7 Aug, 2015
 *  Here we started to make use of the Psr\Log interface and Seldaek\Monolog,
 *  digging the original Log class empty as a wrapper to LoggerInterface, and
 *  moved the backtrace and session info into separate processors in monolog.
 *
 *  Specifically, backtrace context like file, subject and action are moved to
 *  the BacktraceProcessor class, while session info is moved to the
 *  SessionProcessor class.
 *
 *  Node::set() invoke is migrated into NodeHandler class.
 */

Log::setLogger(new Logger('default'));

// Log enabled
if ( conf::get('system::log.enabled', true) ) {
  $level = Logger::toMonologLevel(conf::get('system::log.level', 'debug'));

  Log::getLogger()
    ->pushProcessor(new BacktraceProcessor($level))
    ->pushProcessor(new SessionProcessor($level))
    ->pushProcessor(new ProcessProcessor($level))
    ->pushHandler(new NodeHandler($level));

  unset($level);
}
else {
  // This should not be necessary, no handlers will run nothing.
  Log::getLogger()
    ->pushHandler(new NullHandler());
}

//------------------------------------------------------------------------------
//
//  System shims
//
//------------------------------------------------------------------------------

require_once(__DIR__ . '/framework/environment.php');
