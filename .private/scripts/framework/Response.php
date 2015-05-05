<?php
/*! Response.php | Response object for every request. */

namespace framework;

use core\Utility as util;

use framework\Resolver;
use framework\Resource;
use framework\System;

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

  //----------------------------------------------------------------------------
  //
  //  Constructor
  //
  //----------------------------------------------------------------------------

  public function __construct($useOutputBuffer = false) {
    $this->useOutputBuffer = $useOutputBuffer;
    if ( $useOutputBuffer ) {
      ob_start(null, 1024);
    }
  }

  public function __destruct() {
    if ( !$this->useOutputBuffer ) {
      return;
    }

    if ( $this->header('Location') ||
         $this->status == 204 ||
         ($this->status >= 300 && $this->status < 400) ) {
      $this->writeHeaders();
      // No message body for redirections and mismatched conditional requests.
    }
    else {
      // Push the headers to output buffer
      $this->writeHeaders();

      // Send the body to output buffer
      $this->writeBody();

      // Flush the PHP output buffer
      ob_end_flush();

      // Flush the system output buffer
      flush();
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * Use output buffer as output target.
   */
  protected $useOutputBuffer = false;

  /**
   * @private
   *
   * Headers to be sent outwards after the resolve chain.
   */
  protected $headers = array();

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
   */
  public /* =int */ function status($code = null) {
    if ( $code === null ) {
      return $this->status;
    }
    else {
      $code = (int) $code;
      if ( !$code || $code < 100 || $code > 599 ) {
        $code = 500;
      }

      $this->status = $code;
    }

    return $this;
  }

  /**
   * @private
   *
   * Resource class for translation
   */
  protected $resource;

  public function resource(Resource $resource) {
    $this->resource = $resource;
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
   * @param {string|array} $target The redirection target, can be either relative or absolute.
   *                               If an array of URIs are given, the first truthy value will be used, this is handy
   *                               for a list of fallback URI.
   * @param {int}          $options['status'] The status code used for redirection, defaults to 302 Found.
   * @param {boolean}      $options['secure'] Use secure connection when available.
   */
  public /* void */ function redirect($target, $options = array()) {
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
  public /* =array */ function header($key = null, $value = null) {
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
   */
  public function send($value, $status = 200) {
    $this->status($status);

    if ( $this->status() >= 300 and $this->status() < 400 ) {
      // No body output should be appended
      $value = '';
    }

    if ( $this->useOutputBuffer ) {
      ob_clean();
    }

    $this->body = $value;
  }

  /**
   * Retrieves the message content.
   *
   * @param {?boolean} $clearsBody If true, message content will be cleared upon retrieval.
   */
  public function getBody($clearsBody = false) {
    $message = $this->body;
    if ( $clearsBody ) {
      $this->clearBody();
    }

    return $message;
  }

  /**
   * Clean the output buffer, remove everything not sent.
   */
  public function clearBody() {
    $this->body = '';
  }

  /**
   * Sends headers to browser.
   */
  public function writeHeaders() {
    if ( PHP_SAPI == 'cli' ) {
      return;
    }

    if ( function_exists('headers_sent') && headers_sent() ) {
      return;
    }

    // Status code and headers
    if ( function_exists('http_response_code') ) {
      http_response_code($this->status());
    }
    else {
      // FastCGI and CGI expects "Status:" instead of "HTTP/1.0" for status code.
      $statusPrefix = false !== strpos(@$_SERVER['GATEWAY_INTERFACE'], 'CGI') ? 'Status:' : 'HTTP/1.0';
      switch ( $this->status() ) {
        case 200:
          header("$statusPrefix 200 OK", true, 200);
          return;
        case 301:
          header("$statusPrefix 301 Moved Permanently", true, 301);
          return;
        case 304: // CAUTION: Do not create an errordoc for this code.
          header("$statusPrefix 304 Not Modified", true, 304);
          die;
        case 400: // TODO: Use this when the URI is unrecognizable.
          header("$statusPrefix 400 Bad Request", true, 400);
          break;
        case 401:
          header("$statusPrefix 401 Unauthorized", true, 401);
          break;
        case 403:
          header("$statusPrefix 403 Forbidden", true, 403);
          break;
        case 404: // TODO: Use this when file resolver can resolve the URI, but no file actually exists.
          header("$statusPrefix 404 Not Found", true, 404);
          break;
        case 405:
          header("$statusPrefix 405 Method Not Allowed", true, 405);
          break;
        case 412:
          header("$statusPrefix 412 Precondition Failed", true, 412);
          break;
        case 500:
          header("$statusPrefix 500 Internal Server Error", true, 500);
          break;
        case 501:
          header("$statusPrefix 501 Not Implemented", true, 501);
          break;
        case 503:
          header("$statusPrefix 503 Service Unavailable", true, 503);
          break;
      }

      unset($statusPrefix);
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
  public function writeBody() {
    if ( $this->body instanceof \SplFileObject ) {
      $this->body->fpassthru();
    }
    else if ( is_resource($this->body) ) {
      fpassthru($this->body);
    }
    else if ( is_string($this->body) && is_file($this->body) ) {
      $path = realpath(System::getRoot() . $this->body);

      if ( $path && is_readable($path) ) {
        readfile($path);
      }
    }

    echo $this->contentEncode($this->body);
  }

  public function contentEncode($message) {
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

  public function __($key) {
    if ( is_callable($this->resource) ) {
      return $this->resource($key);
    }
  }

}
