<?php
/*! IExecutableCommand.php | Interface for executable command classes. */

/*! Note @ 9 May, 2015
 *  Try to remove the necessity of response context, take care of output in CliRouter instead.
 */

namespace framework\interfaces;

use framework\Request;
use framework\Response;

interface IExecutableCommand {

  public function execute(Request $request, Response $response);

}
