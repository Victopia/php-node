<?php
/*! FileResolver.php \ IRequestResolver
 *
 *  Physical file resolver, subsequence
 */

namespace resolvers;

class FileResolver implements \framework\interfaces\IRequestResolver {
  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  //------------------------------
  //  directoryIndex
  //------------------------------
  private static $directoryIndex;

  /**
   * Emulate DirectoryIndex chain
   */
  public function directoryIndex($value = NULL) {
    if ($value === NULL) {
      return self::$directoryIndex;
    }

    if (is_string($value)) {
      $value = explode(' ', $value);
    }

    self::$directoryIndex = $value;
  }

  //------------------------------
  //  cacheExclusions
  //------------------------------
  private static $cacheExclusions = array('php');

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
  public function cacheExclusions($value = NULL) {
    if ($value === NULL) {
      return self::$cacheExclusions;
    }

    if (is_string($value)) {
      $value = explode(' ', $value);
    }

    self::$cacheExclusions = $value;
  }

  //--------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //--------------------------------------------------

  public
  /* Boolean */ function resolve($path) {
    $res = explode('?', $path, 2);

    $queryString = isset($res[1]) ? $res[1] : '';

    if (@$res[0][0] === '/') {
      $res = ".$res[0]";
    }
    else {
      $res = $res[0];
    }

    $res = urldecode($res);

    //------------------------------
    //  Emulate DirectoryIndex
    //------------------------------
    if (is_dir($res)) {

      // apache_lookup_uri($path)
      if (false && function_exists('apache_lookup_uri')) {
        $res = apache_lookup_uri($path);
        $res = $_SERVER['DOCUMENT_ROOT'] . $res->uri;

        // $_SERVER[REDIRECT_URL]
        if (!is_file($res)) {
          $res = "./$path" . basename($_SERVER['REDIRECT_URL']);
        }
      }

      if (!is_file($res)) {
        $files = $this->directoryIndex();

        foreach ($files as $file) {
          $file = $this->resolve("$res$file");

          // Not a fully resolved path at the moment,
          // starts resolve sub-chain.
          if ($file !== FALSE) {
            return $file;
          }
        }
      }

    }

    //------------------------------
    //  Virtual file handling
    //------------------------------
    $this->chainResolve($res);

    if (!is_file($res)) {
      return FALSE;
    }

    //------------------------------
    //  Browser blocking
    //------------------------------
    /* Note by Eric @ 20 Dec, 2012
        Fucking dirty code insertion here, breaks the framework.
        But who cares? Just make it works.
    */
    // Prevent other browsers from accessing the site.
    if (!\utils::isCLI() && isset($_SERVER['HTTP_USER_AGENT']) &&
        !preg_match('/webkit/i', $_SERVER['HTTP_USER_AGENT']) &&
        !preg_match('/ebay\.com/i', @$_SERVER['HTTP_REFERER']) &&
        !isset($_SESSION['notSupport'])) {
      $_SESSION['notSupport'] = true;

      $target = \conf::get('misc.Compatibility::notSupported');

      if ($target) {
        header("Location: $target", true);
        die;
      }
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
    if (preg_match('/^\..*$/', basename($file))) {
      return FALSE;
    }

    return TRUE;
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
    $this->sendCacheHeaders($path, $mime !== NULL);

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
      strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
      throw new \framework\exceptions\ResolverException(304);
    }

    $eTag = $this->fileETag($path);

    /* Note by Eric @ 24 Jan, 2013

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
    if (!isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
      // Exists but not GET or HEAD
      switch (@$_SERVER['REQUEST_METHOD']) {
        case 'GET': case 'HEAD':
          break;

        default:
          throw new \framework\exceptions\ResolverException(412);
          break;
      }

      /* Note by Eric @ 24 Jan, 2013
         If-None-Match means 304 when target resources exists.
      */
      if ($_SERVER['HTTP_IF_NONE_MATCH'] === '*' && $eTag) {
        throw new \framework\exceptions\ResolverException(304);
      }

      preg_match_all('/(?:^\*$|("[^\*"]+")(?:\s*,\s*("[^\*"]+")))$/', $_SERVER['HTTP_IF_NONE_MATCH'], $eTags);

      if (@$eTags[1] && in_array($eTag, (array) $eTags[1])) {
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
    if (!isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && isset($_SERVER['HTTP_IF_MATCH'])) {
      // Exists but not GET or HEAD
      switch (@$_SERVER['REQUEST_METHOD']) {
        case 'GET': case 'HEAD':
          break;

        default:
          throw new \framework\exceptions\ResolverException(412);
          break;
      }

      /* Note by Eric @ 24 Jan, 2013
         Normally this framework would not generate 412 with *,
         all existing files has an entity tag with md5 hashing.

         One single condition is that md5_file() fails to generate
         a hash from target file, which is normally an exception.
         In such cases, a 500 Internal Server Error sholud be
         generated instead of this.

         But anyways ... let's follow the spec.
      */
      if ($_SERVER['HTTP_IF_MATCH'] === '*' && !$eTag) {
        throw new \framework\exceptions\ResolverException(412);
      }

      preg_match_all('/(?:^\*$|(:?"([^\*"]+)")(?:\s*,\s*(:?"([^\*"]+)")))$/', $_SERVER['HTTP_IF_MATCH'], $eTags);

      // 412 Precondition Failed when nothing matches.
      if (@$eTags[1] && !in_array($eTag, (array) $eTags[1])) {
        throw new \framework\exceptions\ResolverException(412);
      }

      unset($eTags);
    }

    unset($eTag);

    // $_SERVER field mapping
    $_SERVER['SCRIPT_FILENAME'] = realpath($path);
    $_SERVER['SCRIPT_NAME'] = $_SERVER['REQUEST_URI'];
    $_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];

    ob_start();

    switch($mime) {
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
        readfile($path);
        break;

      // mime-types that possibly be PHP script.
      case 'text/html':
      case 'text/x-php':
      case NULL: // ... ahem
        include_once($path);
        break;
    }

    $contentLength = ob_get_length();

    $response = ob_get_clean();

    // Send HTTP header Content-Length according to the output buffer if it is not sent.
    $headers = headers_list();
    $contentLengthSent = FALSE;
    foreach ($headers as $header) {
      if (stripos($header, 'Content-Length') !== FALSE) {
        $contentLengthSent = TRUE;
        break;
      }
    }
    unset($headers, $header);

    if ($mime !== NULL) {
      header("Content-Type: $mime", true);
    }

    if (!$contentLengthSent) {
      header("Content-Length: $contentLength", true);
    }

    echo $response;
  }

  /**
   * @private
   */
  private function chainResolve(&$path) {
    switch (pathinfo($path , PATHINFO_EXTENSION)) {
      case 'js':
        // When requesting *.min.js, minify it from the original source.
        $opath = preg_replace('/\.min(\.js)$/', '\\1', $path, -1, $count);

        if (true && $count > 0 && is_file($opath)) {
          $mtime = filemtime($opath);

          // Whenever orginal source exists and is newer,
          // udpate minified version.
          if (!is_file($path) || $mtime > filemtime($path)) {
            $output = `cat $opath | .private/uglifyjs -o $path 2>&1`;

            if ($output) {
              $output = "[uglifyjs] $output.";
            }
            elseif (!file_exists($path)) {
              $output = "[uglifyjs] Error writing output file $path.";
            }

            if ($output) {
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

        if ($count > 0 && is_file($opath)) {
          $mtime = filemtime($opath);

          // Whenever orginal source exists and is newer,
          // udpate minified version.
          if (!is_file($path) || $mtime > filemtime($path)) {
            $res = `/usr/bin/convert -density 72 -background none $opath $path 2>&1`;

            touch($path, $mtime);
          }
        }
        break;
      case 'css':
        // When requesting *.min.css, minify it from the original source.
        $opath = preg_replace('/\.min(\.css)$/', '\\1', $path, -1, $count);

        if ($count > 0 && is_file($opath)) {
          $mtime = filemtime($opath);

          $ctime = \cache::get($path);

          // Whenever orginal source exists and is newer,
          // udpate minified version.

          // Wait for a time before re-download, no matter the
          // last request failed or not.
          if (time() - $ctime > 3600 && (!is_file($path) || $mtime > filemtime($path))) {
            // Store the offset in cache, enabling a waiting time before HTTP retry.
            \cache::delete($path);
            \cache::set($path, time());

            $opath = realpath($opath);

            \core\Net::httpRequest(array(
                'url' => 'http://www.cssminifier.com/raw'
              , 'type' => 'post'
              , 'data' => array( 'input' => file_get_contents($opath) )
              , '__curlOpts' => array(
                  CURLOPT_TIMEOUT => 2
                )
              , 'callbacks' => array(
                  'success' => function($response, $request) use($path) {
                    if ($response) {
                      file_put_contents($path, $response);
                    }
                  }
                , 'failure' => function() use($path) {
                    @unlink($path);
                  }
                )
              ));
          }

          if (!is_file($path)) {
            $path = $opath;
          }
        }
        break;
      case 'csv':
        header('Content-Disposition: attachment;', true);
        break;
      default:
        // Extension-less
        if (!is_file($path) &&
          preg_match('/^[^\.]+$/', basename($path))) {
          $files = glob("./$path.*");

          foreach ($files as &$file) {
            if (is_file($file) && $this->handles($file)) {
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
  private function sendCacheHeaders($path, $permanant = FALSE) {
    if (array_search(pathinfo($path , PATHINFO_EXTENSION),
      self::$cacheExclusions) !== FALSE) {
      return;
    }

    if ($permanant) {
      header_remove('Pragma');
      header('Cache-Control: max-age=' . FRAMEWORK_RESPONSE_CACHE_PERMANANT, TRUE);
      header('Expires: ' . gmdate(DATE_RFC1123, time() + FRAMEWORK_RESPONSE_CACHE_PERMANANT), TRUE);
      header('ETag: "' . $this->fileETag($path) . '"', TRUE); // Strong ETag
    }
    else {
      header('Cache-Control: private, max-age=' . FRAMEWORK_RESPONSE_CACHE_TEMPORARY . ', must-revalidate', TRUE);
      header('ETag: W/"' . $this->fileETag($path) . '"', TRUE); // Weak ETag
    }

    // TODO: Generates an ETag base on target mime type.

    header('Last-Modified: ' . date(DATE_RFC1123, filemtime($path)), TRUE);
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
  private function mimetype($path) {
    $mime = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $mime->file($path);

    switch (pathinfo($path , PATHINFO_EXTENSION)) {
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
        $mime = NULL;
        break;
      case 'ttf':
        $mime = 'application/x-font-ttf';
        break;
      case 'woff':
        $mime = 'application/x-font-woff';
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
      default:
        if (!preg_match('/(^image|pdf$)/', $mime)) {
          $mime = NULL;
        }
        break;
    }

    return $mime;
  }
}