<?php
/*! RequestPostFile.php | Wrapper class for uploaded files. */

namespace framework;

/**
 * The RequestPostFile object wraps files uploaded via both POST and PUT method.
 */
class RequestPostFile extends \SplFileObject {

  /**
   * @constructor
   *
   * @param {array|string} Either path to target file, or an array in $_FILES format.
   */
  function __construct($file, $open_mode = 'r', $use_include_path = false, $resource = null) {
    if ( is_array($file) ) {
      // POST filename
      if ( trim(@$file['name']) ) {
        $this->filename = $file['name'];
      }

      $filename = $file['tmp_name'];
    }
    else {
      $filename = (string) $file;
    }

    parent::__construct($filename, $open_mode, $use_include_path, $resource);
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   *
   * Original name of the uploaded file.
   */
  protected $filename;

  /**
   * Returns the original name of the posted file.
   */
  public function getFilename() {
    return $this->filename;
  }

  /**
   * Returns the fileinfo expression of current file.
   *
   * @param {int} $type One of the FILEINFO_* constants.
   * @return {array|string|boolean} Result of finfo_file($type), or false when not applicable.
   */
  public function getInfo($type = FILEINFO_MIME_TYPE) {
    return \core\Utility::getInfo($this->getRealPath(), $type);
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Same as calling move_uploaded_file() to target directory.
   *
   * @param {string} $path Path to save the uploaded file.
   *
   * @return {SplFileObject|boolean} SplFileObject describing the saved file, or
   *                                 false on failure.
   */
  public function save($path) {
    // Append filename when saving to directory
    if ( is_dir($path) ) {
      $path = preg_replace('/\\' . preg_quote(DS) . '?$/', '$1/' . $this->getFilename(), $path);
    }

    if ( move_uploaded_file($this->getRealPath(), $path) ) {
      return new \SplFileObject($path);
    }
    else {
      return false;
    }
  }

}
