<?php
/*! Cache.php | https://github.com/victopia/php-node */

namespace framework;

use core\Log;

use framework\exceptions\FrameworkException;

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
	public static $maxRevisions = 1;

	/**
	 * Retrieves a cached resource.
	 *
	 * @param $key {string} Identifier of target cache, can be of any format.
	 * @param $hash {?string} Reversion hash returned from set().
	 */
	public static function get($key, $hash = null) {
		$res = self::resolve($key, $hash);

		if ( $res !== null ) {
			$res = file_get_contents($res->getRealPath());
			$res = unserialize($res);
		}

		return $res;
	}

	/**
	 * Retrieves a cached resource file as a SplFileInfo object.
	 *
	 * @param $key {string} Identifier of target cache.
	 * @param $hash {?string} Revision hash returned from set().
	 */
	public static function getInfo($key, $hash = null) {
		return self::resolve($key, $hash);
	}

	/**
	 * Cache a resource.
	 *
	 * @param {string} $key Identifier of target cache, can be of any format.
	 * @param {mixed} $content Serialized contents to be stored.
	 * @param {?string} $hash Overwrites target revision.
	 */
	public static function set($key, $content, $hash = '*') {
		if ( $hash && $hash instanceof SplFileInfo ) {
			$hash = $hash->getFilename();
		}

		$res = self::resolve($key, $hash);

		$content = serialize($content);

		// Return a revision file if a target is specified.
		if ( $res->isFile() && $res->isWritable() ) {
			$file = $res->openFile('w');

			$file->fwrite($content);

			return $hash;
		}

		// RecuresiveDirectoryIterator points to the first file after rewind,
		// parent directory can't be retrieved with ->getPath() or ->getRealPath()
		// after after iteration.
		$cacheDirectory = $res->getRealPath() . DIRECTORY_SEPARATOR;

		if ( $res->isDir() ) {
			$hash = md5($content);

			$fileCache = array();

			// If there is only one file in the directory, RecursiveDirectoryIterator
			// pointing to a directory will never get to the first file before a proper
			// next() and rewind() in foreach statements.
			$res->next();
			$res->rewind();

			// Iterate through existing caches
			foreach ( $res as $file ) {
				if ( $file->isFile() ) {
					$res1 = $file->getRealPath();
					$res1 = file_get_contents($res1);
					$res1 = md5($res1);

					// If a revision contains an exact content,
					// return that revision hash.
					if ( $hash === $res1 ) {
						touch($file->getPathname());

						return $hash;
					}

					unset($res1);

					$fileCache[$file->getCTime()] = $file;
				}
			}

			// Remove revisions exceed the revision limit.
			ksort($fileCache);

			if ( count($fileCache) > self::$maxRevisions ) {
				// Keys are not preserved with array_splice(),
				// but we do not use it anymore.
				$fileCache = array_splice($fileCache, self::$maxRevisions);

				foreach ( $fileCache as $file ) {
					unlink($fileCache);
				}
			}

			unset($fileCache);

			// Generate revision hash
			$hash = md5(microtime(1));

			$res = file_put_contents("$cacheDirectory$hash", $content);

			if ( $res <= 0 ) {
				return false;
			}

			return $hash;
		}
	}

	/**
	 * Force deletion on a cached resource.
	 *
	 * @param {string} $key Identifier of target cache.
	 * @param {?string} $hash Target revision to delete, all revisions will be deleted if omitted.
	 */
	public static function delete($key, $hash = '*') {
		$res = self::resolve($key, $hash);

		// Skip the delete if nothing is found.
		if ( $res === null ) {
			return;
		}

		if ( $res->isFile() ) {
			// Remove target revision(s).
			if ( !$res->isWritable() ) {
				Log::warning('Target file is not writable, deletion skipped.');
			}
			else {
				$path = $res->getRealPath();

				unlink($path);

				$path = dirname($path);

				// Remove the directory if empty.
				$res = new \RecursiveDirectoryIterator($path, \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);

				if ( $res->isDir() && !$res->hasChildren() ) {
					rmdir($path);
				}
			}
		}
		else if ( $res->isDir() ) {
			$cacheDirectory = $res->getRealPath();

			foreach ( $res as $file ) {
				if ( $file->isFile() ) {
					unlink($file->getRealPath());
				}
				else if ( $file->isDir() ) {
					rmdir($file->getRealPath());
				}
			}

			rmdir($cacheDirectory);
		}
	}

	/**
	 * @private
	 *
	 * 1. Mimics tmp directory
	 * 2. Find latest cache if $hash is null
	 * 3. Return directory if $hash is '*'
	 */
	private static /* SplFileInfo */ function resolve($key, $hash = null) {
		$target = sys_get_temp_dir();

		if ( strrpos($target, DIRECTORY_SEPARATOR) !== strlen($target) - 1 ) {
			$target.= DIRECTORY_SEPARATOR;
		}

		$target.= md5($key);

		if ( !file_exists($target) ) {
			if ( $hash !== '*' ) {
				return null;
			}

			// Only false when you _want_ a folder, mkdir() it.
			elseif ( mkdir($target) === false ) {
				throw new FrameworkException('Error creating cache folder "'.$target.'", please check folder permissions.');
			}
		}
		else if ( is_file($target) ) {
			throw new FrameworkException($target . ' is already a file, please specify another path.');
		}

		$target.= DIRECTORY_SEPARATOR;

		if ( $hash === '*' ) {
			return new \RecursiveDirectoryIterator($target,
				\FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);
		}

		if ( $hash !== null ) {
			$target = "$target$hash";

			if ( !is_file($target) ) {
				throw new FrameworkException('Target revision is not a file.');
			}

			return new \SplFileInfo($target);
		}
		else {
			$res = new \RecursiveDirectoryIterator($target,
				\FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);

			$res->next();
			$res->rewind();

			$lfile = null;

			foreach ( $res as $file ) {
				if (($file->isFile() && strlen($file->getFilename()) == 32) &&
					($lfile == null || $lfile->getMTime() < $file->getMTime())) {
					$lfile = $file;
				}
			}

			if ( $lfile === null ) {
				return null;
			}

			if ( !$lfile->isReadable() || !$lfile->isWritable() ) {
				throw new FrameworkException('Cache cannot be read or written, please check file permission to PHP user.');
			}

			return $lfile;
		}
	}

}
