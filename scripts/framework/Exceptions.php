<?php
/*! Exception.php
 *
 *  Exception handling class of the framework.
 */

namespace framework;

class Exceptions {
  public static function setHandlers() {
        set_error_handler('framework\Exceptions::handle');
    set_exception_handler('framework\Exceptions::handle');
  }

  public static function handle($e, $eS = null, $eF = null, $eL = null, $eC = null) {
  	if (error_reporting() == 0) {
  		return;
  	}

  	if ($e instanceof \Exception) {
      $eS = $e->getMessage();
  		$eF = $e->getFile();
  		$eL = $e->getLine();
  		$eC = $e->getTrace();
  		$eN = $e->getCode();

  		if ($e instanceof exceptions\GeneralException) {
        $eS = Resource::getString($eS);
      }

      $type = 'Exception';
  	}
  	else {
  		$eN = $e;

  		$type = 'Error';
  	}

  	// Log the error
  	\log::write("Gateway:: uncaught \"$eS\" #$eN, on $eF:$eL.", $type, $eC);

  	$output = json_encode(array(
	  		'error' => $eS
	  	, 'code' => $eN
	  	));

  	header('Content-Type: application/json; charset=utf-8');
  	header('Content-Length: ' . count($output));

  	// Display error message
  	echo $output;

  	// Terminates on Exceptions and Errors.
  	if ($type == 'Exception' || $type == 'Error') {
  		die;
  	}
  }
}