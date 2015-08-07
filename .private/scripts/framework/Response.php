<?php
/*! Response.php | Response object for every request. */

namespace framework;

use core\Utility as util;

use framework\Configuration as conf;

use framework\exceptions\FrameworkException;
use framework\exceptions\ResolverException;

/**
 * Response object that stores outputs from all resolvers.
 *
 * This class cannot do a global output buffer upon instantiation, because it is
 * not designed in a static way, or a singleton pattern. Therefore output
 * buffering should be handled by resolvers themselves.
 *
 * Note @ 22 Apr, 2015
 * Only FileResolver allows custom PHP code and thus accessing the output buffer,
 * we only need to update FileResolver for this change.
 */
class Response {

  /**
   * @constructor
   */
  public function __construct(array $options = array()) {
    if ( !empty($options['outputBuffer']) ) {
      $this->useOutputBuffer = true;

      $this->outputBufferOptions = (array) $options['outputBuffer'];

      if ( !empty($options['outputBuffer']['size']) ) {
        ob_start(null, (int) $options['outputBuffer']['size']);
      }
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @protected
   */
  protected $useOutputBuffer = false;

  /**
   * Indicates whether the response object should make use of output buffer when
   * capturing outputs.
   *
   * If false, all script outputs will be sent directly to the ISAPI output
   * instead, header modification is not allowed after the first output.
   */
  public function useOutputBuffer() {
    return $this->useOutputBuffer;
  }

  /**
   * @protected
   */
  protected $outputBufferOptions = array();

  /**
   * Output buffer options to use when it is enabled.
   */
  public function outputBufferOptions() {
    return $this->outputBufferOptions;
  }

  /**
   * @private
   *
   * Headers to be sent outwards after the resolve chain.
   */
  protected $headers = array(
      'X-Powered-By' => ['Victopia/php-node']
    );

  /**
   * @private
   *
   * The output contents, this could be a stream, a path to a file, an array or
   * plain string.
   */
  protected $body = '';

  /**
   * @private
   *
   * HTTP response status code, defaults to 200 OK.
   */
  protected $status = null;

  /**
   * The HTTP response status code.
   *
   * Whenever a code is out of range or invalid, it will be set to 500.
   *
   * @param {?int} $code Desired HTTP status code.
   * @return Returns the current status code.
   */
  public function status($code = null) {
    $status = $this->status;

    if ( $code !== null ) {
      $code = (int) $code;
      if ( !$code || $code < 100 || $code > 599 ) {
        $code = 500;
      }

      $this->status = $code;
    }

    return $status;
  }

  /**
   * @private
   *
   * Translation class for translation
   */
  protected $translation;

  public function translation(Translation $value) {
    $translation = $this->translation;

    if ( $value ) {
      $this->translation = $value;
    }

    return $translation;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Enhanced version of the global redirect().
   *
   * This function normalize the redirection target, make sure secure hostname
   * is configured and else.
   *
   * The main difference is that with the same url we still do redirection.
   *
   * @param {string|array} $target The redirection target, can be either relative
   *                               or absolute. If an array of URIs are given,
   *                               the first truthy value will be used, this is
   *                               handy for a list of fallback URI.
   * @param {int}     $options['status'] The status code used for redirection, defaults to 302 Found.
   * @param {boolean} $options['secure'] Use secure connection when available.
   */
  public function redirect($target, $options = array()) {
    // Default values
    $options+= array(
        'status' => 302
      );

    // Array of uri
    if ( is_array($target) && !util::isAssoc($target) ) {
      $target = array_filter($target);
      $target = array_reduce($target, function($result, $target) {
        if ( $result ) {
          return $result;
        }

        if ( $target ) {
          return $target;
        }
      });
    }

    // Normalize redirection target
    $target = parse_url($target);

    if ( @$options['secure'] && System::getHostname('secure') ) {
      $target['scheme'] = 'https';
      $target['host'] = System::getHostname('secure');
    }

    // Fallback to normal hostname
    if ( empty($target['host']) ) {
      $target['host'] = System::getHostname();
    }

    $target = http_build_url($target);

    $this->status($options['status']);
    $this->header('Location', $target);
  }

  /**
   * Sets cookies information.
   *
   * Takes the same parameters as setcookie(), but this updates current cookies immediately.
   */
  public function cookie($name, $value = null) {
    call_user_func_array('setcookie', func_get_args());

    if ( $value ) {
      $_COOKIE[$name] = $value;
    }
    else {
      unset($_COOKIE[$name]);
    }
  }

  /**
   * HTTP request headers
   *
   * Subsequent updates to the same header will append to it, to remove a header,
   * pass a falsy value as $value.
   *
   * Usage:
   * 1. $response->header('Content-Type: text/html');
   * 2. $response->header('Content-Length', strlen($content));
   *
   * @param {string} $key Either the whole header string, or the header key.
   * @param {?string} $value When value is specified, $key will be used as key.
   */
  public function header($key = null, $value = null) {
    if ( $key === null ) {
      return $this->headers;
    }
    else if ( !$value ) {
      if ( preg_match('/^([\w-_]+)\s*:\s*(.+)$/', trim($key), $matches) ) {
        $key = $matches[1];
        $value = $matches[2];
      }
      else {
        return; // Unsupported headers.
      }
    }

    // Normalize capitalization of header keys
    $key = str_replace(' ', '-', ucwords(str_replace('-', ' ', trim($key))));

    if ( $value ) {
      $this->headers[$key] = array_values(array_unique(array_merge((array) @$this->headers[$key], (array) $value)));
    }
    else if ( $value === null ) {
      return @$this->headers[$key];
    }
    else {
      unset($this->headers[$key]);
    }

    return $this;
  }

  /**
   * Removes all headers to be sent.
   */
  public function clearHeaders() {
    $this->headers = array();
  }

  /**
   * Le mighty express.js send(), only difference is this one also do XML.
   *
   * Function name inspired by express.js
   */
  public function send($value, $status = null) {
    if ( $status !== null ) {
      $this->status($status);
    }

    if ( $this->useOutputBuffer ) {
      @ob_clean();
    }

    $this->body = $value;
  }

  /**
   * Appends string message to current body.
   */
  public function write($message) {
    if ( !is_string($message) || !is_string($this->body) || is_file($this->body) ) {
      throw new FrameworkException('Message body is not appendable, please clear before write.');
    }

    $this->body.= $message;
  }

  /**
   * Retrieves the message content.
   */
  public function body() {
    return $this->body;
  }

  /**
   * Clean the output buffer, remove everything not sent.
   */
  public function clearBody() {
    $this->body = '';

    if ( $this->useOutputBuffer ) {
      @ob_clean();
    }
  }

  /**
   * Sends headers to browser.
   */
  public function flushHeaders() {
    if ( PHP_SAPI == 'cli' ) {
      return;
    }

    if ( function_exists('headers_sent') && headers_sent() ) {
      return;
    }

    header_remove();

    // Status code and headers
    if ( function_exists('http_response_code') ) {
      http_response_code($this->status());
    }
    else {
      $statusMessage = $this->getStatusMessage($this->status());
      if ( $statusMessage ) {
        // FastCGI and CGI expects "Status:" instead of "HTTP/1.0" for status code.
        $statusMessage =
          [ false !== strpos(@$_SERVER['GATEWAY_INTERFACE'], 'CGI') ? 'Status:' : 'HTTP/1.1'
          , $this->status()
          , $statusMessage
          ];

        header(implode(' ', $statusMessage), true, $this->status());

        if ( $status > 299 ) {
          return;
        }
      }

      unset($statusMessage);
    }

    foreach ( $this->headers as $key => $values ) {
      // Replace the first one
      header("$key: " . array_shift($values), true);

      // Then appends the following
      foreach ( $values as $value ) {
        header("$key: $value", false);
      }
    }
  }

  /**
   * Sends the body to output buffer.
   */
  public function flushBody() {
    $body = $this->body();

    if ( $body instanceof \SplFileObject ) {
      $body->fpassthru();
    }
    else if ( is_resource($body) ) {
      fpassthru($body);
    }
    else if ( is_string($body) && is_file($body) ) {
      $path = realpath(System::getPathname() . '/' . $body);

      if ( $path && is_readable($path) ) {
        readfile($path);
      }
    }
    else {
      echo $this->contentEncode($body);
    }
  }

  public function __destruct() {
    if ( !$this->useOutputBuffer ) {
      if ( !function_exists('headers_sent') || headers_sent() ) {
        return;
      }

      // We can still write to output before headers are sent.
    }

    if ( $this->header('Location') ) {
      $this->flushHeaders();
      // No message body for redirections and mismatched conditional requests.
    }
    else {
      // Push the headers to output buffer
      $this->flushHeaders();

      // Send the body to output buffer
      $this->flushBody();

      if ( $this->useOutputBuffer ) {
        // Flush the PHP output buffer
        @ob_end_flush();

        // Flush the system output buffer
        flush();
      }

      // $this->clearBody();
    }
  }

  /**
   * Translation shorthand
   */
  public function __($key) {
    $translation = $this->translation;
    if ( is_callable($translation) ) {
      return $translation($key);
    }
  }

  /**
   * Cast body as string.
   */
  public function __toString() {
    return $this->body();
  }

  /**
   * Encodes the content base on current headers.
   */
  protected function contentEncode($message) {
    $contentTypes = (array) @$this->headers['Content-Type'];

    if ( preg_grep('/json/i', $contentTypes) ) {
      $message = ResponseEncoder::jsonp($message);
    }
    else if ( preg_grep('/xml/i', $contentTypes) ) {
      $message = ResponseEncoder::xml($message);
    }
    else if ( preg_grep('/php.serialized/i', $contentTypes) ) {
      $message = ResponseEncoder::serialize($message);
    }
    else if ( preg_grep('/php.var_dump/i', $contentTypes) ) {
      $message = ResponseEncoder::dump($message);
    }

    return $message;
  }

  /**
   * Retrieves HTTP status message depends on the given code, also removes message
   * body on statuses that do not allows it.
   */
  protected function getStatusMessage($statusCode) {
    switch ( $statusCode ) {
      // 1xx
      case 100: return 'Continue';
      case 101: return 'Switching Protocols';
      case 102: return 'Processing'; // WebDAV

      // 2xx
      case 200: return 'OK';
      case 201: return 'Created';
      case 202: return 'Accepted';
      case 203: return 'Non-Authoritative Information';
      case 204: $this->clearBody(); return 'No Content';
      case 205: $this->clearBody(); return 'Reset Content';
      case 206: return 'Partial Content';
      case 207: return 'Multi-Status'; // WebDAV; RFC 4918
      case 208: return 'Already Reported'; // WebDAV; RFC 5842
      case 226: return 'IM Used'; // RFC 3229

      // 3xx
      case 300: return 'Multiple Choices';
      case 301: $this->clearBody(); return 'Moved Permanently';
      case 302: $this->clearBody(); return 'Found';
      case 303: $this->clearBody(); return 'See Other';
      case 304: $this->clearBody(); return 'Not Modified';
      case 305: $this->clearBody(); return 'Use Proxy';
      case 306: $this->clearBody(); return 'Switch Proxy';
      case 307: $this->clearBody(); return 'Temporary Redirect'; // HTTP method persistant version of 302
      case 308: $this->clearBody(); return 'Permenant Redirect'; // HTTP method persistant version of 301

      // 4xx
      case 400: return 'Bad Request';
      case 401: return 'Unauthorized';
      case 403: return 'Forbidden';
      case 404: return 'Not Found';
      case 405: return 'Method Not Allowed';
      case 406: return 'Not Acceptable';
      case 407: return 'Proxy Authentication Required';
      case 408: return 'Request Timeout';
      case 409: return 'Conflict';
      case 410: return 'Gone'; // TODO: Make an error doc for this.
      case 411: return 'Length Required';
      case 412: return 'Precondition Failed';
      case 413: return 'Request Entity Too Large'; // Note: Try to make use of this.
      case 414: return 'Request-URI Too Long'; // Note: Try to make use of this.
      case 415: return 'Unsupported Media Type';
      case 416: return 'Requested Range Not Satisfiable';
      case 417: return 'Expectation Failed';
      case 418: return 'I\'m a teapot'; // Unused
      case 419: return 'Authentication Timeout'; // Out of spec
      // case 420: return 'Enhance Your Calm'; // Twitter API
      case 421: return 'Misdirected Request';
      case 426: return 'Upgrade Required';
      case 428: return 'Precondition Required';
      case 429: return 'Too Many Requests'; // Note: For rate limit use
      case 431: return 'Request Header Fields Too Large';
      case 444: return 'No Response'; // Nginx

      // 5xx
      case 500: return 'Internal Server Error';
      case 501: return 'Not Implemented';
      case 502: return 'Bad Gateway';
      case 503: return 'Service Unavailable';
      case 504: return 'Gateway Timeout';
      case 505: return 'HTTP Version Not Supported';
      // Transparent content negotiation for the request results in a circular reference. (RFC 2295)
      case 506: return 'Variant Also Negotiates';
      // The server is unable to store the representation needed to complete the request. (WebDAV; RFC 4918)
      case 507: return 'Insufficient Storage';
      // The server detected an infinite loop while processing the request (sent in lieu of 208 Already Reported). (WebDAV; RFC 5842)
      case 508: return 'Loop Detected';
      // This status code is not specified in any RFCs. Its use is unknown. (Apache bw/limited extension)
      case 509: return 'Bandwidth Limit Exceeded';
      // Further extensions to the request are required for the server to fulfil it. (RFC 2774)
      case 510: return 'Not Extended';
      // The client needs to authenticate to gain network access. Intended for use by intercepting proxies used to control access to the network (e.g., "captive portals" used to require agreement to Terms of Service before granting full Internet access via a Wi-Fi hotspot). (RFC 6585)
      case 511: return 'Network Authentication Required';
      // This status code is not specified in any RFCs, but is used by Microsoft HTTP proxies to signal a network read timeout behind the proxy to a client in front of the proxy. (Unknown)
      case 598: return 'Network read timeout error';
      case 599: return 'Network connect timeout error'; // Unknown
    }
  }

}
