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
    $finfo = new \finfo($type);
    $finfo = $finfo->file($this->getPathname());

    if ( $type === FILEINFO_MIME_TYPE ) {
      $mime = &$finfo;
    }
    else if ( is_array($finfo) ) {
      $mime = &$finfo['type'];
    }

    // Predefined mime type based on file extensions.
    switch ( pathinfo($this->getFilename(), PATHINFO_EXTENSION) ) {
      case 'css':
        $mime = 'text/css; charset=utf-8';
        break;
      case 'js':
        $mime = 'text/javascript; charset=utf-8';
        break;
      case 'pdf':
        $mime = 'application/pdf';
        break;
      case 'php':
      case 'phps':
      case 'html':
        $mime = 'text/x-php';
        break;
      case 'cff':
        $mime = 'application/font-cff';
        break;
      case 'ttf':
        $mime = 'application/font-ttf';
        break;
      case 'woff':
        $mime = 'application/font-woff';
        break;
      case 'eot':
        $mime = 'applicaiton/vnd.ms-fontobject';
        break;
      case 'otf':
        $mime = 'font/otf';
        break;
      case 'svgz':
        header('Content-Encoding: gzip');
      case 'svg':
        $mime = 'image/svg+xml; charset=utf-8';
        break;
      case 'csv':
        $mime = 'text/csv; charset=utf-8';
        break;
      case 'doc':
      case 'dot':
        $mime = 'application/msword';
        break;
      case 'docx':
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        break;
      case 'dotx':
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.template';
        break;
      case 'docm':
        $mime = 'application/vnd.ms-word.document.macroEnabled.12';
        break;
      case 'dotm':
        $mime = 'application/vnd.ms-word.template.macroEnabled.12';
        break;
      case 'xls':
      case 'xlt':
      case 'xla':
        $mime = 'application/vnd.ms-excel';
        break;
      case 'xlsx':
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        break;
      case 'xltx':
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.template';
        break;
      case 'xlsm':
        $mime = 'application/vnd.ms-excel.sheet.macroEnabled.12';
        break;
      case 'xltm':
        $mime = 'application/vnd.ms-excel.template.macroEnabled.12';
        break;
      case 'xlam':
        $mime = 'application/vnd.ms-excel.addin.macroEnabled.12';
        break;
      case 'xlsb':
        $mime = 'application/vnd.ms-excel.sheet.binary.macroEnabled.12';
        break;
      case 'ppt':
      case 'pot':
      case 'pps':
      case 'ppa':
        $mime = 'application/vnd.ms-powerpoint';
        break;
      case 'pptx':
        $mime = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        break;
      case 'potx':
        $mime = 'application/vnd.openxmlformats-officedocument.presentationml.template';
        break;
      case 'ppsx':
        $mime = 'application/vnd.openxmlformats-officedocument.presentationml.slideshow';
        break;
      case 'ppam':
        $mime = 'application/vnd.ms-powerpoint.addin.macroEnabled.12';
        break;
      case 'pptm':
        $mime = 'application/vnd.ms-powerpoint.presentation.macroEnabled.12';
        break;
      case 'potm':
        $mime = 'application/vnd.ms-powerpoint.template.macroEnabled.12';
        break;
      case 'ppsm':
        $mime = 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12';
        break;
      default:
        $mime = $this->defaultContentType;
        break;
    }

    return $finfo;
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
    // $this->
    $target = new \SplFileObject($path, $append ? 'a' : 'w');

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
