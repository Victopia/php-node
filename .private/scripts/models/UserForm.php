<?php /*! UserForm.php | User accessible CMS modules. */

namespace models;

use authenticators\IsSuperUser;

use core\ContentEncoder;
use core\ContentDecoder;

use core\Utility as util;

use framework\exceptions\ResolverException;

class UserForm extends abstraction\UuidModel {

  public function find(array $filter = array()) {
    // note; only admin users can read others' form.
    $r = @$this->__request;
    if ( !IsSuperUser::authenticate($r) ) {
      $filter['uuid'] = (array) @$r->user->forms;
      if ( !$filter['uuid'] ) {
        return [];
      }
    }

    if ( empty($filter['@sorter']) ) {
      $filter['@sorter'] = [ 'sortIndex', 'title' ];
    }

    return parent::find($filter);
  }

  public function load($identity = null) {
    parent::load($identity);

    if ( $this->identity() && !IsSuperUser::authenticate(@$this->__request) ) {
      if ( !in_array($this->identity(), (array) @$this->__request->user->forms) ) {
        throw new ResolverException(401);
      }
    }

    return $this;
  }

  public function populate() {
    // note; Use default model form if undefined.
    if ( empty($this->formSchema) ) {
      $module = 'models\\' . $this->module;

      $this->formSchema = (new $module)->schema('form');
    }

    if ( isset($this->formSchema) ) {
      $this->formSchemaJson = ContentEncoder::json($this->formSchema);
    }

    if ( isset($this->searchSchema) ) {
      $this->searchSchemaJson = ContentEncoder::json($this->searchSchema);
    }

    return parent::populate();
  }

  protected function beforeSave(array &$errors = array()) {
    if ( !empty($this->formSchemaJson) ) {
      $form = (array) @ContentDecoder::json($this->formSchemaJson);
      if ( $form ) {
        $this->formSchema = $form;
      }
      else {
        $errors[601] = 'Malformed JSON input.';
      }

      unset($form, $this->formSchemaJson);
    }

    if ( !empty($this->searchSchemaJson) ) {
      $form = (array) @ContentDecoder::json($this->searchSchemaJson);
      if ( $form ) {
        $this->searchSchema = $form;
      }
      else {
        $errors[601] = 'Malformed JSON input.';
      }

      unset($form, $this->searchSchemaJson);
    }

    $this->timestamp = util::formatDate('Y-m-d H:i:s.u');

    return parent::beforeSave($errors);
  }

}
