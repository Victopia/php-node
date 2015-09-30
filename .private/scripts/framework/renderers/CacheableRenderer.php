<?php
/*! CacheableRenderer.php | Handles conditional requests and sends cache headers. */

namespace framework\renderers;

use framework\Cache;

class CacheableRenderer extends AbstractRenderer {

  //----------------------------------------------------------------------------
  //
  //  Constants
  //
  //----------------------------------------------------------------------------

  const HTTP_DATE = 'D, d M Y H:i:s \G\M\T';

  const MAX_AGE_TEMPORARY = 300; // 5 mins

  const MAX_AGE_PERMANENT = 2592000; // 30 days

  public function render($path) {
    $this->sendCacheHeaders($path);

    $request = $this->request();
    $response = $this->response();

    // Request header: If-Modified-Since
    $mtime = filemtime($path);
    if ( strtotime($request->header('If-Modified-Since')) >= $mtime ) {
      return $response->status(304);
    }

    $eTag = $this->generateETag($path);

    /*! Note by Vicary @ 17 Jul, 2013
     *  Since apache handles the Range header on its own,
     *  we can safely treate If-Range as if it is If-Unmodified-Since or If-Match.
     */
    // Request header: If-Range
    if ( $request->header('If-Range') ) {
      /*! Note by Vicary @ 17 Jul, 2013
       *  RFC2616 section 14.27:
       *  The server can distinguish between a valid HTTP-date and any form of
       *  entity-tag by examining no more than two characters.
       *
       *  Possible entity tag formats: (Not sure if weak eTags applies here)
       *  If-Range: "xyzzy"[, "r2d2xxxx", "c3piozzzz"]
       *  If-Range: W/"xyzzy"[, W/"r2d2xxxx", W/"c3piozzzz"]
       */
      /*! Note by Vicary @ 29 Apr, 2015
       *  RFC7233 section 3.2:
       *  Entity tags in If-Range must be a single strong (non-weak) validator,
       *  weak entity tags must be ignored.
       *
       *  Note that this comparison by exact match, including when the validator
       *  is an HTTP-date, differs from the "earlier than or equal to"
       *  comparison used when evaluating an If-Unmodified-Since conditional.
       *
       *  Because of the above paragraph, tracing back to RFC7232 section 2.2.2.
       */
      // Entity tag
      if ( strpos(substr($request->header('If-Range'), 0, 2), '"') !== false ) {
        if ( $this->compareETags($eTag, $request->header('If-Range')) ) {
          return $this->response()->status(304);
        }
      }
      // Http date
      elseif ( strtotime($request->header('If-Range')) == $mtime ) {
        return $this->response()->status(304);
      }
    }
    unset($mtime);

    /*! Note by Vicary @ 24 Jan, 2013
     *  It's damn complicated on If-None-Match ... see RFC2616 section 14.26 If-None-Match for more details.
     *  http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
     *
     *  Particularly, the following sentences give hints that we should pay attention to,
     *  1. The requesting resources
     *  2. The request method
     *
     *  If any of the entity tags match the entity tag of the entity that would have been returned in the
     *  response to a similar GET request (without the If-None-Match header) on that resource, or if "*"
     *  is given and any current entity exists for that resource, then the server MUST NOT perform the
     *  requested method, unless required to do so because the resource's modification date fails to match
     *  that supplied in an If-Modified-Since header field in the request. Instead, if the request method
     *  was GET or HEAD, the server SHOULD respond with a 304 (Not Modified) response, including the cache-
     *  related header fields (particularly ETag) of one of the entities that matched. For all other request
     *  methods, the server MUST respond with a status of 412 (Precondition Failed).
     *
     *  CAUTION: If-Modified-Since take precedence!
     */
    // Request header: If-None-Match
    if ( !$request->header('If-Modified-Since') && $request->header('If-None-Match') ) {
      // Exists but not GET or HEAD
      switch ( $request->method() ) {
        case 'get': case 'head':
          break;

        default:
          return $this->response()->status(412);
      }

      /*! Note by Vicary @ 24 Jan, 2013
       *  If-None-Match means 304 when target resources exists.
       */
      if ( $request->header('If-None-Match') === '*' && $eTag ) {
        return $this->response()->status(304);
      }

      if ( $this->compareETags($eTag, preg_split('/\s*,\s*/', $request->header('If-None-Match'))) ) {
        return $this->response()->status(304);
      }
    }

    /*! Note by Vicary @ 24 Jan, 2013
     *  According to RFC2616 section 14.24 If-Match, ...
     *  If the request would, without the If-Match header field, result in anything other
     *  than a 2xx or 412 status, then the If-Match header MUST be ignored.
     *
     *  And since section 14.26 If-None-Match contains no conflicting sentence over this,
     *  HTTP header If-None-Match should take precedence over this.
     *
     *  CAUTION: If-Modified-Since take precedence!
     */
    // Request header: If-Match
    if ( !$request->header('If-Modified-Since') && $request->header('If-Match') ) {
      // Exists but not GET or HEAD
      switch ( $request->method() ) {
        case 'get': case 'head':
          break;

        default:
          return $this->response()->status(412);
      }

      /*! Note by Vicary @ 24 Jan, 2013
       *  Normally this framework would not generate 412 with *,
       *  all existing files has an entity tag with md5 hashing.
       *
       *  One single condition is that md5_file() fails to generate
       *  a hash from target file, which is normally an exception.
       *  In such cases, a 500 Internal Server Error sholud be
       *  generated instead of this.
       *
       *  But anyways ... let's follow the spec.
       */
      if ( $request->header('If-Match') === '*' && !$eTag ) {
        return $this->response()->status(412);
      }

      preg_match_all('/(?:^\*$|(:?"([^\*"]+)")(?:\s*,\s*(:?"([^\*"]+)")))$/', $request->header('If-Match'), $eTags);

      // 412 Precondition Failed when nothing matches.
      if ( @$eTags[1] && !in_array($eTag, (array) $eTags[1]) ) {
        return $this->response()->status(412);
      }
    }
  }

  /**
   * @private
   */
  private function sendCacheHeaders($path) {
    $res = $this->response();

    // Weak cache for virtual contents
    if ( @$res->isVirtual ) {
      $res->header('Cache-Control', 'public, must-revalidate, max-age=' . self::MAX_AGE_TEMPORARY . '');
      $res->header('Expires', gmdate(static::HTTP_DATE, time() + self::MAX_AGE_TEMPORARY));
    }
    else {
      $res->header('Cache-Control', 'max-age=' . self::MAX_AGE_PERMANENT);
      $res->header('Expires', gmdate(static::HTTP_DATE, time() + self::MAX_AGE_PERMANENT));

      // For backward compatibility
      header_remove('Pragma');
    }

    $res->header('ETag', $this->generateETag($path));
    $res->header('Last-Modified', gmdate(static::HTTP_DATE, filemtime($path)), true);
  }

  /**
   * @private
   *
   * ETag generation mechanism is basically a MD5 hash of the file content,
   *   added that it will be a weak reference when:
   *
   * 1. The file is dynamically generated by automated process such as
   *    compilation, minification and lint.
   * 2. Target is a server cache genereated by either links to external files,
   *    or locally generated contents.
   *
   * This essentially require two types resolvers sorted ahead of FileResolver:
   *
   * 1. LessCompiler, CssMinifier, JavascriptMinifier, SvgConverter
   * 2. ExteralResolver, CacheResolver
   */
  private function generateETag($value) {
    if ( is_file($value) ) {
      if ( is_readable($value) ) {
        $value = md5_file($value);
      }
      else {
        return null;
      }
    }
    else {
      $value = md5($value);
    }

    $value = '"'.$value.'"';
    if ( @$this->response()->isVirtual ) {
      $value = "W\\$value";
    }

    return $value;
  }

  /**
   * @private
   *
   * Weak $needles will strip all weak tags in $haystack before matching,
   * otherwise it will invoke in_array() search as-is.
   */
  private function compareETags($needle, $haystack) {
    $haystack = (array) $haystack;

    // Weak entity-tag, strip all weak tags in $haystack
    if ( strpos($needle, 'W/') === 0 ) {
      $needle = substr($needle, 2);
      $haystack = array_map(unshiftsArg('preg_replace', '/^W\//', ''), $haystack);
    }

    return in_array($needle, $haystack);
  }

}
