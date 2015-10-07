<?php
/*! LocaleResolver.php | Resolve locale and creates a resource object for request. */

namespace resolvers;

use Locale;

use framework\Request;
use framework\Response;
use framework\Translation;

class LocaleResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @private
   *
   * Default locale
   */
  protected $defaultLocale;

  public function __construct($options) {
    if ( !empty($options['default']) ) {
      if ( is_string($options['default']) ) {
        $locale = Locale::parseLocale($options['default']);
      }

      $locale = array_select($locale, ['language', 'region']);

      $this->defaultLocale = implode('_', $locale);
    }
  }

  public function resolve(Request $request, Response $response) {
    // Then request params
    if ( empty($locale) ) {
      $locale = $request->param('locale');
    }

    // User preference
    if ( empty($request->user) ) {
      $locale = @$request->user['locale'];
    }

    // Accept from HTTP headers
    if ( empty($locale) ) {
      $locale = Locale::acceptFromHttp($request->header('Accept-Language'));
    }

    // Default locale
    if ( empty($locale) ) {
      $locale = $this->defaultLocale;
    }

    if ( !empty($locale) ) {
      if ( $request->meta('locale') != $locale ) {
        $response->cookie('__locale', $locale, FRAMEWORK_COOKIE_EXPIRE_TIME, '/');
      }

      $response->translation(new Translation($locale));
    }
  }

}
