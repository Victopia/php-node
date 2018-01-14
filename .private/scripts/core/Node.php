<?php /* Node.php | The node data framework. */

namespace core;

use PDO;

use core\exceptions\CoreException;

/**
 * Node entity access class.
 */
class Node implements \Iterator, \ArrayAccess, \Countable {

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
  const PATTERN_DATETIME = '/^(<|<=|==|!=|>=|>)?\s*\'([0-9- \.:TZ\+]+)\'\s*$/';

  /**
   * Regex pattern to match null type filter expressions.
   */
  const PATTERN_NULL_TYPE = '/^((?:==|!=)=?)\s*null\s*$/i';

  /**
   * Number of rows to fetch when virtual column is in sight.
   */
  public static $fetchSize = 200;

  /**
   * Underlying PDOStatement
   */
  protected $statement = null;

  /**
   * Current data offset
   */
  protected $offset = -1;

  /**
   * Current fetched data object
   */
  protected $data = null;

  /**
   * @constructor
   *
   * Node instances as an iteratrable, countable wrapper.
   */
  public function __construct($filter) {
    $filter = static::composeQuery($filter);

    if ( !$filter ) {
      throw new CoreException('Invalid filter specified.');
    }

    $filter['table'] = "`$filter[table]`";

    if ( $filter['filter'] ) {
      if ( is_string($filter['indexHints']) ) {
        $filter['table'].= " $filter[indexHints]";
      }
      else if ( is_array($filter['indexHints']) && array_key_exists($filter['indexHints'], $filter['table']) ) {
        $filter['table'] = "$filter[table] {$filter['indexHints']['$tableName']}";
      }
    }
    else {
      if ( is_array($filter['indexHints']) && array_key_exists($filter['table'], $filter['indexHints']) ) {
        $filter['indexHints'] = $filter['indexHints'][$filter['table']];
      }
    }

    // note; When all fields are not virtual, we can safely apply limits to query.
    if ( !$filter['filter'] ) {
      if ( $filter['limits'] ) {
        $filter['query'].= ' LIMIT ' . implode(', ', $filter['limits']);
      }
    }

    $query = "SELECT $filter[select] FROM $filter[table]$filter[query]";

    unset($filter['select']);

    if ( !$filter['limits'] ) {
      $filter['limits'] = [ 0, PHP_INT_MAX ];
    }

    $filter['indexMap'] = [];

    // note; virtual only affects context limit, it does not
    $this->statement = Database::query($query, $filter['params'],
      [ PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL
      ]);

    unset($query);

    // note; virtual fields will not use this for counting.
    if ( $filter['filter'] ) {
      unset($filter['indexHints'], $filter['query']);
    }

    $this->context = $filter;

    $this->rewind();
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : Iterator
  //
  //----------------------------------------------------------------------------

  public function current() {
    return $this->data;
  }

  public function key() {
    return $this->offset;
  }

  public function next() {
    // note; stop fetching when offset exceed desired limit
    $limits = @$this->context['limits'];
    if ( $limits && $this->offset > $limits[0] + $limits[1] ) {
      return;
    }

    $this->offset++;

    // todo; fetch the next shit from PDOStatement and store the data.
    if ( $this->statement ) {
      $offset = &$this->context['indexMap'][$this->offset];

      // // note;warning; Mysql does not support scrollable cursor.
      // if ( $offset !== null ) {
      //   $data = $this->statement->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_ABS, $offset);
      //   $data = $this->decodesContent($data);
      // }
      // else {
        $offset = $this->context['limits'][0];

        if ( $this->context['filter'] ) {
          while ( empty($data) ) {
            $data = $this->statement->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_ABS, $offset);
            if ( !$data ) {
              unset($this->context['indexMap'][$this->offset]); // remove the exceed offset
              break; // no more data
            }

            $data = $this->decodesContent($data);
            if ( $data ) {
              break; // data found
            }

            $offset++;
          }

          $this->context['limits'][0] = $offset;
          if ( empty($data) ) {
            $this->context['limits'][1] = $offset;
          }
          else {
            $this->offset = $offset;
          }
        }
        else {
          $data = $this->statement->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_ABS, $offset);
          if ( $data ) {
            $data = $this->decodesContent($data);
          }
          else {
            unset($this->context['indexMap'][$this->offset]); // remove the exceed offset
          }
        }
      // }

      $this->data = @$data ? $data : null;
    }

    return $this;
  }

  public function rewind() {
    if ( $this->offset != 0 ) {
      $this->offset = -1;
      $this->data = null;

      // note; fucking creates the PDOStatement again, no scrollable cursor.
      $this->reload();
      $this->next();
    }

    return $this;
  }

  public function valid() {
    return $this->offset < 0 || $this->data;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : ArrayAccess
  //
  //----------------------------------------------------------------------------

  public function offsetExists($offset) {
    if ( !is_numeric($offset) ) {
      return false;
    }

    $this->seek($offset);

    return isset($this->context['indexMap'][$offset]);
  }

  public function offsetGet($offset) {
    if ( $this->offsetExists($offset) ) {
      return $this->data;
    }
  }

  public function offsetSet($offset, $value) {
    throw new CoreException('Node::offsetSet() is ambiguous thus not implemented.');
  }

  public function offsetUnset($offset) {
    if ( $this->offsetExists($offset) ) {
      Node::delete($this->data);

      $this->context['indexMap'] = [];
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Methods : Countable
  //
  //----------------------------------------------------------------------------

  public function count() {
    $context = &$this->context;

    if ( $context['filter'] ) {
      $this->rewind();

      while ($this->valid()) $this->next();

      return count($context['indexMap']);
    }
    else {
      $count = Database::fetchField(
        "SELECT COUNT(*) FROM $context[table] $context[indexHints]$context[query]",
        $context['params']);

      // note; Since LIMIT in SQL does not restrict COUNT(*), we can only do this.
      return (int) min($context['limits'][1], $count);
    }
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Refresh the underlying result set to retrieve new data changes.
   *
   * @return {Node} Chainable.
   */
  public function reload() {
    $this->statement = Database::query(
      $this->statement->queryString,
      $this->context['params'],
      [ PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ]
    );

    return $this;
  }

  /**
   * Move the cursor to target offset, halts when end of result is reached.
   */
  protected function seek($offset) {
    if ( $this->offset > $offset ) {
      $this->rewind();
    }

    while ( $this->valid() && $this->offset < $offset ) {
      $this->next();
    }
  }

  /**
   * Fetch the whole result and return as an array.
   */
  public function toArray() {
    $result = [];

    foreach ($this as $value) {
      $result[] = $value;
    }

    return $result;
  }

  //----------------------------------------------------------------------------
  //
  //  Static Methods
  //
  //----------------------------------------------------------------------------

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
   *                          4.1 @limits Either $length, or [$offset, $length].
   *                          4.2 @sorter Either [ $field1 => $isAscending, $field2 => $isAscending ], or a compare function.
   * @param $limits (mixed) Can be integer specifying the row count from
   *                       first row, or an array specifying the starting
   *                       row and row count.
   * @param $sorter (array) Optional. 1. fields to be ordered ascending, or
   *                                  2. Hashmap with fields as keys and boolean values
   *                                  with true interprets as ascending and false otherwise.
   *
   * @return Array of filtered data rows.
   */
  static /* array */ function get($filter, $limits = null, $sorter = null) {
    if ( $limits !== null && (!$limits || !is_int($limits) && !is_array($limits)) ) {
      return [];
    }

    // Defaults string to collections.
    if ( is_string($filter) ) {
      $filter = [ static::FIELD_COLLECTION => $filter ];
    }

    if ( $limits !== null ) {
      $filter['@limits'] = $limits;
    }

    if ( $sorter !== null ) {
      $filter['@sorter'] = $sorter;
    }

    return new Node($filter);
  }

  /**
   * Get the first matching item.
   *
   * @see Node#getAync()
   */
  static function getOne($filter) {
    // Defaults string to collections.
    if ( is_string($filter) ) {
      $filter = [ static::FIELD_COLLECTION => $filter ];
    }

    // Force length to be 1
    if ( is_array(@$filter['@limits']) ) {
      $filter['@limits'][1] = 1;
    }
    else {
      $filter['@limits'] = 1;
    }

    return static::get($filter)->rewind()->current();
  }

  static function getCount($filter) {
    return count(new Node($filter));
  }

  /**
   * New cursor approach, invokes $dataCallback the moment we found a matching row.
   *
   * @return An array of return values from every invokes to $dataCallback, works like array_map().
   */
  static function getAsync($filter, $dataCallback) {
    $result = [];
    foreach (static::get($filter) as $value) {
      $result = $dataCallback($value);
    }
    return $result;
  }

  private static function composeQuery($filter) {
    // Defaults string to collections.
    if ( is_string($filter) ) {
      $filter = [ static::FIELD_COLLECTION => $filter ];
    }

    $limits = @$filter['@limits'];
    $sorter = @$filter['@sorter'];

    if ( $limits !== null && (!$limits || !is_int($limits) && !is_array($limits)) ) {
      return false;
    }

    if ( !is_array($filter) ) {
      return false;
    }

    $collectionName = @$filter[static::FIELD_COLLECTION];

    $tableName = static::resolveCollection($collectionName);

    if ( empty($collectionName) ) {
      $collectionName = $tableName;
    }

    if ( $tableName !== static::BASE_COLLECTION ) {
      unset($filter[static::FIELD_COLLECTION]);
    }

    /* If index hint is specified, append the clause to collection. */
    if ( @$filter[static::FIELD_INDEX] ) {
      $indexHints = $filter[static::FIELD_INDEX];
    }
    else {
      $indexHints = null;
    }

    /* Removes the index hint field. */
    unset($filter[static::FIELD_INDEX]);

    if ( isset($filter[static::FIELD_SELECT]) ) {
      if ( is_array($filter[static::FIELD_SELECT]) ) {
        $selectField = implode(', ', $filter[static::FIELD_SELECT]);
      }
      else {
        $selectField = (string) $filter[static::FIELD_SELECT];
      }
    }
    else {
      $selectField = '*';
    }

    unset($filter[static::FIELD_SELECT]);

    $queryString = '';

    $query = [];
    $params = [];

    /* Merge $filter into SQL statements. */
    $columns = Database::getFields($tableName);

    if ( isset($filter[static::FIELD_RAWQUERY]) ) {
      $rawQuery = (array) $filter[static::FIELD_RAWQUERY];

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

      unset($filter[static::FIELD_RAWQUERY], $rawQuery);
    }

    // Pick real columns for SQL merging
    $columnsFilter = array_filter_keys(
      $filter,
      function($key) use($columns) {
        return in_array($key, $columns) ||
          array_reduce($columns, function($result, $column) use($key) {
            return $result || strpos($key, "`$column`") !== false;
          }, false);
      }
    );

    /* Merge $filter into SQL statement. */
      array_walk($columnsFilter, function(&$contents, $field) use(&$query, &$params, $tableName) {
        $queryOptions = [
          'operator' => 'OR'
        ];

        if ( is_array($contents) && array_key_exists('@options', $contents) ) {
          // note; Pick up query options before processing
          $queryOptions = (array) @$contents['@options'] + $queryOptions;

          unset($contents['@options']);
        }

        $contents = Utility::wrapAssoc($contents);

        $subQuery = [];

        // Explicitly groups equality into expression: IN (...[, ...])
        $inValues = [];

        // Explicitly groups equality into expression: NOT IN (...[, ...])
        $notInValues = [];

        array_walk($contents, function($content) use(&$subQuery, &$params, &$inValues, &$notInValues) {
          // Error checking
          if ( is_array($content) ) {
            throw new CoreException('Node does not support composite array types.', 301);
          }

          // 1. Advanced expression, class should __toString() itself.
          if ( $content instanceof NodeExpression ) {
            $subQuery[] = "$content";
            $params = array_merge($params, $content->params());
          }

          // 1. Boolean comparison: true, false
          else if ( is_bool($content) ) {
            $subQuery[] = 'IS ' . ($content ? 'TRUE' : 'FALSE');
          }
          else if ( preg_match(static::PATTERN_BOOL, trim($content), $matches) ) {
            $content = 'IS ';

            if ( $matches[1] != '==' ) {
              $content.= 'NOT ';
            }

            if ( $matches[2] == 'true' ) {
              $content.= 'TRUE';
            }
            else {
              $content.= 'FALSE';
            }

            $subQuery[] = $content;
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

          // 4. null types
          if ( is_null($content) || preg_match(static::PATTERN_NULL_TYPE, trim($content), $matches) ) {
            $content = 'IS ' . (@$matches[1][0] == '!' ? 'NOT ' : '') . 'null';
            $subQuery[] = "$content";
          }
          else

          // 5. Regular Expression
          if ( ctype_print($content) && @preg_match($content, null) !== false ) {
            $subQuery[] = "REGEXP ?";
            $params[] = substr($content, 1, -1);
          }
          else

          // 6. Plain string.
          if ( is_string($content) ) {
            // note: Unescaped *, % or _ characters
            if ( preg_match('/[\*\%\_]/', preg_replace('/\\\[\\\*\%\_]/', '', $content)) ) {
              $operator = 'LIKE';
            }
            else {
              $operator = '=';
            }

            if ( preg_match('/^(!|=)=?\'([^\']*)\'$/', $content, $matches) ) {
              if ( $matches[1] == '!' ) {
                $subQuery[] = "NOT $operator ?";
              }
              else {
                $subQuery[] = "$operator ?";
              }

              $content = $matches[2];
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
          $query[] = '(' . implode(" $queryOptions[operator] ", $subQuery) . ')';
        }
      });

      // Remove real columns from the filter
      remove($columns, $filter);

      $queryString = $query ? ' WHERE ' . implode(' AND ', $query) : null;
    /* Merge $filter into SQL statement, end of. */

    /* Merge $sorter into SQL statement. */
      if ( is_array($sorter) ) {
        $columnsSorter = call_user_func(
          Utility::isAssoc($sorter) ? 'array_filter_keys' : 'array_filter',
          $sorter,
          function($key) use($columns) {
            return in_array($key, $columns) || array_reduce(
              $columns,
              function($result, $column) use($key) {
                return $result || false !== strpos($key, "`$column`") || false !== strpos($key, '()');
              },
              false
            );
          }
        );

        // We are free to reuse $query at this point.
        $query = [];

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
      $limits = [ 0, $limits ];
    }
    elseif ( is_array($limits) ) {
      $limits = array_slice($limits, 0, 2) + [ 0, 0 ];
    }

    // note; Everything not in $columnsFilter goes into $filter
    $filter = array_filter_keys($filter, funcAnd(
      notIn(array_keys($columnsFilter)),
      compose('not', startsWith('@'))
    ));

    return
      [ 'filter' => $filter
      , 'select' => $selectField
      , 'indexHints' => $indexHints
      , 'collection' => $collectionName
      , 'table' => $tableName
      , 'query' => $queryString
      , 'limits' => $limits
      , 'params' => $params
      ];
  }

  protected function decodesContent($row) {
    if ( isset($row[static::FIELD_VIRTUAL]) ) {
      $contents = (array) ContentDecoder::json($row[static::FIELD_VIRTUAL], true);

      unset($row[static::FIELD_VIRTUAL]);

      if ( is_array($contents) ) {
        $row = $contents + $row;
      }

      unset($contents);
    }

    foreach ( (array) $this->context['filter'] as $field => $expr ) {
      if ( !static::filterWalker($row, $field, $expr) ) {
        return false;
      }
    }

    $row[static::FIELD_COLLECTION] = $this->context['collection'];

    return $row;
  }

  private static function filterWalker($data, $field, $expr) {
    $prop = prop(explode('.', $field));

    $value = $prop($data);

    unset($prop);

    if ( is_array($expr) ) {
      // OR operation here.
      foreach ( $expr as $value ) {
        if ( static::filterWalker($data, $field, $value) ) {
          return true; // pop a true right away.
        }
      }

      return false;
    }
    else {
      // Check field existance.
      if ( $expr && !isset($value) ) {
        return false;
      }

      // Normalize numeric values into exact match.
      else if ( is_numeric($expr) ) {
        if ( $expr != $value ) {
          return false;
        }
      }

      // Boolean comparison: true, false
      else if ( is_bool($expr) ) {
        if ( $expr !== (bool) $value ) {
          return false;
        }
      }

      // null type: null
      else if ( is_null($expr) ) {
        if ( !is_null($value) ) {
          return false;
        }
      }

      // Regexp matching: "/^AB\d+/"
      else if ( $expr instanceof NodeRegularExpression ) {
        if ( preg_match($expr->pattern(), $value) == 0 ) {
          return false;
        }
      }

      else if ( @preg_match($expr, null) !== false ) {
        if ( preg_match($expr, $value) == 0 ) {
          return false;
        }
      }

      // Numeric or null type comparison: direct evals;
      else if ( preg_match(static::PATTERN_NUMERIC, $expr, $matches) || preg_match(static::PATTERN_NULL_TYPE, $expr, $matches) ) {
        if ( !eval("return \$value$expr;") ) {
          return false;
        }
      }

      // Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc
      else if ( preg_match(static::PATTERN_DATETIME, $expr, $matches) ) {
        if ( !eval("return strtotime(\$value)$matches[1]strtotime($matches[2]);") ) {
          return false;
        }
      }

      // Plain string
      else if ( !$matches && is_string($expr) && !preg_match('/^(<|<=|==|>=|>)/', $expr) ) {
        if ( $expr !== $value ) {
          return false;
        }
      }

      return true;
    }
  }

  public static /* int */
  function nodeSorter($itemA, $itemB) {
    $itemIndex = strcmp($itemA[static::FIELD_COLLECTION], $itemB[static::FIELD_COLLECTION]);

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
    if ( !static::ensureConnection() ) {
      return false;
    }

    if ( !$contents ) {
      return [];
    }

    $contents = Utility::wrapAssoc($contents);

    $result = [];

    foreach ( $contents as $row ) {
      if ( !is_array($row) || !$row ) {
        continue;
      }

      if ( !trim(@$row[static::FIELD_COLLECTION]) ) {
        throw new CoreException('Data object must specify a collection with property "'.static::FIELD_COLLECTION.'".', 302);

        continue;
      }

      $tableName = static::resolveCollection($row[static::FIELD_COLLECTION]);

      // Get physical columns of target collection,
      // merges into SQL for physical columns.
      $res = Database::getFields($tableName, null, false);

      // This is used only when $extendExists is true,
      // contains primary keys and unique keys for retrieving
      // the exact existing object.
      $keys = array_filter($res, propHas('Key', [ 'PRI', 'UNI' ]));

      // Normal columns for merging SQL statements.
      $cols = array_diff_key($res, $keys);

      $keys = array_keys($keys);
      $cols = array_keys($cols);

      // Composite a filter and call static::get() for the existing object.
      // Note that this process will break when one of the primary key
      // is not provided inside $content object, thus unable to search
      // the exact row.
      if ( $extendExists === true ) {
        $res =
          [ static::FIELD_COLLECTION =>
              $tableName === static::BASE_COLLECTION ? $row[static::FIELD_COLLECTION] : $tableName
          ];

        $res+= array_select($row, $keys);

        $res = static::get($res);

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
      $data = [];

      foreach ( $row as $field => $fieldContents ) {
        // Physical columns exists, pass in.
        if ( $field !== static::FIELD_VIRTUAL && in_array($field, array_merge($keys, $cols)) ) {
          $data[$field] = $fieldContents;

          unset($row[$field]);
        }
      }

      // Do not pass in @collection as data row below, physical tables only.
      if ( @$row[static::FIELD_COLLECTION] !== static::BASE_COLLECTION ) {
        unset($row[static::FIELD_COLLECTION]);
      }

      // Encode the rest columns and put inside virtual field.
      // Skip the whole action when `@contents` columns doesn't exists.
      if ( in_array(static::FIELD_VIRTUAL, $cols) ) {
        array_walk_recursive($row, function(&$value) {
          if ( is_resource($value) ) {
            $value = get_resource_type($value);
          }
        });

        ksort($row);

        // Silently swallow json encode errors.
        $data[static::FIELD_VIRTUAL] = @ContentEncoder::json($row, JSON_NUMERIC_CHECK);

        // Defaults to be an empty object.
        if ( !$data[static::FIELD_VIRTUAL] || $data[static::FIELD_VIRTUAL] == '[]' ) {
          $data[static::FIELD_VIRTUAL] = '{}';
        }
      }

      $result[] = Database::upsert($tableName, $data, false);
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
   *
   * @return The total number of affected rows.
   */
  static /* int */
  function delete($filter = null, $limit = null) {
    // Shortcut for TRUNCATE TABLE
    if ( is_string($filter) ) {
      $tableName = static::resolveCollection($filter);

      // Delete all fields of specified collection name.
      if ( $tableName == static::BASE_COLLECTION ) {
        $res = Database::query('DELETE FROM `'.static::BASE_COLLECTION.'` WHERE `@collection` = ?', [$filter]);

        return $res->rowCount();
      }
      else if ( Database::hasTable($tableName) ) {
        return Database::truncateTable($tableName);
      }

      unset($tableName);
    }

    $res = static::get($filter, $limit);

    $affectedRows = 0;

    foreach ( $res as $key => $row ) {
      $tableName = static::resolveCollection($row[static::FIELD_COLLECTION]);

      $fields = Database::getFields($tableName, [ 'PRI', 'UNI' ]);

      $deleteKeys = [];

      foreach ( $fields as &$field ) {
        if ( array_key_exists($field, $row) ) {
          if ( !is_array(@$deleteKeys[$field]) ) {
            $deleteKeys[$field] = [];
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
  //  Private helper methods
  //
  //-----------------------------------------------------------------------

  public static /* String */
  function resolveCollection($tableName) {
    if ( !is_string($tableName) ) {
      throw new CoreException('Collection name must be string.');
    }

    if ( !static::ensureConnection() ) {
      return null;
    }

    if ( !Database::hasTable($tableName) ) {
      return static::BASE_COLLECTION;
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
  //  DML
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

    if ( !in_array(static::FIELD_VIRTUAL, $fields) ) {
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

    $fields = Database::getFields($collection, [ 'PRI' ]);

    array_push($fields, static::FIELD_VIRTUAL, $fieldName);

    $fields = Database::escapeField($fields);

    // Data migration.
    $migrated = true;

    $res = Database::query('SELECT ' . implode(', ', $fields) . ' FROM ' . $collection);

    $res->setFetchMode(PDO::FETCH_ASSOC);

    foreach ( $res as $row ) {
      $contents = (array) ContentDecoder::json($row[static::FIELD_VIRTUAL], true);

      $row[$fieldName] = $contents[$fieldName];

      unset($contents[$fieldName]);

      $row[static::FIELD_VIRTUAL] = @ContentEncoder::json($contents, JSON_NUMERIC_CHECK);
      $row[static::FIELD_COLLECTION] = $collection;

      if ( static::set($row) === false ) {
        $migrated = false;
      }
    }

    return $migrated;
  }
}
