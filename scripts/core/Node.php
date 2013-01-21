<?php

namespace core;

/**
 * Node entity access class.
 */
class Node {

  //--------------------------------------------------
  //
  //  Functions
  //
  //--------------------------------------------------

  /**
   * Retrieves all nodes with the specified name, as return as an array.
   *
   * @param $filter (mixed) Either:
   *                        1. An array of fields to be searched,
   *                          ID  - Integer or array of integers, will
   *                                be merged into the query string to
   *                                improve performance.
   *                          ... - Any type of node and field names.
   *                        2. Name of target collection, all contents will be
   *                           fetched.
   *                        3. ID from the default collection, usually be 'Nodes'.
   * @param $fieldsRequired (bool) If TRUE, rows must contain all fields
   *                               specified in argument filter to survive.
   * @param $limit (mixed) Can be integer specifying the row count from
   *                       first row, or an array specifying the starting
   *                       row and row count.
   * @parma $sorter (array) Optional. 1. fields to be ordered ascending, or
   *                                  2. Hashmap with fields as keys and boolean values
   *                                  with TRUE interprets as ascending and FALSE otherwise.
   *
   * @returns Array of filtered data rows.
   */
  static function
  /* Array */ get($filter, $fieldsRequired = TRUE, $limit = NULL, $sorter = NULL) {
    if ($limit !== NULL && (!$limit || !is_int($limit) && !is_array($limit))) {
      return array();
    }

    // Defaults string to collections.
    if (is_string($filter)) {
      $filter = array(
        NODE_FIELD_COLLECTION => $filter
      );
    }

    // Defaults numbers to ID
    if (is_numeric($filter)) {
      $filter = array(
        'ID' => intval($filter)
      );
    }

    $filter = (array) $filter;

    $tableName = self::resolveCollection(@$filter[NODE_FIELD_COLLECTION]);

    $queryString = '';

    $query = array();
    $params = array();

    /* Merge $filter into SQL statements. */
    $columns = Database::query("SHOW COLUMNS FROM $tableName;");
    $columns = $columns->fetchAll(\PDO::FETCH_COLUMN, 0);

    if (isset($filter[NODE_RAWQUERY])) {
      $rawQuery = (array) $filter[NODE_RAWQUERY];

      array_walk($rawQuery,
        function($value, $key) use(&$query, &$params) {
          if (is_int($key)) {
            $query[] = "($value)";
          }
          else
          if (is_string($key)) {
            $values = \utils::wrapAssoc($value);

            $subQuery = array_fill(0, count($values), "$key");
            $subQuery = '(' . implode(' OR ', $subQuery) . ')';

            $query[] = $subQuery;
            $params = array_merge($params, $values);
          }
        });

      unset($filter[NODE_RAWQUERY], $rawQuery);
    }

    // Pick real columns for SQL merging
    $columnsFilter = array_select($filter, $columns);

    /* Merge $filter into SQL statement. */
      array_walk($columnsFilter, function(&$contents, $field) use($tableName, &$query, &$params) {
        $contents = \utils::wrapAssoc($contents);

        $subQuery = array();

        array_walk($contents, function($content) use($tableName, $field, &$subQuery, &$params) {
          $escapedField = Database::escape($field, $tableName);

          // 1. Boolean comparison: true, false
          if (is_bool($content)) {
            $subQuery[] = "$escapedField = ?";
            $params[] = $content;
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
          }
          else

          // 3. Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc.
          if (preg_match('/^(?:<|<=|==|>=|>)\'([0-9- :]+)\'$/', $content, $matches) &&
            count($matches) > 2 && strtotime($matches[2]) !== FALSE)
          {
            $subQuery[] = "$escapedField $matches[1] ?";
            $params[] = $matches[2];
          }
          else

          // 4. Regexp matching: "/^AB\d+/"
          if (preg_match('/^\/([^\/]+)\/g?i?$/i', $content, $matches) &&
            count($matches) > 1)
          {
            $subQuery[] = "$escapedField REGEXP ?";
            $params[] = $matches[1];
          }
          else

          // 5. NULL types
          if (is_null($content) || preg_match('/^((?:==|\!=)=?)\s*NULL\s*$/i', $content, $matches)) {
            $content = 'IS ' . (@$matches[1][0] == '!' ? 'NOT ' : '') . 'NULL';
            $subQuery[] = "$escapedField $content";
          }
          else

          // 6. Plain string.
          if (is_string($content)) {
            $subQuery[] = "$escapedField LIKE ?";
            $params[] = $content;
          }
        });

        /* Note by Eric @ 4 Dec, 2012
            Inclusive search in real columns, within the same column.
        */
        if ($subQuery) {
          $query[] = '(' . implode(' OR ', $subQuery) . ')';
        }
      });

      unset($columnsFilter);

      // Remove real columns from the filter
      remove($columns, $filter);

      $queryString = $query ? ' WHERE ' . implode(' AND ', $query) : NULL;
    /* Merge $filter into SQL statement, end of. */

    /* Merge $sorter into SQL statement. */
      if (is_array($sorter)) {
        $columnsSorter = \utils::isAssoc($sorter) ?
          array_select($sorter, $columns) :
          array_intersect($sorter, $columns);

        // We are free to reuse $query at this point.
        $query = array();

        array_walk($columnsSorter, function($direction, $field) use(&$query, $tableName) {
            // Numeric key, swap if supplied array-value as field, defaulting to ascending order.
            if (is_int($field)) {
              // ... or simply do nothing on unknown cases.
              if (!is_string($direction)) {
                return;
              }

              $field = $direction;
              $direction = TRUE;
            }

            $query[] = Database::escape($field, $tableName) . ($direction ? ' ASC' : ' DESC');
          });

        if ($query) {
          $queryString.= ' ORDER BY ' . implode(', ', $query);
        }

        unset($query, $columns, $columnsSorter);
      }
    /* Merge $sorter into SQL statement, end of. */

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
        foreach ($filter as $field => $expr) {
          $expr = \utils::wrapAssoc($expr);

          array_walk_recursive($expr, 'Node::filterWalker', array(
              'row' => &$row,
              'field' => $field,
              'fieldsRequired' => $fieldsRequired
            ));

          // Break out when row is set NULL, which means dropped.
          if (!$row) {
            break;
          }
        }

        // If the row passes the filter, add it.
        // if ( $row !== NULL ) {
        if ($row) {
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
    else {
      return $itemIndex;
    }

    return $indexA < $indexB ? -1 : 1;
  }

  /**
   * Upserts one or a set of data.
   *
   * Important: All "timestamp" properties in argument $contents will be removed.
   *
   * @param $contents An array of data to be updated, data row will be identified by id.
   * @param $extendExists TRUE means $contents can be partial update, fields not specified
   *        will have their old value retained instead of replacing the whole row.
   *
   * @returns Array of Booleans, TRUE on success of specific row, FALSE otherwises.
   *
   * @throws CoreException thrown when more than one row is selected with the provided keys,
   *                       and $extendExists is TRUE.
   */
  static function
  /* Array */ set($contents = NULL, $extendExists = FALSE) {
    if (!$contents) {
      return array();
    }

    $isArray = is_array($contents) && !Utility::isAssoc($contents);

    $contents = Utility::wrapAssoc($contents);

    $result = array();

    foreach ($contents as $row) {
      if (!is_array($row) || !count($row))
        continue;

      if (!isset($row[NODE_FIELD_COLLECTION])) {
        /* Note by Eric @ 4 Dec, 2012
            Should discuss whether we should use trigger_error
            instead of throwing exceptions in all non-fatal cases.
        */
        trigger_error('Data object must specify a collection with property "'.NODE_FIELD_COLLECTION.'".', E_USER_WARNING);
        // throw new exceptions\CoreException('Data object must specify a collection with property "'.NODE_FIELD_COLLECTION.'".');
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

    if ( !$isArray ) {
      $result = Utility::unwrapAssoc($result);
    }

    return $result;
  }

  /**
   * Delete a data row.
   *
   * @param $filter (mixed) Uses the same filtering mechanism as get(),
   *                        then delete all rows retrieved from within.
   * @param $fieldsRequired (bool) Same as get().
   *
   * @returns The total number of affected rows.
   */
  static function
  /* int */ delete($filter = NULL, $fieldsRequired = FALSE) {
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
  /* String */ resolveCollection($tableName) {
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
  /* Boolean */ makePhysical($collection, $fieldName = NULL, $fieldDesc = NULL) {
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