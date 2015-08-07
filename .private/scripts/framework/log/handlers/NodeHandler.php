<?php
/*! NodeHandler.php | Insert the log record into database. */

namespace framework\log\handlers;

use core\Node;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class NodeHandler extends AbstractProcessingHandler {

  protected $collectionName = FRAMEWORK_COLLECTION_LOG;

  public function write(array $record) {
    $record[NODE_FIELD_COLLECTION] = $this->collectionName;
    $record['type'] = $record['level_name'];
    $record['subject'] = strtolower("$record[channel].$record[level_name]");

    if ( isset($record['extra']['action']) ) {
      $record['action'] = $record['extra']['action'];
    }

    // Convert datetime to timestamp format
    if ( isset($record['datetime']) ) {
      $record['timestamp'] = $record['datetime']->format('Y-m-d H:i:s');
    }

    if ( empty($record['context']) ) {
      unset($record['context']);
    }

    unset($record['level'], $record['channel'], $record['level_name'], $record['extra']['action'], $record['datetime'], $record['formatted']);

    return (bool) Node::set($record);
  }

}
