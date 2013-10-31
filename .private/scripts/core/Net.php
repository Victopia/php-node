<?php
/* Net.php | The Net class provides network related functionalities. */

namespace core;

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

  private static $maximumRequests = 100;

  public static /* =int */
  function maximumRequests($value = NULL) {
    if (is_null($value)) {
      return self::$maximumRequests;
    }
    elseif (self::$maximumRequests !== $value) {
      self::$maximumRequests = $value;
    }
  }

  //------------------------------
  //  timeout
  //------------------------------

  private static $timeout = 10;

  public static /* =int */
  function timeout($value = NULL) {
    if (is_null($value)) {
      return self::$timeout;
    }
    elseif (self::$timeout !== $value) {
      self::$value = $value;
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
   * To initiate multiple requests, pass it as an array and wrapping parameters of
   * each single request as an array.
   *
   * @return void
   */
  public static function httpRequest($options) {
    $options = \utils::wrapAssoc((array) $options);

    $options = array_map(function(&$option) {
      if ( is_string($option) ) {
        $option = array( 'url' => $option );
      }
      elseif (!@$option['url']) {
        throw new \core\exceptions\CoreException('No URL set!');
      }

      // Auto prepend http, default protocol.
      if ( preg_match('/^(\/\/)/', $option['url']) ) {
        $option['url'] = "http:" . $option['url'];
      }

      $curlOption = array(
          CURLOPT_URL => $option['url']
        , CURLOPT_RETURNTRANSFER => TRUE
        , CURLOPT_SSL_VERIFYHOST => FALSE
        , CURLOPT_SSL_VERIFYPEER => FALSE
        , CURLOPT_CAINFO => NULL
        , CURLOPT_CAPATH => NULL
        , CURLOPT_FOLLOWLOCATION => TRUE
        );

      // Request method: 'GET', 'POST', 'PUT', 'HEAD', 'DELETE'
      if ( !isset($option['type']) && is_array(@$option['data']) || preg_match('/^post$/i', @$option['type']) ) {
        $curlOption[CURLOPT_POST] = TRUE;
        $curlOption[CURLOPT_CUSTOMREQUEST] = 'POST';
      }
      elseif ( preg_match('/^put$/i', @$option['type']) ) {
        if ( !@$option['file'] || !is_file($option['file']) ) {
          throw new exceptions\CoreException('Please specify the \'file\' option when using PUT method.');
        }

        $curlOption[CURLOPT_PUT] = TRUE;
        $curlOption[CURLOPT_CUSTOMREQUEST] = 'PUT';

        $curlOption[CURLOPT_UPLOAD] = TRUE;
        $curlOption[CURLOPT_INFILE] = fopen($option['file'], 'r');
        $curlOption[CURLOPT_INFILESIZE] = filesize($option['file']);
      }
      elseif (preg_match('/^head$/i', @$option['type'])) {
        $curlOption[CURLOPT_NOBODY] = TRUE;
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
              return $ret || is_string($val) && strpos($val, '@') === 0 &&
                file_exists(Utility::unwrapAssoc(explode(';', substr($val, 1))));
            }, FALSE);

        // Build query regardless if file exists on PHP < 5.2.0, otherwise
        // only build when there is NOT files to be POSTed.

        // Skip the whole build if $data is not array or object.
        if ((version_compare(PHP_VERSION, '5.2.0', '<') || !$hasPostFile) &&
            (is_array($data) || is_object($data))) {
            $data = http_build_query($data);
        }

        if ( version_compare(PHP_VERSION, '5.5.0', '>=') && $hasPostFile ) {
          array_walk_recursive($data, function(&$value, $key) {
            if ( strpos($value, '@') === 0 ) {
              list($path, $type) = @explode(';', substr($value, 1));

              if ( !$type ) {
                $type = Utility::getInfo($path, FILEINFO_MIME_TYPE);
              }

              $value = new CurlFile($path, $type, $key);
            }
          });
        }

        if (@$curlOption[CURLOPT_POST] === TRUE) {
          $curlOption[CURLOPT_POSTFIELDS] = $data;
        }
        else {
          $url = &$curlOption[CURLOPT_URL];

          $url.= ( strpos($url, '?') === FALSE ? '?' : '&' ) . $data;
        }
      }

      // HTTP Headers
      if ( isset($option['headers']) ) {
        $curlOption[CURLOPT_HTTPHEADER] = &$option['headers'];
      }

      // Data type converting
      if ( isset($option['success']) ) {
        $originalSuccess = @$option['success'];

        switch ( @$option['dataType'] ) {
          case 'json':
            $option['success'] = function($response, $curlOptions) use($option, $originalSuccess) {
              $result = @json_encode($response, TRUE);

              if ( $result === NULL && $response ) {
                Utility::forceInvoke(@$option['failure'], array(
                    3
                  , 'Malformed JSON string returned.'
                  , $curlOptions
                  ));
              }
              else {
                Utility::forceInvoke(@$originalSuccess, array(
                    $result
                  , $curlOptions
                  ));
              }
            };
            break;

          case 'xml':
            $option['success'] = function($response, $curlOptions) use($option, $originalSuccess) {
              try {
                $result = XMLConverter::fromXML($response);
              } catch (\Exception $e) {
                $result = NULL;
              }

              if ( $result === NULL && $response ) {
                Utility::forceInvoke(@$option['failure'], array(
                    2
                  , 'Malformed XML string returned.'
                  , $curlOptions
                  ));
              }
              else {
                Utility::forceInvoke(@$originalSuccess, array(
                    $result
                  , $curlOptions
                  ));
              }
            };
            break;
        }

        unset($originalSuccess);
      }

      $curlOption['callbacks'] = array_filter(array(
          'success' => @$option['success']
        , 'failure' => @$option['failure']
        , 'complete' => @$option['complete']
        ));

      $curlOption = (array) @$option['__curlOpts'] + $curlOption;

      if (FRAMEWORK_ENVIRONMENT == 'debug') {
        log::write('Net ' . $curlOption[CURLOPT_CUSTOMREQUEST] . ' to ' . $curlOption[CURLOPT_URL], 'Debug', $curlOption);
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
    $options = \utils::wrapAssoc(array_values((array) $options));

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
      if ($progressCallback) {
        $option[CURLOPT_NOPROGRESS] = FALSE;

        $option[CURLOPT_PROGRESSFUNCTION] = function($dSize, $dLen, $uSize, $uLen) use(&$progressCallback) {
          if ($dSize || $dLen) {
            static $_dLen = 0;

            if ($_dLen != $dLen) {
              $_dLen = $dLen;

              /* Note by Vicary @ 2.Oct.2012
                 Total download size is often 0 if server doesn't
                 response with a Content-Length header.

                 Total size guessing logic:
                 1. if $dLen < 1M, assume 1M.
                 2. if $dLen < 10M, assume 10M.
                 3. if $dLen < 100M, assume 100M.
                 4. if $dLen < 1G, assume 1G.
               */
              if (!$dSize) {
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
          if ($uSize) {
            static $_uLen = 0;

            if ($_uLen != $uLen) {
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

          if (isset($progressArgs) && $tOffset - $_tOffset > FRAMEWORK_NET_PROGRESS_INTERVAL) {
            $_tOffset = $tOffset;

            \utils::forceInvoke($progressCallback, $progressArgs);
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
        if ($info['result'] === CURLE_OK) {
          // Fire a 100% downloaded event.
          if (@$callbacks['progress']) {
            Utility::forceInvoke($callbacks['progress'], array(1, 1, 1));

            usleep(FRAMEWORK_NET_PROGRESS_INTERVAL * 1000000);
          }

          // Append HTTP status code
          $curlOption['status'] =
            curl_getinfo($info['handle'], CURLINFO_HTTP_CODE);

          Utility::forceInvoke(@$callbacks['success'], array(
              curl_multi_getcontent($info['handle'])
            , $curlOption
            ));
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

              $errorMessage = "curl error #$matches[1]: " . @$curlErrors[$errorNumber];
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

        if (isset($options[$requestIndex])) {
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

    } while($status === CURLM_CALL_MULTI_PERFORM || $active);

    curl_multi_close($multiHandle);
  }
}
