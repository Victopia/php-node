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

		//------------------------------
		//  Remove virtual path prefixes
		//------------------------------

		$scriptDir = dirname($_SERVER['SCRIPT_NAME']);

		if ($scriptDir != '/') {
  		$res = str_replace($scriptDir, '', $res);
		}

		unset($scriptDir);

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

		$this->handle($res);

	}

	//--------------------------------------------------
	//
	//  Methods: Serializable
	//
	//--------------------------------------------------

	public
	/* String */ function serialize() {
		return serialize($this);
	}

	public
	/* void */ function unserialize($serial) {
		return unserialize($this);
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
			redirect(304);
		}

		// $_SERVER field mapping
		$_SERVER['SCRIPT_FILENAME'] = realpath($path);
		$_SERVER['SCRIPT_NAME'] = $_SERVER['REQUEST_URI'];
		$_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];

		ob_start();

		if (preg_match('/^image/', $mime)) {
			readfile($path);
		}
		else {
			// TODO: Pure include_once at beta stage.
			include_once($path);
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
      header('Cache-Control: max-age=' . FRAMEWORK_RESPONSE_CACHE_PERMANANT, TRUE);
      header('Expires: ' . gmdate(DATE_RFC1123, time() + FRAMEWORK_RESPONSE_CACHE_PERMANANT), TRUE);
      header_remove('Pragma');
    }
    else {
      header('Cache-Control: private, max-age=' . FRAMEWORK_RESPONSE_CACHE_TEMPORARY . ', must-revalidate', TRUE);
    }

    header('Last-Modified: ' . date(DATE_RFC1123, filemtime($path)), TRUE);
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