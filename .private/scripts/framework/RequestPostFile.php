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
    $finfo = new \finfo($type);
    $finfo = $finfo->file($this->getRealPath());

    if ( $type === FILEINFO_MIME_TYPE ) {
      $mime = &$finfo;
    }
    else if ( is_array($finfo) ) {
      $mime = &$finfo['type'];
    }

    // Predefined mime type based on file extensions.
    switch ( pathinfo($this->filename, PATHINFO_EXTENSION) ) {
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
    }

    return $finfo;
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
      $path = preg_replace('/\\' . preg_quote(DS) . '?$/', '$1' . DS . $this->getFilename(), $path);
    }

    if ( move_uploaded_file($this->getRealPath(), $path) ) {
      return new \SplFileObject($path);
    }
    else {
      return false;
    }
  }

}
