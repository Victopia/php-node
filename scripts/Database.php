<?php

/**
 * Basic database helper functions.
 */
class Database
{
	private static $con;
	
	private static $preparedStatments = Array();
	
	private static /*DatabaseOptions*/ $options;
	
	//----------------------------------------------------------------------------------------
	//
	//  Properties
	//
	//----------------------------------------------------------------------------------------
	
	public static function setOptions($options)
	{
		if (!($options instanceof DatabaseOptions)) {
			throw new PDOException('Options must be an instance of DatabaseOptions class.');
		}
		
		self::$options = $options;
	}
	
	public static function getOptions()
	{
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
	private static function getConnection()
	{
		if ( self::$con === NULL ) {
			if ( self::$options === NULL ) {
				throw new PDOException('Please specify connection options with setOptions() before accessing database.');
			}
			
			if (!(self::$options instanceof DatabaseOptions)) {
				throw new PDOException('Options must be an instance of DatabaseOptions class.');
			}
			
			$connectionString = 
				self::$options->driver . 
				':host=' . self::$options->host . 
				';port=' . self::$options->port . 
				';dbname=' . self::$options->schema;
			
			self::$con = new PDO($connectionString, 
				self::$options->username, 
				self::$options->password, 
				self::$options->driverOptions);
		}
		
		return self::$con;
	}
	
	/**
	 * Escape a string to be used as table names and field names, 
	 * and should only be used to quote table names and field names.
	 */
	public static function escape($value, $tables = NULL)
	{
		$con = self::getConnection();
		
		// Escape with column determination.
		if ( $tables !== NULL ) {
			// Force array casting.
			$values = (array)$value;
			
			$fields = self::getFields($tables);
			
			foreach ($values as &$val) {
				if ( In_Array($val, $fields) ) {
					$val = self::escape($val);
				}
			} unset($val);
			
			// Restore to scalar if it was not an array.
			if ( !Is_Array($value) ) {
				$values = $values[0];
			}
			
			return $values;
		}
		
		// Direct value escape.
		if ( Is_Array($value) ) {
			foreach ( $value as $key => &$item ) {
				$item = self::escape( $item );
			}
			
			return $value;
		}
		else {
			return '`' . str_replace('`', '``', $value) . '`';
		}
	}
	
	/**
	 * Gets fields with primary or unique key.
	 */
	private static function getFields($tables, $key = NULL)
	{
		$tables = (array)$tables;
		
		$fields = Array();
		
		foreach ($tables as $table) {
			if ( self::hasTable($table) ) {
				$table = self::escape($table);
				
				$columns = "SHOW COLUMNS FROM $table";
				
				if ( $key !== NULL ) {
					$key = (array)$key;
					$columns.= " WHERE `key` IN (" . Implode(', ', Array_Fill(0, Count($key), '?')) . ');';
				}
				
				$columns = self::query($columns, $key);
				
				$fields = Array_Merge($columns->fetchAll(PDO::FETCH_COLUMN, 0));
			}
			else {
				throw new PDOException("Table `$table` doesn't exists!");
				
				return NULL;
			}
		}
		
		return $fields;
	}
	
	/**
	 * Return the PDOStatement result.
	 */
	public static function query($query, $params = NULL)
	{
		if ( !isset($preparedStatments[$query]) ) {
			$con = self::getConnection();
			
			$preparedStatments[$query] = $con->prepare( $query );
		}
		
		$res = $preparedStatments[$query]->execute( $params );
		
		if ( $res == FALSE ) {
			$errorInfo = $preparedStatments[$query]->errorInfo();
			
			throw new PDOException($errorInfo[2]);
			
			return FALSE;
		}
		else {
			return $preparedStatments[$query];
		}
	}
	
	/**
	 * Fetch the result set as a two-deminsional array.
	 */
	public static function fetchArray($query, 
									  $params = NULL, 
									  $fetch_type = PDO::FETCH_ASSOC,
									  $fetch_argument = NULL)
	{
		$res = self::query($query, $params);
		
		if ( $res === FALSE ) {
			return FALSE;
		}
		else if ( $fetch_argument === NULL ) {
			return $res->fetchAll( $fetch_type );
		}
		else {
			return $res->fetchAll($fetch_type, $fetch_argument);
		}
	}
	
	/**
	 * Fetch the first row as an associative array or indexed array.
	 */
	public static function fetchRow($query, 
									$params = NULL, 
									$fetch_type = PDO::FETCH_ASSOC,
									$fetch_argument = NULL)
	{
		$res = self::query( $query, $params );
		
		if ( $fetch_argument === NULL ) {
			$row = $res->fetch( $fetch_type );
		}
		else {
			$row = $res-fetch($fetch_type, $fetch_argument);
		}
		
		$res->closeCursor();
		
		return $row;
	}
	
	/**
	 * Fetch a single field from the first row.
	 */
	public static function fetchField($query, 
									  $params = NULL, 
									  $field_offset = 0)
	{
		$res = self::query($query, $params);
		
		$field = $res->fetchColumn( $field_offset );
		
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
	public static function hasTable($table)
	{
		$res = self::fetchArray('SHOW TABLES LIKE ?', Array($table));
		
		return Count($res) > 0;
	}
	
	/**
	 * Getter function.
	 * 
	 * @param $tables Array of the target table names, or string on single target.
	 * @param $fields Array of field names or string, direct application into query when string is given.
	 * @param $criteria String of WHERE and ORDER BY clause, as well as GROUP BY statments.
	 * @param $params Array of parameters to be passed in to the prepared statement.
	 */
	public static function select($tables, $fields = '*', $criteria = NULL, $params = NULL,
								  $fetch_type = PDO::FETCH_ASSOC, $fetch_argument = NULL)
	{
		// Get fields
		$fields = self::escape($fields, $tables);
		
		if ( Is_Array($fields) ) {
			$fields = Implode($fields, ', ');
		}
		
		// Get tables
		$tables = self::escape( $tables );
		
		if ( Is_Array($tables) ) {
			$tables = Implode($tables, ', ');
		}
		
		$res = "SELECT $fields FROM $tables" . ($criteria ? " $criteria" : '');
		
		return self::fetchArray($res, $params, $fetch_type, $fetch_argument);
	}
	
	/**
	 * Upsert function.
	 * 
	 * @param $table Target table name.
	 * @param $fields Key-value pairs of field names and values.
	 * 
	 * @returns True on update succeed, insertId on a row inserted, false on failure.
	 */
	public static function upsert($table, $fields = NULL)
	{
		if ($fields === NULL || !Utility::isAssociative($fields)) {
			$fields = Array();
		}
		
		$columns = self::getFields($table, 'PRI');
		
		$keys = Array();
		
		// Setup keys.
		foreach ($fields as $field => $value) {
			if (Array_Search($field, $columns) !== FALSE) {
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
			implode(', ', Array_Fill(0, Count($keys), '?')) . ') ON DUPLICATE KEY UPDATE ';
		
		/* Performs upsert here. */
		if (Is_Array($fields) && Count($fields) > 0) {
			$res.= implode(', ', Array_Keys($fields));
		}
		else {
			foreach ($keys as $key => $column) {
				$keys[$key] = "$column = $column";
			}
			
			$res.= Implode($keys, ', ');
		}
		
		$res = self::query($res, $values);
		
		if ( $res !== FALSE ) {
			$res->closeCursor();
			
			if ( $res->rowCount() == 1 ) {
				// Inserted, get last insert id.
				// Note: mysql_insert_id() doesn't do UNSIGNED ZEROFILL!
				$res = self::getConnection()->lastInsertId();
				//$res = self::fetchField("SELECT MAX(ID) FROM `$table`;");
			}
			/* rowCount() should be 2 here as long as it stays in MySQL driver. */
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
	public static function delete($table, $keys)
	{
		$columns = self::getFields($table, Array('PRI', 'UNI'));
		$columns = self::escape($columns);
		
		$table = self::escape($table);
		
		foreach ($columns as &$column) {
			$column = "$column = ?";
		}
		
		if (!Is_Array($keys) || 
			(Count($keys) > 0 && Utility::isAssociative($keys))) {
			$keys = Array($keys);
		}
		
		$res = "DELETE FROM $table WHERE " . implode(' AND ', $columns);
		
		$affectedRows = 0;
		
		foreach ( $keys as $key ) {
			if ( !Is_Array($key) ) {
				$key = Array($key);
			}
			
			$res_1 = self::query($res, $key);
			
			if ( $res_1 === FALSE ) {
				return FALSE;
			}
			
			$affectedRows += $res_1->rowCount();
		}
		
		return $affectedRows;
	}
	
	public static function lastInsertId()
	{
		return self::getConnection()->lastInsertId();
	}
	
	public static function lastError()
	{
		return self::getConnection()->errorInfo();
	}
}