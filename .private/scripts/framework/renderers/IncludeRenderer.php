<?php
/*! PHPRenderer.php | Render target file as PHP. */

namespace framework\renderers;

class IncludeRenderer extends AbstractRenderer {

  /**
   * The most simple way to implement a rendering logic.
   */
  public function render() {
    $this->response()->header('Content-Type', 'text/html; charset=utf-8');

    include_once($this->path);

    return $this;
  }

}
