<?php /*! LocaleResolver.php | Resolve locale and creates a resource object for request. */

namespace resolvers;

use Locale;

use framework\Configuration as conf;
use framework\Request;
use framework\Response;
use framework\System;
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
    // note; Request params will always update cookies
    $locale = $request->param('locale');

    if ( empty($locale) ) {
      $locale = $request->meta('locale');
    }

    if ( $locale ) {
      $response->cookie(
        '__locale',
        $locale,
        FRAMEWORK_COOKIE_EXPIRE_TIME,
        conf::get('system::domains.path_prefix'),
        System::getHostname('cookies'),
        $request->client('secure')
      );

      $request->updateParamCache();
    }

    // note; Default settings only updates when cookie is not set.
    // User preference
    if ( empty($locale) ) {
      $locale = @$request->user['locale'];
    }

    // Default locale
    if ( empty($locale) ) {
      $locale = $this->defaultLocale;
    }

    // Accept from HTTP headers
    if ( empty($locale) ) {
      $locale = Locale::acceptFromHttp($request->header('Accept-Language'));
    }

    if ( $locale ) {
      if ( !$request->cookie('__locale') ) {
        $response->cookie(
          '__locale',
          $locale,
          FRAMEWORK_COOKIE_EXPIRE_TIME,
          conf::get('system::domains.path_prefix'),
          System::getHostname('cookies'),
          $request->client('secure')
        );

        $request->updateParamCache();
      }

      $request->locale($locale);

      $response->translation(new Translation($locale));
    }
  }

}
