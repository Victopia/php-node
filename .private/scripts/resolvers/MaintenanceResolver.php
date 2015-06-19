<?php
/*! MaintenanceResolver.php \ IRequestResolver | Enable maintenance mode from database level. */

namespace resolvers;

use core\Utility as util;

use framework\Configuration as conf;
use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

class MaintenanceResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @constructor
   *
   * @param {string} $options[templatePath] Path to template when rendering maintenance note.
   * @param {array} $options['whitelist'] Allowed $_SERVER[REMOTE_ADDR] to pass through.
   */
  public function __construct($options) {
    if ( empty($options['templatePath']) ) {
      throw new ResolverException('$options[templatePath] must be provided.');
    }
    else {
      $this->template($options['templatePath']);
    }

    if ( !empty($options['whitelist']) ) {
      $this->whitelist($options['whitelist']);
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   */
  protected $template;

  /**
   * Maintenance template path.
   */
  public function template($value = null) {
    $template = $this->template;

    if ( $value !== null ) {
      $this->template = $value;
    }

    return $template;
  }

  /**
   * @private
   */
  protected $whitelist = array();

  /**
   * Whitelist remote addreses.
   *
   * Note: Depends on server settings, remote address can be ip address or
   *       domain names done by reverse lookup.
   */
  public function whitelist(array $value = null) {
    $whitelist = $this->whitelist;

    if ( $value !== null ) {
      $this->whitelist = $value;
    }

    return $whitelist;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //----------------------------------------------------------------------------

  public function resolve(Request $request, Response $response) {
    // Check whitelisted IP
    if ( in_array($request->client('address'), $this->whitelist()) ) {
      return;
    }

    $request->setUri($this->template);
  }

}
