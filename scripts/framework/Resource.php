<?php
/*! Resource.php
 *
 *  Universal access to locale based resources.
 */

namespace framework;

class Resource {
  
  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------
  
  /**
   * Set a locale object.
   */
  public static function 
  /* void */ set($key, $value, $locale = NULL) {
    $contents = Array(
      NODE_FIELD_COLLECTION => 'Resource',
      'contents' => $value
    );
    
    if ($locale !== NULL) {
      $contents['locale'] = $locale;
    }
    
    return Node::set($contents);
  }
  
  /**
   * Retrieve a locale object.
   */
  public static function 
  /* Mixed */ get($key, $locale = NULL) {
    $filter = Array(
      NODE_FIELD_COLLECTION => 'Resource',
      'contents' => $value
    );
    
    if ($locale !== NULL) {
      $filter['locale'] = $locale;
    }
    
    $res = Node::get($filter);
    
    if (count($res) > 0) {
      return $res['contents'];
    }
    
    return NULL;
  }
  
}