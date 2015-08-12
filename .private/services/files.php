<?php
/*! files.php | A service to give HTTP interface to files in database. */

namespace services;

use core\Database;
use core\Node;
use core\Utility;

use framework\Session;
use framework\Service;

/**
 * This class act as a sample service, further demonstrates how to write RESTful functions.
 */
class files implements \framework\interfaces\IAuthorizableWebService {

  //--------------------------------------------------
  //
  //  Methods: IAuthorizableWebService
  //
  //--------------------------------------------------

  public function authorizeMethod($name, $args = array()) {
    // Two ways to delete a file:
    // 1. files/get/$userId/$fileId
    // 2. files/$userId/$fileId

    if ( strcasecmp($_SERVER['REQUEST_METHOD'], 'DELETE') === 0 ) {
      // Normalize method 2.
      if ( count($args) == 1 ) {
        array_unshift($args, $name);
      }

      // If local redirect, username must exists.
      if ( @Resolver::getActiveInstance()->request()->__local ) {
        return isset($args[0]);
      }

      // Otherwise, only allow logged in session to access themselves.
      else {
        // Session will be enforced in this service, don't need to do again.
        $user = Service::call('users', 'get', array(@$args[0]));

        return @$user['ID'] == Session::currentUser('ID');
      }
    }
  }

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  private static $fileDef = array(
      'id' => PDO::PARAM_INT
    , 'name' => PDO::PARAM_STR
    , 'mime' => PDO::PARAM_STR
    , '@contents' => PDO::PARAM_LOB
    , 'timestamp' => PDO::PARAM_STR
    );

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  function let($username = '~') {
    $fileObj = self::$fileDef;

    unset($fileObj['@contents']);

    $res = array_select($_GET, array_keys($fileObj));

    $res = self::getQuery($res, $fileObj, $username);

    $res->setFetchMode(PDO::FETCH_BOUND);

    $result = array();

    foreach ( $res as $row ) {
      $result[] = array_map(function($i){return $i;},$fileObj);
    }

    return $result;
  }

  /**
   * Get stats of target file.
   *
   * @param $fileId Either (int) file ID or (string) file name.
   * @param $username (int) user ID or (string) username.
   *
   * @returns (array) with following properties:
   *                  'id' => (int) The file ID.
   *                  'UserID' => (int) User ID of the owner.
   *                  'name' => (string) file name.
   *                  'timestamp' => (string) Date string of last modified time.
   */
  function stat($username, $fileId) {
    $fileObj = self::$fileDef;

    if ( is_numeric($fileId) ) {
      $res = array('id' => (int) $fileId);
    }
    else {
      $res = array('name' => $fileId);
    }

    $res = self::getQuery($res, $fileObj, $username);

    if ( !$res->fetch(PDO::FETCH_BOUND) ) {
      return NULL;
    }

    unset($fileObj['@contents']);

    return $fileObj;
  }

  /**
   * Download target file.
   *
   * @param $fileId Either (int) file ID or (string) file name.
   * @param $username (int) user ID or (string) username.
   *
   * @returns Binary data of the file.
   */
  function get($username, $fileId) {
    $fileObj = self::$fileDef;

    if ( is_numeric($fileId) ) {
      $res = array('id' => (int) $fileId);
    }
    else {
      $res = array('name' => "$fileId");
    }

    $res = self::getQuery($res, $fileObj, $username);

    if ( !$res->fetch(PDO::FETCH_BOUND) ) {
      throw new framework\exceptions\ResolverException(404);

      return null;
    }

    // File deletion
    if ( strcasecmp(@$_SERVER['REQUEST_METHOD'], 'DELETE') === 0 ) {
      return Node::delete(array(
          Node::FIELD_COLLECTION => FRAMEWORK_COLLECTION_FILE
        , 'id' => $fileObj['id']
        ));
    }

    if ( is_string($fileObj['@contents']) ) {
      $fileObj['@contents'] = fopen('data://text/plain;base64,'.base64_encode($fileObj['@contents']), 'r');
    }

    /* Quoted by Eric @ 3 Dec, 2012
        Unused mime types and constants.

    // Hold on for internally locked files
    switch ($fileObj['mime']) {
      case FRAMEWORK_MIME_INTERMEDIATE:
        if (is_resource($fileObj['@contents'])) {
          $fileObj['@contents'] = stream_get_contents($fileObj['@contents']);
        }

        return $fileObj['@contents'];

      case FRAMEWORK_MIME_LOCKED:
        throw new framework\exceptions\ResolverException(405);
        break;
    }
    */

    // HTTP cache headers
    $this->sendCacheHeaders($fileObj);

    if ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
      strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= strtotime($fileObj['timestamp']) ) {
      redirect(304);
    }

    $meta = stream_get_meta_data($fileObj['@contents']);

    if ( @$meta['unread_bytes'] ) {
      header('Content-Length: ' . $meta['unread_bytes'], TRUE);
    }

    header("Content-Type: $fileObj[mime]", TRUE);

    if ( isset($_GET['dl']) ) {
      header('Content-Disposition: attachment;filename="'.$fileObj['name'].'"', TRUE);
    }

    fpassthru($fileObj['@contents']);

    // Prevent default json_encode() from WebServiceResolver.
    die;
  }

  function set($username = '~') {
    if ( !$_FILES ) {
      throw new framework\exceptions\ServiceException('Please upload a file via POST.');
    }
    else {
      Utility::filesFix();
    }

    $user = Service::call('users', 'get', $username);

    $result = array();

    foreach ( $_FILES as $key => $file ) {
      $fileObj = array(
          'id' => @$_POST['id'][$key]
        , 'name' => $file['name']
        , 'mime' => $file['type']
        , '@contents' => fopen($file['tmp_name'], 'rb')
        );

      if ( $this->setQuery($fileObj, $user['ID']) ) {
        $result[$key] = sprintf('http://%s/services/files/%s/%s', FRAMEWORK_STATIC_HOSTNAME, $user['username'], $fileObj['name']);
      }
      else {
        $result[$key] = false;
      }
    }

    return $result;
  }

  //--------------------------------------------------
  //
  //  Overloading
  //
  //--------------------------------------------------

  /**
   * Mimic a real RESTful thing, users can simply omit
   * the "/get" method and retrieve the file.
   */
  function __call($name, $params) {
    // Dirty check, assume file/get if parameter count is two ($username, $fileId).
    if ( count($params) <= 2 ) {
      return $this->get($name, @$params[0]);
    }
    else {
      throw new framework\exceptions\ResolverException(404);
    }
  }

  //--------------------------------------------------
  //
  //  Private methods
  //
  //--------------------------------------------------

  /**
   * Send appropriate HTTP cache headers according to
   * request headers.
   *
   * Unlike the FileResolver, we store only static
   * files here so permanant headers only.
   */
  private static function
  /* void */ sendCacheHeaders($fileObj) {
    header_remove('Pragma');

    header('Cache-Control: max-age=' . FRAMEWORK_RESPONSE_CACHE_PERMANANT, TRUE);
    header('Expires: ' . gmdate(DATE_RFC1123, time() + FRAMEWORK_RESPONSE_CACHE_PERMANANT), TRUE);
    header('Last-Modified: ' . date(DATE_RFC1123, strtotime($fileObj['timestamp'])), TRUE);
  }

  private static function
  /* PDOStatement */ getQuery($filter, &$bindArray, $username = '~') {
    // Use ~ to specify current user
    $user = Service::call('users', 'get', array($username));

    $fields = Database::getFields(FRAMEWORK_COLLECTION_FILE);

    $query = 'SELECT * FROM `'.FRAMEWORK_COLLECTION_FILE.'` WHERE ';

    // id, UserID, name, mime
    $param = array(
      'UserID' => @$user['ID']
    ) + (array) $filter;

    $param = array_select($param, $fields);

    $query.= implode(' AND ', array_map(appends(' = ?'), array_keys($param)));

    $query = Database::query($query, array_values($param));

    foreach ($bindArray as $key => $value) {
      $query->bindColumn($key, $bindArray[$key], $value);
    }

    return $query;
  }

  private static function
  /* void */ setQuery($bindArray, $userId) {
    /* Note by Eric @ 8.Nov.2012
        As of PHP 5.4.8, PDO still has no solution on streaming PARMA_LOB
        when database driver has no native support for it.

        Now we read stream contents into memory and pass it down.
     */
    if ( is_resource(@$bindArray['@contents']) ) {
      $bindArray['@contents'] = stream_get_contents($bindArray['@contents']);
    }

    // Try to search for the exact same file.
    if ( !@$bindArray['id'] && @$bindArray['name'] ) {
      $bindArray['id'] = Database::fetchField('SELECT id FROM `'.FRAMEWORK_COLLECTION_FILE.'`
        WHERE UserID = ? AND name = ?', array($userId, $bindArray['name']));
    }

    $query = 'INSERT INTO `'.FRAMEWORK_COLLECTION_FILE.'` VALUES (:id, :UserID, :name, :mime, :contents, CURRENT_TIMESTAMP)
      ON DUPLICATE KEY UPDATE name = :name, mime = :mime, `@contents` = :contents, `timestamp` = CURRENT_TIMESTAMP';

    $query = Database::prepare($query);

    $query->bindParam(':id', $bindArray['id'], PDO::PARAM_INT, 20);
    $query->bindParam(':UserID', $userId, PDO::PARAM_INT, 20);
    $query->bindParam(':name', $bindArray['name'], PDO::PARAM_STR, 255);
    $query->bindParam(':mime', $bindArray['mime'], PDO::PARAM_STR, 255);
    $query->bindParam(':contents', $bindArray['@contents'], PDO::PARAM_LOB);

    $ret = $query->execute();

    if ( $ret ) {
      $query->closeCursor();

      if ( $query->rowCount() == 1 ) {
        return Database::lastInsertId();
      }
      else {
        return $ret;
      }
    }

    return $ret;
  }

}
