<?php
/*! Log.php | https://github.com/victopia/PHPNode
 *
 * CAUTION: This class is not production ready, use at own risk.
 */

namespace core;

class Log {

	static function write($message, $type = 'Notice', $context = NULL) {
		switch ($type) {
			case 'Access':
			case 'Information':
			case 'Notice':
				$message = array('remarks' => $message);
				break;
			default:
				$message = array('reason' => $message);
		}

		// Try to backtrace the callee, performance impact at this point.
		$backtrace = \utils::getCallee();

		if (!@$backtrace['file']) {
			$backtrace = array( 'file' => __FILE__, 'line' => 0 );
		}

		$backtrace['file'] = str_replace(getcwd(), '', basename($backtrace['file'], '.php'));

		$message['subject'] = '['.getmypid()."@$backtrace[file]:$backtrace[line]]";
		$message['action'] = @"$backtrace[class]$backtrace[type]$backtrace[function]";

		if ($context !== NULL) {
			$message['context'] = $context;
		}

		$message['type'] = $type;

		$message[NODE_FIELD_COLLECTION] = FRAMEWORK_COLLECTION_LOG;

		return Node::set($message);
	}

	static function sessionWrite($sid, $identifier, $action, $remarks = NULL) {
		$res = '0';

		if ($sid !== NULL && \framework\Session::ensure($sid)) {
			$res = \framework\Session::currentUser();
			$res = intval($res['ID']);
		}

		$res = Utility::sanitizeInt($res);

		$identifier = Utility::sanitizeString($identifier);

		$remarks = Utility::sanitizeString($remarks);

		return Node::set(array(
    	NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_LOG
    , 'subject' => "User#$res"
    , 'action' => $identifier . '->' . Utility::sanitizeString($action)
    , 'remarks' => $remarks
    ));
	}
}