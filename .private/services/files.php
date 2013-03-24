<?php
/*! files.php | A service to give HTTP interface to files in database. */

/* Note by Vicary @ 24 Mar, 2013
   This class also act as a sample service, further demonstrates how to
   write RESTful functions.
*/

class files implements framework\interfaces\IAuthorizableWebService {

  public function authorizeMethod($name, $args = NULL) {
    // Only allow deletion from file owners.
    // Two ways:
    // 1. files/get/$userId/$fileId
    // 2. files/$userId/$fileId
    if (@$_SERVER['REQUEST_METHOD'] === 'DELETE') {
      // The second method, normalize it.
      if (count($args) == 1) {
        array_unshift($args, $name);
      }

      $user = service::call('users', 'get', array($args[0]));

      if (!@$user['ID'] || @$user['ID'] !== session::currentUser('ID')) {
        return FALSE;
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

  function let($userId = '~') {
    $fileObj = self::$fileDef;

    unset($fileObj['@contents']);

    $res = array_select($_GET, array_keys($fileObj));

    $res = self::getQuery($res, $fileObj, $userId);

    $res->setFetchMode(PDO::FETCH_BOUND);

    $result = array();

    foreach ($res as $row) {
      $result[] = array_map(function($i){return $i;},$fileObj);
    }

    return $result;
  }

  /**
   * Get stats of target file.
   *
   * @param $userId (int) user ID or (string) username, '~' indicates current user.
   * @param $fileId Either (int) file ID or (string) file name.
   *
   * @returns (array) with following properties:
   *                  'id' => (int) The file ID.
   *                  'UserID' => (int) User ID of the owner.
   *                  'name' => (string) file name.
   *                  'timestamp' => (string) Date string of last modified time.
   */
  function stat($userId, $fileId) {
    $fileObj = self::$fileDef;

    if (is_numeric($fileId)) {
      $res = array('id' => (int) $fileId);
    }
    else {
      $res = array('name' => $fileId);
    }

    $res = self::getQuery($res, $fileObj, $userId);

    if (!$res->fetch(PDO::FETCH_BOUND)) {
      return NULL;
    }

    unset($fileObj['@contents']);

    return $fileObj;
  }

  /**
   * Download or deletes target file, according to the HTTP request method.
   *
   * Use GET to download, DELETE to delete.
   *
   * @param $userId (int) user ID or (string) username, '~' indicated current user.
   * @param $fileId Either (int) file ID or (string) file name.
   *
   * @returns Binary data of the file.
   */
  function get($userId, $fileId) {
    $fileObj = self::$fileDef;

    if (is_numeric($fileId)) {
      $res = array('id' => (int) $fileId);
    }
    else {
      $res = array('name' => "$fileId");
    }

    $res = self::getQuery($res, $fileObj, $userId);

    if (!$res->fetch(PDO::FETCH_BOUND)) {
      return NULL;
    }

    // File deletion
    if (@$_SERVER['REQUEST_METHOD'] == 'DELETE') {
      return node::delete($fileObj);
    }

    if (is_string($fileObj['@contents'])) {
      $fileObj['@contents'] = fopen('data://text/plain;base64,'.base64_encode($fileObj['@contents']), 'r');
    }

    // HTTP cache headers
    $this->sendCacheHeaders($fileObj);

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
      strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= strtotime($fileObj['timestamp'])) {
      redirect(304);
    }

    $meta = stream_get_meta_data($fileObj['@contents']);

    if (@$meta['unread_bytes']) {
      header('Content-Length: ' . $meta['unread_bytes'], TRUE);
    }

    header("Content-Type: $fileObj[mime]", TRUE);

    if (isset($_GET['dl'])) {
      header('Content-Disposition: attachment;filename="'.$fileObj['name'].'"', TRUE);
    }

    fpassthru($fileObj['@contents']);

    // Prevent default json_encode() from WebServiceResolver.
    die;
  }

  /**
   * Updates a file according to $_FILES.
   *
   * @param $userId (int) User ID or (string) username.
   */
  function set($userId = '~') {
    if (!$_FILES) {
      throw new framework\exceptions\ServiceException('Please upload a file via POST.');
    }
    else {
      \utils::filesFix();
    }

    $result = array();

    foreach ($_FILES as $key => $file) {
      $fileObj = array(
          'id' => @$_POST['id'][$key]
        , 'name' => $file['name']
        , 'mime' => $file['type']
        , '@contents' => fopen($file['tmp_name'], 'rb')
        );

      $result[$key] = $this->setQuery($fileObj, $userId);
    }

    return $result;
  }

  function delete($userId, $fileId) {
    $filter = array(
        NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_FILE
      );

    if (is_numeric($fileId)) {
      $filter['ID'] = $fileId;
    }
    else {
      $filter['name'] = $fileId;
    }

    return node::delete($filter);
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
    // Dirty check, assume file/get if parameter count is one.
    if (count($params) == 1) {
      return $this->get($name, $params[0]);
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
  /* PDOStatement */ getQuery($filter, &$bindArray, $userId = '~') {
    // Use ~ to specify current user
    $user = service::call('users', 'get', array($userId));

    $fields = core\Database::getFields(FRAMEWORK_COLLECTION_FILE);

    $query = 'SELECT * FROM `'.FRAMEWORK_COLLECTION_FILE.'` WHERE ';

    // id, UserID, name, mime
    $param = array(
      'UserID' => @$user['ID']
    ) + (array) $filter;

    $param = array_select($param, $fields);

    $query.= implode(' AND ', array_map(appends(' = ?'), array_keys($param)));

    $query = core\Database::query($query, array_values($param));

    foreach ($bindArray as $key => $value) {
      $query->bindColumn($key, $bindArray[$key], $value);
    }

    return $query;
  }

  private static function
  /* void */ setQuery($bindArray, $userId = '~') {
    /* Note by Vicary @ 8.Nov.2012
        As of PHP 5.4.8, PDO still has no solution on streaming PARMA_LOB
        when database driver has no native support for it.

        Now we read stream contents into memory and pass it down.
     */
    if (is_resource(@$bindArray['@contents'])) {
      $bindArray['@contents'] = stream_get_contents($bindArray['@contents']);
    }

    $user = service::call('users', 'get', array($userId));

    // Try to search for the exact same file.
    if (!@$bindArray['id'] && @$bindArray['name']) {
      $bindArray['id'] = core\Database::fetchField('SELECT id FROM `'.FRAMEWORK_COLLECTION_FILE.'`
        WHERE UserID = ? AND name = ?', array($user['ID'], $bindArray['name']));
    }

    $query = 'INSERT INTO `'.FRAMEWORK_COLLECTION_FILE.'` VALUES (:id, :UserID, :name, :mime, :contents, CURRENT_TIMESTAMP)
      ON DUPLICATE KEY UPDATE name = :name, mime = :mime, `@contents` = :contents, `timestamp` = CURRENT_TIMESTAMP';

    $query = core\Database::prepare($query);

    $query->bindParam(':id', $bindArray['id'], PDO::PARAM_INT, 20);
    $query->bindParam(':UserID', $user['ID'], PDO::PARAM_INT, 20);
    $query->bindParam(':name', $bindArray['name'], PDO::PARAM_STR, 255);
    $query->bindParam(':mime', $bindArray['mime'], PDO::PARAM_STR, 255);
    $query->bindParam(':contents', $bindArray['@contents'], PDO::PARAM_LOB);

    $ret = $query->execute();

    if ($ret) {
      $query->closeCursor();

      if ($query->rowCount() == 1) {
        return core\Database::lastInsertId();
      }
      else {
        return $ret;
      }
    }

    return $ret;
  }

}