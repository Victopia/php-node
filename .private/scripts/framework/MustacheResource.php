<?php
/* MustacheResource.php | Mustache compatible Resource accessor. */

namespace framework;

/**
 * This class implements the "dot notation" way to get our locale resources from database.
 */
class MustacheResource {

  private $resourceInstance;

  private $path;

  //----------------------------------------------------------------------------
  //
  //  Constructor
  //
  //----------------------------------------------------------------------------

  /**
   * @param $locale_resource Either a string of locale chain,
   *                         or a cascading resource object.
   *
   * @param $path Stored path to target resources.
   */
  public function __construct($locale_resource = 'en_US', $path = '') {
    if ( $locale_resource instanceof Translation ) {
      $this->resourceInstance = $locale_resource;
    }
    else {
      $this->resourceInstance = new Translation($locale_resource);
    }

    $this->path = $path;
  }

  //----------------------------------------------------------------------------
  //
  //  Magic methods
  //
  //----------------------------------------------------------------------------

  /**
   * The context resolve mechanism of PHP Mustache requires
   * __isset() to always return true.
   */
  public function __isset($name) {
    return true;
  }

  public function __get($name) {
    $name = implode('.', array_filter(array($this->path, $name)));

    return new MustacheResource($this->resourceInstance, $name);
  }

  /**
   * Other methods never get the resource key directly,
   * instead it relies on this __toString() method as a
   * last-second-resolving.
   */
  public function __toString() {
    $value = $this->resourceInstance->{$this->path};

    if ( is_array($value) ) {
      $flattenArray = function($input) use(&$flattenArray) {
        foreach ( $input as $value ) {
          if ( is_array($value) ) {
            $value = $flattenArray($value);
          }
        }

        return implode(', ', $input);
      };

      $value = $flattenArray($value);

      unset($flattenArray);
    }

    return (string) $value;
  }

}
