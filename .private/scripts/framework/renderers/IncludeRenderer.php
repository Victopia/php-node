<?php
/*! PHPRenderer.php | Render target file as PHP. */

namespace framework\renderers;

class IncludeRenderer extends AbstractRenderer {

  /**
   * The most simple way to implement a rendering logic.
   */
  public function render() {
    $this->response()->header('Content-Type', 'text/html; charset=utf-8');

    $bufferEnabled = $this->response()->useOutputBuffer();
    $bufferOptions = $this->response()->outputBufferOptions();

    if ( $bufferEnabled ) {
      ob_start(null, (int) @$bufferOptions['size']);
    }

    include_once($this->path);

    if ( $bufferEnabled ) {
      $output = trim(ob_get_clean());
      if ( $output ) {
        $this->response()->write($output);
      }
    }

    return $this;
  }

}
