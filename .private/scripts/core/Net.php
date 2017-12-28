<?php /* Net.php | The Net class provides network related functionalities. */

namespace core;

use framework\System;
use framework\Response;

/**
 * Net class
 */
class Net {

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  //------------------------------
  //  maximumReqeusts
  //------------------------------

  private static $maximumRequests = 5;

  public static /* =int */
  function maximumRequests($value = null) {
    if ( is_null($value) ) {
      return self::$maximumRequests;
    }
    elseif ( self::$maximumRequests !== $value ) {
      self::$maximumRequests = $value;
    }
  }

  //------------------------------
  //  timeout
  //------------------------------

  private static $timeout = 10;

  public static /* =int */
  function timeout($value = null) {
    if ( is_null($value) ) {
      return self::$timeout;
    }
    elseif ( self::$timeout !== $value ) {
      self::$value = $value;
    }
  }

  //------------------------------
  //  progressInterval
  //------------------------------
  /**
   * Seconds to wait before sending the next progress event, defaults to 0.3 second.
   */
  public static function progressInterval($value = null) {
    static $progressInterval = .3;

    if ( $value === null ) {
      return $progressInterval;
    }
    else {
      $progressInterval = $value;
    }
  }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  /*
    { 'url' => [string]
    , 'type' => 'GET' | 'POST' | 'HEAD'
    , 'data' => [object] | [string]
    , 'dataType' => 'xml' | 'json' | 'text'
    , 'headers' => [array]
    , 'callbacks' => array(
        'progress' => [Function]
      , 'success' => [Function]
      , 'failure' => [Function]
      , 'complete' => [Function]
      )
    , '__curlOpts' => array()
    }
  */
  /**
   * Send HTTP request to a URL.
   *
   * The format is purposedly copied as much as possible from jQuery.ajax() function.
   *
   * To initiate multiple requests, pass it as an array and wrapping parameters of
   * each single request as an array.
   *
   * @param {string} $options['url'] Target request url
   * @param {?string} $options['type'] Request method, defaults to GET.
   * @param {?array|string} $options['data'] Either raw string or array to be encoded,
   *                                         to be sent as query string on GET, HEAD, DELETE and OPTION
   *                                         or message body on POST or PUT.
   * @param {?array} $options['headers'] Request headers to be sent.
   * @param {?callable} $options['progress'] Callback function for progress ticks.
   *                                         function($progress, $current, $maximum);
   * @param {?callable} $options['success'] Callback function on successful request.
   *                                        function($responseText, $curlOptions);
   * @param {?callable} $options['failure'] Callback function on request failure, with curl errors as parameters.
   *                                        function($errorNumber, $errorMessage, $curlOptions);
   * @param {?array} $options['__curlOpts'] Curl options to be passed directly to curl_setopt_array.
   *
   * @return void
   */
  public static function httpRequest($options) {
    $options = Utility::wrapAssoc((array) $options);

    $options = array_map(function(&$option) {
      if ( is_string($option) ) {
        $option = array( 'url' => $option );
      }
      else if ( !@$option['url'] ) {
        throw new exceptions\CoreException('No URL set!');
      }

      // Auto prepend http, default protocol.
      if ( preg_match('/^(\/\/)/', $option['url']) ) {
        $option['url'] = "http:" . $option['url'];
      }

      $curlOption = array(
          CURLOPT_URL => $option['url']
        , CURLOPT_RETURNTRANSFER => true
        , CURLOPT_SSL_VERIFYHOST => false
        , CURLOPT_SSL_VERIFYPEER => false
        , CURLOPT_CAINFO => null
        , CURLOPT_CAPATH => null
        , CURLOPT_FOLLOWLOCATION => true
        );

      if ( isset($options['userAgent']) ) {
        $curlOption[CURLOPT_USERAGENT] = $options['userAgent'];
      }

      // Request method: 'GET', 'POST', 'PUT', 'HEAD', 'DELETE'
      if ( !isset($option['type']) && is_array(@$option['data']) || preg_match('/^post$/i', @$option['type']) ) {
        $curlOption[CURLOPT_POST] = true;
        $curlOption[CURLOPT_CUSTOMREQUEST] = 'POST';
      }
      elseif ( preg_match('/^put$/i', @$option['type']) ) {
        if ( !@$option['file'] || !is_file($option['file']) ) {
          throw new exceptions\CoreException('Please specify the \'file\' option when using PUT method.');
        }

        $curlOption[CURLOPT_PUT] = true;
        $curlOption[CURLOPT_CUSTOMREQUEST] = 'PUT';

        $curlOption[CURLOPT_UPLOAD] = true;
        $curlOption[CURLOPT_INFILE] = fopen($option['file'], 'r');
        $curlOption[CURLOPT_INFILESIZE] = filesize($option['file']);
      }
      elseif (preg_match('/^head$/i', @$option['type'])) {
        $curlOption[CURLOPT_NOBODY] = true;
        $curlOption[CURLOPT_CUSTOMREQUEST] = 'HEAD';
      }
      elseif (preg_match('/^delete$/i', @$option['type'])) {
        $curlOption[CURLOPT_CUSTOMREQUEST] = 'DELETE';
      }
      else {
        $curlOption[CURLOPT_CUSTOMREQUEST] = 'GET';
      }

      // Query data, applicable for all request methods.
      if ( @$option['data'] ) {
        $data = $option['data'];

        // The data contains traditional file POST value: "@/foo/bar"
        $hasPostFile = is_array($data) &&
            array_reduce($data, function($ret, $val) {
              return $ret || is_a($val, 'CurlFile') ||
                is_string($val) && strpos($val, '@') === 0 &&
                file_exists(Utility::unwrapAssoc(explode(';', substr($val, 1))));
            }, false);

        // Build query regardless if file exists on PHP < 5.2.0, otherwise
        // only build when there is NOT files to be POSTed.

        // Skip the whole build if $data is not array or object.
        if ((version_compare(PHP_VERSION, '5.2.0', '<') || !$hasPostFile) &&
            (is_array($data) || is_object($data))) {
            $data = http_build_query($data);
        }

        if ( version_compare(PHP_VERSION, '5.5.0', '>=') && $hasPostFile ) {
          array_walk_recursive($data, function(&$value, $key) {
            if ( is_string($value) && strpos($value, '@') === 0 ) {
              @list($path, $type) = explode(';', substr($value, 1));

              if ( !$type ) {
                $type = Utility::getInfo($path, FILEINFO_MIME_TYPE);
              }

              $value = curl_file_create($path, $type, $key);
            }
          });
        }

        if ( @$curlOption[CURLOPT_POST] === true ) {
          $curlOption[CURLOPT_POSTFIELDS] = $data;
        }
        else {
          $url = &$curlOption[CURLOPT_URL];

          $url.= ( strpos($url, '?') === false ? '?' : '&' ) . $data;
        }
      }

      // HTTP Headers
      if ( isset($option['headers']) ) {
        $curlOption[CURLOPT_HTTPHEADER] = &$option['headers'];
      }

      // Data type converting
      if ( isset($option['success']) ) {
        $option['success'] = function($response, $curlOptions) use($option) {
          $status = (int) @$curlOptions['response']['status'];
          if ( $status && ($status < 200 || $status > 399) ) {
            if ( isset($option['failure']) ) {
              $curlOptions['response']['data'] = $response;

              Utility::forceInvoke($option['failure'],
                [ $status
                , Response::getStatusMessage($status)
                , $curlOptions
                ]);
            }
          }
          else {
            switch ( @$option['dataType'] ) {
              case 'json':
                $result = @ContentDecoder::json($response);

                if ( $result === false && $response ) {
                  Utility::forceInvoke(@$option['failure'],
                    [ 3
                    , 'Malformed JSON string returned.'
                    , $curlOptions
                    ]);
                }
                else {
                  Utility::forceInvoke($option['success'], [ $result, $curlOptions ]);
                }
                break;

              case 'xml':
                try {
                  $result = XMLConverter::fromXML($response);
                } catch (\Exception $e) {
                  $result = NULL;
                }

                if ( $result === NULL && $response ) {
                  Utility::forceInvoke(@$option['failure'],
                    [ 2
                    , 'Malformed XML string returned.'
                    , $curlOptions
                    ]);
                }
                else {
                  Utility::forceInvoke($option['success'], [ $result, $curlOptions ]);
                }
                break;

              default:
                Utility::forceInvoke($option['success'], [ $response, $curlOptions ]);
                break;
            }
          }
        };
      }

      $curlOption['callbacks'] = array_filter(array(
          'progress' => @$option['progress']
        , 'success' => @$option['success']
        , 'failure' => @$option['failure']
        , 'complete' => @$option['complete']
        ));

      $curlOption = (array) @$option['__curlOpts'] + $curlOption;

      if ( System::environment() == 'debug' ) {
        Log::debug('Net ' . $curlOption[CURLOPT_CUSTOMREQUEST] . ' to ' . $curlOption[CURLOPT_URL], $curlOption);
      }

      return $curlOption;
    }, $options);

    return self::curlRequest($options);
  }

  /**
   * Perform cURL requests and throw appropriate exceptions.
   *
   * An array of parameters used in a curl_setopt_array,
   * multiple calls can be passed in.
   *
   * This function make use of curl_multi no matter it is
   * single request or not.
   *
   * Callbacks are used to handle results inside the array.
   *
   * $option['callbacks'] = array(
   *   'progress' => [Function]
   * , 'success' => [Function]
   * , 'failure' => [Function]
   * , 'always'  => [Function]
   * );
   *
   * @return void
   */
  public static function curlRequest($options) {
    $options = Utility::wrapAssoc(array_values((array) $options));

    $multiHandle = curl_multi_init();

    // Initialize cUrl options
    array_walk($options, function(&$option) {
      // 1. Request headers
      $option['response'] = array(
        'headers' => ''
      );

      $option[CURLOPT_HEADERFUNCTION] = function($curl, $data) use(&$option) {
        $option['response']['headers'] .= $data;

        return strlen($data);
      };

      // 2. Progress function
      $progressCallback = &$option['callbacks']['progress'];

      if ( $progressCallback ) {
        $option[CURLOPT_NOPROGRESS] = false;

        $option[CURLOPT_PROGRESSFUNCTION] = function() use(&$progressCallback) {
          if ( func_num_args() == 4 ) {
            list($dSize, $dLen, $uSize, $uLen) = func_get_args();
          }
          else {
            list($req, $dSize, $dLen, $uSize, $uLen) = func_get_args();
          }

          if ( $dSize || $dLen ) {
            static $_dLen = 0;

            if ( $_dLen != $dLen ) {
              $_dLen = $dLen;

              /*! Note by Vicary @ 2.Oct.2012
               *  Total download size is often 0 if server doesn't
               *  response with a Content-Length header.
               *
               *  Total size guessing logic:
               *  1. if $dLen < 1M, assume 1M.
               *  2. if $dLen < 10M, assume 10M.
               *  3. if $dLen < 100M, assume 100M.
               *  4. if $dLen < 1G, assume 1G.
               */
              if ( !$dSize ) {
                // Do not assume when size under 1K
                if ($dLen < 5000) {
                  return;
                }
                elseif ($dLen < 10000000) {
                  $dSize = 20000000;
                }
                elseif ($dLen < 100000000) {
                  $dSize = 200000000;
                }
                elseif ($dLen < 1000000000) {
                  $dSize = 2000000000;
                }
                else {
                  $dSize = 20000000000;
                }
                // $dSize = $dLen / .05;
              }

              // Download progress, from 0 to 1.
              $progressArgs = array($dLen / $dSize, $dLen, $dSize);
            }
          }
          else
          if ( $uSize ) {
            static $_uLen = 0;

            if ( $_uLen != $uLen ) {
              $_uLen = $uLen;

              $uSize *= -1;
              $uLen += $uSize;

              // Upload progress, from -1 to 0.
              $progressArgs = array( $uLen / $uSize, $uLen, $uSize);
            }
          }

          // Fire the event for each µSeconds.
          static $_tOffset = 0;

          $tOffset = microtime(1);

          if ( isset($progressArgs) && $tOffset - $_tOffset > self::progressInterval() ) {
            $_tOffset = $tOffset;

            Utility::forceInvoke($progressCallback, $progressArgs);
          }
        };
      }
      unset($progressCallback);

      // 3. Apply cUrl options, numeric keys only.
      $option['handle'] = curl_init();

      curl_setopt_array($option['handle'], array_filter_keys($option, 'is_int'));
    });

    $requestIndex = 0;

    while ( $requestIndex < self::$maximumRequests && isset($options[$requestIndex]) ) {
      curl_multi_add_handle( $multiHandle
                           , $options[$requestIndex++]['handle']
                           );
    }

    // Start the multi request
    do {
      $status = curl_multi_exec($multiHandle, $active);

      /* Added by Vicary @ 6.Nov.2012
         Blocks until there is a message arrives.
      */
      curl_multi_select($multiHandle);

      do {
        $info = curl_multi_info_read($multiHandle, $queueLength);

        if ($info === FALSE) {
          continue;
        }

        $optionIndex = array_search($info['handle'], array_map(prop('handle'), $options));

        if ($optionIndex === FALSE) {
          continue;
        }

        $curlOption = &$options[$optionIndex];

        $callbacks = &$curlOption['callbacks'];

        // Success handler
        if ( $info['result'] === CURLE_OK ) {
          // Fire a 100% downloaded event.
          if ( @$callbacks['progress'] ) {
            Utility::forceInvoke($callbacks['progress'], array(1, 1, 1));

            usleep(self::progressInterval() * 1000000);
          }

          // Append HTTP status code
          $curlOption['response']['status'] =
            curl_getinfo($info['handle'], CURLINFO_HTTP_CODE);

          $responseText = curl_multi_getcontent($info['handle']);

          // Check for gzip and deflate.
          if ( preg_match('/Content-Encoding:\s*(\w+)\r?\n/', @$curlOption['response']['headers'], $matches) ) {
            switch ( $matches[1] ) {
              case 'gzip':
                $responseText = gzdecode($responseText);
                break;

              case 'deflate':
                $responseText = gzinflate($responseText);
                break;
            }
          }
          unset($matches);

          Utility::forceInvoke(@$callbacks['success'],
            [ $responseText
            , $curlOption
            ]);
        }

        // Failure handler
        else {
          $errorNumber = curl_errno($info['handle']);
          $errorMessage = curl_error($info['handle']);

          // libcurl errors, try to parse it.
          if ($errorNumber === 0) {
            if (preg_match('/errno: (\d+)/', $errorMessage, $matches) ) {
              $errorNumber = (int) $matches[1];

              $curlErrors = unserialize(FRAMEWORK_NET_CURL_ERRORS);
              if ( isset($curlErrors[$errorNumber]) ) {
                $errorMessage = $curlErrors[$errorNumber];
              }
            }
          }

          Utility::forceInvoke(@$callbacks['failure'], array(
              $errorNumber
            , $errorMessage
            , $curlOption
            ));

          unset($errorNumber, $errorMessage);
        }

        // Always handler
        Utility::forceInvoke(@$callbacks['always'], array($curlOption));

        if ( isset($options[$requestIndex]) ) {
          curl_multi_add_handle( $multiHandle
                               , $options[$requestIndex++]['handle']
                               );

          // Keep the loop alive.
          $active = TRUE;
        }

        curl_multi_remove_handle( $multiHandle
                                , $info['handle']
                                );

        curl_close($info['handle']);

        unset($info, $callbacks, $curlOption, $options[$optionIndex], $optionIndex);
      } while( $queueLength > 0 );

    } while( $status === CURLM_CALL_MULTI_PERFORM || $active );

    curl_multi_close($multiHandle);
  }
}
