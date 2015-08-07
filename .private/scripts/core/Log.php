<?php
/* Log.php | Shorthand class for writing system logs. */

namespace core;

use framework\Configuration;
use framework\Process;
use framework\Session;
use framework\System;

use Psr\Log\LoggerInterface;

class Log {

  private static $loggers = array();

  static function setLogger(LoggerInterface $logger) {
    self::$loggers[$logger->getName()] = $logger;
  }

  static function getLogger($name = 'default') {
    return @self::$loggers[$name];
  }

  /**
   * Pipe method calls into the logger.
   */
  static function __callStatic($name, $args) {
    return call_user_func_array(array(self::getLogger(), $name), $args);
  }

}
