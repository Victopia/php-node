<?php
/*! Exception.php
 *
 *  Exception handling class of the framework.
 */

namespace framework;

class ExceptionsHandler {
  public static function setHandlers() {
        set_error_handler('framework\ExceptionsHandler::handleError');
    set_exception_handler('framework\ExceptionsHandler::handleException');
  }

  public static function handleError($eN, $eS, $eF, $eL) {
    if (error_reporting() == 0) {
      return;
    }

    throw new \ErrorException($eS, 0, $eN, $eF, $eL);
  }

  public static function handleException($e) {
    if (error_reporting() == 0) {
      return;
    }

    $eS = $e->getMessage();
    $eF = $e->getFile();
    $eL = $e->getLine();
    $eC = $e->getTrace();
    $eN = $e->getCode();

    if ($e instanceof exceptions\GeneralException) {
      $eS = Resource::getString($eS);
    }

    if ($e instanceof \ErrorException) {
      switch (error_reporting()) {
        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_USER_ERROR:
        default:
          $type = 'Error';
          break;

        case E_WARNING:
        case E_CORE_WARNING:
        case E_USER_WARNING:
          $type = 'Warning';
          break;

        case E_DEPRECATED:
        case E_NOTICE:
        case E_USER_DEPRECATED:
        case E_USER_NOTICE:
          $type = 'Notice';
          break;

        case E_STRICT:
          $type = 'Information';
          break;
      }
    }
    else {
      $type = 'Exception';
    }

    // Prevent recursive errors on logging when database fails to connect.
    if (\core\Database::isConnected()) {
      // Log the error
      \log::write("Gateway:: uncaught \"$eS\" #$eN, on $eF:$eL.", $type, $eC);
    }

    $output = array(
        'error' => $eS
      , 'code' => $eN
      );

    if (FRAMEWORK_ENVIRONMENT == 'debug') {
      $output['file'] = $eF;
      $output['line'] = $eL;
    }

    $output = ob_get_clean() . json_encode($output);

    if (!\utils::isCLI()) {
      if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($output));
      }

      // JSONP support
      if (@$_GET['callback']) {
        $output = "$_GET[callback]($output)";
      }
    }

    // Display error message
    echo $output;

    // Terminates on Exceptions and Errors.
    if ($type == 'Exception' || $type == 'Error') {
      die;
    }
  }
}