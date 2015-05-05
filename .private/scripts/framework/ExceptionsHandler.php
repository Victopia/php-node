<?php
/*! ExceptionsHandler.php | Exception handler class of the framework. */

namespace framework;

use ErrorException;

use core\Database;
use core\Log;
use core\Utility;

use framework\Resolver;
use framework\System;

class ExceptionsHandler {
  public static function setHandlers() {
        set_error_handler('framework\ExceptionsHandler::handleError', error_reporting());
    set_exception_handler('framework\ExceptionsHandler::handleException');
  }

  public static function handleError($eN, $eS, $eF, $eL) {
    if ( error_reporting() == 0 ) {
      return;
    }

    // Use severity as the error placement
    throw new ErrorException($eS, 0, $eN, $eF, $eL);
  }

  public static function handleException($e) {
    if ( error_reporting() == 0 ) {
      return;
    }

    $eS = $e->getMessage();
    $eN = $e->getCode();
    $eC = $e->getTrace();

    // Put current context into stack trace
    array_unshift($eC, array('file' => $e->getFile(), 'line' => $e->getLine()));

    if ( $e instanceof exceptions\GeneralException ) {
      $r = (string) (new Resource)->$eS;
      if ( $r ) {
        $eS = $r;
      }

      unset($r);
    }

    if ( $e instanceof ErrorException ) {
      switch ( $e->getSeverity() ) {
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

      if ( strpos($exceptionType, '\\') !== false ) {
        $exceptionType = substr(strrchr($exceptionType, '\\'), 1);
      }

      $logType = 'Exception';
    }

    // Current request context
    $request = @Resolver::getActiveInstance()->request();
    $response = @Resolver::getActiveInstance()->response();

    // Prevent recursive errors on logging when database fails to connect.
    if ( Database::isConnected() ) {
      // Release table locks of current session.
      @Database::unlockTables(false);

      $logContext = array('errorContext' => $eC);
      if ( $request ) {
        $logContext+= $request->client();
      }

      $logString = sprintf('[Gateway] Uncaught %s with message: "%s" #%d.', $exceptionType, $eS, $eN);

      // Log the error
      Log::write($logString, $logType, $logContext);

      unset($logString, $logContext);
    }

    $output = array(
        'error' => $eS
      , 'code' => $eN
      );

    if ( System::environment() == 'debug' ) {
      $output['trace'] = $eC;
    }

    // Display error message
    $response->clearHeaders();
    $response->header('Content-Type', 'application/json; charset=utf-8');
    $response->send($output, $e instanceof ErrorException ? 500 : 400);

    // CLI exit code on Exceptions and Errors
    if ( in_array($logType, array('Exception', 'Error')) ) {
      $exitCode = $e->getCode();
      if ( $exitCode <= 0 ) {
        $exitCode = 1;
      }

      die($exitCode);
    }
  }
}
