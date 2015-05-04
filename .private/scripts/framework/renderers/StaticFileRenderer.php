<?php
/*! StaticFileRenderer.php | Sends the file as binary content. */

namespace framework\renderers;

use core\Utility as util;

class StaticFileRenderer extends CacheableRenderer {

  public function render() {
    $res = $this->response();

    $mime = util::getInfo($this->path);
    if ( preg_match('/^text\//', $mime) ) {
      $res->header('Content-Type', "$mime; charset=utf-8");
    }
    else {
      $res->header('Content-Transfer-Encoding', 'binary');
      $res->header('Content-Type', $mime);
    }
    unset($mime);

    parent::render();

    // Ouptut the file
    if ( $res->status() < 300 ) {
      $res->header('Content-Length', filesize($this->path));

      $res->send($this->path);
    }
  }

}
