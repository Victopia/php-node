<?php /*! GarbageCollector.php | Sweeps expired data (e.g. sessions, emails, notifications ...) every 5 mins. */

require_once('.private/scripts/Initialize.php');

use core\Node;

use framework\Session;

// note; Sweep sessions
Node::delete(
  [ Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION
  , 'timestamp' => date("< 'Y-m-d H:i:s'", strtotime(Session::DELETE_TIME))
  ]
);
