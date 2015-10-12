<?php
/*! ExternalResolver.php \ IRequestResolver | Simple CDN for remote files. */

namespace resolvers;

use core\Utility as util;

use framework\Cache;
use framework\Request;
use framework\Response;

/*! Note:
 *  Resolve *.url files, it can be a plain URL string or
 *  the standard format of *.url files in Microsoft Windows.
 */

class ExternalResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * Base directory of URL pointer files.
   */
  protected $srcPath = '.';

  /**
   * @constructor
   *
   * @param {array} $options['source'] Source directory of URL file pointers, defaults to current working directory.
   */
  public function __construct(array $options = array()) {
    if ( !empty($options['source']) ) {
      $this->srcPath = (string) $options['source'];
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Methods: IRequestResolver
  //
  //----------------------------------------------------------------------------

  public function resolve(Request $request, Response $response) {
    $path = $this->srcPath . $request->uri('path') . '.url';

    // Check if target file is a proxy.
    if ( !is_file($path) ) {
      return;
    }

    $cacheTarget = parse_ini_file($path);
    $cacheTarget = @$cacheTarget['URL'];

    unset($path);

    if ( !$cacheTarget ) {
      Log::warning('Proxy file has not URL parameter.', array(
          'requestUri' => $request->uri(),
          'proxyFile' => $request->uri('path') . '.uri'
        ));

      $response->status(502); // Bad Gateway
      return;
    }

    /*! Cache Header Notes
     *
     *  # Cache-Control
     *  [public | private] Cacheable when public, otherwise the client is responsible for caching.
     *  [no-cache( \w+)?]  When no fields are specified, the whole thing must revalidate everytime,
     *                     otherwise cache it except specified fields.
     *  [no-store]         Ignore caching and pipe into output.
     *  [max-age=\d+]      Seconds before this cache is meant to expire, this overrides Expires header.
     *  [s-maxage=\d+]     Overrides max-age and Expires header, behaves just like max-age.
     *                     (This is for CDN and we are using it.)
     *  [must-revalidate]  Tells those CDNs which are intended to serve stale contents to revalidate every time.
     *  [proxy-revalidate] Like the "s-" version of max-age, a "must-revalidate" override only for CDN.
     *  [no-transform]     Some CDNs will optimize images and other formats, this "opt-out" of it.
     *
     *  # Expires
     *  RFC timestamp for an absolute cache expiration, overridden by Cache-Control header.
     *
     *  # ETag
     *  Hash of anything, weak ETags is not supported at this moment.
     *
     *  # vary
     *  Too much fun inside and we are too serious about caching, ignore this.
     *
     *  # pragma
     *  This guy is too old to recognize.
     *  [no-cache] Only this is known nowadays and is already succeed by Cache-Control: no-cache.
     *
     */

    // note; Use "cache-meta://" scheme for header and cache meta info, for performance.

    // 1. Check if cache exists.
    $cache = (array) Cache::get("cache-meta://$cacheTarget");

    // Cache expiration, in seconds.
    // expires = ( s-maxage || max-age || Expires );
    if ( @$cache['expires'] && time() > $cache['expires'] ) {
      Cache::delete("cache-meta://$cacheTarget");
      Cache::delete("cache://$cacheTarget");
      $cache = null;
    }

    // - If not exists, make normal request to remote server.
    // - If exists, make conditional request to remote server.
    //   - Revalidation, we can skip this request and serve the content if false.
    //     revalidates = ( Cache-Control:proxy-revalidate || Cache-Control:must-revalidate )
    if ( !$cache || @$cache['revalidates'] ) {
      $_request = array(
          'uri' => $cacheTarget
        );

      if ( $cache ) {
        // Last-Modified
        if ( @$cache['headers']['Last-Modified'] ) {
          $_request['headers']['If-Modified-Since'] = $cache['Last-Modified'];
        }

        // Entity-Tag
        if ( @$cache['headers']['ETag'] && strpos($cache['headers']['ETag'], 'W\\') !== 0 ) {
          $_request['headers']['If-None-Match'] = $cache['ETag'];
        }
      }
      else {
        $cache = array();
      }

      // Make the request
      $_response = new Response(array('autoOutput' => false));

      (new Request($_request))->send(null, $_response);

      unset($_request);

      // parse headers into cache settings.
      if ( in_array($_response->status(), array(200, 304)) ) {
        $res = preg_split('/\s*,\s*/', util::unwrapAssoc($_response->header('Cache-Control')));

        $res = array_reduce($res, function($res, $value) {
          // todo; Take care of no-cache with field name.

          if ( strpos($value, '=') > 0 ) {
            $value = explode('=', $value);
            $res[$value[0]] = $value[1];
          }
          else {
            $res[$value] = true;
          }

          return $res;
        }, array());

        // private, no-store, no-cache
        if ( @$res['private'] || @$res['no-store'] || @$res['no-cache'] ) {
          // note; in case the upstream server change this to uncacheable
          Cache::delete("cache-meta://$cacheTarget");
          Cache::delete("cache://$cacheTarget");

          $_response->clearBody();
        }

        if ( $_response->status() == 200 && $_response->body() ) {
          $cache['contents'] = $_response->body();
        }

        // expires = ( s-maxage || max-age || Expires );
        if ( @$res['s-maxage'] ) {
          $cache['expires'] = time() + $res['s-maxage'];
        }
        elseif ( @$res['max-age'] ) {
          $cache['expires'] = time() + $res['max-age'];
        }
        else {
          $res = util::unwrapAssoc($_response->header('Expires'));
          if ( $res ) {
            $cache['expires'] = strtotime($res);
          }
        }

        // revalidates = ( Cache-Control:proxy-revalidate || Cache-Control:must-revalidate )
        if ( @$res['proxy-revalidate'] || @$res['must-revalidate'] ) {
          $cache['revalidates'] = true;
        }

        unset($res);
      }

      $cache['headers'] = array_map('core\Utility::unwrapAssoc', $_response->header());

      // PHP does not support chunked, skip this one.
      unset($cache['headers']['Transfer-Encoding']);

      // note; If cache is to be ignored, the $cacheTarget variable will be already unset().
      if ( isset($cacheTarget) ) {
        if ( @$cache['contents'] ) {
          Cache::set("cache://$cacheTarget", $cache['contents']);
        }

        Cache::set("cache-meta://$cacheTarget", array_filter_keys($cache, isNot('contents')));
      }

      unset($_response);
    }

    // note; Send cache headers regardless of the request condition.
    if ( @$cache['headers'] ) {
      $response->clearHeaders();
      foreach ( $cache['headers'] as $name => $value ) {
        $response->header($name, $value, true);
      } unset($name, $value);
    }

    // note; Handles conditional request

    $ch = array_map('core\Utility::unwrapAssoc', (array) @$cache['headers']);

    $mtime = @$ch['Last-Modified'] ? strtotime($ch['Last-Modified']) : false;

    // Request headr: If-Modified-Since
    if ( @$ch['Last-Modified'] && $mtime ) {
      if ( strtotime($request->header('If-Modified-Since')) >= $mtime ) {
        return $response->status(304);
      }
    }

    // Request header: If-Range
    if ( $request->header('If-Range') ) {
      // Entity tag
      if ( strpos(substr($request->header('If-Range'), 0, 2), '"') !== false && @$ch['ETag']) {
        if ( $this->compareETags(@$ch['ETag'], $request->header('If-Range')) ) {
          return $this->response()->status(304);
        }
      }
      // Http date
      elseif ( strtotime($request->header('If-Range')) === $mtime ) {
        return $this->response()->status(304);
      }
    }

    unset($mtime);

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
      if ( $request->header('If-None-Match') === '*' && @$ch['ETag'] ) {
        return $this->response()->status(304);
      }

      if ( $this->compareETags(@$ch['ETag'], preg_split('/\s*,\s*/', $request->header('If-None-Match'))) ) {
        return $this->response()->status(304);
      }
    }

    // Request header: If-Match
    if ( !$request->header('If-Modified-Since') && $request->header('If-Match') ) {
      // Exists but not GET or HEAD
      switch ( $request->method() ) {
        case 'get': case 'head':
          break;

        default:
          return $this->response()->status(412);
      }

      if ( $request->header('If-Match') === '*' && !@$ch['ETag'] ) {
        return $this->response()->status(412);
      }

      preg_match_all('/(?:^\*$|(:?"([^\*"]+)")(?:\s*,\s*(:?"([^\*"]+)")))$/', $request->header('If-Match'), $eTags);

      // 412 Precondition Failed when nothing matches.
      if ( @$eTags[1] && !in_array($eTag, (array) $eTags[1]) ) {
        return $this->response()->status(412);
      }
    }

    if ( $cacheTarget && empty($cache['contents']) ) {
      $cache['contents'] = Cache::get("cache://$cacheTarget");
    }

    // Output the cahce content
    $response->send($cache['contents'], 200);
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
