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
        '.private/scripts' // Script files, meant to be included on initialize.
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

// Error & Exception handling.
framework\Exceptions::setHandlers();

// Database options
$options = new core\DatabaseOptions(
  'mysql', null, null,
  'fashionboo_magazine', 'fashionboo', 'lo23e'
);

$options->driverOptions = Array(
    PDO::ATTR_PERSISTENT => true
  , PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
  , PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
  );

core\Database::setOptions($options);

unset($options);

// Ensure base functionalities
require_once(__DIR__ . '/framework/environment.php');

//--------------------------------------------------
//
//  Global functions
//
//--------------------------------------------------

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