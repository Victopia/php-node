<?php
/*! CliRouter.php | Route CLI commands to execute. */

namespace resolvers;

use framework\Request;
use framework\Response;

use framework\exceptions\FrameworkException;

class CliRouter implements \framework\interfaces\IRequestResolver {

  protected $basePath = '.private/scripts/commands';

  public function __construct($options = array()) {
    if ( is_dir(@$options['path']) ) {
      if ( !is_readable(@$options['path']) ) {
        throw new FrameworkException('Specified path is not readable.');
      }

      $this->basePath = @$options['path'];
    }
  }

  public function resolve(Request $request, Response $response) {
    $commandPath = $this->basePath . '/' . $request->uri();
    if ( !is_file($commandPath) ) {
      throw new FrameworkException('Target command does not exist.');
    }

    require_once($commandPath);

    $fx = compose(
      unshiftsArg('str_replace', ' ', ''),
      'ucwords', 'strtolower',
      unshiftsArg('str_replace', '-', ' '));

    $className = $fx($request->uri());
    if ( !class_exists($className) || is_a($className, 'IExecutableCommand', true) ) {
      throw new FrameworkException("File is loaded, expecting class $className from $commandPath.");
    }

    $command = new $className();

    $command->execute($request, $response);
  }

}
