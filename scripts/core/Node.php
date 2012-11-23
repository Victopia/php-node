<?php

namespace core;

/**
 * Node entity access class.
 */
class Node
{
	/**
	 * Retrieves all nodes with the specified name, as return as an array.
	 *
	 * @param $name A string specifying the node name. e.g. User
	 * @param $filter An array of fields to be searched.
	 *				ID	- Integer or array of integers, will be merged into
	 *							the query string to improve performance.
	 *					... - Any type of node and field names.
	 * @param $fieldsRequired If TRUE, rows must contain all fields
	 *				specified in argument filter to survive.
	 * @param $limit Can be integer specifying the row count from first
	 *				row, or an array specifying the starting row and row count.
	 *
	 * @returns Array of filtered data rows.
	 */
	static function
	/* Array */ get($filter, $fieldsRequired = TRUE, $limit = NULL, $sorter = NULL) {
		if ($limit !== NULL && (!$limit || !is_int($limit) && !is_array($limit))) {
			return array();
		}

		$queryString = '';

		$query = array();
		$params = array();

		$tableName = self::resolveCollection(@$filter[NODE_FIELD_COLLECTION]);

		/* Merge $filter into SQL statements. */
		$columns = Database::query("SHOW COLUMNS FROM $tableName;");
		$columns = $columns->fetchAll(\PDO::FETCH_COLUMN, 0);

		if (is_array($filter)) {
			// Raw queries
			if (isset($filter[NODE_RAWQUERY])) {
				$rawQueries = (array) $filter[NODE_RAWQUERY];

				foreach ($rawQueries as $rawQueryKey => $rawQuery) {
					if (is_int($rawQueryKey)) {
						$query[] = $rawQuery;
					}
					elseif (is_string($rawQueryKey)) {
						$query[] = $rawQueryKey;
						$params = array_merge((array) $rawQuery);
					}
				}

				unset($filter[NODE_RAWQUERY], $rawQueries, $rawQueryKey, $rawQuery);
			}

			foreach ($filter as $field => &$contents) {
				if ( !is_array($contents) ) {
					$contents = array($contents);
				}

				$subQuery = array();

				foreach ($contents as $key => &$content) {
					// Rational column exists, start merging according to data types.
					if (in_array($field, $columns)) {
						$escapedField = Database::escape($field);

						// 1. Boolean comparison: true, false
						if (is_bool($content)) {
							$subQuery[] = "$escapedField = ?";
							$params[] = $content;
							unset($contents[$key]);
						}
						else

						// 2. Numeric comparison: 3, <10, >=20, ==3.5 ... etc.
						if (preg_match('/^(<|<=|==|>=|>)?(\d+)$/', $content, $matches) &&
							count($matches) > 2)
						{
							if (!$matches[1] || $matches[1] == '==') {
								$matches[1] = '=';
							}

							$subQuery[] = "$escapedField $matches[1] ?";
							$params[] = $matches[2];
							unset($contents[$key]);
						}
						else

						// 3. Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc.
						if (preg_match('/^(?:<|<=|==|>=|>)\'([0-9- :]+)\'$/', $content, $matches) &&
							count($matches) > 2 && strtotime($matches[2]) !== FALSE)
						{
							$subQuery[] = "$escapedField $matches[1] ?";
							$params[] = $matches[2];
							unset($contents[$key]);
						}
						else

						// 4. Regexp matching: "/^AB\d+/"
						if (preg_match('/^\/([^\/]+)\/g?i?$/i', $content, $matches) &&
							count($matches) > 1)
						{
							$subQuery[] = "$escapedField REGEXP ?";
							$params[] = $matches[1];
							unset($contents[$key]);
						}
						else

						// 5. NULL types
						if (preg_match('/^((?:==|\!=)=?)\s*NULL\s*$/i', $content, $matches)) {
							$content = 'IS ' . ($matches[1][0] == '!' ? 'NOT ' : '') . 'NULL';
							$subQuery[] = "$escapedField $content";
							unset($contents[$key]);
						}
						else

						// 6. Plain string.
						if (is_string($content)) {
							$subQuery[] = "$escapedField LIKE ?";
							$params[] = $content;
							unset($contents[$key]);
						}
					}
				}

				unset($content);

				if (count($contents) == 0) {
					unset($filter[$field]);
				}

				if (count($subQuery) > 0) {
					$query[] = '(' . implode(' OR ', $subQuery) . ')';
				}
			}

			unset($contents);
		}

		$queryString = count($query) > 0 ? ' WHERE ' . implode(' AND ', $query) : NULL;
		/* Merge $filter into SQL statements, end of. */

		/* Merge $sorter into SQL statements. */
		if ($sorter && is_array($sorter)) {
			$query = array();

			foreach ($sorter as $sorterKey => $sorterValue) {
				if (in_array($sorterKey, $columns)) {
					$query[] = Database::escape($sorterKey, $tableName) . ($sorterValue ? ' ASC' : ' DESC');
					unset($sorter[$sorterKey]);
				}
			}

			// If $sorter still has values
			if ($sorter) {
				$columns = Database::query("SHOW COLUMNS FROM $tableName WHERE `Key` LIKE 'PRI';");
				$columns = $columns->fetchAll(\PDO::FETCH_COLUMN, 0);

				foreach ($columns as $column) {
					if (!in_array($column, (array) $sorter)) {
						$query[] = Database::escape($column, $tableName);
					}
				}
			}

			if (count($query) > 0) {
				$queryString.= ' ORDER BY ' . implode(', ', $query);
			}

			unset($query, $columns, $column, $sorterKey, $sorterValue);
		}
		/* Merge $sorter into SQL statements, end of. */

		$rowCount = Database::fetchField('SELECT COUNT(*) FROM ' . Database::escape($tableName) . $queryString, $params);
		$rowOffset = 0;

		if (is_int($limit)) {
			$limit = array(0, $limit);
		}
		elseif (is_array($limit)) {
			$limit = array_slice($limit, 0, 2) + array(0, 0);
		}

		$result = array();

		while ($rowCount > 0 && ($limit === NULL || $limit[1] > 0)) {
			$res = Database::select($tableName, '*', "$queryString LIMIT $rowOffset, " . NODE_FETCHSIZE, $params);

			foreach ($res as $key => &$row) {
				if (isset($row[NODE_FIELD_VIRTUAL])) {
					$content = json_decode($row[NODE_FIELD_VIRTUAL], true);

					unset($row[NODE_FIELD_VIRTUAL]);

					$row = array_merge($row, (array) $content);

					unset($name, $value, $content);
				}

				if ($tableName !== NODE_COLLECTION) {
					$row[NODE_FIELD_COLLECTION] = $tableName;
				}

				/* Start filtering. */
				if ( is_array($filter) ) {
					foreach ($filter as $field => $expr) {
						// Break out on null.
						if ( $row === NULL ) {
							break;
						}

						array_walk_recursive($expr, 'Node::filterWalker', array(
								'row' => &$row,
								'field' => $field,
								'fieldsRequired' => $fieldsRequired
							));
					}

					unset($walker);
				}

				// If the row passes the filter, add it.
				if ( $row !== NULL ) {
					if ($limit !== NULL && $limit[0] > 0) {
						$limit[0]--;
					}
					elseif ($limit === NULL || $limit[1] > 0) {
						$result[] = $row;

						if ( $limit[1] > 0 ) {
							$limit[1]--;
						}
					}
				}
			}

			$rowOffset += NODE_FETCHSIZE;
			$rowCount -= NODE_FETCHSIZE;
		}

		// Skip default nodeSorter when custom $sorter is provided.
		if ($sorter === NULL) {
			usort($result, array('self', 'nodeSorter'));
		}

		return $result;
	}

	private static function filterWalker($content, $key, $data) {
		$field = $data['field'];
		$required = $data['fieldsRequired'];

		// Just skip the field on optional matcher.
		if ( !$required && !isset($data['row'][$field]) ) {
			return;
		}

		$value = &$data['row'][$field];

		// For required fields.
		if ($required && !isset($value) ||
		// Boolean comparison: true, false
				( Is_Bool($content) && $content !== (bool) $value ) ||
		// NULL type: NULL
				( is_null($content) && $value !== $content ) ||
		// Array type: direct comparison
				( is_array($content) && $value == $content ) ||
		// Numeric comparison: <10, >=20, ==3.5 ... etc
				( preg_match('/^(<|<=|==|>=|>)\s*\d+$/', $content, $matches) && !eval('return $value' . $content . ';') ) ||
		// Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc
				( preg_match('/^(<|<=|==|>=|>)\'([0-9- :]+)\'$/', $content, $matches ) &&
					!eval('return strtotime($value)' . $matches[1] . strtotime($matches[2]) . ';')) ||
		// Regexp matching: "/^AB\d+/"
				( preg_match('/^\/.+\/g?i?$/i', $content, $matches) ? preg_match( $content, $value ) == 0 :
		// NULL type: ==NULL, !=NULL, ===NULL, !==NULL
					( preg_match('/^((?:==|!=)=?)\s*NULL\s*$/i', $content, $matches) && !eval('return $value' . $content . ';') ) ||
		// Plain string
		// This was "count($matches) > 0", find out why.
					( count($matches) == 0 && is_string($content) && $content !== $value )
				))
		{
			$data['row'] = NULL;
		}
	}

	public static function
	/* int */ nodeSorter($itemA, $itemB) {
		$itemIndex = strcmp($itemA[NODE_FIELD_COLLECTION], $itemB[NODE_FIELD_COLLECTION]);

		if ( $itemIndex === 0 ) {
			$itemIndex = 'ID';

			if (!isset($itemA[$itemIndex])) {
				$itemIndex = array_keys($itemA);

				foreach ($itemIndex as $key) {
					if (array_key_exists($key, $itemB)) {
						$itemIndex = $key;
						break;
					}
				}

				if (is_array($itemIndex)) {
					$itemIndex = $itemIndex[0];
				}
			}

			$indexA = intval(@$itemA[$itemIndex]);
			$indexB = intval(@$itemB[$itemIndex]);

			if ( $indexA === $indexB ) {
				$itemIndex = 0;
			}
			else {
				$itemIndex = $indexA > $indexB ? 1 : -1;
			}
		}

		return $indexA < $indexB ? -1 : 1;
	}

	/**
	 * Upserts one or a set of data.
	 *
	 * Important: "timestamp" property in argument $contents will be removed.
	 *
	 * @param $contents An array of data to be updated, data row will be identified by id.
	 * @param $extendExists TRUE means $contents can be partial update, fields not specified
	 *				will have their old value retained instead of replacing the whole row.
	 *
	 * @returns Array of Booleans, TRUE on success of specific row, FALSE otherwises.
	 */
	static function
	/* Array */ set($contents = NULL, $extendExists = FALSE)
	{
		if (!$contents) {
			return array();
		}

		if (Utility::isAssoc($contents)) {
			$contents = array($contents);
		}

		$result = array();

		foreach ($contents as $row) {
			if (!is_array($row) || !count($row))
				continue;

			if (!isset($row[NODE_FIELD_COLLECTION])) {
				throw new exceptions\CoreException('Data object must specify a collection with property "'.NODE_FIELD_COLLECTION).'".';
				continue;
			}

			$tableName = self::resolveCollection($row[NODE_FIELD_COLLECTION]);

			// Get physical columns of target collection,
			// merges into SQL for physical columns.
			$res = Database::fetchArray("SHOW COLUMNS FROM `$tableName`;");

			// This is used only when $extendExists is TRUE,
			// contains primary keys and unique keys for retrieving
			// the exact existing object.
			$keys = array_map(prop('Field'), array_filter($res, propIn('Key', array('PRI', 'UNI'))));

			// Normal columns for merging SQL statements.
			$cols = array_diff(array_map(prop('Field'), $res), $keys);

			// Composite a filter and call self::get() for the existing object.
			// Note that this process will break when one of the primary key
			// is not provided inside $content object, thus unable to search
			// the exact row.
			if ($extendExists === TRUE) {
				$res = array(
					NODE_FIELD_COLLECTION => $tableName === NODE_COLLECTION ?
						$row[NODE_FIELD_COLLECTION] : $tableName
				);

				$res+= array_select($row, $keys);

				$res = node::get($res);

				if (count($res) > 1) {
					throw new exceptions\CoreException('More than one row is selected when extending '.
						'current object, please provide ALL keys when calling with $extendExists = TRUE.');
				}

				if ($res) {
					$row += array_select($res[0], array_diff(array_keys($res[0]), $cols));
				}

				unset($res);
			}

			// Real array to be passed down Database::upsert().
			$data = array();

			foreach ($row as $field => $contents) {
				// Physical columns exists, pass in.
				if ($field !== NODE_FIELD_VIRTUAL && in_array($field, array_merge($keys, $cols))) {
					$data[$field] = $contents;

					unset($row[$field]);
				}
			}

			// Do not pass in @collection as data row below, physical tables only.
			if (@$row[NODE_FIELD_COLLECTION] !== NODE_COLLECTION) {
				unset($row[NODE_FIELD_COLLECTION]);
			}

			// Encode the rest columns and put inside virtual field.
			// Skip the whole action when `@contents` columns doesn't exists.
			if (in_array(NODE_FIELD_VIRTUAL, $cols)) {
				$data[NODE_FIELD_VIRTUAL] = json_encode($row);
			}

			$result[] = Database::upsert($tableName, $data);
		}

		if ( Count($result) == 1 ) {
			$result = $result[0];
		}

		return $result;
	}

	/**
	 * Delete a data row.
	 *
	 * @param $filter
	 *
	 * @returns The total number of affected rows.
	 */
	static function
	/* int */ delete($filter = NULL, $fieldsRequired = FALSE)
	{
		$res = self::get($filter, $fieldsRequired);

		$affectedRows = 0;

		foreach ($res as $key => $row) {
			$tableName = self::resolveCollection($row[NODE_FIELD_COLLECTION]);

			$fields = Database::getFields($tableName, array('PRI', 'UNI'));

			$deleteKeys = array();

			foreach ($fields as &$field) {
				if (array_key_exists($field, $row)) {
					if (!is_array(@$deleteKeys[$field])) {
						$deleteKeys[$field] = array();
					}

					$deleteKeys[$field] = $row[$field];
				}
			}

			$affectedRows += Database::delete($tableName, $deleteKeys);
		}

		return $affectedRows;
	}

	//-----------------------------------------------------------------------
	//
	//  Private helper functions
	//
	//-----------------------------------------------------------------------

	private static function
	/* String */ resolveCollection($tableName)
	{
    return $tableName && Database::hasTable($tableName) ? $tableName : NODE_COLLECTION;
	}

	//-----------------------------------------------------------------------
	//
	//  Manipulations
	//
	//-----------------------------------------------------------------------

	/**
	 * Convert a virtual table and/or column into physical one.
	 *
	 * @param $collection Name of target @collection.
	 * @param $fieldName Name of target virtual field, optional.
	 * @param $fieldDesc Database specific column definition, should beware of data type and length.
	 *                   This must exists when $fieldName is specified.
	 *
	 * @returns TRUE on success, FALSE otherwise;
	 */
	public static function
	/* Boolean */ makePhysical($collection, $fieldName = NULL, $fieldDesc = NULL)
	{
    // Create table
    if (!Database::hasTable($collection)) {
      throw new NodeException('Table creation is not supported in this version.');

      // Mimic result after table creation.
      if ($fieldName === NULL) {
        return TRUE;
      }
    }

    $fields = Database::getFields($collection);

    if (!in_array(NODE_FIELD_VIRTUAL, $fields)) {
      throw new NodeException( 'Specified table `'
      											 . $collection
                             . '` does not support virtual fields, no action is taken.'
                             );
    }

    if ($fieldName !== NULL && !$fieldDesc) {
      throw new NodeException('You must specify $fieldDesc when adding physical column.');
    }

    $field = Database::escape($fieldName);

    $res = Database::query( 'ALTER TABLE ' . $collection . ' ADD COLUMN '
                          . $field . ' ' . str_replace(';', '', $fieldDesc)
                          );

    if ($res === FALSE) {
      return FALSE;
    }

    $fields = Database::getFields($collection, array('PRI'));

    array_push($fields, NODE_FIELD_VIRTUAL, $fieldName);

    $fields = Database::escape($fields);

    // Data migration.
    $migrated = TRUE;

    $res = Database::query('SELECT ' . implode(', ', $fields) . ' FROM ' . $collection);

    $res->setFetchMode(\PDO::FETCH_ASSOC);

    foreach ($res as $row) {
      $contents = json_decode($row[NODE_FIELD_VIRTUAL], true);

      $row[$fieldName] = $contents[$fieldName];

      unset($contents[$fieldName]);

      $row[NODE_FIELD_VIRTUAL] = json_encode($contents);
      $row[NODE_FIELD_COLLECTION] = $collection;

      if (self::set($row) === FALSE) {
        $migrated = FALSE;
      }
    }

    return $migrated;
	}
}

class NodeException extends \Exception {}