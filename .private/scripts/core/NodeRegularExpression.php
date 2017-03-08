<?php /*! NodeRegularExpression.php | Regular expressions. */

namespace core;

class NodeRegularExpression extends NodeExpression {

  public function __construct($pattern) {
    $this->query = 'REGEXP ?';

    $this->pattern = $pattern;

    $this->params[] = preg_replace(
      '/\\\b(.*?)\\\b/', '[[:<:]]$1[[:>:]]',
      trim(rtrim(trim($pattern), 'igxmysADSUXJ'), '/')
    );
  }

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  protected $pattern = '';

  public function pattern() {
    return $this->pattern;
  }

}
