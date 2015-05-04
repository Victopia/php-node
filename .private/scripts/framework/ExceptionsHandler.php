<?php
/* ExceptionsHandler.php | Exception handler class of the framework. */

namespace framework;

use core\Database;
use core\Log;
use core\Utility;

use framework\System;

class ExceptionsHandler {
  public static function setHandlers() {
        set_error_handler('framework\ExceptionsHandler::handleError');
    set_exception_handler('framework\ExceptionsHandler::handleException');
  }

  public static function handleError($eN, $eS, $eF, $eL) {
    if ( error_reporting() == 0 ) {
      return;
    }

    throw new \ErrorException($eS, 0, $eN, $eF, $eL);
  }

  public static function handleException($e) {
    if ( error_reporting() == 0 ) {
      return;
    }

    $eS = $e->getMessage();
    $eF = $e->getFile();
    $eL = $e->getLine();
    $eC = $e->getTrace();
    $eN = $e->getCode();

    if ( $e instanceof exceptions\GeneralException ) {
      $r = new Resource;

      $r = (string) $r->$eS;

      if ( $r ) {
        $eS = $r;
      }

      unset($r);
    }

    if ( $e instanceof \ErrorException ) {
      switch ( error_reporting() ) {
        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_USER_ERROR:
        default:
          $logType = 'Error';
          break;

        case E_WARNING:
        case E_CORE_WARNING:
        case E_USER_WARNING:
          $logType = 'Warning';
          break;

        case E_DEPRECATED:
        case E_NOTICE:
        case E_USER_DEPRECATED:
        case E_USER_NOTICE:
          $logType = 'Notice';
          break;

        case E_STRICT:
          $logType = 'Information';
          break;
      }

      $exceptionType = 'error';
    }
    else {
      $exceptionType = get_class($e);

      if ( strpos($exceptionType, '\\') !== FALSE ) {
        $exceptionType = substr(strrchr($exceptionType, '\\'), 1);
      }

      $logType = 'Exception';
    }

    // Prevent recursive errors on logging when database fails to connect.
    if ( Database::isConnected() ) {
      // Release table locks of current session.
      @Database::unlockTables(false);

      if ( Utility::isCLI() ) {
        $logContext = $eC;
      }
      else {
        $logContext = array_filter(array(
            'remoteAddr' => @$_SERVER['REMOTE_ADDR']
          , 'forwarder' => @$_SERVER['HTTP_X_FORWARDED_FOR']
          , 'referrer' => @$_SERVER['HTTP_REFERER']
          , 'userAgent' => Utility::cascade(@$_SERVER['HTTP_USER_AGENT'], 'Unknown')
          , 'errorContext' => $eC
          ));
      }

      $logString = sprintf('[Gateway] Uncaught %s with message: "%s" #%d, on %s:%d.', $exceptionType, $eS, $eN, $eF, $eL);

      // Log the error
      Log::write($logString, $logType, $logContext);

      unset($logContext);
    }

    $output = array(
        'error' => $eS
      , 'code' => $eN
      );

    if ( System::environment() == 'debug' ) {
      $output['file'] = $eF;
      $output['line'] = $eL;
      $output['trace'] = $eC;
    }

    $output = ob_get_clean() . @json_encode($output);

    if ( !Utility::isCLI() ) {
      if ( !headers_sent() ) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($output));
      }

      // JSONP support
      if ( @$_GET['callback'] ) {
        $output = "$_GET[callback]($output)";
      }
    }

    http_response_code(500);

    // Display error message
    echo $output;

    // Terminates on Exceptions and Errors.
    if ( $logType == 'Exception' || $logType == 'Error' ) {
      $exitCode = $e->getCode();

      if ( $exitCode <= 0 ) {
        $exitCode = 1;
      }

      die($exitCode);
    }
  }
}
