<?php

namespace core;

/**
 * Relation resolver.
 *
 * Index collection identifiers for easy searching, saving up system resources.
 *
 * Functions have pretty descriptive names, while Subject and Objects are custom defined.
 *
 * Database expects Subject and Object as a string with no more than 40 characters,
 * users are encouraged to SHA1 hash their custom identifiers for unique data objects.
 *
 * @author Vicary Arcahgnel <vicary@victopia.org>
 */
class Relation {
	//--------------------------------------------------
	//
	//  Getters
	//
	//--------------------------------------------------

	static function getSubjects($collection, $object) {
		$object = (array) $object;

		$params = array_merge((array) $collection, $object);

		$result = Database::select(FRAMEWORK_COLLECTION_RELATION
			, 'Subject'
			, 'WHERE `@collection` = ? AND `Object` IN ('. implode(',', array_fill(0, count($object), '?')) .')'
			, $params
			, \PDO::FETCH_COLUMN
			, 0
			);

		return $result;
	}

	static function getObjects($collection, $subject) {
		$subject = (array) $subject;

		$params = array_merge((array) $collection, $subject);

		$result = Database::select(FRAMEWORK_COLLECTION_RELATION
			, 'Object'
			, 'WHERE `@collection` = ? AND `Subject` IN ('. implode(',', array_fill(0, count($subject), '?')) .')'
			, $params
			, \PDO::FETCH_COLUMN
			, 0
			);

		return $result;
	}

	static function getAncestors($collection, $object) {
		$object = (array) $object;

		$ancestors = array();

		while (count($object) > 0) {
			$object = self::getSubjects($collection, $object);

			$ancestors = array_merge($ancestors, $object);
		}

		$ancestors = array_unique($ancestors);

		sort($ancestors);

		return $ancestors;
	}

	static function getDescendants($collection, $subject) {
		$subject = (array) $subject;

		$descendants = array();

		while (count($subject) > 0) {
			$subject = self::getObjects($collection, $subject);

			$descendants = array_merge($descendants, $subject);
		}

		$descendants = array_unique($descendants);

		sort($descendants);

		return $descendants;
	}

	//--------------------------------------------------
	//
	//  Setter
	//
	//--------------------------------------------------

	static function set($collection, $subject, $object) {
		return Database::upsert(FRAMEWORK_COLLECTION_RELATION, array(
				NODE_FIELD_COLLECTION => $collection
			, 'Subject' => $subject
			, 'Object' => $object
			));
	}

	//--------------------------------------------------
	//
	//  Delete
	//
	//--------------------------------------------------

	static function deleteSubjects($collection, $object) {
		return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `Object` = ?', array($object))->rowCount();
	}

	static function deleteObjects($collection, $subject) {
		return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `Subject` = ?', array($object))->rowCount();
	}

	static function delete($collection, $object) {
		return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `Subject` = ? OR `Object` = ?', array($object, $object))->rowCount();
	}
}