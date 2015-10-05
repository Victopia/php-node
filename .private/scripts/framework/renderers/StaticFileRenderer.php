<?php
/*! StaticFileRenderer.php | Sends the file as binary content. */

namespace framework\renderers;

use core\Utility as util;

use framework\System;

class StaticFileRenderer extends CacheableRenderer {

  public function render($path) {
    $res = $this->response();

    $mime = util::getInfo($path);
    if ( preg_match('/^text\//', $mime) ) {
      $res->header('Content-Type', "$mime; charset=utf-8");
    }
    else {
      $res->header('Content-Type', $mime);
    }
    unset($mime);

    // note; during developement everything must be revalidated
    if ( System::environment() == System::ENV_DEVELOPMENT ) {
      $res->isVirtual = true;
    }

    parent::render($path);

    // Ouptut the file
    if ( $res->status() < 300 ) {
      $res->header('Content-Length', filesize($path));

      $res->send($path);
    }
  }

}
