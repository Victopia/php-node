<?php
/* Translation.php | Universal access to locale based resources. */

namespace framework;

use core\Node;

use framework\exceptions\FrameworkException;

class Translation {

  private $localeChain;

  private $localeCache = array();

  //----------------------------------------------------------------------------
  //
  //  Constructor
  //
  //----------------------------------------------------------------------------

  public function __construct($localeChain = 'en_US') {
    if ( !is_array($localeChain) ) {
      $localeChain = preg_split('/\s*,\s*/', trim($localeChain));
    }

    $this->localeChain = $localeChain;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Shorthand to get()
   */
  public function __invoke($string, $key = 'default') {
    return $this->get($string, $key);
  }

  /**
   * Set a locale object.
   */
  public function set($string, $key = 'default', $locale = null) {
    // Use the last one as default
    if ( $locale === null ) {
      $locale = end($this->localeChain);
    }

    $contents = array(
      Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_TRANSLATION
    , 'identifier' => md5($string)
    , 'value' => $string
    , 'key' => $key
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
  public function get($string, $key = 'default') {
    $localeChain = (array) $this->localeChain;

    // Map translations with the hash of $string.
    $cache = (array) $this->loadCache(md5($string));

    // Only search when something is loaded.
    if ( $cache ) {
      // Search the cache based on locale chain.
      $cache = array_filter($cache, propIn('locale', $localeChain));

      // Search by preferred key, if nothing is found just fall back to the whole cache.
      $_cache = array_filter($cache, propIs('key', $key));
      if ( $_cache ) {
        $cache = $_cache;
      }

      unset($_cache);

      /*! Note
       *  For anything survives the search, return the first one. This allows
       *  $key fall back mechanism, because the specified $key could be of
       *  inexistance.
       */
      if ( $cache ) {
        $value = reset($cache);
        return @$value['value'];
      }
    }
    // Otherwise, insert it into database for future translation.
    else {
      $this->set($string, $key);
    }

    // When everything fails, return as-is.
    return $string;
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
