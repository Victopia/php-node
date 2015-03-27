<?php
/*! MaintenanceResolver.php \ IRequestResolver | Enable maintenance mode from database level. */

namespace resolvers;

use core\Utility as util;

use framework\Configuration as conf;

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

  public /* string */ function resolve($path) {
    $maintenance = (array) conf::get($this->configPath);

    // Check maintenance mode
    if ( !@$maintenance['enabled'] ) {
      return $path;
    }

    // Check whitelisted IP
    if ( @$maintenance['whitelist'] && in_array(@$_SERVER['REMOTE_ADDR'], (array) $maintenance['whitelist']) ) {
      return $path;
    }

    return $this->template;
  }

}
