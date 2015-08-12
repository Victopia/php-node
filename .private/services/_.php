<?php
/*! _.php | Basic data service, manipulate data with specified model class. */

namespace services;

use core\Node;

use framework\exceptions\ServiceException;

/**
 * Inspired by sails.js, but the DataService is implemented as an abstract class.
 *
 * That way we don't store anything without question upon first setup, users
 * are required to make data services with extends this class to enable that
 * collection.
 *
 * Further policy and logics can be done by overriding the CRUD methods, or
 * createFilter() method.
 */
class _ extends \framework\WebService {

  //----------------------------------------------------------------------------
  //
  //  Constants
  //
  //----------------------------------------------------------------------------

  /**
   * Default length for lists without explicitly specifing lengths.
   */
  const DEFAULT_LIST_LENGTH = 20;

  /**
   * Maximum length for lists, hard limit.
   */
  const MAXIMUM_LIST_LENGTH = 100;

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  protected $modelClass;

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  function __invoke($model = null) {
    if ( !$model ) {
      $this->response()->send('Please specifiy a model name.', 501);
      return;
    }
    else if ( !class_exists("models\\$model") ) {
      throw new ServiceException("Model $model does not exist.");
    }

    $model = "models\\$model";
    $this->modelClass = new $model();

    // Remove model name
    $args = array_slice(func_get_args(), 1);

    // Exposed model methods: public function _*() { }
    if ( $args && method_exists($this->modelClass, "_$args[0]") ) {
      $method = array($this->modelClass, '_' . array_shift($args));
    }
    else {
      $method = array($this, $this->resolveMethodName($args));
    }

    return call_user_func_array($method, $args);
  }

  //----------------------------------------------------------------------------
  //
  //  CRUD Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Gets a list of data from target collection.
   */
  protected function let() {
    return $this->modelClass->find(
      $this->createFilter()
      );
  }

  /**
   * Gets a data object with specified entity id from target collection.
   */
  protected function get($identity) {
    $this->modelClass->load($identity);
    if ( !$this->modelClass->identity() ) {
      $this->response()->status(404);
    }
    else {
      return $this->modelClass;
    }
  }

  /*! Note
   *  This should work just like let() and get(), that it passes the whole thing
   *  into Node::set() instead. Inherited classes can make modifications to
   *  parameter values before passing down to this.
   *
   *  One thing this differs from Node::set() is that it returns the whole entity
   *  with processed values, including the last insert id.
   *
   *  A problem is that it assumes the primary key is already named "ID", should
   *  tackle of this.
   */
  protected function set() {
    $data = (array) $this->request()->param();
    if ( $data ) {
      // This will append the existing data to the submitted one.
      if ( $this->request()->meta('extends') ) {
        if ( !isset($data[$this->modelClass->primaryKey()]) ) {
          throw new ServiceException('Extending data without identity field.');
        }

        $this->modelClass->load(
          $data[$this->modelClass->primaryKey()]
          );

        $this->modelClass->appendData($data);
      }
      else {
        $this->modelClass->data($data);
      }

      $this->modelClass->validate($errors);
      if ( $errors ) {
        $this->response()->send($errors, 400);
        return;
      }

      $this->modelClass->save($result);
      switch ( @$result['action'] ) {
        case 'insert':
          $this->response()->status(201); // Created
          break;

        case 'update':
          break;

        default:
          return $result; // $result['errors']
      }

      return $this->modelClass;
    }
    else {
      throw new ServiceException('No contents provided.');
    }
  }

  /**
   * Deletes target data object.
   *
   * @param {int} $identity ID of target data object.
   */
  protected function delete($identity = null) {
    $model = $this->get($identity);
    if ( $model ) {
      $model->delete($isDeleted);

      return (bool) $isDeleted;
    }
  }

  /*! Note
   *  In order to have consistant header returns, all other functions must abide
   *  or calls this method for appropriate headers.
   *
   *  This method should be able to independently serve headers for standard methods,
   *  1. let (collection)
   *  2. get (single data)
   *  3. set (no body)
   *  4. delete (no body)
   *
   *  While other methods should returns nothing, coz we'll have no way to define this.
   */
  protected function head() { }

  /*! Note
   *  This request method is originally designed for a list of supported request
   *  methods. But since nobody is using this and the message body is undocumneted,
   *  we can make use of it and returns the designated model structure.
   */
  protected function option() { }

  //----------------------------------------------------------------------------
  //
  //  Angular Schema Form Methods
  //
  //----------------------------------------------------------------------------

  /**
   * JSON Schema definition.
   */
  protected function schema($type = 'data') {
    switch ( $this->request()->method() ) {
      case 'get':
        return $this->modelClass->schema($type);

      case 'post':
        // TODO: Perform meta-schema validation
        // TODO: Save this schema to target configuration key
        break;
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Private Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Compose a filter for data retrieval.
   *
   * Override this method to implement extended logics and policies.
   */
  protected function createFilter() {
    $filter = [
        '@limit' => $this->listRange()
      ];

    return $filter + $this->request()->param();
  }

  /**
   * Parse List-Range headers for collection retrieval.
   */
  private function listRange() {
    $listRange = $this->request()->get('@limit');
    if ( !$listRange ) {
      $listRange = $this->request()->header('List-Range');
    }

    if ( preg_match('/\s*(\d+)(?:-(\d+))?\s*/', $listRange, $listRange) ) {
      $listRange = [(int) $listRange[1], (int) @$listRange[2]];
    }
    else {
      $listRange = [0];
    }

    if ( !@$listRange[1] ) {
      $listRange[1] = self::DEFAULT_LIST_LENGTH;
    }

    $listRange[1] = min((int) $listRange[1], self::MAXIMUM_LIST_LENGTH);

    return $listRange;
  }

}
