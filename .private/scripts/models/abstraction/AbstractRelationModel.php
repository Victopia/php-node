<?php
/*! AbstractRelationModel.php | Data models with relation functions built in. */

namespace models\abstraction;

use core\Relation;

use framework\exceptions\FrameworkException;

/**
 * Model class added with relation functionalities.
 *
 * Collection names are model's collection name by default.
 */
abstract class AbstractRelationModel extends AbstractModel {

  /**
   * Accessor to parent relations.
   *
   * @param {?AbstractModel|int|string|array} $parents If provided, add them as parents in relation.
   * @param {?string} $collection Collection identifier for the relation, defaults to the model's collection name.
   *
   * @return {array} Existing parents before modification.
   */
  protected function parents($collection = null, $parents = null, $replace = false) {
    if ( $collection === null ) {
      $collection = $this->collectionName();
    }

    $return = Relation::getParents($collection, $this->identity());

    if ( $parents ) {
      if ( $replace ) {
        $this->deleteParents($collection);
      }

      if ( !is_array($parents) ) {
        $parents = array($parents);
      }

      foreach ( $parents as $parent ) {
        if ( $parent instanceof AbstractModel ) {
          $parent = $parent->identity();
        }

        Relation::set($collection, $parent, $this->identity());
      }
    }

    return $return;
  }

  /**
   * Accessor to children relations.
   *
   * @param {?AbstractModel|int|string|array} $children If provided, add them as children in relation.
   * @param {?string} $collection Collection identifier for the relation, defaults to the model's collection name.
   *
   * @return {array} Existing children before modification.
   */
  protected function children($collection = null, $children = null, $replace = false) {
    if ( $collection === null ) {
      $collection = $this->collectionName();
    }

    $return = Relation::getChildren($collection, $this->identity());

    if ( $children ) {
      if ( $replace ) {
        $this->deleteChildren($collection);
      }

      if ( !is_array($children) ) {
        $children = array($children);
      }

      foreach ( $children as $child ) {
        if ( $child instanceof AbstractModel ) {
          $child = $child->identity();
        }

        Relation::set($collection, $this->identity(), $child);
      }
    }

    return $return;
  }

  /**
   * Get related ancestors of current model.
   *
   * @param {?string} $collection Collection identifier of the relation, defaults to the model's collection name.
   */
  protected function ancestors($collection = null) {
    if ( $collection === null ) {
      $collection = $this->collectionName();
    }

    return Relation::getAncestors($collection, $this->identity());
  }

  /**
   * Get related descendants of current model.
   *
   * @param {?string} $collection Collection identifier of the relation, defaults to the model's collection name.
   */
  protected function descendants($collection = null) {
    if ( $collection === null ) {
      $collection = $this->collectionName();
    }

    return Relation::getDescendants($collection, $this->identity());
  }

  /**
   * Delete parent(s) relation from current model.
   *
   * @param {?array|AbstractModel|int|string} $parents Target parents to delete, or all parents of omitted.
   * @param {?string} $collection Collection identifier of the relation, defaults to the model's collection name.
   */
  function deleteParents($collection = null, $parents = null) {
    if ( $collection === null ) {
      $collection = $this->collectionName();
    }

    if ( $parents === null ) {
      return Relation::deleteParents($collection, $this->identity());
    }
    else {
      if ( !is_array($parents) ) {
        $parents = (array) $parents;
      }

      return array_reduce($parents, function($result, $parent) use($collection) {
        if ( $parent instanceof AbstractModel ) {
          $parent = $parent->identity();
        }

        return $result && Relation::delete($collection, $parent, $this->identity());
      }, true);
    }
  }

  /**
   * Delete children relation from current model.
   *
   * @param {?array|AbstractModel|int|string} $children Target children to delete, or all children of omitted.
   * @param {?string} $collection Collection identifier of the relation, defaults to the model's collection name.
   */
  function deleteChildren($collection = null, $children = null) {
    if ( $collection === null ) {
      $collection = $this->collectionName();
    }

    if ( $children === null ) {
      return Relation::deleteParents($collection, $this->identity());
    }
    else {
      if ( !is_array($children) ) {
        $children = (array) $children;
      }

      return array_reduce($children, function($result, $child) use($collection) {
        if ( $child instanceof AbstractModel ) {
          $child = $child->identity();
        }

        return $result && Relation::delete($collection, $this->identity(), $child);
      }, true);
    }
  }

}
