<?php
/* Database.php | https://github.com/victopia/php-node */

namespace core;

/**
 * Basic database helper functions.
 *
 * @author Vicary Archangel <vicary@victopia.org>
 */
final class Database {

  private static $con;

  private static $preparedStatments = array();

  private static $schemaCache = array();

  private static /*DatabaseOptions*/ $options;

  /**
   * @private
   *
   * Store values specific to transactions.
   */
  private static $transactionStore = array();

  /**
   * Prevent instantiation
   */
  private function __constrict() {}

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  public static function setOptions($options) {
    if ( !($options instanceof DatabaseOptions) ) {
      throw new \PDOException('Options must be an instance of DatabaseOptions class.');
    }

    self::$options = $options;

    self::$con = null;
    self::$preparedStatments = array();
  }

  public static /* DatabaseOptions */
  function getOptions() {
    return self::$options;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * @private
   */
  protected static /* PDO */
  function getConnection() {
    if ( self::$con === null ) {
      if ( self::$options === null ) {
        if ( error_reporting() ) {
          throw new \PDOException('Please specify connection options before connecting database.');
        }
        else {
          return null;
        }
      }

      if ( !(self::$options instanceof DatabaseOptions) ) {
        throw new \PDOException('Options must be an instance of DatabaseOptions class.');
      }

      $connectionString = self::$options->driver
        . ':host=' . self::$options->host
        . ';port=' . self::$options->port
        . ';dbname=' . self::$options->schema;

      try {
        self::$con = new \PDO($connectionString
          , self::$options->username
          , self::$options->password
          , self::$options->driverOptions
          );
      }
      catch(\PDOException $e) {
        if ( error_reporting() ) {
          throw new \PDOException("Unable to connect to database, error: " . $e->getMessage(), 0);
        }

        self::$con = null;
      }
    }

    return self::$con;
  }

  /**
   * Explicitly releases the current database connection.
   *
   * If there is no active connection, nothing will happen.
   */
  public static /* null */
  function disconnect() {
    if ( static::isConnected() ) {
      self::$con = null;
    }
  }

  public static /* Boolean */
  function isConnected() {
    return @static::getConnection() !== null && static::ping();
  }

  public static /* Boolean */
  function ping() {
    try {
      $res = static::fetchField('SELECT 1');
    }
    catch (\PDOException $e) {
      return false;
    }

    return @$res == 1;
  }

  /**
   * Attempt to create a new database connection,
   * if a current connection exists, abandon it.
   *
   * @param {DatabaseOptions} Optionally provide new connection
   *                          options when reconnecting.
   */
  public static /* Boolean */
  function reconnect($dbOptions = null) {
    self::$con = null;

    // Temporarily apply new options
    if ( $dbOptions && $dbOptions instanceof DatabaseOptions ) {
      list($dbOptions, self::$options) = array(self::$options, $dbOptions);
    }

    $con = static::getConnection();

    // Swap them back afterwards
    if ( $dbOptions && $dbOptions instanceof DatabaseOptions ) {
      list($dbOptions, self::$options) = array(self::$options, $dbOptions);
    }

    return $con !== null;
  }

  /**
   * Escape a string to be used as table names and field names,
   * and should only be used to quote table names and field names.
   */
  public static /* String */
  function escapeField($value, $tables = null) {
    // Escape with column determination.
    if ( $tables !== null ) {
      // Force array casting.
      $values = (array) $value;

      $sourceFields = static::getFields($tables);

      $quotedFields = array_map(function($field) {
        return '`' . str_replace('`', '``', $field) . '`';
      }, $sourceFields);

      $values = array_map(function($value) use($sourceFields, $quotedFields) {
        $index = array_search($value, $sourceFields);

        if ($index !== false) {
          return $quotedFields[$index];
        }
        else {
          return $value;
        }
      }, $values);

      // Restore to scalar if it was not an array.
      if ( !is_array($value) ) {
        $values = reset($values);
      }

      return $values;
    }

    // Direct value escape, but only on those without space and parentheses.
    if ( is_array($value) ) {
      return array_map(function($field) {
        return static::escapeField($field);
      }, $value);
    }
    elseif ( preg_match('/^[^\s\(\)\*]*$/', $value) ) {
      return '`' . str_replace('`', '``', $value) . '`';
    }
    else {
      return $value;
    }
  }

  /**
   * Escape wildcard characters for exact string matching.
   *
   * @param {string} $value Input string containing wildcard characters.
   * @return {string} String with all wildcard characters escaped.
   */
  public static function escapeValue($value) {
    return preg_replace('/([\\@*_])/', '\\\$1', $value);
  }

  /**
   * Check whether specified table exists or not.
   *
   * @param $table String that carrries the name of target table.
   *
   * @returns true on table exists, false otherwise.
   */
  public static /* Boolean */
  function hasTable($table, $clearCache = false) {
    $cache = &self::$schemaCache;

    if ( $clearCache || (isset($cache['timestamp']) && $cache['timestamp'] < strtotime('-30min')) ) {
      unset($cache['collections']);

      $cache['timestamp'] = microtime(1);
    }

    if ( !isset($cache['collections']) ) {
      $tables = (array) static::fetchArray('SHOW TABLES', null, \PDO::FETCH_COLUMN);

      $cache['collections'] = array_fill_keys($tables, array());
    }

    $tables = (array) @$cache['collections'];

    return array_key_exists($table, $tables);
  }

  /**
   * Gets fields with specified key type.
   */
  public static /* Array */
  function getFields($tables, $key = null, $nameOnly = true) {
    $tables = Utility::wrapAssoc($tables);

    $cache = &self::$schemaCache;

    // Clear the cache on expire
    if ( @$cache['timestamp'] < strtotime('-30min') ) {
      unset($cache['collections']);

      $cache['timestamp'] = microtime(1);
    }

    array_walk($tables, function($tableName) use(&$cache) {
      if ( !Database::hasTable($tableName) ) {
        throw new \PDOException("Table $tableName doesn't exists!");
      }

      if ( @$cache['collections'][$tableName] ) {
        return;
      }

      $res = Database::fetchArray('SHOW COLUMNS FROM ' . static::escapeField($tableName));

      $res = array_combine(
          array_map(prop('Field'), $res)
        , array_map(removes('Field'), $res)
        );

      $res = array_map(function($info) {
        $info['Key'] = preg_split('/\s*,\s*/', $info['Key']);

        foreach ($info as &$value) {
          switch ($value) {
            case 'YES':
              $value = true;
              break;

            case 'NO':
              $value = false;
              break;
          }
        }

        return $info;
      }, $res);

      $cache['collections'][$tableName] = $res;
    });

    $tables = array_map(function($tableName) use($cache, $key, $nameOnly) {
      $cache = $cache['collections'][$tableName];

      if ( $key !== null ) {
        $key = Utility::wrapAssoc($key);

        $cache = array_filter($cache, propHas('Key', $key));
      }

      return $cache;
    }, $tables);

    $tables = array_reduce($tables, function($result, $fields) {
      return array_merge($result, (array) $fields);
    }, array());

    if ( $nameOnly ) {
      $tables = array_unique(array_keys($tables));
    }

    return $tables;
  }

  public static /* PDOStatement */
  function prepare($query, $options = array()) {
    $stmt = &$preparedStatments[$query][serialize($options)];

    if (!$stmt instanceof PDOStatement) {
      $con = static::getConnection();

      $stmt = $con->prepare($query, $options);
    }

    return $stmt;
  }

  /**
   * Return the PDOStatement result.
   */
  public static /* PDOStatement */
  function query($query, $param = null, $options = array()) {
    $stmt = static::prepare($query, $options);

    $param = (array) $param;

    array_walk($param, function($param, $index) use(&$stmt) {
      $parmType = \PDO::PARAM_STR;

      if ( is_int($param) ) {
        $parmType = \PDO::PARAM_INT;
      }

      $stmt->bindValue($index + 1, $param, $parmType);
    });

    if ( $stmt->execute() ) {
      return $stmt;
    }

    $errorInfo = $stmt->errorInfo();

    $ex = new \PDOException($errorInfo[2], $errorInfo[1]);

    $ex->errorInfo = $errorInfo;

    throw $ex;

    return false;
  }

  /**
   * Fetch the result set as a two-deminsional array.
   */
  public static /* Array */
  function fetchArray( $query
                     , $param = null
                     , $fetch_type = \PDO::FETCH_ASSOC
                     , $fetch_argument = null ) {
    $res = static::query($query, $param);

    if ( $res === false ) {
      return false;
    }
    elseif ( $fetch_argument === null ) {
      return $res->fetchAll($fetch_type);
    }
    else {
      return $res->fetchAll($fetch_type, $fetch_argument);
    }
  }

  /**
   * Fetch the first row as an associative array or indexed array.
   */
  public static /* Array */
  function fetchRow( $query
                   , $param = null
                   , $fetch_offset = 0
                   , $fetch_type = \PDO::FETCH_ASSOC ) {
    /*! Note by Vicary @ 8.Nov.2012
     *  As of PHP 5.4.8, this shit is still not supported by SQLite and MySQL.
     *
     * $res = static::query($query, $param, array(
     *   \PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL
     * ));
     *
     * $row = $res->fetch($fetch_type, \PDO::FETCH_ORI_ABS, $fetch_offset);
     */
    $res = static::query($query, $param);

    // fetch whatever I don't care.
    while ($fetch_offset-- > 0 && $res->fetch());

    $row = $res->fetch($fetch_type);

    $res->closeCursor();

    return $row;
  }

  /**
   * Fetch a single field from the first row.
   */
  public static /* Mixed */
  function fetchField( $query
                     , $param = null
                     , $field_offset = 0
                     , $fetch_offset = 0 ) {
    $res = static::query($query, $param);

    // fetch whatever I don't care.
    while ( $fetch_offset-- > 0 && $res->fetch() );

    $field = $res->fetchColumn($field_offset);

    $res->closeCursor();

    return $field;
  }

  /**
   * Truncate target table.
   */
  public static /* Mixed */
  function truncateTable($tableName) {
    $tableName = static::escapeField($tableName);

    return (bool) static::query("TRUNCATE TABLE $tableName");
  }

  /**
   * Begin transaction, lock table.
   */
  public static /* Boolean */
  function beginTransaction() {
    $res = static::fetchRow('SHOW VARIABLES LIKE ?', ['autocommit']);

    self::$transactionStore['autocommit'] = $res['Value'];

    // Must switch autocommit to off in transactions.
    static::query('SET autocommit = ?', 0);

    return static::getConnection()->beginTransaction();
  }

  /**
   * Returns of current connection is in the middle of a transaction.
   */
  public static /* boolean */ function inTransaction() {
    return static::getConnection()->inTransaction();
  }

  /**
   * Commit changes.
   */
  public static /* Boolean */
  function commit() {
    // Restore whatever value it was when transaction ends.
    if ( isset(self::$transactionStore['autocommit']) ) {
      static::query('SET autocommit = ?', self::$transactionStore['autocommit']);
    }

    self::$transactionStore = [];

    return static::getConnection()->commit();
  }

  /**
   * Rollback changes.
   */
  public static /* Boolean */
  function rollback() {
    // Restore whatever value it was when transaction ends.
    if ( isset(self::$transactionStore['autocommit']) ) {
      static::query('SET autocommit = ?', self::$transactionStore['autocommit']);
    }

    self::$transactionStore = [];

    return static::getConnection()->rollBack();
  }

  /**
   * Getter function.
   *
   * @param $tables Array of the target table names, or string on single target.
   * @param $fields Array of field names or string, direct application into query when string is given.
   * @param $criteria String of WHERE and ORDER BY clause, as well as GROUP BY statments.
   * @param $param Array of parameters to be passed in to the prepared statement.
   */
  public static /* Array */
  function select( $tables
                 , $fields = '*'
                 , $criteria = null
                 , $param = null
                 , $fetch_type = \PDO::FETCH_ASSOC
                 , $fetch_argument = null ) {
    // Escape fields
    $fields = static::escapeField($fields, $tables);

    if ( is_array($fields) ) {
      $fields = implode($fields, ', ');
    }

    // Escape tables
    $tables = static::escapeField( $tables );

    if ( is_array($tables) ) {
      $tables = implode($tables, ', ');
    }

    $res = "SELECT $fields FROM $tables" . ($criteria ? " $criteria" : '');

    return static::fetchArray($res, $param, $fetch_type, $fetch_argument);
  }

  /**
   * Upsert function.
   *
   * @param $table Target table name.
   * @param $data Key-value pairs of field names and values.
   *
   * @returns True on update succeed, insertId on a row inserted, false on failure.
   */
  public static /* Mixed */
  function upsert($table, array $data, $update = null) {
    $fields = static::escapeField(array_keys($data), $table);

    $values = array_values($data);

    $query = sprintf('INSERT INTO %s (%s) VALUES (%s)',
      static::escapeField($table),
      implode(', ', $fields),
      implode(', ', array_fill(0, count($fields), '?'))
      );

    // append "ON DUPLICATE KEY UPDATE ..."
    $keys = static::getFields($table, 'PRI');

    $fields = array_intersect($fields, static::escapeField($keys, $table));

    if ( $fields ) {
      // selective update
      if ( $update !== null ) {
        $data = array_select($data, (array) $update);
      }

      foreach ( $data as $field => $value ) {
        $data["`$field` = ?"] = $value;

        unset($data[$field]);
      }

      // full dataset appended with non-key fields
      $values = array_merge($values,
        array_values(array_filter_keys($data, notIn($keys))));

      $query.= ' ON DUPLICATE KEY UPDATE ';

      if ( $data ) {
        $query.= implode(', ', array_keys($data));
      }
      else {
      // note: key1 = key1; We do not use INSERT IGNORE because it'll ignore other errors.
        $value = reset($fields);
        $query.= "$value = $value";
        unset($value);
      }
    }

    unset($keys, $fields);

    $res = static::query($query, $values);

    unset($query, $values);

    if ( $res !== false ) {
      $res->closeCursor();

      // Inserted, return the new ID.
      if ( $res->rowCount() == 1 ) {
        // Note: mysql_insert_id() doesn't do UNSIGNED ZEROFILL!
        $res = (int) static::getConnection()->lastInsertId();
        //$res = static::fetchField("SELECT MAX(ID) FROM `$table`;");
      }
      // Updated, return true.
      // rowCount() should be 2 here as long as it stays in MySQL driver.
      else {
        $res = true;
      }
    }

    return $res;
  }

  /**
   * Delete function.
   *
   * @param $table Target table name.
   * @param $filters Array of keys to be deleted.
   *                 This can be multiple keys, use a two-dimensional array in such cases.
   *                 Note that only real columns that is PRIMARY KEY or UNIQUE KEY is
   *                 applied to the statement, the rest will be discarded.
   *
   * @return The total number of affected rows.
   */
  public static /* null */
  function delete($table, $keySets) {
    $columns = static::getFields($table, array('PRI', 'UNI'));

    $keySets = Utility::wrapAssoc($keySets);

    // Build queries from key sets.
    array_walk($keySets, function(&$keySet) use($table, $columns) {
      if ( !Utility::isAssoc($keySet) ) {
        throw new \PDOException('Key set for deletion must be associative array(s).');
      }

      $filter = array();

      foreach ( $keySet as $key => $value ) {
        if ( in_array($key, $columns) ) {
          $key = static::escapeField($key, $table);

          // Apply appropriate equality operators: =, IS
          if ( is_null($value) ) {
            $key.= ' IS ?';
          }
          else {
            $key.= ' = ?';
          }

          $filter[$key] = $value;
        }
      }

      if ( $filter ) {
        $keySet = $filter;
      }
      else {
        $keySet = null;
      }
    });

    $table = static::escapeField($table);

    // Execute queries and accumulate affected rows.
    return array_reduce($keySets, function($result, $keySet) use($table) {
      if ( $keySet ) {
        $res = sprintf('DELETE FROM %s WHERE ', $table) . implode(' AND ', array_keys($keySet));

        $res = static::query($res, array_values($keySet));

        if ( $res !== false ) {
          $result+= $res->rowCount();
        }
      }

      return $result;
    }, 0);
  }

  public static /* int */
  function lastInsertId() {
    return static::getConnection()->lastInsertId();
  }

  public static /* Array */
  function lastError() {
    return static::getConnection()->errorInfo();
  }

  public static function lockTables($tables) {
    if ( !is_array($tables) ) {
      $tables = func_get_args();
    }

    foreach ($tables as &$table) {
      if (!preg_match('/(?:READ|WRITE)\s*$/', $table)) {
        $table.= ' WRITE';
      }
    }

    return (bool) static::query('LOCK TABLES ' . implode(', ', $tables));
  }

  /**
   * Unlock tables in database, optionally with a retrying mechanism on failure.
   *
   * @param {int}   $autoRetry Same as $autoRetry['count'].
   * @param {int}   $autoRetry['count'] The number of retries before giving up.
   * @param {float} $autoRetry['interval'] Seconds to wait before the next retry.
   */
  public static function unlockTables($autoRetry = false) {
    if ( $autoRetry ) {
      if ( !is_array($autoRetry) ) {
        $autoRetry = array(
            'count' => (int) $autoRetry
          );
      }

      $autoRetry+= array(
        'interval' => .4
        );

      $ret = false;

      $i = 0;

      while ( false === ( $ret = static::query('UNLOCK TABLES') && $i++ < $autoRetry['count'] ) ) {
        usleep(@$autoRetry['interval'] * 1000000);
      }

      return $ret;
    }
    else {
      return (bool) static::query('UNLOCK TABLES');
    }
  }
}
