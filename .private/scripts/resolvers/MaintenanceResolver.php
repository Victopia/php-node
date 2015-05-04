<?php
/*! MaintenanceResolver.php \ IRequestResolver | Enable maintenance mode from database level. */

namespace resolvers;

use core\Utility as util;

use framework\Configuration as conf;
use framework\Request;
use framework\Response;

class MaintenanceResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @private
   *
   * Maintenance template path.
   */
  protected $template;

  protected $configPath;

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($template = '', $configPath = 'system.status::maintenance') {
    $this->template = $template;

    $this->configPath = $configPath;
  }

  //--------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //--------------------------------------------------

  public /* string */ function resolve(Request $request, Response $response) {
    // Check maintenance mode
    $maintenance = (array) conf::get($this->configPath);
    if ( !@$maintenance['enabled'] ) {
      return;
    }

    // Check whitelisted IP
    if ( in_array($request->client('address'), (array) @$maintenance['whitelist']) ) {
      return;
    }

    $request->setUri($this->template);
  }

}
