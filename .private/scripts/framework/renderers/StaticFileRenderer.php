<?php
/*! StaticFileRenderer.php | Sends the file as binary content. */

namespace framework\renderers;

use core\Utility as util;

class StaticFileRenderer extends CacheableRenderer {

  public function render($path) {
    $res = $this->response();

    $mime = util::getInfo($path);
    if ( preg_match('/^text\//', $mime) ) {
      $res->header('Content-Type', "$mime; charset=utf-8");
    }
    else {
      $res->header('Content-Transfer-Encoding', 'binary');
      $res->header('Content-Type', $mime);
    }
    unset($mime);

    parent::render($path);

    // Ouptut the file
    if ( $res->status() < 300 ) {
      $res->header('Content-Length', filesize($path));

      $res->send($path);
    }
  }

}
