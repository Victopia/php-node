<?php
/*  ExternalResolver.php \ IRequestResolver
 *
 *  Resolve *.url files, it can be a plain URL string or
 *  the standard format of *.url files in Microsoft Windows.
 */

namespace resolvers;

use core\Utility;

use framework\Cache;

class ExternalResolver implements \framework\interfaces\IRequestResolver {
	//--------------------------------------------------
	//
	//  Methods: IPathResolver
	//
	//--------------------------------------------------

	public
	/* Boolean */ function resolve($path) {
		$url = $this->getURL("$_SERVER[DOCUMENT_ROOT]$path");

		if ( $url === false ) {
			// Use case of 500 Internal Server Error
			return $path;
		}

		// Check cached resource
		if ( $this->cacheExpired($url) ) {
			$this->updateCache($url);
		}

		$cache = Cache::get($url);

		$cHead = &$cache['headers'];

		// 1. If-Modified-Since from client
		if ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
			isset($cHead['last-modified']) ) {
			$mtime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
			$cmtime = strtotime($cHead['last-modified']);

			if ( $mtime <= $cmtime ) {
				header('HTTP/1.1 304 Not Modified', true, 304);

				$this->outputHeaders($cache['headers']);

				return;
			}
		}

		// 2. If-None-Match from client
		if ( isset($_SERVER['HTTP_IF_NONE_MATCH']) && isset($cHead['etag']) &&
			preg_match('/^"?(.+)(:?+gzip)?"?/i', $_SERVER['HTTP_IF_NONE_MATCH'], $matches) &&
			$matches[1] === $cHead['etag'] ) {
			header('HTTP/1.1 304 Not Modified', true, 304);

			$this->outputHeaders($cache['headers']);

			return;
		}

		unset($matches);

		$this->outputHeaders($cache['headers']);

		echo $cache['body'];
	}

	//--------------------------------------------------
	//
	//  Methods
	//
	//--------------------------------------------------

	private function getURL($path) {
		if ( !is_file($path) ) {
			return false;
		}

		$content = file_get_contents($path);

		$res = @parse_ini_string($content);

		if ( $res && count($res) && isset($res['URL']) ) {
			$content = $res['URL'];
		}
		else {
			$pos = strpos($content, "\n");

			if ( $pos ) {
				$content = substr($content, 0, $pos);
			}
		}

		if ( !Utility::isURL($content) ) {
			return false;
		}

		return $content;
	}

	/**
	 * @private
	 */
	private function cacheExpired($url) {
		$cacheInfo = Cache::getInfo($url);

		return $cacheInfo === null ||
			($cacheInfo->getMTime() + FRAMEWORK_EXTERNAL_UPDATE_DELAY > time());
	}

	/**
	 * @private
	 */
	private function updateCache($url) {
		$cacheData = Cache::get($url);

		$cHead = &$cacheData['headers'];

		// Request headers
		$reqHeaders = array();

		if ( isset($cHead['last-modified']) ) {
			$reqHeaders[] = 'If-Modified-Since: ' . $cHead['last-modified'];
		}

		if ( isset($cHead['etag']) ) {
			$reqHeaders[] = 'If-None-Match: ' . $cHead['etag'];
		}

		// HEADER request before processing.
		$ch = curl_init($url);

		curl_setopt_array($ch, Array(
			CURLOPT_HTTPHEADER => $reqHeaders,
			CURLOPT_HEADER => true,
			CURLOPT_NOBODY => true,
			CURLOPT_RETURNTRANSFER => true
		));

		$resHeaders = curl_exec($ch);

		curl_close($ch);

		$resHeaders = $this->parseConditionalHeaders($resHeaders);

		// Last Modified time check against http headers,
		// only when we already have a body cached.
		if ( @$cacheData['body'] ) {
			$cmtime = $rmtime = null;

			if ( isset($cHead['last-modified']) )
				$cmtime = strtotime($cHead['last-modified']);

			if ( isset($resHeaders['last-modified']) )
				$rmtime = strtotime($resHeaders['last-modified']);

			// Output the cache if not modified.
			if ( $cmtime && $rmtime && $cmtime >= $rmtime ) {
				$cacheInfo = Cache::getInfo($url);

				touch($cacheInfo->getRealPath());

				return false;
			}

			unset($cmtime, $rmtime, $interval);
		}

		// Store response headers
		$cacheData['headers'] = $cHead = $resHeaders;

		// HTTP GET target contents
		$ch = curl_init($url);

		curl_setopt_array($ch, array(
			CURLOPT_HTTPHEADER => $reqHeaders,
			CURLOPT_RETURNTRANSFER => true
		));

		$res = curl_exec($ch);

		curl_close($ch); unset($ch);

		$cacheData['body'] = $res;

		Cache::delete($url);

		Cache::set($url, $cacheData);

		return true;
	}

	/**
	 * @private
	 */
	private function parseConditionalHeaders($headers) {
		$patterns = array(
			'/^(Last\-Modified):\s*(.+)/i',
			'/^(ETag):\s*("?[^"]+(:?\+gzip)?"?)/i',
			'/^(Expires):\s*(.+)/i',
			'/^(Cache-Control):\s*(.+)/i',
			'/^(Content-Type):\s*(.+)/i'
		);

		$result = array();
		$headers = explode("\n", $headers);

		foreach ( $headers as $header ) {
			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $header, $matches)) {
					$result[strtolower($matches[1])] = $matches[2];
					break;
				}
			}
		}

		return $result;
	}

	private function updateAndOutputCache($cache, $url) {
		Cache::delete($url);

		Cache::set($url, $cache);

		$this->outputHeaders($cache);

		echo $cache['body'];
	}

	private function outputHeaders($headers) {
		foreach ( $headers as $key => $header ) {
			// Ignoring the status code parameter, seems not necessary.
			header("$key: $header", true);
		}

		header('Cache-Control: private, max-age=' . FRAMEWORK_RESPONSE_CACHE_AGE . ', must-revalidate', true);
	}
}
