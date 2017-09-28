<?php /*! StaticFileRenderer.php | Sends the file as binary content. */

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
      /*
      // todo; Supports byte-range header.
      if ( $req->header('Range') ) {
        if ( !preg_match('/^bytes=(.*)/', $req->header('Range'), $matches) ) {
          if ( strpos($matches[1], ',') !== false ) {
            $res->status(416); // note; 416 Requested Range Not Satisfiable, we don't handle multipart response yet.
          }
        }
      }
      else {
        $res->header('Content-Length', filesize($path));
        $res->send($path);
      }
      */
      $res->header('Content-Length', filesize($path));
      $res->send($path);
    }
  }

}
