<?php
/* Node.php | The node data framework. */

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
   * @param $fieldsRequired (bool) If true, rows must contain all fields
   *                               specified in argument filter to survive.
   * @param $limit (mixed) Can be integer specifying the row count from
   *                       first row, or an array specifying the starting
   *                       row and row count.
   * @parma $sorter (array) Optional. 1. fields to be ordered ascending, or
   *                                  2. Hashmap with fields as keys and boolean values
   *                                  with true interprets as ascending and false otherwise.
   *
   * @returns Array of filtered data rows.
   */
  static /* Array */
  function get($filter, $fieldsRequired = true, $limits = null, $sorter = null) {
    if ( $limits !== null && (!$limits || !is_int($limits) && !is_array($limits)) ) {
      return array();
    }

    // Defaults string to collections.
    if ( is_string($filter) ) {
      $filter = array(
        NODE_FIELD_COLLECTION => $filter
      );
    }

    $filter['@fieldsRequired'] = $fieldsRequired;
    $filter['@limits'] = $limits;
    $filter['@sorter'] = $sorter;

    $result = array();

    $emitter = self::getAsync($filter, function($data) use(&$result) {
      $result[] = $data;
    });

    return $result;
  }

  /**
   * New cursor approach, invokes $dataCallback the moment we found a matching row.
   */
  static /* void */
  function getAsync($filter, $dataCallback) {
    // Defaults string to collections.
    if ( is_string($filter) ) {
      $filter = array(
        NODE_FIELD_COLLECTION => $filter
      );
    }

    $fieldsRequired = (bool) @$filter['@fieldsRequired'];
    $limits = @$filter['@limits'];
    $sorter = @$filter['@sorter'];

    if ( $limits !== null && (!$limits || !is_int($limits) && !is_array($limits)) ) {
      return;
    }

    // Defaults numbers to ID
    if ( is_numeric($filter) ) {
      $filter = array(
        'ID' => intval($filter)
      );
    }

    $filter = (array) $filter;

    $tableName = self::resolveCollection(@$filter[NODE_FIELD_COLLECTION]);

    if ( $tableName !== NODE_COLLECTION ) {
      unset($filter[NODE_FIELD_COLLECTION]);
    }

    /* If index hint is specified, append the clause to collection. */
    if ( @$filter[NODE_FIELD_INDEX_HINT] ) {
      $indexHints = $filter[NODE_FIELD_INDEX_HINT];
    }
    else {
      $indexHints = null;
    }

    /* Removes the index hint field. */
    unset($filter[NODE_FIELD_INDEX_HINT]);

    $selectField = isset($filter[NODE_FIELD_SELECT]) ? $filter[NODE_FIELD_SELECT] : '*';

    unset($filter[NODE_FIELD_SELECT]);

    $queryString = '';

    $query = array();
    $params = array();

    /* Merge $filter into SQL statements. */
    $columns = Database::getFields($tableName);
    // $columns = Database::query("SHOW COLUMNS FROM $tableName;");
    // $columns = $columns->fetchAll(\PDO::FETCH_COLUMN, 0);

    if ( isset($filter[NODE_FIELD_RAWQUERY]) ) {
      $rawQuery = (array) $filter[NODE_FIELD_RAWQUERY];

      array_walk($rawQuery,
        function($value, $key) use(&$query, &$params) {
          if ( is_int($key) ) {
            $query[] = "($value)";
          }
          else
          if ( is_string($key) ) {
            $values = \utils::wrapAssoc($value);

            if ( $values ) {
              $subQuery = array_fill(0, count($values), "$key");
              $subQuery = '(' . implode(' OR ', $subQuery) . ')';

              $query[] = $subQuery;
              $params = array_merge($params, $values);
            }
          }
        });

      unset($filter[NODE_FIELD_RAWQUERY], $rawQuery);
    }

    // Pick real columns for SQL merging
    $columnsFilter = array_select($filter, $columns);

    /* Merge $filter into SQL statement. */
      array_walk($columnsFilter, function(&$contents, $field) use(&$query, &$params, $tableName) {
        $contents = \utils::wrapAssoc($contents);

        $subQuery = array();

        // Explicitly groups equality into expression: IN (...)
        $inValues = array();

        array_walk($contents, function($content) use(&$subQuery, &$params, &$inValues) {
          // Error checking
          if ( is_array($content) ) {
            throw new NodeException('Node does not support composite array types.');
          }

          // 1. Boolean comparison: true, false
          if ( is_bool($content) ) {
            /*
            $subQuery[] = "IS ?";
            $params[] = $content;
            */
            $inValues[] = $content;
          }
          else

          // 2. Numeric comparison: 3, <10, >=20, ==3.5 ... etc.
          if (preg_match('/^(<|<=|==|>=|>)?(\d+)$/', trim($content), $matches) &&
            count($matches) > 2)
          {
            if ( !$matches[1] || $matches[1] == '==' ) {
              // $matches[1] = '=';
              $inValues[] = $matches[2];
            }
            else {
              $subQuery[] = "$matches[1] ?";
              $params[] = $matches[2];
            }
          }
          else

          // 3. Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc.
          if (preg_match('/^(<|<=|==|>=|>)?\'([0-9- :]+)\'$/', trim($content), $matches) &&
            count($matches) > 2 && strtotime($matches[2]) !== false)
          {
            if ( !$matches[1] || $matches[1] == '==' ) {
              $inValues[] = $matches[2];
            }
            else {
              $subQuery[] = "$matches[1] ?";
              $params[] = $matches[2];
            }
          }
          else

          // 4. Regexp matching: "/^AB\d+/"
          if (preg_match('/^\/([^\/]+)\/[\-gismx]*$/i', trim($content), $matches) &&
            count($matches) > 1)
          {
            $subQuery[] = "REGEXP ?";
            $params[] = $matches[1];
          }
          else

          // 5. null types
          if ( is_null($content) || preg_match('/^((?:==|\!=)=?)\s*null$/i', trim($content), $matches) ) {
            $content = 'IS ' . (@$matches[1][0] == '!' ? 'NOT ' : '') . 'null';
            $subQuery[] = "$content";
          }
          else

          // 6. Plain string.
          if ( is_string($content) ) {
            $content = trim($content);

            if ( preg_match('/[^\\][\\*%_]/', $content) ) {
              $operator = 'LIKE';
            }
            else {
              $operator = '=';
            }

            if ( preg_match('/^!\'([^\']+)\'$/', $content, $matches) ) {
              $subQuery[] = "NOT $operator ?";
              $content = $matches[1];
            }
            else {
              $subQuery[] = "$operator ?";
            }

            $params[] = $content;

            unset($operator);
          }
        });

        // Group equality comparators (=) into IN (...) statement.
        if ( $inValues ) {
          $params = array_merge($params, $inValues);
          $subQuery[] = 'IN (' . Utility::fillArray($inValues) . ')';
        }

        unset($inValues);

        $subQuery = array_map(prepends(Database::escape($field, $tableName) . ' '), $subQuery);

        /* Note by Vicary @ 4 Dec, 2012
           Inclusive search in real columns, within the same column.
        */
        if ( $subQuery ) {
          $query[] = '(' . implode(' OR ', $subQuery) . ')';
        }
      });

      unset($columnsFilter);

      // Remove real columns from the filter
      remove($columns, $filter);

      $queryString = $query ? ' WHERE ' . implode(' AND ', $query) : null;
    /* Merge $filter into SQL statement, end of. */

    /* Merge $sorter into SQL statement. */
      if ( is_array($sorter) ) {
        $columnsSorter = \utils::isAssoc($sorter) ?
          array_select($sorter, $columns) :
          array_intersect($sorter, $columns);

        // We are free to reuse $query at this point.
        $query = array();

        array_walk($columnsSorter, function($direction, $field) use(&$query, $tableName) {
            // Numeric key, swap if supplied array-value as field, defaulting to ascending order.
            if ( is_int($field) ) {
              // ... or simply do nothing on unknown cases.
              if ( !is_string($direction) ) {
                return;
              }

              $field = $direction;
              $direction = true;
            }

            $query[] = Database::escape($field, $tableName) . ($direction ? ' ASC' : ' DESC');
          });

        if ( $query ) {
          $queryString.= ' ORDER BY ' . implode(', ', $query);
        }

        unset($query, $columns, $columnsSorter);
      }
    /* Merge $sorter into SQL statement, end of. */

    // Normalize $limits into the format of [offset, length].
    if ( is_int($limits) ) {
      $limits = array(0, $limits);
    }
    elseif ( is_array($limits) ) {
      $limits = array_slice($limits, 0, 2) + array(0, 0);
    }

    $filter = array_select($filter, array_filter(array_keys($filter), compose('not', startsWith('@'))));

    // Simple SQL statment when all filtering fields are real column.
    if ( !$filter ) {
      if ( $limits ) {
        $queryString.= " LIMIT $limits[0], $limits[1]";
      }

      if ( is_array($indexHints) && array_key_exists($tableName, $indexHints) ) {
        $indexHints = " $indexHints[$tableName] ";
      }

      $res = "SELECT $selectField FROM `$tableName` $indexHints $queryString";

      unset($limits);

      $res = Database::query($res, $params);

      $res->setFetchMode(\PDO::FETCH_ASSOC);

      $decodesContent = function(&$row) use($tableName) {
        if ( isset($row[NODE_FIELD_VIRTUAL]) ) {
          $contents = (array) json_decode($row[NODE_FIELD_VIRTUAL], true);

          unset($row[NODE_FIELD_VIRTUAL]);

          if ( is_array($contents) ) {
            $row = $contents + $row;
          }

          unset($contents);
        }

        $row[NODE_FIELD_COLLECTION] = $tableName;

        return $row;
      };

      foreach ( $res as $row ) {
        $dataCallback($decodesContent($row));
      }

      unset($res, $row, $decodesContent);
    }

    // otherwise goes vritual route with at least one virtual field.
    else {
      // Fetch until the fetched size is less than expected fetch size.
      if ( $limits === null ) {
        $limits = array(0, PHP_INT_MAX);
      }

      $fetchOffset = 0; // Always starts with zero as we are calculating virtual fields.
      $fetchLength = NODE_FETCHSIZE; // Node fetch size, or the target size if smaller.

      // Row decoding function.
      $decodesContent = function(&$row) use($filter, $fieldsRequired) {
        if ( isset($row[NODE_FIELD_VIRTUAL]) ) {
          $contents = (array) json_decode($row[NODE_FIELD_VIRTUAL], true);

          unset($row[NODE_FIELD_VIRTUAL]);

          $row+= $contents;

          unset($contents);
        }

        // Calculates if the row qualifies the $contentFilter or not.
        foreach ( $filter as $field => $expr ) {
          // Reuse $expr as it's own result here, do not confuse with the name.
          $expr = self::filterWalker($expr, array(
              'row' => $row,
              'field' => $field,
              'fieldsRequired' => $fieldsRequired
            ));

          if ( !$expr ) {
            return false;
          }
        }

        return true;
      };

      if ( is_string($indexHints) ) {
        $tableName.= " $indexHints";
      }
      else if ( is_array($indexHints) && array_key_exists($indexHints, $tableName) ) {
        $tableName = "`$tableName` $indexHints[$tableName]";
      }

      while ( $res = Database::select($tableName, $selectField, "$queryString LIMIT $fetchOffset, $fetchLength", $params) ) {
        $fetchOffset+= count($res);

        while ( $row = array_shift($res) ) {
          if ( $decodesContent($row) ) {
            if ( $limits[0] ) {
              $limits[0]--;
            }
            else {
              if ( $tableName !== NODE_COLLECTION ) {
                $row[NODE_FIELD_COLLECTION] = $tableName;
              }

              $dataCallback($row);

              // Result size is less than specified size, it means end of data.
              if ( !--$limits[1] ) {
                break;
              }
            }
          }
        }

        // You'll never know if upcoming rows are qualifying.
        // $fetchLength = min($limits[1], NODE_FETCHSIZE);
      } unset($fetchOffset, $fetchLength, $res);
    }
  }

  private static function filterWalker($content, $data) {
    $field = $data['field'];
    $required = $data['fieldsRequired'];

    // Just skip the field on optional matcher.
    // Return TURE to include those fields, false to drop them.
    if ( !$required && !isset($data['row'][$field]) ) {
      return true;
    }

    $value = &$data['row'][$field];

    if ( is_array($content) ) {
      // OR operation here.

      $content = array_map(function($content) use($data) {
        return self::filterWalker($content, $data);
      }, $content);

      return in_array(true, $content, true);
    }
    else {
      // Normalize numeric values into exact match.
      if ( is_numeric($content) ) {
        $content = "==$content";
      }

      // For required fields.
      if ($required && !isset($value) ||
      // Boolean comparison: true, false
          ( is_bool($content) && $content !== (bool) $value ) ||
      // null type: null
          ( is_null($content) && $value !== $content ) ||

      /* Quoted by Vicary @ 1 Jan, 2013
         This will make inconsistency between real and virtual columns.

      // Array type: direct comparison
          // ( is_array($content) && $value == $content ) ||
      */

      // Numeric comparison: <10, >=20, ==3.5 ... etc
          ( preg_match('/^(<|<=|==|>=|>)\s*\d+$/', $content, $matches) && !eval('return $value' . $content . ';') ) ||
      // Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc
          ( preg_match('/^(<|<=|==|>=|>)\'([0-9- :]+)\'$/', $content, $matches ) &&
            !eval('return strtotime($value)' . $matches[1] . strtotime($matches[2]) . ';')) ||
      // Regexp matching: "/^AB\d+/"
          ( preg_match('/^\/.+\/g?i?$/i', $content, $matches) ? preg_match( $content, $value ) == 0 :
      // null type: ==null, !=null, ===null, !==null
            ( preg_match('/^((?:==|!=)=?)\s*null\s*$/i', $content, $matches) && !eval('return $value' . $content . ';') ) ||
      // Plain string
      // This was "count($matches) > 0", find out why.
            ( count($matches) == 0 && is_string($content) && false == preg_match('/^(<|<=|==|>=|>)/', $content) && $content !== $value )
          ))
      {
        // $data['row'] = null;
        return false;
      }

      return true;
    }
  }

  public static /* int */
  function nodeSorter($itemA, $itemB) {
    $itemIndex = strcmp($itemA[NODE_FIELD_COLLECTION], $itemB[NODE_FIELD_COLLECTION]);

    if ( $itemIndex === 0 ) {
      $itemIndex = 'ID';

      if ( !isset($itemA[$itemIndex]) ) {
        $itemIndex = array_keys($itemA);

        foreach ( $itemIndex as $key ) {
          if ( array_key_exists($key, $itemB) ) {
            $itemIndex = $key;
            break;
          }
        }

        if ( is_array($itemIndex) ) {
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
   * @param $extendExists true means $contents can be partial update, fields not specified
   *        will have their old value retained instead of replacing the whole row.
   *
   * @returns Array of Booleans, true on success of specific row, false otherwises.
   *
   * @throws NodeException thrown when $contents did not specify a collection.
   * @throws NodeException thrown when more than one row is selected with the provided keys,
   *                       and $extendExists is true.
   */
  static /* Array */
  function set($contents = null, $extendExists = false) {
    if ( !$contents ) {
      return array();
    }

    $contents = Utility::wrapAssoc($contents);

    $result = array();

    foreach ( $contents as $row ) {
      if ( !is_array($row) || !$row ) {
        continue;
      }

      if ( !trim(@$row[NODE_FIELD_COLLECTION]) ) {
        throw new NodeException('Data object must specify a collection with property "'.NODE_FIELD_COLLECTION.'".');

        continue;
      }

      $tableName = self::resolveCollection($row[NODE_FIELD_COLLECTION]);

      // Get physical columns of target collection,
      // merges into SQL for physical columns.
      $res = Database::getFields($tableName, null, false);

      // This is used only when $extendExists is true,
      // contains primary keys and unique keys for retrieving
      // the exact existing object.
      $keys = array_filter($res, propHas('Key', array('PRI', 'UNI')));

      // Normal columns for merging SQL statements.
      $cols = array_diff_key($res, $keys);

      $keys = array_keys($keys);
      $cols = array_keys($cols);

      // Composite a filter and call self::get() for the existing object.
      // Note that this process will break when one of the primary key
      // is not provided inside $content object, thus unable to search
      // the exact row.
      if ( $extendExists === true ) {
        $res = array(
          NODE_FIELD_COLLECTION => $tableName === NODE_COLLECTION ?
            $row[NODE_FIELD_COLLECTION] : $tableName
        );

        $res+= array_select($row, $keys);

        $res = node::get($res);

        if ( count($res) > 1 ) {
          throw new NodeException('More than one row is selected when extending '.
            'current object, please provide ALL keys when calling with $extendExists = true.');
        }

        if ( $res ) {
          $row += array_select($res[0], array_diff(array_keys($res[0]), $cols));
        }

        unset($res);
      }

      // Real array to be passed down Database::upsert().
      $data = array();

      foreach ( $row as $field => $fieldContents ) {
        // Physical columns exists, pass in.
        if ( $field !== NODE_FIELD_VIRTUAL && in_array($field, array_merge($keys, $cols)) ) {
          $data[$field] = $fieldContents;

          unset($row[$field]);
        }
      }

      // Do not pass in @collection as data row below, physical tables only.
      if ( @$row[NODE_FIELD_COLLECTION] !== NODE_COLLECTION ) {
        unset($row[NODE_FIELD_COLLECTION]);
      }

      // Encode the rest columns and put inside virtual field.
      // Skip the whole action when `@contents` columns doesn't exists.
      if ( in_array(NODE_FIELD_VIRTUAL, $cols) ) {
        // Silently swallow json_encode() errors.
        $data[NODE_FIELD_VIRTUAL] = @json_encode($row);

        // Store nothing on encode error, or there is nothing to be stored.
        if ( !$data[NODE_FIELD_VIRTUAL] ) {
          unset($data[NODE_FIELD_VIRTUAL]);
        }
      }

      $result[] = Database::upsert($tableName, $data);
    }

    if ( count($contents) == 1 ) {
      $result = $result[0];
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
  static /* int */
  function delete($filter = null, $fieldsRequired = false, $limit = null) {
    $res = self::get($filter, $fieldsRequired, $limit);

    $affectedRows = 0;

    foreach ( $res as $key => $row ) {
      $tableName = self::resolveCollection($row[NODE_FIELD_COLLECTION]);

      $fields = Database::getFields($tableName, array('PRI', 'UNI'));

      $deleteKeys = array();

      foreach ( $fields as &$field ) {
        if ( array_key_exists($field, $row) ) {
          if ( !is_array(@$deleteKeys[$field]) ) {
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

  private static /* String */
  function resolveCollection($tableName) {
    if ( !Database::hasTable($tableName) ) {
      return NODE_COLLECTION;
    }
    else {
      return $tableName;
    }
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
   * @returns true on success, false otherwise;
   *
   * @throws NodeException thrown when the call tries to make a virtual collection physical.
   * @throws NodeException thrown when target table is no virtual column field.
   * @throws NodeException thrown when no physical field description ($fieldDesc) is given.
   */
  public static /* Boolean */
  function makePhysical($collection, $fieldName = null, $fieldDesc = null) {
    // Create table
    if ( !Database::hasTable($collection) ) {
      throw new NodeException('Table creation is not supported in this version.');

      // Mimic result after table creation.
      if ( $fieldName === null ) {
        return true;
      }
    }

    $fields = Database::getFields($collection);

    if ( !in_array(NODE_FIELD_VIRTUAL, $fields) ) {
      throw new NodeException( 'Specified table `'
                             . $collection
                             . '` does not support virtual fields, no action is taken.'
                             );
    }

    if ( $fieldName !== null && !$fieldDesc ) {
      throw new NodeException('You must specify $fieldDesc when adding physical column.');
    }

    $field = Database::escape($fieldName);

    $res = Database::query( 'ALTER TABLE ' . $collection . ' ADD COLUMN '
                          . $field . ' ' . str_replace(';', '', $fieldDesc)
                          );

    if ( $res === false ) {
      return false;
    }

    $fields = Database::getFields($collection, array('PRI'));

    array_push($fields, NODE_FIELD_VIRTUAL, $fieldName);

    $fields = Database::escape($fields);

    // Data migration.
    $migrated = true;

    $res = Database::query('SELECT ' . implode(', ', $fields) . ' FROM ' . $collection);

    $res->setFetchMode(\PDO::FETCH_ASSOC);

    foreach ( $res as $row ) {
      $contents = json_decode($row[NODE_FIELD_VIRTUAL], true);

      $row[$fieldName] = $contents[$fieldName];

      unset($contents[$fieldName]);

      $row[NODE_FIELD_VIRTUAL] = json_encode($contents);
      $row[NODE_FIELD_COLLECTION] = $collection;

      if ( self::set($row) === false ) {
        $migrated = false;
      }
    }

    return $migrated;
  }
}

class NodeException extends \Exception {}
