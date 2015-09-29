<?php
/*! RequestPutFile.php | SplFileObject interface for files uploaded via HTTP PUT method. */

namespace framework;

/**
 * In order to correctly determine mimetype, we must save the contents to a temp
 * file first.
 */
class RequestPutFile extends \SplFileObject {

  /**
   * @constructor
   *
   * @param {?string} $defaultContentType Default mimetype of the uploading file,
   *                                      when finfo failed in guessing one.
   */
  function __construct($defaultContentType = 'application/octet-stream') {
    if ( $defaultContentType ) {
      $this->defaultContentType = $defaultContentType;
    }

    $file = tempnam(sys_get_temp_dir(), 'tmp.');;

    stream_copy_to_stream(fopen('php://input', 'r'), fopen($file, 'r'));

    parent::__construct($file);
  }

  function __destruct() {
    @unlink($this->getRealPath());
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @protected
   *
   * Explicitly set the mime type of this file when finfo cannot determine this.
   */
  protected $defaultContentType;

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
   * Saves the current upload stream to designated target.
   *
   * @param {string} Target path to save the contents, cannot be a directory.
   * @param {?bool} True to append to target file, defaults to replace.
   * @return {boolean} True on success, false otherwise.
   */
  public function save($path, $append = false) {
    if ( is_dir($path) ) {
      $path = preg_replace('/\\' . preg_quote(DS) . '?$/', '$1/' . $this->getFilename(), $path);
    }

    $target = new \SplFileObject($path, $append ? 'a' : 'w');

    $this->rewind();
    while ( !$this->eof() ) {
      $target->fwrite(
        $this->fread(4096)
        );
    }

    $target->fflush();
    $target->rewind();

    return $target;
  }

}
