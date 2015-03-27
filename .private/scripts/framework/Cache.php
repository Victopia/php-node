<?php
/*! Cache.php | https://github.com/victopia/php-node */

namespace framework;

/**
 * This class aims to provide a brainless way to store and retrieve binary-safe resources.
 *
 * Users don't have to care the deletion and storage usage, cache is deleted in a timely basis.
 *
 * @author Vicary Archangel
 */
class Cache {

	/**
	 * Max revisions to keep in cache.
	 */
	public static $revisionLimit = 10;

	/**
	 * Retrieves a cached resource.
	 *
	 * $key     : String to identify target cache.
	 * $hash    : Specific revision to retrieve, NULL is returned if not found.
	 */
	public static function get($key, $hash = NULL) {
		$res = self::resolve($key, $hash);

		if ($res !== NULL) {
			$res = file_get_contents($res->getRealPath());
			$res = unserialize($res);
		}

		return $res;
	}

	/**
	 * Retrieves a cached resource file as a SplFileInfo object.
	 */
	public static function getInfo($key, $hash = NULL) {
		return self::resolve($key, $hash);
	}

	/**
	 * Cache a resource.
	 *
	 * $key     : String to identify target cache.
	 * $content : Contents to be stored.
	 * $hash    : Writes to
	 */
	public static function set($key, $content, $hash = FALSE) {
		if ($hash && $hash instanceof SplFileInfo) {
			$hash = $hash->getFilename();
		}

		$res = self::resolve($key, $hash);

		$content = serialize($content);

		// Return a revision file if a target is specified.
		if ($res->isFile()) {
			if ($res->isWritable()) {
				$file = $res->openFile('w');

				$file->fwrite($content);

				return $hash;
			}
		}

		// RecuresiveDirectoryIterator points to the first file after rewind,
		// parent directory can't be retrieved with ->getPath() or ->getRealPath()
		// after after iteration.
		$cacheDirectory = $res->getRealPath() . DIRECTORY_SEPARATOR;

		if ($res->isDir()) {
			$hash = md5($content);

			$fileCache = array();

			// If there is only one file in the directory, RecursiveDirectoryIterator
			// pointing to a directory will never get to the first file before a proper
			// next() and rewind() in foreach statements.
			$res->next();
			$res->rewind();

			// Iterate through existing caches
			foreach ($res as $file) {
				if ($file->isFile()) {
					$res1 = $file->getRealPath();
					$res1 = file_get_contents($res1);
					$res1 = md5($res1);

					// If a revision contains an exact content,
					// return that revision hash.
					if ($hash === $res1) {
						touch($file->getPathname());

						return $hash;
					}

					unset($res1);

					$fileCache[$file->getCTime()] = $file;
				}
			}

			// Remove revisions exceed the revision limit.
			ksort($fileCache);

			if (count($fileCache) > self::$revisionLimit) {
				// Keys are not preserved with array_splice(),
				// but we do not use it anymore.
				$fileCache = array_splice($fileCache, self::$revisionLimit);

				foreach ($fileCache as $file) {
					unlink($fileCache);
				}
			}

			unset($fileCache);

			// Generate revision hash
			$hash = md5(microtime(1));

			$res = file_put_contents("$cacheDirectory$hash", $content);

			if ($res <= 0) {
				return FALSE;
			}

			return $hash;
		}
	}

	/**
	 * Force deletion on a cached resource.
	 *
	 * $key     : String to identify target cache.
	 * $hash    : (Optional) Revision to delete, all revisions will be deleted if omitted.
	 */
	public static function delete($key, $hash = NULL) {
		$res = self::resolve($key, $hash);

		/* Note by Eric @ 6 Jun 2012
			Possibly don't need it again, this just removes the existing empty parent folder.

		// Do nothing if a revision is specified but nothing found.
		if ($hash !== NULL && $res === NULL) {
			return;
		}

		// Delete everything in the directory.
		if ($res === NULL) {
			$res = self::resolve($key, $hash);
		}
		*/

		// Skip the delete if nothing is found.
		if ($res === NULL) {
			return;
		}

		if ($res->isFile()) {
			// Remove target revision(s).
			if (!$res->isWritable()) {
				\log::write('Target file is not writable, deletion skipped.', 'Warning');
			}
			else {
				$path = $res->getRealPath();

				unlink($path);

				$path = dirname($path);

				// Remove the directory if empty.
				$res = new \RecursiveDirectoryIterator($path, \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);

				if ($res->isDir() && !$res->hasChildren()) {
					rmdir($path);
				}
			}
		}
		elseif ($res->isDir()) {
			$cacheDirectory = $res->getRealPath();

			foreach ($res as $file) {
				if ($file->isFile()) {
					unlink($file->getRealPath());
				}
				elseif ($file->isDir()) {
					rmdir($file->getRealPath());
				}
			}

			rmdir($cacheDirectory);
		}
	}

	/**
	 * @private
	 *
	 * 1. Prepend TMP directory
	 * 2. Find latest cache if $hash is NULL
	 * 3. Return directory if $hash is FALSE
	 */
	private static function
	/* SplFileInfo */ resolve($key, $hash = FALSE) {
		$target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . md5($key);

		if (!file_exists($target)) {
			if ($hash !== FALSE) {
				return NULL;
			}

			// Only FALSE when you _want_ a folder, mkdir() it.
			elseif (mkdir($target) === FALSE) {
				throw new \CacheException('Error creating cache folder "'.$target.'", please check folder permissions.');
			}
		}
		elseif (is_file($target)) {
			throw new \CacheException($target . ' is already a file, please specify another path.');
		}

		$target.= DIRECTORY_SEPARATOR;

		if ($hash === FALSE) {
			return new \RecursiveDirectoryIterator($target, \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);
		}

		if ($hash !== NULL) {
			$target = "$target$hash";

			if (!is_file($target)) {
				throw new \CacheException('Target revision is not a file.');
			}

			return new \SplFileInfo($target);
		}
		else {
			$res = new \RecursiveDirectoryIterator($target, \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);

			$res->next();
			$res->rewind();

			$lfile = NULL;

			foreach ($res as $file) {
				if (($file->isFile() && strlen($file->getFilename()) == 32) &&
					($lfile == NULL || $lfile->getMTime() < $file->getMTime())) {
					$lfile = $file;
				}
			}

			if ($lfile === NULL) {
				return NULL;
			}

			if (!$lfile->isReadable() || !$lfile->isWritable()) {
				throw new \CacheException('Cache cannot be read or written, please check file permission to PHP user.');
			}

			return $lfile;
		}
	}

}

class CacheException extends \Exception {}
