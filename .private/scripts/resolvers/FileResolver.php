<?php
/* FileResolver.php \ IRequestResolver | Physical file resolver, creating minified versions on demand. */

namespace resolvers;

class FileResolver implements \framework\interfaces\IRequestResolver {
  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  //------------------------------
  //  defaultPath
  //------------------------------
  private $defaultPath = '.';

  /**
   * Target path to serve.
   */
  public function defaultPath($value = null) {
    if ( $value === null ) {
      return $this->defaultPath;
    }

    if ( $value ) {
      $value = preg_replace('/\/$/', '', $value);
    }

    $this->defaultPath = $value;
  }

  //------------------------------
  //  directoryIndex
  //------------------------------
  private $directoryIndex;

  /**
   * Emulate DirectoryIndex chain
   */
  public function directoryIndex($value = null) {
    if ( $value === null ) {
      return $this->directoryIndex;
    }

    if ( is_string($value) ) {
      $value = explode(' ', $value);
    }

    $this->directoryIndex = $value;
  }

  //------------------------------
  //  cacheExclusions
  //------------------------------
  private $cacheExclusions = array('php');

  /**
   * Conditional request is disregarded when
   * requested file contains these extensions.
   *
   * Related HTTP server headers are:
   * 1. Last Modified
   * 2. ETag
   *
   * Related HTTP client headers are:
   * 1. If-Modified-Since
   * 2. If-None-Match
   */
  public function cacheExclusions($value = null) {
    if ( $value === null ) {
      return $this->cacheExclusions;
    }

    if ( is_string($value) ) {
      $value = explode(' ', $value);
    }

    $this->cacheExclusions = $value;
  }

  //--------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //--------------------------------------------------

  public /* Boolean */
  function resolve($path) {
    $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

    if ( $path[0] == DIRECTORY_SEPARATOR ) {
      $path = substr($path, 1);
    }

    $prefix = $this->defaultPath . DIRECTORY_SEPARATOR;

    if ( stripos($path, $prefix) !== 0 ) {
      $path = $prefix . $path;
    }

    $res = explode('?', $path, 2);

    $queryString = isset($res[1]) ? $res[1] : '';

    $res = urldecode($res[0]);

    //------------------------------
    //  Emulate DirectoryIndex
    //------------------------------

    if ( is_dir($res) ) {
      // apache_lookup_uri($path)
      if ( false && function_exists('apache_lookup_uri') ) {
        $res = apache_lookup_uri($path);
        $res = $_SERVER['DOCUMENT_ROOT'] . $res->uri;

        // $_SERVER[REDIRECT_URL]
        if ( !is_file($res) ) {
          $res = "./$path" . basename($_SERVER['REDIRECT_URL']);
        }
      }

      if ( !is_file($res) ) {
        $files = $this->directoryIndex();

        foreach ( $files as $file ) {
          $file = $this->resolve("$res$file");

          // Not a fully resolved path at the moment,
          // starts resolve sub-chain.
          if ( $file !== false ) {
            return $file;
          }
        }
      }
    }

    //------------------------------
    //  Virtual file handling
    //------------------------------
    $this->chainResolve($res);

    if ( !is_file($res) ) {
      return false;
    }

    $this->handle($res);

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
   *
   * Q: Possible to make an internal request through Apache?
   * A: Not realistic, too much configuration.
   */
  private function handle($path) {
    $mtime = filemtime($path);

    $mime = $this->mimetype($path);
    $this->sendCacheHeaders($path, $mime !== null);

    // Request header: If-Modified-Since
    if ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
      strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime ) {
      throw new \framework\exceptions\ResolverException(304);
    }

    $eTag = $this->fileETag($path);

    /* Note by Vicary @ 17 Jul, 2013
       Since apache handles the Range header on its own,
       we can safely treate If-Range as if it is If-Modified-Since or If-Match.
    */
    // Request header: If-Range
    if ( isset($_SERVER['HTTP_IF_RANGE']) ) {
      /* Note by Vicary @ 17 Jul, 2013
         According to RFC2616 section 14.27 ...
         The server can distinguish between a valid HTTP-date and any form of
         entity-tag by examining no more than two characters.

         Possible entity tag formats: (Not sure if weak eTags applies here)
         If-Range: "xyzzy"[, "r2d2xxxx", "c3piozzzz"]
         If-Range: W/"xyzzy"[, W/"r2d2xxxx", W/"c3piozzzz"]
      */

      // Entity tag
      if ( preg_match('/^("|W\/")/', $_SERVER['HTTP_IF_RANGE']) ) {
        preg_match_all('/(?:^\*$|("[^\*"]+")(?:\s*,\s*("[^\*"]+")))$/', @$_SERVER['HTTP_IF_NONE_MATCH'], $eTags);

        if ( @$eTags[1] && in_array($eTag, (array) $eTags[1]) ) {
          throw new \framework\exceptions\ResolverException(304);
        }

        unset($eTags);
      }

      // Http date
      elseif ( strtotime($_SERVER['HTTP_IF_RANGE']) >= $mtime ) {
        throw new \framework\exceptions\ResolverException(304);
      }
    }

    /* Note by Vicary @ 24 Jan, 2013

       CAUTION: If-Modified-Since take precedence!

       It's damn complicated on If-None-Match ... see RFC2616 section 14.26 If-None-Match for more details.
       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html

       Particularly, the following sentences give hints that we should pay attention to,
       1. The requesting resources
       2. The request method

       If any of the entity tags match the entity tag of the entity that would have been returned in the
       response to a similar GET request (without the If-None-Match header) on that resource, or if "*"
       is given and any current entity exists for that resource, then the server MUST NOT perform the
       requested method, unless required to do so because the resource's modification date fails to match
       that supplied in an If-Modified-Since header field in the request. Instead, if the request method
       was GET or HEAD, the server SHOULD respond with a 304 (Not Modified) response, including the cache-
       related header fields (particularly ETag) of one of the entities that matched. For all other request
       methods, the server MUST respond with a status of 412 (Precondition Failed).

    */
    // Request header: If-None-Match
    if ( !isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && isset($_SERVER['HTTP_IF_NONE_MATCH']) ) {
      // Exists but not GET or HEAD
      switch ( @$_SERVER['REQUEST_METHOD'] ) {
        case 'GET': case 'HEAD':
          break;

        default:
          throw new \framework\exceptions\ResolverException(412);
          break;
      }

      /* Note by Vicary @ 24 Jan, 2013
         If-None-Match means 304 when target resources exists.
      */
      if ( $_SERVER['HTTP_IF_NONE_MATCH'] === '*' && $eTag ) {
        throw new \framework\exceptions\ResolverException(304);
      }

      preg_match_all('/(?:^\*$|("[^\*"]+")(?:\s*,\s*("[^\*"]+")))$/', $_SERVER['HTTP_IF_NONE_MATCH'], $eTags);

      if ( @$eTags[1] && in_array($eTag, (array) $eTags[1]) ) {
        throw new \framework\exceptions\ResolverException(304);
      }
    }

    /* Note by Vicary @ 24 Jan, 2013

       CAUTION: If-Modified-Since take precedence!

       According to RFC2616 section 14.24 If-Match, ...
       If the request would, without the If-Match header field, result in anything other
       than a 2xx or 412 status, then the If-Match header MUST be ignored.

       And since section 14.26 If-None-Match contains no conflicting sentence over this,
       HTTP header If-None-Match should take precedence over this.

    */
    // Request header: If-Match
    if ( !isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && isset($_SERVER['HTTP_IF_MATCH']) ) {
      // Exists but not GET or HEAD
      switch ( @$_SERVER['REQUEST_METHOD'] ) {
        case 'GET': case 'HEAD':
          break;

        default:
          throw new \framework\exceptions\ResolverException(412);
          break;
      }

      /* Note by Vicary @ 24 Jan, 2013
         Normally this framework would not generate 412 with *,
         all existing files has an entity tag with md5 hashing.

         One single condition is that md5_file() fails to generate
         a hash from target file, which is normally an exception.
         In such cases, a 500 Internal Server Error sholud be
         generated instead of this.

         But anyways ... let's follow the spec.
      */
      if ( $_SERVER['HTTP_IF_MATCH'] === '*' && !$eTag ) {
        throw new \framework\exceptions\ResolverException(412);
      }

      preg_match_all('/(?:^\*$|(:?"([^\*"]+)")(?:\s*,\s*(:?"([^\*"]+)")))$/', $_SERVER['HTTP_IF_MATCH'], $eTags);

      // 412 Precondition Failed when nothing matches.
      if ( @$eTags[1] && !in_array($eTag, (array) $eTags[1]) ) {
        throw new \framework\exceptions\ResolverException(412);
      }

      unset($eTags);
    }

    unset($eTag);

    // $_SERVER field mapping
    $_SERVER['SCRIPT_FILENAME'] = realpath($path);
    $_SERVER['SCRIPT_NAME'] = $_SERVER['REQUEST_URI'];
    $_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];

    // Send appropriate mime headers.
    switch ( $mime ) {
      case 'text/x-php':
        header('Content-Type: text/html; charset=utf8', true);
        break;

      default:
        $headerString = $mime;

        if ( preg_match('/^text\//', $mime) ) {
          $headerString.= '; charset=utf-8';
        }

        header("Content-Type: $headerString", true);

        unset($headerString);
        break;
    }

    ob_start();

    switch ( $mime ) {
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
      default:
        unset($mtime, $mime);

        readfile($path);
        break;

      case 'text/x-php':
        include_once($path);
        break;

      case 'text/html':
      case null: // ... ahem
        readfile($path);
        break;
    }

    $contentLength = ob_get_length();

    $response = ob_get_clean();

    // Send HTTP header Content-Length according to the output buffer if it is not sent.
    $headers = headers_list();

    $contentLengthSent = false;

    foreach ( $headers as $header ) {
      if ( stripos($header, 'Content-Length') !== false ) {
        $contentLengthSent = true;
        break;
      }
    }
    unset($headers, $header);

    if ( !$contentLengthSent && !headers_sent() ) {
      header("Content-Length: $contentLength", true);
    }

    echo $response;
  }

  /**
   * @private
   */
  private function chainResolve(&$path) {
    switch ( pathinfo($path , PATHINFO_EXTENSION) ) {
      case 'js':
        // When requesting *.min.js, minify it from the original source.
        $opath = preg_replace('/\.min(\.js)$/', '\\1', $path, -1, $count);

        if ( $count > 0 && is_file($opath) ) {
          $mtime = filemtime($opath);

          // Whenever orginal source exists and is newer,
          // udpate minified version.
          if ( !is_file($path) || $mtime > filemtime($path) ) {
            $output = shell_exec("cat $opath | uglifyjs -o $path 2>&1");

            if ( $output ) {
              $output = "[uglifyjs] $output.";
            }
            elseif ( !file_exists($path) ) {
              $output = "[uglifyjs] Error writing output file $path.";
            }

            if ( $output ) {
              \log::write($output, 'Warning');

              // Error caught when minifying javascript, rollback to original.
              $path = $opath;
            }
            else {
              touch($path, $mtime);
            }
          }
        }
        break;
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
      case 'css':
        // Auto compile CSS from LESS
        $lessPath = preg_replace('/(?:\.min)?\.css$/', '.less', $path);

        if ( file_exists($lessPath) ) {
          try {
            (new \lessc)->checkedCompile($lessPath, $path);
          }
          catch (\Exception $e) {
            \log::write('Unable to compile CSS from less.', 'Exception', $e);

            die;
          }
        }

        unset($lessPath);

        // Auto minify *.min.css it from the original *.css.
        $opath = preg_replace('/\.min(\.css)$/', '\\1', $path, -1, $count);

        if ( $count > 0 && is_file($opath) ) {
          $mtime = filemtime($opath);

          $ctime = \cache::get($path);

          /* Whenever orginal source exists and is newer, update minified version. */
          if ( !is_file($path) || $mtime > filemtime($path) ) {
            // Store the offset in cache, enabling a waiting time before HTTP retry.
            \cache::delete($path);
            \cache::set($path, time());

            $opath = realpath($opath);
            $contents = $this->resolve($opath);
            $contents = $this->minifyCSS($contents);

            if ( $contents ) {
              file_put_contents($path, $contents);
            }

            unset($contents);
          }

          if ( !is_file($path) ) {
            $path = $opath;
          }
        }
        break;
      case 'csv':
        header('Content-Disposition: attachment;', true);
        break;
      default:
        // Extension-less
        if (!is_file($path) && preg_match('/^[^\.]+$/', basename($path))) {
          $files = glob("./$path.*");

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

  /**
   * @private
   */
  private function sendCacheHeaders($path, $permanant = false) {
    if ( false !== array_search(pathinfo($path , PATHINFO_EXTENSION), $this->cacheExclusions) ) {
      return;
    }

    if ( $permanant ) {
      header_remove('Pragma');
      header('Cache-Control: max-age=' . FRAMEWORK_RESPONSE_CACHE_PERMANANT, true);
      header('Expires: ' . gmdate(DATE_RFC1123, time() + FRAMEWORK_RESPONSE_CACHE_PERMANANT), true);
      header('ETag: "' . $this->fileETag($path) . '"', true); // Strong ETag
    }
    else {
      header('Cache-Control: private, max-age=' . FRAMEWORK_RESPONSE_CACHE_TEMPORARY . ', must-revalidate', true);
      header('ETag: W/"' . $this->fileETag($path) . '"', true); // Weak ETag
    }

    // TODO: Generates an ETag base on target mime type.

    header('Last-Modified: ' . date(DATE_RFC1123, filemtime($path)), true);
  }

  /**
   * @private
   */
  private function fileETag($path) {
    return md5_file($path);
  }

  /**
   * @private
   */
  private function minifyCSS($contents) {
    $result = '';

    \core\Net::httpRequest(array(
        'url' => 'http://cssminifier.com/raw'
      , 'type' => 'post'
      , 'data' => array( 'input' => $contents )
      , '__curlOpts' => array(
          CURLOPT_TIMEOUT => 2
        )
      , 'success' => function($response, $request) use(&$result) {
          if ( @$request['status'] == 200 && $response ) {
            $result = $response;
          }
        }
      ));

    return $result;
  }

  /**
   * @private
   */
  private function mimetype($path) {
    $mime = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $mime->file($path);

    switch ( pathinfo($path , PATHINFO_EXTENSION) ) {
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
        if ( !preg_match('/(^image|pdf$)/', $mime) ) {
          $mime = null;
        }
        break;
    }

    return $mime;
  }
}
