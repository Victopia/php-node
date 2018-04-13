<?php // CountableCollection.php: Implements a count public method.

namespace models\abstraction;

use framework\WebService;

trait CountableCollection {

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  public function __count(array $filter = []) {
    if ( !$filter ) {
      $filter = $this->createFilter(false);
    }

    return $this->find($filter)->count();
  }

  //----------------------------------------------------------------------------
  //
  //  Private Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Compose a filter for data retrieval.
   *
   * Override this method to implement extended logics and policies.
   */
  protected function createFilter($withMeta = true) {
    $filter = $this->request()->param();

    if ( $withMeta ) {
      $filter = [
        '@limits' => $this->listRange(),
        '@sorter' => $this->listOrder()
      ] + $filter;
    }

    return $filter;
  }

  /**
   * Parse "__range" parameter or "List-Range" header for collection retrieval.
   */
  protected function listRange() {
    $listRange = $this->request()->meta('range');
    if ( !$listRange ) {
      $listRange = $this->request()->header('List-Range');
    }

    if ( preg_match('/\s*(\d+)(?:-(\d+))?\s*/', $listRange, $listRange) ) {
      $listRange = [(int) $listRange[1], (int) @$listRange[2]];
    }
    else {
      $listRange = [0];
    }

    if ( !@$listRange[1] ) {
      $listRange[1] = WebService::DEFAULT_LIST_LENGTH;
    }

    $listRange[1] = min((int) $listRange[1], WebService::MAXIMUM_LIST_LENGTH);

    return $listRange;
  }

  /**
   * Parse "__order" parameter for a collection ordering.
   */
  protected function listOrder() {
    return (array) $this->request()->meta('order');
  }

}
