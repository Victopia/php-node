<?php
/* Database.php | https://github.com/victopia/php-node */

namespace core;

/**
 * Basic database helper functions.
 *
 * @author Vicary Arcahgnel <vicary@victopia.org>
 */
class Database {

  private static $con;

  private static $preparedStatments = array();

  private static /*DatabaseOptions*/ $options;

  //----------------------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------------------

  public static function
  /* void */ setOptions($options) {
    if (!($options instanceof DatabaseOptions)) {
      throw new \PDOException('Options must be an instance of DatabaseOptions class.');
    }

    self::$options = $options;

    self::$con = NULL;
    self::$preparedStatments = array();
  }

  public static function
  /* DatabaseOptions */ getOptions() {
    return self::$options;
  }

  //----------------------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------------------

  /**
   * @private
   */
  private static function
  /* PDO */ getConnection() {
    if ( self::$con === NULL ) {
      if ( self::$options === NULL ) {
        if (error_reporting()) {
          debug_print_backtrace();
          throw new \PDOException('Please specify connection options with setOptions() before accessing database.');
        }
        else {
          return NULL;
        }
      }

      if (!(self::$options instanceof DatabaseOptions)) {
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
      } catch(\PDOException $e) {
        throw new exceptions\CoreException('Unable to connect to database.', 0, $e);

        self::$con = NULL;
      }
    }

    return self::$con;
  }

  public static function
  /* bool */ isConnected() {
    return @self::getConnection() !== NULL;
  }

  /**
   * Escape a string to be used as table names and field names,
   * and should only be used to quote table names and field names.
   */
  public static function
  /* String */ escape($value, $tables = NULL) {
    // Escape with column determination.
    if ( $tables !== NULL ) {
      // Force array casting.
      $values = (array) $value;

      $sourceFields = self::getFields($tables);

      $quotedFields = array_map(function($field) {
        return '`' . str_replace('`', '``', $field) . '`';
      }, $sourceFields);

      $values = array_map(function($value) use($sourceFields, $quotedFields) {
        $fields = explode(' ', $value);

        foreach ($fields as &$field) {
          $index = array_search($field, $sourceFields);

          if ($index !== FALSE) {
            $field = $quotedFields[$index];
          }
        } unset($field);

        return implode(' ', $fields);
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
        return Database::escape($field);
      }, $value);
    }
    elseif (preg_match('/^[^\s\(\)\*]*$/', $value)) {
      return '`' . str_replace('`', '``', $value) . '`';
    }
    else {
      return $value;
    }
  }

  /**
   * Gets fields with specified key type.
   */
  public static function
  /* Array */ getFields($tableName, $key = NULL, $nameOnly = TRUE) {
    $tables = \utils::wrapAssoc($tableName);

    $tables = array_map(function($tableName) use($key, $nameOnly) {
      if (!Database::hasTable($tableName)) {
        throw new \PDOException("Table `$tableName` doesn't exists!");
      }

      $query = "SHOW COLUMNS FROM $tableName";

      if ($key !== NULL) {
        $key = \utils::wrapAssoc($key);

        $query.= ' WHERE `key` IN (' . \utils::fillArray($key) . ')';
      }

      $query = Database::query($query, $key);

      return (array) ($query ?
          ( $nameOnly ?
            $query->fetchAll(\PDO::FETCH_COLUMN, 0) :
            $query->fetchAll(\PDO::FETCH_ASSOC) )
          : NULL
          );
    }, $tables);

    $tables = array_reduce($tables, function($result, $input) {
      foreach ($input as &$field) {
        switch ($field) {
          case 'YES':
            $field = TRUE;
            break;

          case 'NO':
            $field = FALSE;
            break;
        }
      }

      return array_merge((array) $result, (array) $input);
    });

    if ($nameOnly) {
      $tables = array_unique($tables);
    }

    return $tables;
  }

  public static function
  /* PDOStatement */ prepare($query, $options = array()) {
    $stmt = &$preparedStatments[$query][json_encode($options)];

    if (!$stmt instanceof PDOStatement) {
      $con = self::getConnection();

      $stmt = $con->prepare($query, $options);
    }

    return $stmt;
  }

  /**
   * Return the PDOStatement result.
   */
  public static function
  /* PDOStatement */ query($query, $param = NULL, $options = array()) {
    $query = self::prepare($query);

    $res = $query->execute($param);

    if ($res) {
      return $query;
    }

    $errorInfo = $query->errorInfo();

    $ex = new \PDOException($errorInfo[2]);

    $ex->errorInfo = $errorInfo;

    throw $ex;

    return FALSE;
  }

  /**
   * Fetch the result set as a two-deminsional array.
   */
  public static function
  /* Array */ fetchArray( $query
                        , $param = NULL
                        , $fetch_type = \PDO::FETCH_ASSOC
                        , $fetch_argument = NULL ) {
    $res = self::query($query, $param);

    if ($res === FALSE) {
      return FALSE;
    }
    elseif ($fetch_argument === NULL) {
      return $res->fetchAll($fetch_type);
    }
    else {
      return $res->fetchAll($fetch_type, $fetch_argument);
    }
  }

  /**
   * Fetch the first row as an associative array or indexed array.
   */
  public static function
  /* Array */ fetchRow( $query
                      , $param = NULL
                      , $fetch_offset = 0
                      , $fetch_type = \PDO::FETCH_ASSOC ) {
    /* Note by Vicary @ 8.Nov.2012
       As of PHP 5.4.8, this shit is still not supported by SQLite and MySQL.

    $res = self::query($query, $param, array(
        \PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL
      ));

    $row = $res->fetch($fetch_type, \PDO::FETCH_ORI_ABS, $fetch_offset);
    */

    $res = self::query($query, $param);

    // fetch whatever I don't care.
    while ($fetch_offset-- > 0 && $res->fetch());

    $row = $res->fetch($fetch_type);

    $res->closeCursor();

    return $row;
  }

  /**
   * Fetch a single field from the first row.
   */
  public static function
  /* Mixed */ fetchField( $query
                        , $param = NULL
                        , $field_offset = 0
                        , $fetch_offset = 0 ) {
    $res = self::query($query, $param);

    // fetch whatever I don't care.
    while ($fetch_offset-- > 0 && $res->fetch());

    $field = $res->fetchColumn($field_offset);

    $res->closeCursor();

    return $field;
  }

  /**
   * Check whether specified table exists or not.
   *
   * @param $table String that carrries the name of target table.
   *
   * @returns TRUE on table exists, FALSE otherwise.
   */
  public static function
  /* Boolean */ hasTable($table) {
    $res = self::fetchArray('SHOW TABLES LIKE ?', array($table));

    return count($res) > 0;
  }

  /**
   * Begin transaction, lock table.
   */
  public static function
  /* Boolean */ beginTransaction() {
    $con = self::getConnection();

    return $con->beginTransaction();
  }

  /**
   * Commit changes.
   */
  public static function
  /* Boolean */ commit() {
    $con = self::getConnection();

    return $con->commit();
  }

  /**
   * Rollback changes.
   */
  public static function
  /* Boolean */ rollback() {
    $con = self::getConnection();

    return $con->rollBack();
  }

  /**
   * Getter function.
   *
   * @param $tables Array of the target table names, or string on single target.
   * @param $fields Array of field names or string, direct application into query when string is given.
   * @param $criteria String of WHERE and ORDER BY clause, as well as GROUP BY statments.
   * @param $param Array of parameters to be passed in to the prepared statement.
   */
  public static function
  /* Array */ select( $tables
                    , $fields = '*'
                    , $criteria = NULL
                    , $param = NULL
                    , $fetch_type = \PDO::FETCH_ASSOC
                    , $fetch_argument = NULL ) {
    // Escape fields
    $fields = self::escape($fields, $tables);

    if ( is_array($fields) ) {
      $fields = implode($fields, ', ');
    }

    // Escape tables
    $tables = self::escape( $tables );

    if ( is_array($tables) ) {
      $tables = implode($tables, ', ');
    }

    $res = "SELECT $fields FROM $tables" . ($criteria ? " $criteria" : '');

    return self::fetchArray($res, $param, $fetch_type, $fetch_argument);
  }

  /**
   * Upsert function.
   *
   * @param $table Target table name.
   * @param $fields Key-value pairs of field names and values.
   *
   * @returns True on update succeed, insertId on a row inserted, false on failure.
   */
  public static function
  /* Mixed */ upsert($table, $fields = array()) {
    $columns = self::getFields($table, 'PRI');

    $keys = array();

    // Setup keys.
    foreach ($fields as $field => $value) {
      if (in_array($field, $columns)) {
        $keys[$field] = $value;

        unset($fields[$field]);
      }
    }

    $values = array_merge(array_values($keys), array_values($fields), array_values($fields));

    $table = self::escape( $table );
    $keys = self::escape( array_merge(array_keys($keys), array_keys($fields)) );

    // Setup fields.
    foreach ($fields as $field => $value) {
      $fields["`$field` = ?"] = $value;

      unset($fields[$field]);
    }

    $res = 'INSERT INTO ' . $table . ' (' . implode(', ', $keys) . ') VALUES (' .
        implode(', ', array_fill(0, count($keys), '?')) . ') ON DUPLICATE KEY UPDATE ';

    /* Performs upsert here. */
    if (is_array($fields) && count($fields) > 0) {
      $res.= implode(', ', array_keys($fields));
    }
    else {
      foreach ($keys as $key => $column) {
        $keys[$key] = "$column = $column";
      }

      $res.= implode($keys, ', ');
    }

    $res = self::query($res, $values);

    if ( $res !== FALSE ) {
      $res->closeCursor();

      // Inserted, return the new ID.
      if ( $res->rowCount() == 1 ) {
        // Note: mysql_insert_id() doesn't do UNSIGNED ZEROFILL!
        $res = (int) self::getConnection()->lastInsertId();
        //$res = self::fetchField("SELECT MAX(ID) FROM `$table`;");
      }
      // Updated, return TRUE.
      // rowCount() should be 2 here as long as it stays in MySQL driver.
      else {
        $res = TRUE;
      }
    }

    return $res;
  }

  /**
   * Delete function.
   *
   * @param $table Target table name.
   * @param $keys Array of keys to be deleted.
   *       This can be multiple keys, use a two-dimensional array in such case.
   *
   * @returns The total number of affected rows.
   */
  public static function
  /* void */ delete($table, $keys) {
    $columns = self::getFields($table, array('PRI', 'UNI'));

    $table = self::escape($table);

    foreach ($columns as &$column) {
      if (array_key_exists($column, $keys)) {
        $column = self::escape($column) . ' = ?';
      }
      else {
        $column = NULL;
      }
    }

    $columns = array_filter($columns);

    if (!is_array($keys) || (count($keys) > 0 && \utils::isAssoc($keys))) {
      $keys = array($keys);
    }

    $res = "DELETE FROM $table WHERE " . implode(' AND ', $columns);

    $affectedRows = 0;

    foreach ($keys as $key) {
      if (!is_array($key)) {
        $key = array($key);
      }
      else {
        $key = array_values($key);
      }

      $res_1 = self::query($res, $key);

      if ( $res_1 === FALSE ) {
        return FALSE;
      }

      $affectedRows += $res_1->rowCount();
    }

    return $affectedRows;
  }

  public static function
  /* int */ lastInsertId() {
    return self::getConnection()->lastInsertId();
  }

  public static function
  /* Array */ lastError() {
    return self::getConnection()->errorInfo();
  }

  public static function
  /* void */ lockTables($tables) {
    $tables = (array) $tables;

    foreach ($tables as &$table) {
      if (!preg_match('/(?:READ|WRITE)\s*$/', $table)) {
        $table.= ' WRITE';
      }
    }

    self::query('LOCK TABLES ' . implode(', ', $tables));
  }

  public static function
  /* void */ unlockTables() {
    self::query('UNLOCK TABLES');
  }
}