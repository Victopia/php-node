<?php
/*! BacktraceProcessor.php | Monolog processor about backtrace context of the current log. */

namespace framework\log\processors;

use Monolog\Logger;

use core\Utility as util;

class BacktraceProcessor {

  private $level;
  private $backtraceLevel;

  public function __construct($level = Logger::DEBUG, $backtraceLevel = 8) {
    $this->level = Logger::toMonologLevel($level);
    $this->backtraceLevel = $backtraceLevel;
  }

  public function __invoke(array $record) {
    if ( $record['level'] >= $this->level ) {
      // note: file and line should be one level above the called class.
      $backtrace = util::getCallee($this->backtraceLevel - 1);

      if ( isset($backtrace['file']) ) {
        $record['extra']['file'] = str_replace(getcwd(), '', basename($backtrace['file'], '.php'));;
      }

      if ( isset($backtrace['line']) ) {
        $record['extra']['line'] = $backtrace['line'];
      }

      $backtrace = util::getCallee($this->backtraceLevel);

      $action = @"$backtrace[class]$backtrace[type]$backtrace[function]";
      if ( $action ) {
        $record['extra']['action'] = $action;
      }
      unset($action);
    }

    return $record;
  }

}
