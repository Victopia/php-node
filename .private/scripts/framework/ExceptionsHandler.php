<?php /*! ExceptionsHandler.php | Exception handler class of the framework. */

namespace framework;

use Exception;
use ErrorException;

use core\ContentEncoder;
use core\Database;
use core\Log;
use core\Utility;

use framework\Resolver;
use framework\System;

use framework\exceptions\GeneralException;
use framework\exceptions\FrameworkException;
use framework\exceptions\ValidationException;

use Psr\Log\LogLevel;

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

    while ( ob_get_level() > 0 ) {
      ob_end_clean();
    }

    $eS = $e->getMessage();
    $eN = $e->getCode();
    $eC = $e->getTrace();

    // Put current context into stack trace
    array_unshift($eC, array('file' => $e->getFile(), 'line' => $e->getLine()));

    if ( $e instanceof ErrorException ) {
      switch ( $e->getSeverity() ) {
        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_USER_ERROR:
        default:
          $logType = LogLevel::CRITICAL;
          break;

        case E_WARNING:
        case E_CORE_WARNING:
        case E_USER_WARNING:
          $logType = LogLevel::WARNING;
          break;

        case E_DEPRECATED:
        case E_NOTICE:
        case E_USER_DEPRECATED:
        case E_USER_NOTICE:
          $logType = LogLevel::NOTICE;
          break;

        case E_STRICT:
          $logType = LogLevel::INFO;
          break;
      }

      $exceptionType = 'error';
    }
    else {
      $exceptionType = get_class($e);

      if ( strpos($exceptionType, '\\') !== false ) {
        $exceptionType = substr(strrchr($exceptionType, '\\'), 1);
      }

      $logType = LogLevel::ERROR;
    }

    $logString = sprintf('[Gateway] Uncaught %s#%d with message: "%s".', $exceptionType, $eN, $eS);

    unset($exceptionType);

    // Current request context
    $resolver = Resolver::getActiveInstance();
    if ( $resolver ) {
      if ( $resolver->request() ) {
        $client = $resolver->request()->client();
      }

      $response = $resolver->response();
    }
    unset($resolver);

    // Prevent recursive errors on logging when database fails to connect.
    if ( Database::isConnected() ) {
      // Release table locks of current session.
      @Database::unlockTables(false);

      if ( Database::inTransaction() ) {
        @Database::rollback();
      }
    }

    $logContext = array_filter(array(
        'errorContext' => $eC
      , 'client' => @$client
      ));

    // Log the error
    try {
      @Log::log($logType, $logString, $logContext);
    }
    catch (\Exception $e) { }

    unset($logContext);

    // Send the error to output
    $output = array(
        'error' => $eS
      , 'code' => $eN
      );

    if ( System::environment(false) != System::ENV_PRODUCTION ) {
      $output['trace'] = $eC;
    }

    // Display error message
    if ( @$client['type'] != 'cli' ) {
      if ( $e instanceof ErrorException ) {
        $statusCode = 500;
      }
      else {
        $statusCode = 400;
      }

      if ( $e instanceof ValidationException ) {
        $output['errors'] = $e->getErrors();
      }

      if ( isset($response) ) {
        // Do i18n when repsonse context is available
        if ( $e instanceof GeneralException ) {
          $errorMessage = $response->__($eS, $logType);
          if ( $errorMessage ) {
            $output['error'] = $errorMessage;
          }
        }
        else if ( $e instanceof FrameworkException ) {
          $output['params'] = $e->params();
        }

        $fn = compose(
          maps(function($header) use($response) {
            $response->header($header, false);
          }),
          filters(matches('/^content\-/i')),
          'array_keys'
        );

        $fn($response->header());

        unset($fn);

        $response->header('Content-Type', 'application/json; charset=utf-8', true);

        $response->send($output, $statusCode);
      }
      else {
        header('Content-Type: application/json; charset=utf-8', true, $statusCode);

        echo ContentEncoder::json($output);
      }
    }
    else {
      $logString.= "\n";

      if ( $e instanceof ValidationException ) {
        $logString.= "Errors:\n";
        foreach ( $e->getErrors() as $error ) {
          if ( $error instanceof Exception ) {
            $logString.= $error->getMessage() . "\n";
          }
          else {
            $logString.= "$error\n";
          }
        }
      }

      // Debug stack trace
      if ( System::environment(false) != System::ENV_PRODUCTION ) {
        $logString.= "Trace:\n";
        array_walk($eC, function($stack, $index) use(&$logString) {
          $trace = ($index + 1) . '.';

          $function = implode('->', array_filter(array(
            @$stack['class'], @$stack['function']
          )));
          if ( $function ) {
            $trace.= " $function()";
          }
          unset($function);

          if ( @$stack['file'] ) {
            $trace.= " $stack[file]";

            if ( @$stack['line'] ) {
              $trace.= ":$stack[line]";
            }
          }

          $logString.= "$trace\n";
        });
      }

      error_log($logString);
    }

    // CLI exit code on Exceptions and Errors
    if ( in_array($logType, array(LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY)) ) {
      $exitCode = $e->getCode();
      if ( $exitCode <= 0 ) {
        $exitCode = 1;
      }

      die($exitCode);
    }
  }
}
