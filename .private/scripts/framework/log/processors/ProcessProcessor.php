<?php
/*! ProcessProcessor.php | Adds process related data if the current thread is invoked from Process. */

namespace framework\log\processors;

use Monolog\Logger;

use framework\Process;

class ProcessProcessor {

  private $level;

  public function __construct($level = Logger::DEBUG) {
    $this->level = Logger::toMonologLevel($level);
  }

  public function __invoke(array $record) {
    if ( $record['level'] < $this->level ) {
      return $record;
    }

    // This indicated a background process
    if ( Process::get('pid') ) {
      $record['extra']['pid'] = $processData['pid'];
    }

    return $record;
  }
}
