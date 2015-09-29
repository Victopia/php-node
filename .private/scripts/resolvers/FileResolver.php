<?php
/*! FileResolver.php \ IRequestResolver | Physical file resolver, creating minified versions on demand. */

namespace resolvers;

use lessc;

use core\Log;
use core\Net;
use core\Utility;

use framework\Cache;
use framework\Configuration as conf;
use framework\Request;
use framework\Response;
use framework\System;

use framework\exceptions\ResolverException;

use framework\renderers\IncludeRenderer;
use framework\renderers\StaticFileRenderer;

class FileResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @constructor
   */
  public function __construct($options) {
    if ( !empty($options['prefix']) ) {
      $this->pathPrefix($options['prefix']);
    }

    if ( !empty($options['directoryIndex']) ) {
      $this->directoryIndex($options['directoryIndex']);
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  //------------------------------
  //  pathPrefix
  //------------------------------
  protected $pathPrefix = '/';

  /**
   * Target path to serve.
   */
  public function pathPrefix($value = null) {
    $pathPrefix = $this->pathPrefix;

    if ( $value !== null ) {
      $this->pathPrefix = '/' . trim(trim($value), '/');
    }

    return $pathPrefix;
  }

  //------------------------------
  //  directoryIndex
  //------------------------------
  private $directoryIndex;

  /**
   * Emulate DirectoryIndex chain
   */
  public function directoryIndex($value = null) {
    $directoryIndex = $this->directoryIndex;

    if ( $value !== null ) {
      $value = explode(' ', (string) $value);
      $this->directoryIndex = $value;
    }

    return $directoryIndex;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //----------------------------------------------------------------------------

  public function resolve(Request $request, Response $response) {
    // Stop processing when previous resolvers has done something and given a response status code.
    if ( $response->status() ) {
      return;
    }

    $path = $request->uri('path');

    // note; decode escaped URI characters into escaped shell path
    $path = preg_replace_callback('/%([\dA-F]{2,2})/i', function($matches) {
      return '\\' . chr(hexdec($matches[1]));
    }, $path);

    // Store original request
    if ( empty($request->__directoryIndex) ) {
      $request->__uri = $request->uri();
    }

    if ( stripos($path, $this->pathPrefix) === 0 ) {
      $path = substr($path, strlen($this->pathPrefix));
    }

    if ( strpos($path, '?') !== false ) {
      $path = strstr($path, '?', true);
    }

    $path = urldecode($path);

    if ( !$path ) {
      $path = './';
    }

    //------------------------------
    //  Emulate DirectoryIndex
    //------------------------------
    if ( is_dir($path) ) {
      if ( !is_file($path) && !isset($request->__directoryIndex) ) {
        // Prevent redirection loop
        $request->__directoryIndex = true;

        foreach ( $this->directoryIndex() as $file ) {
          $request->setUri(preg_replace('/^\.\//', '', $path) . $file);
          // Exit whenever an index is handled successfully, this will exit.
          if ( $this->resolve($request, $response) ) {
            return;
          }
        }

        unset($request->__directoryIndex);

        // Nothing works, going down.
        if ( isset($request->__uri) ) {
          $request->setUri($request->__uri);
        }
      }
    }
    // Redirects a directory index path to the parent directory.
    else if ( empty($request->__directoryIndex) ) {
      $dirname = dirname($path);
      if ( $dirname == '.' ) {
        $dirname = '/';
      }

      if ( in_array(pathinfo($path, PATHINFO_FILENAME), $this->directoryIndex()) ) {
        // extension-less
        if ( !pathinfo($path, PATHINFO_EXTENSION) || is_file($path) ) {
          $response->redirect($dirname);
          return true;
        }
      }

      unset($dirname);
    }

    //------------------------------
    //  Virtual file handling
    //------------------------------
    $this->createVirtualFile($path);
    if ( is_file($path) ) {
      try {
        $this->handle($path, $request, $response);
      }
      catch (ResolverException $e) {
        $response->status($e->statusCode());
      }

      if ( !$response->status() ) {
        $response->status(200);
      }

      return true;
    }
  }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  private function handles($file) {
    // Ignore hidden files
    if ( preg_match('/^\..*$/', basename($file)) ) {
      return false;
    }

    return true;
  }

  /**
   * Primary task when including PHP is that we need
   * to change $_SERVER variables to match target file.
   */
  private function handle($path, $request, $response) {
    $context = array(
        'request' => $request
      , 'response' => $response
      );

    $mime = Utility::getInfo($path, FILEINFO_MIME_TYPE);
    if ( strpos($mime, ';') !== false ) {
      $mime = substr($mime, 0, strpos($mime, ';'));
    }

    switch ( $mime ) {
      // note; special case, need content encoding header here. fall over to static file.
      case 'image/svg+xml':
        if ( pathinfo($path, PATHINFO_EXTENSION) == 'svgz' ) {
          $response->header('Content-Encoding: gzip');
        }

      // mime-types that we output directly.
      case 'application/pdf':
      case 'application/octect-stream':
      case 'image/jpeg':
      case 'image/jpg':
      case 'image/gif':
      case 'image/png':
      case 'image/bmp':
      case 'image/vnd.wap.wbmp':
      case 'image/tif':
      case 'image/tiff':
      case 'text/plain':
      case 'text/html':
      default:
        $renderer = new StaticFileRenderer($context);
        break;

      case 'application/x-php':
        $renderer = new IncludeRenderer($context);
        break;
    }

    $renderer->render($path);
  }

  /**
   * @private
   */
  private function createVirtualFile(&$path) {
    switch ( pathinfo($path , PATHINFO_EXTENSION) ) {
      case 'png':
        // When requesting *.png, we search for svg for conversion.
        $opath = preg_replace('/\.png$/', '.svg', $path, -1, $count);

        if ( $count > 0 && is_file($opath) ) {
          $mtime = filemtime($opath);

          // Whenever orginal source exists and is newer,
          // udpate minified version.
          if ( !is_file($path) || $mtime > filemtime($path) ) {
            $res = `/usr/bin/convert -density 72 -background none $opath $path 2>&1`;

            @touch($path, $mtime);
          }
        }
        break;
      case 'csv':
        header('Content-Disposition: attachment;', true);
        break;
      default:
        // Extension-less
        if ( !is_file($path) && preg_match('/^[^\.]+$/', basename($path)) ) {
          $files = glob("$path.*");

          foreach ( $files as &$file ) {
            if ( is_file($file) && $this->handles($file) ) {
              $path = $file;
              return;
            }
          }
        }
        break;
    }
  }
}
