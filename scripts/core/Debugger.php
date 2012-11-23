<?php
/*! Debugger.php | Util class for easy debugging. */

namespace core;

class Debugger {

	static function verbose($message, $type = 'Access', $context = NULL) {
		// Try to backtrace the callee, performance impact at this point.
		if (!is_array($message)) {
			$backtrace = debug_backtrace();
			$backtrace = @$backtrace[0]['file'];

			if (!$backtrace) {
				$backtrace = __FILE__;
			}

			$backtrace = str_replace(getcwd(), '', basename($backtrace, '.php'));

			$message = '[@'.$backtrace.':'.getmypid()."] $message";
		}

		switch ($type) {
			case 'Access':
			case 'Information':
			case 'Notice':
				$message = array('remarks' => $message);
				break;
			default:
				$message = array('reason' => $message);

				if ($context !== NULL) {
					$message['context'] = $context;
				}
		}

		$message['type'] = $type;

		// echo print_r($message, 1) . "\n";

		\log::write($message);
	}

	static function assertTrue($assert, $failMessage) {
		if (!$assert) {
			throw new exceptions\AssertionException($failMessage);
		}

		return !!$assert;
	}

}