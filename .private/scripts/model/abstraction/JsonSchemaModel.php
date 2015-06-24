<?php
/*! JsonSchemaModel.php | Data models that leverages JSON schema. */

namespace model\abstraction;

use framework\Configuration as conf;

abstract class JsonSchemaModel extends AbstractModel {

  public function validate() {
    $schema = $this->schema();

    (new \JsonSchema\RefResolver(new \JsonSchema\Uri\UriRetriever))->resolve($schema);

    $validator = new \JsonSchema\Validator;

    $validator->check($this->data, $schema);
    if ( $validator->isValid() ) {
      return array();
    }
    else {
      return array_reduce($validator->getErrors(), function($result, $error) {
        $result[] = sprintf('[%s] %s', $error['property'], $error['message']);
      }, array());
    }
  }

  public function schema($type = 'data') {
    switch ( $type ) {
      case 'form':
        $default = array('*');
        break;

      default:
        $default = null;
        break;
    }

    return conf::get("$this->collectionName.model::$type", $default);
  }

}
