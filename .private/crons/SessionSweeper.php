<?php
/*! SessionSweeper.php | Clean up expired sessions. */

require_once('.private/scripts/Initialize.php');

use core\Node;

use framework\Session;

Node::delete(array(
    Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_SESSION,
    'timestamp' => date("< 'Y-m-d H:i:s'", strtotime(Session::DELETE_TIME))
  ));
