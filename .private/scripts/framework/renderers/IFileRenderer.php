<?php
/*! IFileRenderer.php | Interface for all renderers. */

namespace framework\renderers;

interface IFileRenderer {

  /**
   * The rendering function for specified file.
   */
  public function render($path);

}
