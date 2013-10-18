<?php
/* Relation.php | Relations independant from node objects. */

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
    $result = (array) $object;

    if ($result) {
      $params = array_merge((array) $collection, $result);

      $result = Database::select(FRAMEWORK_COLLECTION_RELATION
        , 'Subject'
        , 'WHERE `@collection` = ? AND `Object` IN ('. implode(',', array_fill(0, count($result), '?')) .')'
        , $params
        , \PDO::FETCH_COLUMN
        , 0
        );
    }

    return $result;
  }

  static function getObjects($collection, $subject) {
    $result = (array) $subject;

    if ($result) {
      $params = array_merge((array) $collection, $result);

      $result = Database::select(FRAMEWORK_COLLECTION_RELATION
        , 'Object'
        , 'WHERE `@collection` = ? AND `Subject` IN ('. implode(',', array_fill(0, count($result), '?')) .')'
        , $params
        , \PDO::FETCH_COLUMN
        , 0
        );
    }

    return $result;
  }

  static function getAncestors($collection, $object) {
    $result = (array) $object;

    $ancestors = array();

    while ($result) {
      $result = self::getSubjects($collection, $result);

      $ancestors = array_merge($ancestors, $result);
    }

    $ancestors = array_unique($ancestors);

    sort($ancestors);

    return $ancestors;
  }

  static function getDescendants($collection, $subject) {
    $result = (array) $subject;

    $descendants = array();

    while ($result) {
      $result = self::getObjects($collection, $result);

      $descendants = array_merge($descendants, $result);
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
    return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `Subject` = ?', array($subject))->rowCount();
  }

  static function delete($collection, $subject, $object) {
    return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `Subject` = ? AND `Object` = ?', array($subject, $object))->rowCount();
  }
}
