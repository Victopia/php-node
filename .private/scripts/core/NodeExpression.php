<?php
/*! NodeExpression.php | Base class for expressions with custom logics. */

namespace core;

class NodeExpression {

  protected $query = '';

  /**
   * @constructor
   */
  public function __construct($query, $params = []) {
    $this->query = $query;
    $this->params = $params;
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  protected $params = [];

  public function params() {
    return $this->params;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  public function __toString() {
    return "$this->query";
  }

}
