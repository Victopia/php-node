<?php
/* Relation.php | Relations independant from node objects. */

namespace core;

/**
 * Relation resolver.
 *
 * Index collection identifiers for easy searching, saving up system resources.
 *
 * Functions have pretty descriptive names, while Parents and Children are custom defined.
 *
 * Database expects Parent and Child as a string with no more than 40 characters,
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

  static function getParents($children, $collection = '%') {
    $result = (array) $children;

    if ( $result ) {
      $params = array_merge((array) $collection, $result);

      $result = Database::select(FRAMEWORK_COLLECTION_RELATION
        , 'parent'
        , 'WHERE `@collection` LIKE ? AND `child` IN ('. implode(',', array_fill(0, count($result), '?')) .')'
        , $params
        , \PDO::FETCH_COLUMN
        , 0
        );
    }

    return $result;
  }

  static function getChildren($parents, $collection = '%') {
    $result = (array) $parents;

    if ( $result ) {
      $params = array_merge((array) $collection, $result);

      $result = Database::select(FRAMEWORK_COLLECTION_RELATION
        , 'child'
        , 'WHERE `@collection` LIKE ? AND `parent` IN ('. Utility::fillArray($result) .')'
        , $params
        , \PDO::FETCH_COLUMN
        , 0
        );
    }

    return $result;
  }

  static function getAncestors($children, $collection = '%') {
    $result = (array) $children;

    $ancestors = array();

    while ( $result ) {
      $result = self::getParents($result, $collection);

      $ancestors = array_merge($ancestors, $result);
    }

    $ancestors = array_unique($ancestors);

    sort($ancestors);

    return $ancestors;
  }

  static function getDescendants($parents, $collection = '%') {
    $result = (array) $parents;

    $descendants = array();

    while ( $result ) {
      $result = self::getChildren($result, $collection);

      $descendants = array_merge($descendants, $result);
    }

    $descendants = array_unique($descendants);

    sort($descendants);

    return $descendants;
  }

  /**
   * Check whether $child is descendant of $parent.
   */
  static function isRelated($parent, $child, $collection = '%') {
    return in_array($child, self::getDescendants($parent, $collection));
  }

  /**
   * Check whether $child is direct descendant of $parent.
   */
  static function isDirectRelated($parent, $child, $collection = '%') {
    return in_array($child, self::getChildren($parent, $collection));
  }

  //--------------------------------------------------
  //
  //  Setter
  //
  //--------------------------------------------------

  static function set($parent, $child, $collection) {
    return Database::upsert(FRAMEWORK_COLLECTION_RELATION, array(
        Node::FIELD_COLLECTION => $collection
      , 'parent' => $parent
      , 'child' => $child
      ));
  }

  //--------------------------------------------------
  //
  //  Delete
  //
  //--------------------------------------------------

  static function deleteAncestors($child, $collection = '*') {
    return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `@collection` LIKE ? AND `child` = ?',
      array($collection, $child))->rowCount();
  }

  static function deleteDescendants($parent, $collection = '%') {
    return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `@collection` LIKE ? AND `parent` = ?',
      array($collection, $parent))->rowCount();
  }

  static function delete($parent, $child, $collection = '%') {
    return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `@collection` LIKE ? AND `parent` = ? AND `child` = ?',
      array($collection, $parent, $child))->rowCount();
  }
}
