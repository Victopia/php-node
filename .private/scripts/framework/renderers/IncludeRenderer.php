<?php
/*! PHPRenderer.php | Render target file as PHP. */

namespace framework\renderers;

class IncludeRenderer extends AbstractRenderer {

  /**
   * The most simple way to implement a rendering logic.
   */
  public function render($path) {
    $this->response()->header('Content-Type', 'text/html; charset=utf-8', true);

    $bufferEnabled = $this->response()->useOutputBuffer();
    $bufferOptions = $this->response()->outputBufferOptions();

    if ( $bufferEnabled ) {
      ob_start(null, (int) @$bufferOptions['size']);
    }

    // isolate scope
    call_user_func(function() {
      include_once(func_get_arg(0));
    }, $path);

    if ( $bufferEnabled ) {
      $output = trim(ob_get_clean());
      if ( $output ) {
        $this->response()->send($output);
      }
    }

    return $this;
  }

}
