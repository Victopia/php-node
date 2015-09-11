<?php
/* Translation.php | Universal access to locale based resources. */

namespace framework;

use core\Node;

use framework\exceptions\FrameworkException;


class Translation {

  /**
   * @protected
   *
   * Cache for loaded translations.
   */
  private $localeCache = array();

  /**
   * @protected
   *
   * Language bundle key to search with.
   */
  private $bundle;

  /**
   * @protected
   *
   * Chain of locale for fall back.
   */
  private $localeChain;

  /**
   * @constructor
   *
   * @param {string|array} $localeChain Locale chain for this instance.
   * @param {?string} $bundle Language bundle name, defaults to "default".
   */
  public function __construct($localeChain = 'en_US', $bundle = 'default') {
    if ( !is_array($localeChain) ) {
      $localeChain = preg_split('/\s*,\s*/', trim($localeChain));
    }

    $this->localeChain = (array) $localeChain;
    $this->bundle = $bundle;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Shorthand to get()
   *
   * @param {string} $string The string to be translated.
   * @param {... array} $args Extra parameters for sprintf()
   *
   * @return {string} Translated string.
   */
  public function __invoke(/* $string, ... $args */) {
    return call_user_func_array(array($this, 'get'), func_get_args());
  }

  /**
   * Set a locale object.
   */
  public function set($string, $bundle = null, $locale = null) {
    // Use the last one as default
    if ( $locale === null ) {
      $locale = end($this->localeChain);
    }

    if ( $bundle === null ) {
      $bundle = $this->bundle;
    }

    $contents = array(
        Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_TRANSLATION
      , 'identifier' => md5($string)
      , 'value' => $string
      , 'key' => $bundle
      , 'locale' => $locale
      );

    if ( empty($contents['locale']) ) {
      throw new FrameworkException('Invalid locale specified.');
    }

    return Node::set($contents);
  }

  /**
   * Check whether a resource key is set.
   */
  public function __isset($string) {
    return (bool) $this->loadCache(md5($string));
  }

  /**
   * Retrieve a translation.
   */
  public function __get($string) {
    return $this->get($string);
  }

  /**
   * Retrieve a translation with a key preference.
   */
  public function get($string) {
    $localeChain = (array) $this->localeChain;

    // Map translations with the hash of $string.
    $cache = (array) $this->loadCache(md5($string));

    // Only search when something is loaded.
    if ( $cache ) {
      // Search the cache based on locale chain.
      $cache = array_filter($cache, propIn('locale', $localeChain));

      // Search by preferred key, if nothing is found just fall back to the whole cache.
      $_cache = array_filter($cache, propIs('key', $this->bundle));
      if ( $_cache ) {
        $cache = $_cache;
      }

      unset($_cache);

      /*! Note
       *  For anything survives the search, return the first one. This allows
       *  $bundle fall back mechanism, because the specified bundle could be of
       *  inexistance.
       */
      if ( $cache ) {
        $string = reset($cache);
        $string = @$string['value'];
      }
    }
    // Otherwise, insert it into database for future translation.
    else {
      $this->set($string, $this->bundle);
    }

    // note: sprintf() only when there are more arguments
    if ( func_num_args() > 1 ) {
      return sprintf($string, array_slice(func_get_args(), 1));
    }
    else {
      return $string;
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Private methods
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   *
   * Load resources of specified key into cache.
   */
  private function loadCache($identifier) {
    $cache = &$this->localeCache[$identifier];
    if ( !$cache ) {
      $cache = Node::get(array(
          Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_TRANSLATION
        , 'identifier' => $identifier
        , 'locale' => $this->localeChain
        ));

      // Sorts by locale with localChain
      usort($cache, function($subject, $object) {
        $subject = array_search($subject['locale'], $this->localeChain);
        $object = array_search($object['locale'], $this->localeChain);

        if ( $subject == $object ) {
          return 0;
        }
        else {
          return $subject > $object ? 1 : -1;
        }
      });
    }

    return $cache;
  }

}
