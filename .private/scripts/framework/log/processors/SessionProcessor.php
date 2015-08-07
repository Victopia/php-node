<?php
/*! SessionProcessor.php | Add session related info to the log record. */

namespace framework\log\processors;

use Monolog\Logger;

use framework\Process;
use framework\Session;

class SessionProcessor {

  private $level;

  public function __construct($level = Logger::DEBUG) {
    $this->level = Logger::toMonologLevel($level);
  }

  public function __invoke(array $record) {
    if ( $record['level'] < $this->level ) {
      return $record;
    }

    if ( Session::current() ) {
      $record['extra']['user'] = (int) Session::current('UserID');
    }
    else if ( is_numeric(@Process::get('type')) ) {
      $record['extra']['user'] = (int) Process::get('type');
    }

    return $record;
  }

}
