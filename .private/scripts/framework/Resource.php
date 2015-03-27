<?php
/* Resource.php | Universal access to locale based resources. */

namespace framework;

use core\Node;

class Resource {

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
   * Set a locale object.
   */
  public function set($key, $value, $locale = null) {
    $contents = array(
      NODE_FIELD_COLLECTION => 'Resource',
      'contents' => $value
    );

    if ( $locale !== null ) {
      $contents['locale'] = $locale;
    }
    elseif ( $this->localeChain ) {
      $localeChain = (array) $this->localeChain;

      $contents['locale'] = end($localeChain);

      unset($localeChain);
    }

    if ( !@$contents['locale'] ) {
      throw new framework\exceptions\ResourceException('Invalid locale specified.');
    }

    return Node::set($contents);
  }

  /**
   * Check whether a resource key is set.
   */
  public function __isset($key) {
    $localeChain = (array) $this->localeChain;

    $cache = &$this->localeCache[$key];

    // Search the cache based on locale chain.
    if ( !$cache ) {
      $this->ensureCache($key);
    }

    foreach ( $localeChain as $locale ) {
      if ( @$cache[$locale] ) {
        return true;
      }
    }

    return false;
  }

  /**
   * Retrieve a locale object.
   */
  public function __get($key) {
    $localeChain = (array) $this->localeChain;

    $cache = &$this->localeCache[$key];

    // Search the cache based on locale chain.
    if ( !$cache ) {
      @$this->ensureCache($key);
    }

    foreach ( $localeChain as $locale ) {
      if ( @$cache[$locale] ) {
        return $cache[$locale];
      }
    }

    return null;
  }

  //----------------------------------------------------------------------------
  //
  //  Private methods
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   *
   * Add resources of specified key into cache.
   */
  private function ensureCache($key) {
    $cache = &$this->localeCache[$key];

    $res = Node::get(array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_RESOURCES
      , 'identifier' => $key
    ));

    if ( $res ) {
      $cache = array();

      array_walk($res, function($resource) use(&$cache) {
        $cache = $resource['locale'];
      });
    }
  }

}
