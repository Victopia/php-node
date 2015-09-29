<?php
/* Node.php | The node data framework. */

namespace core;

use core\exceptions\CoreException;

/**
 * Node entity access class.
 *
 * @errors Code range 300-399
 */
class Node {

  /**
   * The table name to use when no real table is found.
   */
  const BASE_COLLECTION = 'Nodes';

  /**
   * The field name to store the virtual collection name when using BASE_COLLECTION.
   */
  const FIELD_COLLECTION = '@collection';

  /**
   * The field to store the encoded JSON string for virtual fields.
   */
  const FIELD_VIRTUAL = '@contents';

  /**
   * The field of filter for the fields in SELECT queries.
   */
  const FIELD_SELECT = '@select';

  /**
   * The field of filter to specify index hints.
   */
  const FIELD_INDEX = '@index';

  /**
   * The field of filter to use as raw query text.
   */
  const FIELD_RAWQUERY = '@raw';

  /**
   * Regex pattern to match boolean filter expressions.
   */
  const PATTERN_BOOL = '/^(==|!=)\s*(true|false)\s*$/';

  /**
   * Regex pattern to match numeric filter expressions.
   */
  const PATTERN_NUMERIC = '/^(<|<=|==|!=|>=|>)?\s*(\d+)\s*$/';

  /**
   * Regex pattern to match date-time filter expressions.
   */
  const PATTERN_DATETIME = '/^(<|<=|==|!=|>=|>)?\s*\'([0-9- :TZ\+]+)\'\s*$/';

  /**
   * Regex pattern to match null type filter expressions.
   */
  const PATTERN_NULL_TYPE = '/^((?:==|!=)=?)\s*null\s*$/i';

  /**
   * Number of rows to fetch when virtual column is in sight.
   */
  public static $fetchSize = 200;

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
   *                        4. Special filters
   *                          4.1 @fieldsRequired Excludes the row if is does not contains a filtering property.
   *                          4.2 @limits Either $length, or [$offset, $length].
   *                          4.3 @sorter Either [ $field1 => $isAscending, $field2 => $isAscending ], or a compare function.
   * @param $fieldsRequired (bool) If true, rows must contain all fields
   *                               specified in argument filter to survive.
   * @param $limits (mixed) Can be integer specifying the row count from
   *                       first row, or an array specifying the starting
   *                       row and row count.
   * @param $sorter (array) Optional. 1. fields to be ordered ascending, or
   *                                  2. Hashmap with fields as keys and boolean values
   *                                  with true interprets as ascending and false otherwise.
   *
   * @return Array of filtered data rows.
   */
  static /* array */ function get($filter, $fieldsRequired = true, $limits = null, $sorter = null) {
    if ( $limits !== null && (!$limits || !is_int($limits) && !is_array($limits)) ) {
      return array();
    }

    // Defaults string to collections.
    if ( is_string($filter) ) {
      $filter = array(
        self::FIELD_COLLECTION => $filter
      );
    }

    $filter['@fieldsRequired'] = $fieldsRequired;

    if ( $limits !== null ) {
      $filter['@limits'] = $limits;
    }

    if ( $sorter !== null ) {
      $filter['@sorter'] = $sorter;
    }

    $result = array();
    self::getAsync($filter, function($data) use(&$result) {
      $result[] = $data;
    });

    return $result;
  }

  /**
   * Get the first matching item.
   *
   * @see Node#getAync()
   */
  static function getOne($filter) {
    // Defaults string to collections.
    if ( is_string($filter) ) {
      $filter = array(
        self::FIELD_COLLECTION => $filter
      );
    }

    // Force length to be 1
    if ( is_array(@$filter['@limits']) ) {
      $filter['@limits'][1] = 1;
    }
    else {
      $filter['@limits'] = 1;
    }

    $data = self::get($filter);
    if ( $data ) {
      $data = reset($data);
    }

    return $data;
  }

  static function getCount($filter) {
    if ( !self::ensureConnection() ) {
      return false;
    }

    $context = self::composeQuery($filter);
    if ( !$context ) {
      return 0;
    }

    $count = 0;

    if ( !$context['filter'] ) {
      if ( $context['limits'] ) {
        $context['query'].= ' LIMIT ' . implode(', ', $context['limits']);
      }

      if ( is_array($context['indexHints']) && array_key_exists($context['table'], $context['indexHints']) ) {
        $context['indexHints'] = $context['indexHints'][$context['table']];
      }

      $count = (int) Database::fetchField(
        "SELECT COUNT(*) FROM `$context[table]` $context[indexHints] $context[query]",
        $context['params']);
    }
    else {
      self::getAsync($filter, function() use(&$count) {
        $count++;
      });
    }

    return $count;
  }

  /**
   * New cursor approach, invokes $dataCallback the moment we found a matching row.
   *
   * @return An array of return values from every invokes to $dataCallback, works like array_map().
   */
  static function getAsync($filter, $dataCallback) {
    if ( !self::ensureConnection() ) {
      return false;
    }

    $context = self::composeQuery($filter);
    if ( !$context ) {
      return false;
    }

    $result = array();

    // Simple SQL statment when all filtering fields are real column.
    if ( !$context['filter'] ) {
      if ( $context['limits'] ) {
        $context['query'].= ' LIMIT ' . implode(', ', $context['limits']);
      }

      if ( is_array($context['indexHints']) && array_key_exists($context['table'], $context['indexHints']) ) {
        $context['indexHints'] = $context['indexHints'][$context['table']];
      }

      $res = "SELECT $context[select] FROM `$context[table]` $context[indexHints] $context[query]";

      $res = Database::query($res, $context['params']);

      $res->setFetchMode(\PDO::FETCH_ASSOC);

      $decodesContent = function($row) use($context) {
        if ( isset($row[self::FIELD_VIRTUAL]) ) {
          $contents = (array) ContentDecoder::json($row[self::FIELD_VIRTUAL], true);

          unset($row[self::FIELD_VIRTUAL]);

          if ( is_array($contents) ) {
            $row = $contents + $row;
          }

          unset($contents);
        }

        $row[self::FIELD_COLLECTION] = $context['table'];

        return $row;
      };

      foreach ( $res as $row ) {
        $result[] = $dataCallback($decodesContent($row));
      }

      unset($res, $row, $decodesContent);
    }

    // otherwise goes vritual route with at least one virtual field.
    else {
      // Fetch until the fetched size is less than expected fetch size.
      if ( $context['limits'] === null ) {
        $context['limits'] = array(0, PHP_INT_MAX);
      }

      $fetchOffset = 0; // Always starts with zero as we are calculating virtual fields.
      $fetchLength = self::$fetchSize; // Node fetch size, or the target size if smaller.

      // Row decoding function.
      $decodesContent = function(&$row) use($context) {
        if ( isset($row[self::FIELD_VIRTUAL]) ) {
          $contents = (array) ContentDecoder::json($row[self::FIELD_VIRTUAL], true);

          unset($row[self::FIELD_VIRTUAL]);

          $row+= $contents;

          unset($contents);
        }

        // Calculates if the row qualifies the $contentFilter or not.
        foreach ( $context['filter'] as $field => $expr ) {
          // Reuse $expr as it's own result here, do not confuse with the name.
          $expr = self::filterWalker($expr, array(
              'row' => $row,
              'field' => $field,
              'required' => $context['required']
            ));

          if ( !$expr ) {
            return false;
          }
        }

        return true;
      };

      if ( is_string($context['indexHints']) ) {
        $context['table'].= " $context[indexHints]";
      }
      else if ( is_array($context['indexHints']) && array_key_exists($context['indexHints'], $context['table']) ) {
        $context['table'] = "`$context[table]` {$context['indexHints']['$tableName']}";
      }

      while ( $res = Database::select($context['table'], $context['select'], "$context[query] LIMIT $fetchOffset, $fetchLength", $context['params']) ) {
        $fetchOffset+= count($res);

        while ( $row = array_shift($res) ) {
          if ( $decodesContent($row) ) {
            if ( $context['limits'][0] ) {
              $context['limits'][0]--;
            }
            else {
              if ( $context['table'] !== self::BASE_COLLECTION ) {
                $row[self::FIELD_COLLECTION] = $context['table'];
              }

              $result[] = $dataCallback($row);

              // Result size is less than specified size, it means end of data.
              if ( !--$context['limits'][1] ) {
                break;
              }
            }
          }
        }
      } unset($fetchOffset, $fetchLength, $res);
    }

    return $result;
  }

  private static function composeQuery($filter) {
    // Defaults string to collections.
    if ( is_string($filter) ) {
      $filter = array(
        self::FIELD_COLLECTION => $filter
      );
    }

    $fieldsRequired = (bool) @$filter['@fieldsRequired'];
    $limits = @$filter['@limits'];
    $sorter = @$filter['@sorter'];

    if ( $limits !== null && (!$limits || !is_int($limits) && !is_array($limits)) ) {
      return false;
    }

    // Defaults numbers to ID
    if ( is_numeric($filter) ) {
      $filter = array(
        'ID' => intval($filter)
      );
    }

    $filter = (array) $filter;

    $tableName = self::resolveCollection(@$filter[self::FIELD_COLLECTION]);

    if ( $tableName !== self::BASE_COLLECTION ) {
      unset($filter[self::FIELD_COLLECTION]);
    }

    /* If index hint is specified, append the clause to collection. */
    if ( @$filter[self::FIELD_INDEX] ) {
      $indexHints = $filter[self::FIELD_INDEX];
    }
    else {
      $indexHints = null;
    }

    /* Removes the index hint field. */
    unset($filter[self::FIELD_INDEX]);

    $selectField = isset($filter[self::FIELD_SELECT]) ? $filter[self::FIELD_SELECT] : '*';

    unset($filter[self::FIELD_SELECT]);

    $queryString = '';

    $query = array();
    $params = array();

    /* Merge $filter into SQL statements. */
    $columns = Database::getFields($tableName);

    if ( isset($filter[self::FIELD_RAWQUERY]) ) {
      $rawQuery = (array) $filter[self::FIELD_RAWQUERY];

      array_walk($rawQuery,
        function($value, $key) use(&$query, &$params) {
          if ( is_int($key) ) {
            $query[] = "($value)";
          }
          else
          if ( is_string($key) ) {
            $values = Utility::wrapAssoc($value);

            if ( $values ) {
              $subQuery = array_fill(0, count($values), "$key");
              $subQuery = '(' . implode(' OR ', $subQuery) . ')';

              $query[] = $subQuery;
              $params = array_merge($params, $values);
            }
          }
        });

      unset($filter[self::FIELD_RAWQUERY], $rawQuery);
    }

    // Pick real columns for SQL merging
    $columnsFilter = array_select($filter, $columns);

    /* Merge $filter into SQL statement. */
      array_walk($columnsFilter, function(&$contents, $field) use(&$query, &$params, $tableName) {
        $contents = Utility::wrapAssoc($contents);

        $subQuery = array();

        // Explicitly groups equality into expression: IN (...[, ...])
        $inValues = array();

        // Explicitly groups equality into expression: NOT IN (...[, ...])
        $notInValues = array();

        array_walk($contents, function($content) use(&$subQuery, &$params, &$inValues, &$notInValues) {
          // Error checking
          if ( is_array($content) ) {
            throw new CoreException('Node does not support composite array types.', 301);
          }

          // 1. Boolean comparison: true, false
          if ( is_bool($content) ) {
            $inValues[] = $content;
          }
          else if ( preg_match(static::PATTERN_BOOL, trim($content), $matches) ) {
            if ( $matches[1] == '==' ) {
              $inValues[] = $matches[2] == 'true';
            }
            else {
              $notInValues = $matches[2] == 'true';
            }
          }
          else

          // 2. Numeric comparison: 3, <10, >=20, ==3.5 ... etc.
          if ( preg_match(static::PATTERN_NUMERIC, trim($content), $matches) && count($matches) > 2 )
          {
            if ( !$matches[1] || $matches[1] == '==' ) {
              // $matches[1] = '=';
              $inValues[] = $matches[2];
            }
            else if ( $matches[1] == '!=' ) {
              $notInValues[] = $matches[2];
            }
            else {
              $subQuery[] = "$matches[1] ?";
              $params[] = $matches[2];
            }
          }
          else

          // 3. Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc.
          if ( preg_match(static::PATTERN_DATETIME, trim($content), $matches) &&
            count($matches) > 2 && strtotime($matches[2]) !== false )
          {
            if ( !$matches[1] || $matches[1] == '==' ) {
              $inValues[] = $matches[2];
            }
            else if ( $matches[1] == '!=' ) {
              $notInValues[] = $matches[2];
            }
            else {
              $subQuery[] = "$matches[1] ?";
              $params[] = $matches[2];
            }
          }
          else

          // 4. Regexp matching: "/^AB\d+/"
          if ( @preg_match(trim($content), null) !== false )
          {
            $subQuery[] = "REGEXP ?";
            $params[] = preg_replace('/\\\b(.*?)\\\b/', '[[:<:]]$1[[:>:]]',
              trim(rtrim(trim($content), 'igxmysADSUXJ'), '/'));
          }
          else

          // 5. null types
          if ( is_null($content) || preg_match(static::PATTERN_NULL_TYPE, trim($content), $matches) ) {
            $content = 'IS ' . (@$matches[1][0] == '!' ? 'NOT ' : '') . 'null';
            $subQuery[] = "$content";
          }
          else

          // 6. Plain string.
          if ( is_string($content) ) {
            // note: Unescaped *, % or _ characters
            if ( ctype_print($content) && preg_match('/[^\\][\\*%_]/', $content) ) {
              $operator = 'LIKE';
            }
            else {
              $operator = '=';
            }

            if ( preg_match('/^!\'([^\']*)\'$/', $content, $matches) ) {
              $subQuery[] = "NOT $operator ?";
              $content = $matches[1];
            }
            else {
              $subQuery[] = "$operator ?";
            }

            $params[] = $content;
          }
        });

        $_inValues = array_diff($inValues, $notInValues);
        $_notInValues = array_diff($notInValues, $inValues);

        // Group equality comparators (=) into IN (...) statement.
        if ( $_inValues ) {
          $params = array_merge($params, $_inValues);
          $subQuery[] = 'IN (' . Utility::fillArray($_inValues) . ')';
        }

        if ( $_notInValues ) {
          $params = array_merge($params, $_notInValues);
          $subQuery[] = 'NOT IN (' . Utility::fillArray($_notInValues) . ')';
        }

        unset($inValues, $notInValues, $_inValues, $_notInValues);

        $subQuery = array_map(prepends(Database::escapeField($field, $tableName) . ' '), $subQuery);

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
        $columnsSorter = Utility::isAssoc($sorter) ?
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

            $query[] = Database::escapeField($field, $tableName) . ($direction ? ' ASC' : ' DESC');
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

    return array(
        'filter' => $filter,
        'select' => $selectField,
        'indexHints' => $indexHints,
        'table' => $tableName,
        'query' => $queryString,
        'limits' => $limits,
        'params' => $params,
        'required' => $fieldsRequired
      );
  }

  private static function filterWalker($content, $data) {
    $field = $data['field'];
    $required = $data['required'];

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
        if ( $content != $value ) {
          return false;
        }
      }

      // Required fields
      else if ( $required && !isset($value) ) {
        return false;
      }

      // Boolean comparison: true, false
      else if ( is_bool($content) ) {
        if ( $content !== (bool) $value ) {
          return false;
        }
      }

      // null type: null
      else if ( is_null($content) ) {
        if ( !is_null($value) ) {
          return false;
        }
      }

      // Regexp matching: "/^AB\d+/"
      else if ( @preg_match($content, null) !== false ) {
        if ( preg_match($content, $value) == 0 ) {
          return false;
        }
      }

      // Numeric or null type comparison: direct evals;
      else if ( preg_match(static::PATTERN_NUMERIC, $content, $matches) || preg_match(static::PATTERN_NULL_TYPE, $content, $matches) ) {
        if ( !eval("return \$value$content;") ) {
          return false;
        }
      }

      // Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc
      else if ( preg_match(static::PATTERN_DATETIME, $content, $matches) ) {
        if ( !eval("return strtotime(\$value)$matches[1]strtotime($matches[2]);") ) {
          return false;
        }
      }

      // Plain string
      else if ( !$matches && is_string($content) && !preg_match('/^(<|<=|==|>=|>)/', $content) ) {
        if ( $content !== $value ) {
          return false;
        }
      }

      return true;
    }
  }

  public static /* int */
  function nodeSorter($itemA, $itemB) {
    $itemIndex = strcmp($itemA[self::FIELD_COLLECTION], $itemB[self::FIELD_COLLECTION]);

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
   * @param $contents An array of data to be updated, data row will be identified by id.
   * @param $extendExists true means $contents can be partial update, fields not specified
   *        will have their old value retained instead of replacing the whole row.
   *
   * @return Array of Booleans, true on success of specific row, false otherwises.
   *
   * @throws CoreException thrown when $contents did not specify a collection.
   * @throws CoreException thrown when more than one row is selected with the provided keys,
   *                       and $extendExists is true.
   */
  static /* Array */
  function set($contents = null, $extendExists = false) {
    if ( !self::ensureConnection() ) {
      return false;
    }

    if ( !$contents ) {
      return array();
    }

    $contents = Utility::wrapAssoc($contents);

    $result = array();

    foreach ( $contents as $row ) {
      if ( !is_array($row) || !$row ) {
        continue;
      }

      if ( !trim(@$row[self::FIELD_COLLECTION]) ) {
        throw new CoreException('Data object must specify a collection with property "'.self::FIELD_COLLECTION.'".', 302);

        continue;
      }

      $tableName = self::resolveCollection($row[self::FIELD_COLLECTION]);

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
          self::FIELD_COLLECTION => $tableName === self::BASE_COLLECTION ?
            $row[self::FIELD_COLLECTION] : $tableName
        );

        $res+= array_select($row, $keys);

        $res = node::get($res);

        if ( count($res) > 1 ) {
          throw new CoreException('More than one row is selected when extending '.
            'current object, please provide ALL keys when calling with $extendExists = true.', 303);
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
        if ( $field !== self::FIELD_VIRTUAL && in_array($field, array_merge($keys, $cols)) ) {
          $data[$field] = $fieldContents;

          unset($row[$field]);
        }
      }

      // Do not pass in @collection as data row below, physical tables only.
      if ( @$row[self::FIELD_COLLECTION] !== self::BASE_COLLECTION ) {
        unset($row[self::FIELD_COLLECTION]);
      }

      // Encode the rest columns and put inside virtual field.
      // Skip the whole action when `@contents` columns doesn't exists.
      if ( in_array(self::FIELD_VIRTUAL, $cols) ) {
        array_walk_recursive($row, function(&$value) {
          if ( is_resource($value) ) {
            $value = get_resource_type($value);
          }
        });

        // Silently swallow json encode errors.
        $data[self::FIELD_VIRTUAL] = @ContentEncoder::json($row);

        // Defaults to be an empty object.
        if ( !$data[self::FIELD_VIRTUAL] || $data[self::FIELD_VIRTUAL] == '[]' ) {
          $data[self::FIELD_VIRTUAL] = '{}';
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
   * @return The total number of affected rows.
   */
  static /* int */
  function delete($filter = null, $fieldsRequired = false, $limit = null) {
    // Shortcut for TRUNCATE TABLE
    if ( is_string($filter) ) {
      $tableName = self::resolveCollection($filter);

      // Delete all fields of specified collection name.
      if ( $tableName == self::BASE_COLLECTION ) {
        $res = Database::query('DELETE FROM `'.self::BASE_COLLECTION.'` WHERE `@collection` = ?', [$filter]);

        return $res->rowCount();
      }
      else if ( Database::hasTable($tableName) ) {
        return Database::truncateTable($tableName);
      }

      unset($tableName);
    }

    $res = self::get($filter, $fieldsRequired, $limit);

    $affectedRows = 0;

    foreach ( $res as $key => $row ) {
      $tableName = self::resolveCollection($row[self::FIELD_COLLECTION]);

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
    if ( !self::ensureConnection() ) {
      return null;
    }

    if ( !Database::hasTable($tableName) ) {
      return self::BASE_COLLECTION;
    }
    else {
      return $tableName;
    }
  }

  private static /* void */
  function ensureConnection() {
    if ( !Database::isConnected() ) {
      if ( error_reporting() ) {
        throw new CoreException('Database is not connected.', 300);
      }
      else {
        return false;
      }
    }

    return true;
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
   * @return true on success, false otherwise;
   *
   * @throws CoreException thrown when the call tries to make a virtual collection physical.
   * @throws CoreException thrown when target table is no virtual column field.
   * @throws CoreException thrown when no physical field description ($fieldDesc) is given.
   */
  public static /* Boolean */
  function makePhysical($collection, $fieldName = null, $fieldDesc = null) {
    // Create table
    if ( !Database::hasTable($collection) ) {
      throw new CoreException('Table creation is not supported in this version.');

      // Mimic result after table creation.
      if ( $fieldName === null ) {
        return true;
      }
    }

    $fields = Database::getFields($collection);

    if ( !in_array(self::FIELD_VIRTUAL, $fields) ) {
      throw new CoreException( 'Specified table `'
                             . $collection
                             . '` does not support virtual fields, no action is taken.',
                             305
                             );
    }

    if ( $fieldName !== null && !$fieldDesc ) {
      throw new CoreException('You must specify $fieldDesc when adding physical column.', 306);
    }

    $field = Database::escapeField($fieldName);

    $res = Database::query( 'ALTER TABLE ' . $collection . ' ADD COLUMN '
                          . $field . ' ' . str_replace(';', '', $fieldDesc)
                          );

    if ( $res === false ) {
      return false;
    }

    $fields = Database::getFields($collection, array('PRI'));

    array_push($fields, self::FIELD_VIRTUAL, $fieldName);

    $fields = Database::escapeField($fields);

    // Data migration.
    $migrated = true;

    $res = Database::query('SELECT ' . implode(', ', $fields) . ' FROM ' . $collection);

    $res->setFetchMode(\PDO::FETCH_ASSOC);

    foreach ( $res as $row ) {
      $contents = ContentDecoder::json($row[self::FIELD_VIRTUAL], true);

      $row[$fieldName] = $contents[$fieldName];

      unset($contents[$fieldName]);

      $row[self::FIELD_VIRTUAL] = @ContentEncoder::json($contents);
      $row[self::FIELD_COLLECTION] = $collection;

      if ( self::set($row) === false ) {
        $migrated = false;
      }
    }

    return $migrated;
  }
}
