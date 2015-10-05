<?php
/* Request.php | Helper class that parses useful information from the current HTTP request. */

namespace framework;

use Locale;

use core\ContentDecoder;
use core\Net;
use core\Utility as util;

use framework\exceptions\FrameworkException;

/**
 * Wrapper class of the current request.
 *
 * Normalize reading interface for various request related information.
 */
class Request {

  //----------------------------------------------------------------------------
  //
  //  Constructor
  //
  //----------------------------------------------------------------------------

  /**
   * @constructor
   *
   * @param {?string|array} $options An array of request options, or the URI string.
   * @param {?string} $options['prefix'] Parameters keys start with this prefix
   *                                     will be treated as meta-parameters and
   *                                     not returned in param() related functions.
   *
   *                                     Parameters values start with this prefix
   *                                     will be tried to parsed as constants and
   *                                     booleans.
   *
   *                                     This defaults to the "@" character.
   *
   * @param {?string} $options['uri'] The request uri, defaults to $_SERVER['REQUEST_URI'].
   * @param {?string} $options['method'] Request method, defaults to $_SERVER['REQUEST_METHOD'].
   * @param {?array} $options['headers'] Request headers, defaults to the contents of getallhheaders()
   *                                     if the function is available.
   * @param {?array} $options['client'] Request client details, defaults to everything from $_SERVER.
   * @param {?array} $options['get'] GET parameters in array format.
   * @param {?array} $options['post'] POST parameters in array format.
   * @param {?array} $options['cookies'] COOKIES in array format.
   * @param {?array} $options['files'] Upload files along with this request. (Use with care)
   *                                   When doing CURL requests, files array must compatible with CURL.
   *                                   Otherwise this must obey the resolver-request format.
   * @param {?string} $options['locale'] Requesting locale, defaults to en_US.
   */
  public function __construct($options = array()) {
    global $argv;

    if ( $options instanceof Resolver ) {
      $this->resolver = $options;
      $options = array();
    }

    if ( @$options ) {
      if ( is_string($options) ) {
        $options = array('uri' => $options);
      }

      // Special parameter prefix
      if ( !empty($options['prefix']) ) {
        $this->metaPrefix = $options['prefix'];
      }

      // Request URI
      if ( empty($options['uri']) ) {
        throw new FrameworkException('Request URI is required.');
      }
      else {
        $this->setUri($options['uri']);
      }

      // Request method
      if ( isset($options['method']) ) {
        $this->method = strtolower($options['method']);
      }
      else {
        $this->method = 'get';
      }

      // Request headers
      if ( isset($options['headers']) ) {
        $this->headers = (array) $options['headers'];
      }

      // Request client
      if ( !empty($options['client']) ) {
        $this->client = (array) $options['client'];
      }

      // Request parameters GET
      if ( isset($options['get']) ) {
        $this->paramCache['get'] = (array) $options['get'];
      }

      // Request parameters POST
      if ( !empty($options['post']) ) {
        $this->paramCache['post'] = (array) $options['post'];
      }

      // Cookies
      if ( isset($options['cookies']) ) {
        $this->paramCache['cookies'] = (array) $options['cookies'];
      }

      // File uploads
      if ( isset($options['files']) ) {
        $this->paramCache['files'] = (array) $options['files'];
      }

      // Request locale
      if ( !empty($options['locale']) ) {
        $this->locale = (string) $options['locale'];
      }
    }
    else {
      // Request client
      switch ( constant('PHP_SAPI') ) {
        case 'cli':
          $this->client = array(
              'type' => 'cli'
            , 'host' => gethostname()
            , 'user' => get_current_user()
            );
          break;

        default:
          $this->client = array_filter(array(
              'type' => 'http'
            , 'secure' => @$_SERVER['HTTPS'] && strtolower($_SERVER['HTTPS']) != 'off'
            , 'address' => @$_SERVER['REMOTE_ADDR']
            , 'host' => @$_SERVER['REMOTE_HOST']
            , 'port' => @$_SERVER['REMOTE_PORT']
            , 'user' => @$_SERVER['REMOTE_USER']
            , 'referer' => @$_SERVER['HTTP_REFERER']
            , 'version' => @$_SERVER['SERVER_PROTOCOL']
            , 'userAgent' => @$_SERVER['HTTP_USER_AGENT']
            , 'forwarder' => @$_SERVER['HTTP_X_FORWARDED_FOR']
            , 'isAjax' => strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
            ), compose('not', 'is_null'));
          break;
      }

      // Request method
      switch ( $this->client('type') ) {
        case 'cli':
          $this->method = 'cli';
          break;

        default:
          $this->method = strtolower(@$_SERVER['REQUEST_METHOD']);
          break;
      }

      // Request headers
      switch ( $this->client('type') ) {
        case 'cli':
          break;

        default:
          $this->headers = getallheaders();
          break;
      }

      // Request parameters
      switch ( $this->client('type') ) {
        case 'cli':
          $this->paramCache['cli'] = new Optimist();
          break;

        default:
          // Request parameters GET
          $this->paramCache['get'] = $_GET;

          // Request parameters POST
          if ( preg_match('/^application\/json/', $this->header('Content-Type')) ) {
            $this->paramCache['post'] = ContentDecoder::json(file_get_contents('php://input'), true);
          }
          else {
            $this->paramCache['post'] = $_POST;
          }

          // Cookies
          $this->paramCache['cookies'] = &$_COOKIE;

          // File uploads
          if ( $this->method() == 'put' ) {
            $this->paramCache['files'] = new RequestPutFile($this->header('Content-Type'));
          }
          else {
            util::filesFix();

            $parseFile = function($file) use(&$parseFile) {
              if ( !is_array($file) ) {
                return $file;
              }

              if ( util::isAssoc($file) ) {
                switch ( $file['error'] ) {
                  case UPLOAD_ERR_OK:
                    return new RequestPostFile($file);

                  case UPLOAD_ERR_NO_FILE:
                    // Skip it.
                    break;

                  default:
                    return $file['error'];
                }
              }
              else {
                return array_mapdef($file, $parseFile);
              }
            };

            $this->paramCache['files'] = array_mapdef(array_filter_keys($_FILES, compose('not', startsWith('@'))), $parseFile);

            unset($parseFile);
          }
          break;
      }

      // Request URI
      // CLI requires request parameters
      switch ( $this->client('type') ) {
        case 'cli':
          /*! Note @ 9 May, 2015
           *  Usage: node-cli [OPTIONS] COMMAND
           *  Only one command is supported, simply shift it out.
           */
          $this->uri = @$this->paramCache['cli']['_'][0];
          if ( !$this->uri ) {
            $this->uri = $argv[1];
          }
          break;

        default:
          $uri = array(
              'scheme' => $this->client('secure') ? 'https' : 'http'
            , 'user' => @$_SERVER['REMOTE_USER']
            , 'host' => @$_SERVER['SERVER_NAME']
            , 'port' => @$_SERVER['SERVER_PORT']
            , 'path' => @$_SERVER['REQUEST_URI']
            , 'query' => $_GET
            );

          if ( empty($uri['user']) ) {
            $uri['user'] = @$_SERVER['PHP_AUTH_USER'];
          }

          $this->setUri(array_filter($uri));// = parse_url(http_build_url($this->uri));
          break;
      }

      // Parse special parameter values
      array_walk_recursive($this->paramCache, function(&$value) {
        if ( is_string($value) && strpos($value, $this->metaPrefix) === 0 ) {
          $_value = substr($value, strlen($this->metaPrefix));
          switch ( strtolower($_value) ) {
            case 'true':
              $value = true;
              break;

            case 'false':
              $value = false;
              break;

            default:
              if ( defined($_value) ) {
                $value = constant($_value);
              }
              break;
          }
        }
      });

      // Request timestamp
      $this->timestamp = (float) @$_SERVER['REQUEST_TIME_FLOAT'];
    }

    // Unified params ($_REQUEST mimic)
    switch ( $this->client('type') ) {
      case 'cli':
        // $this->paramCache['request'] = $this->paramCache['cli'];
        break;

      default:
        $this->paramCache['request'] = array_merge(
          (array) @$this->paramCache['cookies'],
          (array) @$this->paramCache['get'],
          (array) @$this->paramCache['post']);
        break;
    }

    // Failover in case of request time not exists.
    if ( !$this->timestamp ) {
      $this->timestamp = microtime(1);
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   *
   * Parameters prefixed with this will be parsed as special.
   */
  protected $metaPrefix = '@';

  //-------------------------------------
  //  Resolver
  //-------------------------------------

  protected $resolver;

  /**
   * (readonly) Returns the current resolver if available.
   */
  public function resolver() {
    return $this->resolver;
  }

  //-------------------------------------
  //  uri
  //-------------------------------------
  protected $uri;

  public function uri($property = null) {
    if ( $property === null ) {
      return $this->uri;
    }
    else {
      return @$this->uri[$property];
    }
  }

  public function setUri($uri) {
    if ( is_string($uri) ) {
      $uri = parse_url($uri);

      $uri = parse_url(http_build_url($uri));
    }

    // note; fallback to system default hostname
    if ( empty($uri['host']) ) {
      $uri['host'] = System::getHostname();
    }

    $this->uri = $uri;
  }

  //-------------------------------------
  //  method
  //-------------------------------------

  /**
   * @private
   */
  protected $method;

  /**
   * (Readonly) Request method.
   */
  public function method() {
    return $this->method;
  }

  //-------------------------------------
  //  headers
  //-------------------------------------

  /**
   * @private
   */
  protected $headers;

  /**
   * (readonly) Retrieve request headers.
   *
   * @param {?string} $name The header name, all headers will be returned if omitted.
   */
  public function header($name = null) {
    static $_headers = array();

    if ( $this->client('type') == 'cli' ) {
      return null;
    }

    if ( $name === null ) {
      return $this->headers;
    }
    else {
      return @$this->headers[$name];
    }
  }

  //-------------------------------------
  //  client
  //-------------------------------------

  /**
   * @private
   */
  protected $client;

  /**
   * (readonly) Getter of the client context.
   */
  public function client($property = null) {
    if ( $property == null ) {
      return $this->client;
    }
    else {
      return @$this->client[$property];
    }
  }

  //-------------------------------------
  //  fingerprint
  //-------------------------------------

  /**
   * (read-only) Create fingerprint from the info available in HTTP request.
   *
   * note: The algorithm is supposed to change from time to time.
   *
   * @return {string} Fingerprint hash from current request, or null when no such info is available.
   */
  public function fingerprint() {
    $res = array_select($this->client, array('address', 'userAgent'));
    $res = array_filter($res);
    $res = implode(':', $res);
    if ( $res ) {
      return sha1($res);
    }
  }

  //-------------------------------------
  //  timestamp
  //-------------------------------------

  /**
   * @private
   *
   * Request time in seconds.
   */
  protected $timestamp;

  /**
   * (readonly) The request timestamp.
   *
   * @param {?string} $format Formats the timestamp with date().
   *
   * @return {float|string} Formatted date string if $format is specified,
   *                        otherwise raw second in cache is returned.
   */
  public function timestamp($format = null) {
    $timestamp = $this->timestamp;
    if ( $format !== null ) {
      $timestamp = date($format, $timestamp);
    }

    return $timestamp;
  }

  //-------------------------------------
  //  locale
  //-------------------------------------

  /**
   * @private
   */
  protected $locale = 'en_US';

  /**
   * (readonly) Accessor to locale value.
   *
   * @return {string} Returns the current locale.
   */
  public function locale($locale = null) {
    $locale = $this->locale;

    if ( $locale !== null ) {
      $this->locale = $locale;
    }

    return $locale;
  }

  //-------------------------------------
  //  parameters
  //-------------------------------------

  /**
   * @private
   *
   * Cache for param related functions.
   */
  protected $paramCache = array();

  /**
   * @private
   */
  private function _param($type = null) {
    switch ( strtolower($type) ) {
      case 'request':
      default:
        switch ( $this->client('type') ) {
          case 'cli':
            return @$this->paramCache['cli']->argv;

          default:
            return @$this->paramCache['request'];
        }

      case 'get':
      case 'post':
      case 'cookies':
      case 'files':
      case 'cli':
        return @$this->paramCache[$type];
    }
  }

  /**
   * @private
   *
   * Parsed meta param cache
   */
  protected $meta;

  /**
   * Parameters starts with metaPrefix will not be returned from param() and
   * must be retrieved from this function.
   */
  public function meta($name = null) {
    $value = &$this->meta;
    if ( !$value ) {
      $value = $this->_param();
      $value = array_filter_keys($value, startsWith($this->metaPrefix));
      $value = array_combine(
        array_map(replaces('/^'.preg_quote($this->metaPrefix).'/', ''), array_keys($value)),
        array_values($value));
    }

    if ( $name === null ) {
      return $value;
    }
    else {
      return @$value[$name];
    }
  }

  /**
   * Because POST can be JSON, or other formats in the future, we cannot simply
   * use $_REQUEST.
   *
   * Another difference with $_REQUEST is this also counts $_COOKIE.
   */
  public function param($name = null, $type = null) {
    /*! Note @ 23 Apr, 2015
     *  POST validation should be simple, just match it with some hash key stored in sessions.
     */
    // TODO: Do form validation, take reference from form key of Magento.

    $result = $this->_param($type);

    if ( is_array($result) ) {
      // remove meta keys and sensitive values
      $result = array_filter_keys($result,
        funcAnd(
          notIn([ ini_get('session.name') ]),
          compose('not', startsWith($this->metaPrefix))
        )
      );
    }

    if ( $name === null ) {
      return $result;
    }
    else {
      $fx = prop($name);
      return $fx($result);
    }
  }

  /**
   * Get parameters
   */
  public function get($name = null) {
    return $this->param($name, 'get');
  }

  /**
   * Post parameters
   */
  public function post($name = null) {
    return $this->param($name, 'post');
  }

  /**
   * Cookies
   */
  public function cookie($name = null) {
    return $this->param($name, 'cookies');
  }

  /**
   * Returns a opened file streams.
   *
   * @param {?string} $postName Returns the file(s) under specified post name,
   *                            the whole array will be returned when omitted in
   *                            POST requests.
   *                            This parameter is ignored in PUT requests and
   *                            returns a file stream.
   */
  public function file($name = null) {
    return $this->param($name, 'files');
  }

  /**
   * CLI parameters, the array accessible Optimist object.
   *
   * @param {?string} $name Name of target comment argument.
   * @return If $name is omitted, the Optimist object will the returned.
   */
  public function cli($name = null) {
    return $this->param($name, 'cli');
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Fire this request object as a request.
   *
   * @param {?Resolver} $resolver If provided, this request will be resolved by
   *                              it instead of creating a real HTTP request.
   *                              A real CURL request will be made upon omission.
   * @param {?Response} $response Response object for the request, a new response
   *                              object will be created if omitted.
   *
   * @return {Response} The response object after resolution.
   */
  public function send(Resolver $resolver = null, Response $response = null) {
    if ( $this->resolver ) {
      trigger_error('Active request cannot be fired again.', E_USER_WARNING);
      return;
    }

    if ( $resolver ) {
      $this->resolver = $resolver;

      $resolver->run($this, $response);

      return $resolver->response();
    }

    // TODO: Handle file uploads?

    // Creates a CURL request upon current request context.
    Net::httpRequest(array(
        'url' => http_build_url($this->uri()),
        'data' => array_replace_recursive((array) $this->param(), (array) $this->file()),
        'type' => $this->method(),
        'headers' => $this->header(),
            'success' => function($responseText, $options) use(&$response) {
              if ( $response === null ) {
                $response = new Response();
              }

              foreach ( array_filter(preg_split('/\r?\n/', @$options['response']['headers'])) as $value ) {
                $response->header($value);
              }

              $response->send($responseText, (int) @$options['status']);
            },
            'failure' => function($errNum, $errMsg, $options) {
              throw new FrameworkException($errMsg, $errNum);
            }
      ));

    return $response;
  }

}
