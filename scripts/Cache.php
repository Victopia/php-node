<?php
/*! Cache.php
 *
 * This class aims to provide a thinkless way to store and retrieve binary-safe resources.
 *
 * Users don't have to care the deletion and storage usage, cache is deleted in a timely basis.
 *
 * @author Vicary Archangel
 */

class Cache {

	/**
	 * Retrieves a cached resource.
	 *
	 * $key     : String to identify target cache.
	 * $hash    : Specific revision to retrieve, NULL is returned if not found.
	 */
	public static function get($key, $hash = NULL) {
		$res = self::resolve($key);
		
		if ($hash !== NULL && !file_exists("$res$hash")) {
			$hash = NULL;
		}
		
		if ($hash === NULL) {
			foreach (new RecursiveDirectoryIterator($res) as $file) {
				if ($file->isFile() && strlen(basename($file)) == 32) {
					$res = "$file";
					break;
				}
			}
		}
		else {
			$res = "$res$hash";
			
			if (!is_file($res)) {
				throw new CacheException('Target revision is not a file.');
			}
		}
		
		return file_get_contents($res);
	}
	
	/**
	 * Gets all available revisions of a cache.
	 *
	 * $key     : String to identify target cache.
	 */
	public static function getRevisions($key) {
		$res = self::resolve($key);
		
		$rev = array();
		
		foreach (new RecursiveDirectoryIterator($res) as $file) {
			$file = basename($file);
			
			if (strlen($file) == 32) {
				$rev[] = $file;
			}
		}
		
		return $rev;
	}
	
	/**
	 * Cache a resource.
	 *
	 * $key     : String to identify target cache.
	 * $content : Contents to be stored.
	 *
	 */
	public static function set($key, $content) {
		$res = self::resolve($key);
		
		$hash = md5($content);
		
		foreach (new RecursiveDirectoryIterator($res) as $file) {
			if (is_file($file) && $hash === md5(file_get_contents($file))) {
				return $hash;
			}
		}
		
		$hash = md5(microtime(1));
		
		if (file_put_contents("$res$hash", $content) <= 0) {
			return FALSE;
		}
		
		return $hash;
	}
	
	/**
	 * Force deletion on a cached resource.
	 *
	 * $key     : String to identify target cache.
	 * $hash    : (Optional) Revision to delete, all revisions will be deleted if omitted.
	 */
	public static function delete($key, $hash = NULL) {
		$res = self::resolve($key);
		
		if (is_dir($res)) {
			if ($hash !== NULL) {
				file_exists("$res$hash") and unlink("$res$hash");
			}
			else {
				foreach (new RecursiveDirectoryIterator($res) as $file) {
					if ($file->isFile()) {
						unlink($file);
					}
					elseif ($file->isDir()) {
						rmdir($file);
					}
				}
			}
		}
		
		if (count(scandir($res)) <= 2) {
			rmdir($res);
		}
	}
	
	/**
	 * @private
	 */
	private static function resolve($key) {
		$target = sys_get_temp_dir() . md5($key);
		
		if (!file_exists($target) && mkdir($target) === FALSE) {
			throw new CacheException('Cannot create cache folder ' . $target . ', please check PHP privileges.');
		}
		
		if (!is_dir($target)) {
			throw new CacheException($target . ' is already a file, please specify another path.');
		}
		
		return $target . DIRECTORY_SEPARATOR;
	}
	
}

class CacheException extends Exception {}