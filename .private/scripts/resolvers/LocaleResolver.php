<?php
/*! LocaleResolver.php | Resolve locale and creates a resource object for request. */

namespace resolvers;

use framework\Request;
use framework\Response;
use framework\Resource;

class LocaleResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @private
   *
   * Default locale
   */
  protected $defaultLocale;

  public function __construct($options) {
    if ( !empty($options['default']) ) {
      $this->defaultLocale = (string) $options['default'];
    }
  }

  public function resolve(Request $request, Response $response) {
    // Then request params
    if ( empty($locale) ) {
      $locale = $request->param('locale');
    }

    // User preference
    if ( $request->user ) {
      $locale = @$request->user['locale'];
    }

    // Default locale
    if ( empty($locale) ) {
      $locale = $this->defaultLocale;
    }

    if ( !empty($locale) ) {
      if ( !@$_COOKIE['locale'] == $locale ) {
        setcookie('locale', $locale, FRAMEWORK_COOKIE_EXPIRE_TIME, '/');
      }

      $response->resource(new Resource($locale));
    }
  }

}
