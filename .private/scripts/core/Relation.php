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

  static function getParents($collection, $children) {
    $result = (array) $children;

    if ( $result ) {
      $params = array_merge((array) $collection, $result);

      $result = Database::select(FRAMEWORK_COLLECTION_RELATION
        , 'parent'
        , 'WHERE `@collection` = ? AND `child` IN ('. implode(',', array_fill(0, count($result), '?')) .')'
        , $params
        , \PDO::FETCH_COLUMN
        , 0
        );
    }

    return $result;
  }

  static function getChildren($collection, $parents) {
    $result = (array) $parents;

    if ( $result ) {
      $params = array_merge((array) $collection, $result);

      $result = Database::select(FRAMEWORK_COLLECTION_RELATION
        , 'child'
        , 'WHERE `@collection` = ? AND `parent` IN ('. Utility::fillArray($result) .')'
        , $params
        , \PDO::FETCH_COLUMN
        , 0
        );
    }

    return $result;
  }

  static function getAncestors($collection, $children) {
    $result = (array) $children;

    $ancestors = array();

    while ( $result ) {
      $result = self::getParents($collection, $result);

      $ancestors = array_merge($ancestors, $result);
    }

    $ancestors = array_unique($ancestors);

    sort($ancestors);

    return $ancestors;
  }

  static function getDescendants($collection, $parents) {
    $result = (array) $parents;

    $descendants = array();

    while ( $result ) {
      $result = self::getChildren($collection, $result);

      $descendants = array_merge($descendants, $result);
    }

    $descendants = array_unique($descendants);

    sort($descendants);

    return $descendants;
  }

  /**
   * Check whether a pair of parent and child is related.
   */
  static function isRelated($collection, $parent, $child, $direct = false) {
    if ( $direct ) {
      $children = self::getChildren($collection, $parent);
    }
    else {
      $children = self::getDescendants($collection, $parent);
    }

    return in_array($child, $children);
  }

  //--------------------------------------------------
  //
  //  Setter
  //
  //--------------------------------------------------

  static function set($collection, $parent, $child) {
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

  static function deleteParents($collection, $child) {
    return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `child` = ?', array($child))->rowCount();
  }

  static function deleteChildren($collection, $parent) {
    return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `parent` = ?', array($parent))->rowCount();
  }

  static function delete($collection, $parent, $child) {
    return Database::query('DELETE FROM ' . FRAMEWORK_COLLECTION_RELATION . ' WHERE `parent` = ? AND `child` = ?', array($parent, $child))->rowCount();
  }
}
