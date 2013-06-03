<?php
//------------------------------------------------------------
// Initialize.php
//
// Define general methods and setup global usage classes.
//------------------------------------------------------------

// Global system constants
require_once(__DIR__ . '/framework/constants.php');

// Sets default Timezone
date_default_timezone_set('Asia/Hong_Kong');

// Setup class autoloading on-demand.
function __autoload($name) {
  // Namespace fix
  $name = str_replace('\\', '/', $name);

  // Look up current folder
  if (file_exists("./$name.php")) {
    require_once("./$name.php");
  }

  // Loop up script folder
  else {
    $lookupPaths = array(
        FRAMEWORK_PATH_SCRIPTS // Script files, meant to be included on initialize.
      );

    foreach ($lookupPaths as $lookupPath) {
      $lookupPath = FRAMEWORK_PATH_ROOT . "/$lookupPath/$name.php";

      if (file_exists($lookupPath)) {
        try {
          require_once($lookupPath);
        }
        catch (ErrorException $e) {
          log::write('Error occurred when loading PHP dependency.', 'Error', $e);
        }
      }
    }
  }
}

//--------------------------------------------------
//
//  Environment setup
//
//--------------------------------------------------

require_once(__DIR__ . '/framework/environment.php');

//--------------------------------------------------
//
//  Functional programming
//
//--------------------------------------------------

require_once(__DIR__ . '/framework/functions.php');

//--------------------------------------------------
//
//  Error & Exception handling.
//
//--------------------------------------------------

framework\ExceptionsHandler::setHandlers();

//--------------------------------------------------
//
//  Database initialization
//
//--------------------------------------------------

/* Note by Vicary @ 24 Mar, 2013
   Uncomment this section and enter database connection criteria.

// Database options
$options = new core\DatabaseOptions(
  'mysql', null, null,
  'database', 'db_username', 'db_password'
);

$options->driverOptions = Array(
    PDO::ATTR_PERSISTENT => true
  , PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
  , PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
  );

core\Database::setOptions($options);

unset($options);
*/

//--------------------------------------------------
//
//  Global functions
//
//--------------------------------------------------

/**
 * Check whether current session has the specified
 * user level, redirect to $rejectTarget if not session
 * is matched.
 *
 * @param {int} $status One of the session::USR_* constants.
 * @param {string} $rejectTarget Optional, redirect target
 *                               on invalid sesssions.
 *
 * @author Vicary Archangel
 */
function authorize($status, $rejectTarget = '/Login') {
  if ($status && session::checkStatus($status) === FALSE) {
    // Already logged in but user doesn't have specified status,
    // redirect to restricted page, defaults to 405 error page.
    if (session::currentUser()) {
      $rejectTarget = 405;
    }

    // Append redirect target when successfully logged in.
    if ($_SERVER['REQUEST_URI']) {
      $rejectTarget.= "?returnUrl=$_SERVER[REQUEST_URI]";
    }

    redirect("$rejectTarget");
    die;
  }
}

/**
 * Redirect to a path, or exit with a HTTP status code.
 *
 * If it is HTTP status code, and that error doc exists,
 * it will be included along with that status header sent.
 *
 * @param {mixed} $response Target redirection path, or
 *                          HTTP status code.
 *
 * @author Vicary Archangel
 */
function redirect($response) {
  if (is_string($response)) {
    $self = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $dest = dirname($self) . $response;

    if ($self && $dest && $self == $dest) {
      return;
    }

    header("Location: $response", true, 302);
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