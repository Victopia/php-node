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

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

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

    $return = Relation::getParents($this->identity(), $collection);

    if ( $parents ) {
      if ( $replace ) {
        $this->deleteAncestors($collection);
      }

      if ( !is_array($parents) ) {
        $parents = array($parents);
      }

      foreach ( $parents as $parent ) {
        if ( $parent instanceof AbstractModel ) {
          $parent = $parent->identity();
        }

        Relation::set($parent, $this->identity(), $collection);
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

    $return = Relation::getChildren($this->identity(), $collection);

    if ( $children ) {
      if ( $replace ) {
        $this->deleteDescendants($collection);
      }

      if ( !is_array($children) ) {
        $children = array($children);
      }

      foreach ( $children as $child ) {
        if ( $child instanceof AbstractModel ) {
          $child = $child->identity();
        }

        Relation::set($this->identity(), $child, $collection);
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

    return Relation::getAncestors($this->identity(), $collection);
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

    return Relation::getDescendants($this->identity(), $collection);
  }

  /**
   * Delete parent(s) relation from current model.
   *
   * @param {?array|AbstractModel|int|string} $parents Target parents to delete, or all parents of omitted.
   * @param {?string} $collection Collection identifier of the relation, defaults to the model's collection name.
   */
  protected function deleteAncestors($collection = '%', $parents = null) {
    if ( $collection === null ) {
      $collection = $this->collectionName();
    }

    if ( $parents === null ) {
      return Relation::deleteAncestors($this->identity(), $collection);
    }
    else {
      if ( !is_array($parents) ) {
        $parents = (array) $parents;
      }

      return array_reduce($parents, function($result, $parent) use($collection) {
        if ( $parent instanceof AbstractModel ) {
          $parent = $parent->identity();
        }

        return $result && Relation::delete($parent, $this->identity(), $collection);
      }, true);
    }
  }

  /**
   * Delete children relation from current model.
   *
   * @param {?array|AbstractModel|int|string} $children Target children to delete, or all children of omitted.
   * @param {?string} $collection Collection identifier of the relation, defaults to the model's collection name.
   */
  protected function deleteDescendants($collection = '%', $children = null) {
    if ( $collection === null ) {
      $collection = $this->collectionName();
    }

    if ( $children === null ) {
      return Relation::deleteDescendants($this->identity(), $collection);
    }
    else {
      if ( !is_array($children) ) {
        $children = (array) $children;
      }

      return array_reduce($children, function($result, $child) use($collection) {
        if ( $child instanceof AbstractModel ) {
          $child = $child->identity();
        }

        return $result && Relation::delete($this->identity(), $child, $collection);
      }, true);
    }
  }

}
