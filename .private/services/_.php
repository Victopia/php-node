<?php /*! _.php | Basic data service, manipulate data with specified model class. */

namespace services;

use framework\exceptions\ResolverException;
use framework\exceptions\ServiceException;
use framework\exceptions\ValidationException;

/**
 * Inspired by sails.js, specific data models are to be defined.
 *
 * That way we don't store anything without question upon first setup, users
 * are required to make data models with extends this class to enable that
 * collection.
 */
class _ extends \framework\WebService {

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
    else if ( class_exists("models\\$model") ) {
      if ( is_a("models\\$model", 'models\\abstraction\\WebServiceModel', true) ) {
        $model = "models\\$model";
        $this->modelClass = new $model();
      }
      else {
        throw new ServiceException("$model is not a web service.", 19023);
      }
    }
    else {
      $this->modelClass = new \models\NodeModel($model);
    }

    unset($model);

    $args = array_values(func_get_args());

    // PHP builds that has $model included in $args.
    if (version_compare(PHP_VERSION, '7') < 0 || @$args[0] === null) {
      array_shift($args);
    }

    $method = reset($args);
    $reservedMethods = array('get', 'set', 'unset', 'isset', 'invoke', 'call', 'callStatic');

    // Exposed model methods: public function __*() { }
    if ( !in_array($method, $reservedMethods) && method_exists($this->modelClass, "__$method") ) {
      // note; custom functions can writes directly into output buffer without taking care of 404.
      $this->response()->status(200);
      $method = array($this->modelClass, '__' . array_shift($args));
    }
    else if ( is_callable($this->modelClass) ) {
      $method = $this->modelClass;
    }
    else {
      // note; remove file extensions which should be taken care by ContentTypeProcessor.
      if (count($args)) {
        $ext = pathinfo($args[0], PATHINFO_EXTENSION);
        if ($ext) {
          $args[0] = substr($args[0], 0, -(strlen($ext) + 1));
        }
        unset($ext);
      }

      $method = array($this, $this->resolveMethodName($args));
    }

    $ret = call_user_func_array($method, $args);

    return $ret;
  }

  //----------------------------------------------------------------------------
  //
  //  HTTP Methods
  //
  //----------------------------------------------------------------------------

  protected function get($identity = null) {
    if ( $identity === null ) {
      return $this->find();
    }
    else {
      return $this->findOne($identity);
    }
  }

  protected function put($identity = null) {
    return $this->post($identity);
  }

  protected function post($identity = null) {
    if ( $identity === null ) {
      $identity = $this->request()->meta('extends');
    }

    return $this->upsert($identity);
  }

  //----------------------------------------------------------------------------
  //
  //  CRUD Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Gets a list of data from target collection.
   */
  protected function find() {
    return $this->modelClass->find(
      $this->createFilter()
      );
  }

  /**
   * Gets a data object with specified entity id from target collection.
   */
  protected function findOne($identity) {
    $this->modelClass->load($identity);

    if ( blank($this->modelClass->data()) ) {
      $this->response()->status(404);
    }
    else {
      return $this->modelClass;
    }
  }

  /*! Note
   *  This should work just like find() and findOne(), that it passes the whole thing
   *  into Node::set() instead. Inherited classes can make modifications to
   *  parameter values before passing down.
   *
   *  One thing this differs from Node::set() is that it returns the whole entity
   *  with processed values, including the last insert id.
   */
  protected function upsert($identity = null) {
    if ( $identity === null ) {
      $identity = $this->request()->param(
        $this->modelClass->primaryKey()
      );
    }

    $data = (array) $this->request()->param();
    if ( $data || $this->request()->file() ) {
      // This will append the existing data to the submitted one.
      if ( $identity ) {
        $this->modelClass->load($identity);
      }

      $this->modelClass->prependData($data);

      // result
      $res = array();
      $this->modelClass->save($res);

      if ( !$res['success'] ) {
        if ( isset($res['errors']) ) {
          throw new ValidationException($res['errors'], sprintf('Validation failure in %s.', get_class($this->modelClass)), 80);
        }
        else if ( isset($res['error']) ) {
          throw new ServiceException($res['error'], @$res['code']);
        }
        else {
          throw new ServiceException('Error saving model.');
        }

        return;
      }

      switch ( @$res['action'] ) {
        case 'insert':
          $this->response()->status(201); // Created
          break;

        case 'update':
          break;

        default:
          return $res; // $res['errors']
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
    $model = $this->findOne($identity);

    if ( $model ) {
      $model->delete($isDeleted);

      return [
        'status' => $isDeleted ? 'success': 'failure'
      ];
    }
  }

  /*! Note
   *  In order to have consistant header returns, all other functions must abide
   *  or calls this method for appropriate headers.
   *
   *  This method should be able to independently serve headers for standard methods,
   *  1. find (collection)
   *  2. findOne (single data)
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
  protected function options() { }

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
        $schema = $this->modelClass->schema($type);
        if ( !$schema ) {
          $this->response()->status(404);
        }
        else {
          return $schema;
        }
        break;

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
        '@limits' => $this->listRange(),
        '@sorter' => $this->listOrder()
      ];

    return $filter + $this->request()->param();
  }

}
