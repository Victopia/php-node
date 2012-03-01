<?php

/**
 * Node entity access class.
 */
class Node
{
	/**
	 *  Retrieves all nodes with the specified name, as return as an array.
	 *
	 *  @param $name A string specifying the node name. e.g. User
	 *  @param $filter An array of fields to be searched.
	 *         ID  - Integer or array of integers, will be merged into 
	 *               the query string to improve performance.
	 *         ... - Any type of node and field names.
	 *  @param $fieldsRequired If TRUE, rows must contain all fields 
	 *         specified in argument filter to survive.
	 *  @param $limit Can be integer specifying the row count from first 
	 *         row, or an array specifying the starting row and row count.
	 *  
	 *  @returns Array of filtered data rows.
	 */
	static function get($filter, $fieldsRequired = FALSE, $limit = NULL)
	{
		if (!Is_Int($limit) && 
			(Utility::IsAssociative($limit) || 
			(Is_Array($limit) && Count($limit) != 2)))
		{
			throw new ServiceException('Parameter 3 $limit must be a numeric array exactly with two elements, or an integer.');
			
			return NULL;
		}
		
		$query = Array();
		$params = Array();
		
		/* Merge into SQL statements. */
		$columns = Database::query('SHOW COLUMNS FROM ' . NODE_TABLENAME);
		$columns = $columns->fetchAll(PDO::FETCH_COLUMN, 0);
		
		if ( Is_Array($filter) ) {
			foreach ($filter as $field => &$contents) {
				if ( !Is_Array($contents) ) {
					$contents = Array($contents);
				}
				
				$subQuery = Array();
				
				foreach ($contents as $key => &$content) {
					// Rational column exists, start merging according to data types.
					if (Array_Search($field, $columns) !== FALSE) {
						$escapedField = Database::escape($field);
						
						// 1. Numeric comparison: 3, <10, >=20, ==3.5 ... etc.
						if (preg_match('/^(<|<=|==|>=|>)?(\d+)$/', $content, $matches) && 
							Count($matches) > 2)
						{
							if (!$matches[1] || $matches[1] == '==') {
								$matches[1] = '=';
							}
							
							$subQuery[] = "$escapedField $matches[1] ?";
							$params[] = $matches[2];
							unset($contents[$key]);
						}
						else
						
						// 2. Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc.
						if (preg_match('/^(<|<=|==|>=|>)\'([0-9- :]+)\'$/', $content, $matches) &&
							Count($matches) > 2 && strtotime($matches[2]) !== FALSE)
						{
							$subQuery[] = "$escapedField $matches[1] ?";
							$params[] = $matches[2];
							unset($contents[$key]);
						}
						else
						
						// 3. Regexp matching: "/^AB\d+/"
						if (preg_match('/^\/([^\/]+)\/g?i?$/i', $content, $matches) &&
							Count($matches) > 1)
						{
							$subQuery[] = "$escapedField REGEXP ?";
							$params[] = $matches[1];
							unset($contents[$key]);
						}
						else
						
						// 5. Boolean comparison: true, false
						if (Is_Bool($content)) {
							$subQuery[] = "$escapedField = ?";
							$params[] = $content;
							unset($contents[$key]);
						}
						else
						
						// 7. Plain string.
						if ( Is_String($content) ) {
							$subQuery[] = "$escapedField LIKE ?";
							$params[] = $content;
							unset($contents[$key]);
						}
					}
				}
				
				if (Count($contents) == 0) {
					unset($filter[$field]);
				}
				
				if ( Count($subQuery) > 0 ) {
					$query[] = '(' . Implode(' OR ', $subQuery) . ')';
				}
			}
		}
		/* Merge into SQL statements, end of. */
		
		$query = Count($query) > 0 ? ' WHERE ' . implode(' AND ', $query) : NULL;
		$query.= ' ORDER BY `identifier`, `ID`';
		
		$rowCount = Database::fetchField('SELECT COUNT(*) FROM ' . Database::escape(NODE_TABLENAME) . $query, $params);
		$rowOffset = 0;
		
		if ($limit !== NULL && Is_Int($limit)) {
			$limit = Array(0, $limit);
		}
		
		$result = Array();
		
		while ($rowCount > 0 && ($limit === NULL || $limit[1] > 0)) {
			$res = Database::select(NODE_TABLENAME, '*', "$query LIMIT $rowOffset, " . DATA_FETCHSIZE, $params);
			
			foreach ($res as $key => &$row) {
				$row['content'] = JSON_Decode($row['content'], true);
				
				$row['content']['ID'] = $row['ID'];
				$row['content']['identifier'] = $row['identifier'];
				$row['content']['timestamp'] = $row['timestamp'];
				
				$row = $row['content'];
				
				/* Start filtering. */
				if ( Is_Array($filter) ) {
					$walker = <<<FUNC
					\$row = \$data['row'];
					\$field = \$data['field'];
					\$required = \$data['fieldsRequired'];
					
					// Just skip the field on optional matcher.
					if ( !\$required && !isset(\$row[\$field]) ) {
					    return;
					}
					
					\$value = &\$row[\$field];
					
					unset(\$row);
					
					if (
					// For required fields.
					    \$required && !isset(\$value) || 
					// Numeric comparison: <10, >=20, ==3.5 ... etc
					    ( preg_match('/^(<|<=|==|>=|>)\d+$/', \$content) && !eval('return \$value' . \$content . ';') ) ||
					// Datetime comparison: <'2010-07-31', >='1989-06-21' ... etc
					    ( preg_match('/^(<|<=|==|>=|>)\'([0-9- :]+)\'$/', \$content, \$matches ) && 
					    !eval('return strtotime(\$value)' . \$matches[1] . strtotime(\$matches[2]) . ';')) ||
					// Regexp matching: "/^AB\d+/"
					    ( preg_match('/^\/.+\/g?i?$/i', \$content) ? preg_match( \$content, \$value) == 0 : 
					// Plain string
					    Is_String(\$content) && \$content !== \$value ) ||
					// Boolean comparison: true, false
					    ( Is_Bool(\$content) && \$content !== (bool) \$value )
					    )
					{
						//throw new Exception( !preg_match( \$content, \$value) );
					    \$data['row'] = NULL;
					}
FUNC;
					$walker = create_function('$content, $key, $data', $walker);
					
					foreach ($filter as $field => $expr) {
						// Break out on null.
						if ( $row === NULL ) {
							break;
						}
						
						array_walk_recursive($expr, $walker, array(
							'row' => &$row,
							'field' => $field,
							'matches' => $matches,
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
			
			$rowOffset += DATA_FETCHSIZE;
			$rowCount -= DATA_FETCHSIZE;
		}
		
		usort($result, array('self', 'nodeSorter'));
		
		return $result;
	}
	
	public static function nodeSorter($itemA, $itemB)
	{
		$itemIndex = strcmp($itemA['identifier'], $itemB['identifier']);
		
		if ( $itemIndex === $itemIndex ) {
			$indexA = intval($itemA['ID']);
			$indexB = intval($itemB['ID']);
			
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
	 *  Upserts one or a set of data.
	 *  
	 *  Important: "timestamp" property in argument $contents will be removed.
	 *
	 *  @param $contents An array of data to be updated, data row will be identified by id.
	 *  @param $extendExists TRUE means $contents can be partial update, fields not specified 
	 *         will have their old value retained instead of replacing the whole row.
	 *
	 *  @returns Array of Booleans, TRUE on success of specific row, FALSE otherwises.
	 */
	static function set($contents = NULL, $extendExists = FALSE)
	{
		if ( $contents === NULL ) {
			$contents = Array();
		}
		
		if ( Utility::IsAssociative($contents) ) {
			$contents = Array($contents);
		}
		
		/* Merge into SQL for physical columns. */
		$columns = Database::query('SHOW COLUMNS FROM ' . NODE_TABLENAME);
		$columns = $columns->fetchAll(PDO::FETCH_COLUMN, 0);
		
		$result = Array();
		
		foreach ($contents as $row) {
			$fields = Array();
			
			if (isset($row['ID']) && $extendExists === TRUE) {
				$res = self::get( Array('ID' => $row['ID']) );
				
				if ( Count($res) > 0 ) {
					$row = Array_Merge($res[0], $row);
				}
				
				unset($res);
			}
			
			foreach ($row as $field => $contents) {
				// Physical columns exists, start merging.
				if ( In_Array($field, $columns) ) {
					$fields[$field] = $contents;
					
					unset($row[$field]);
				}
			}
			
			// Encode the rest of the data and put inside content field.
			$fields['content'] = JSON_Encode($row);
			
			$result[] = Database::upsert(NODE_TABLENAME, $fields);
		}
		
		if ( Count($result) == 1 ) {
			$result = $result[0];
		
		}
		
		return $result;
	}
	
	/**
	 *  Delete a data row.
	 *  
	 *  @param $filter 
	 *  
	 *  @returns The total number of affected rows.
	 */
	static function delete($filter = NULL, $fieldsRequired = FALSE)
	{
		$res = self::get($filter, $fieldsRequired);
		
		foreach ($res as $key => $row) {
			$res[$key] = $row['ID'];
		}
		
		return Database::delete(NODE_TABLENAME, $res);
	}
	
	//-----------------------------------------------------------------------
	//
	//  Relation based functions
	//
	//-----------------------------------------------------------------------
	// Node relations are merged into Node wrapper itself. - 29.Jan.2010
	
	/**
	 *  Get all direct parent nodes of target node, optionally specify a node type.
	 *
	 *  @param $childNodes Child node or an array of child nodes.
	 *
	 *  @returns Array of parent nodes of specified nodes.
	 */
	static function getParents($childNodes, $filter = NULL, $fieldsRequired = FALSE, $limit = NULL)
	{
		$childNodes = (Array)$childNodes;
		
		if ( Utility::isAssociative($childNodes) ) {
			$childNodes = Array($childNodes);
		}
		
		if ( Count($childNodes) == 0 ) {
			return NULL;
		}
		
		// Get all node ID.
		foreach ($childNodes as &$childNode) {
			$childNode = $childNode['ID'];
		} unset($childNode);
		
		$res = Implode(', ', Array_Fill(0, Count($childNodes), '?'));
		$res = ' WHERE `ObjectID` IN (' . $res . ')';
		$res = Database::select(RELATION_TABLENAME, 'parentID', $res, $childNodes, PDO::FETCH_COLUMN, 0);
		
		// Return nothing.
		if ( Count($res) == 0 ) {
			return Array();
		}
		
		if ( Is_Null($filter) ) {
			$filter = Array();
		}
		
		// Restrict the search within parents ID.
		if ( !isset($filter['ID']) ) {
			$filter['ID'] = $res;
		}
		else {
			$filter['ID'] = Array_Intersect((array)$filter['ID'], $res);
		}
		
		return self::get($filter, $fieldsRequired, $limit);
	}
	
	/**
	 *  Get all direct child nodes of target node, optionally specify a node type.
	 *
	 *  @param $parentNodes The parent node.
	 *
	 *  @returns Array of child nodes of specified nodes.
	 */
	static function getChildren($parentNodes, $filter = NULL, $fieldsRequired = FALSE, $limit = NULL)
	{
		$parentNodes = (Array)$parentNodes;
		
		if ( Utility::isAssociative($parentNodes) ) {
			$parentNodes = Array($parentNodes);
		}
		
		if ( Count($parentNodes) == 0 ) {
			return NULL;
		}
		
		foreach ($parentNodes as &$parentNode) {
			$parentNode = $parentNode['ID'];
		} unset($parentNode);
		
		$res = Implode(', ', Array_Fill(0, Count($parentNodes), '?'));
		$res = ' WHERE `parentID` IN (' . $res . ')';
		$res = Database::select(RELATION_TABLENAME, 'ObjectID', $res, $parentNodes, PDO::FETCH_COLUMN, 0);
		
		// Return nothing.
		if ( Count($res) == 0 ) {
			return Array();
		}
		
		if ( Is_Null($filter) ) {
			$filter = Array();
		}
		
		// Restrict the search within children ID.
		if ( !isset($filter['ID']) ) {
			$filter['ID'] = $res;
		}
		else {
			$filter['ID'] = Array_Intersect((array)$filter['ID'], $res);
		}
		
		return self::get($filter, $fieldsRequired, $limit);
	}
	
	/**
	 *  Recursively gets the ancestor tree as a flat array of nodes.
	 *
	 *  @param $descendantNodes The grand child or an array of grand children.
	 *
	 *  @returns Array of ancestor nodes of specified nodes.
	 */
	static function getAncestors($descendantNodes, $filter = NULL, $fieldsRequired = FALSE, $limit = NULL)
	{
		if ( Utility::isAssociative($descendantNodes) ) {
			$descendantNodes = Array($descendantNodes);
		}
		
		$descendantNodes = (array)$descendantNodes;
		
		if ( Count($descendantNodes) == 0 ) {
			return NULL;
		}
		
		foreach ($descendantNodes as &$descendantNode) {
			if ( isset($descendantNode['ID']) ) {
				$descendantNode = $descendantNode['ID'];
			}
		}
		
		// Get full set of ancestors id
		$res = self::getAncestorsID($descendantNodes);
		
		if ( Is_Null($filter) ) {
			$filter = Array();
		}
		
		// Restrict the search within ancestors ID.
		if ( !isset($filter['ID']) ) {
			$filter['ID'] = $res;
		}
		else {
			$filter['ID'] = Array_Intersect((array)$filter['ID'], $res);
		}
		
		// Call get function to do $limit at once.
		return self::get($filter, $fieldsRequired, $limit);
	}
	
	/**
	 * @private
	 * Retrieve all ID till the top of the node tree, as a flat array.
	 */
	private static function getAncestorsID($descendantNodes)
	{
		// Get all parents ID.
		$res = Implode(', ', Array_Fill(0, Count($descendantNodes), '?'));
		$res = ' WHERE `ObjectID` IN (' . $res . ')';
		$res = Database::select(RELATION_TABLENAME, 'parentID', $res, $descendantNodes, PDO::FETCH_COLUMN, 0);
		
		if ( Count($res) > 0 ) {
			$ancestorsID = self::getAncestorsID($res);
			
			// This pack has parents, add it and go on the recursion.
			$ancestorsID = self::getAncestorsID($res);
			
			// Take any ID once, in case of common parents.
			$res = Array_Unique($res);
		}
		
		return $res;
	}
	
	/**
	 *  Recursively gets the ancestor tree as a flat array of nodes.
	 *
	 *  @param $descendantNodes The grand child or an array of grand children.
	 *
	 *  @returns Array of ancestor nodes of specified nodes.
	 */
	static function getDescendants($ancestorNodes, $filter = NULL, $fieldsRequired = FALSE, $limit = NULL)
	{
		if ( Utility::isAssociative($ancestorNodes) ) {
			$ancestorNodes = Array($ancestorNodes);
		}
		
		$ancestorNodes = (array)$ancestorNodes;
		
		if ( Count($ancestorNodes) == 0 ) {
			return NULL;
		}
		
		foreach ($ancestorNodes as &$ancestorNode) {
			if ( isset($ancestorNode['ID']) ) {
				$ancestorNode = $ancestorNode['ID'];
			}
		}
		
		// Get full set of ancestors id
		$res = self::getDescendantsID($ancestorNodes);
		
		if ( Is_Null($filter) ) {
			$filter = Array();
		}
		
		// Restrict the search within ancestors ID.
		if ( !isset($filter['ID']) ) {
			$filter['ID'] = $res;
		}
		else {
			$filter['ID'] = Array_Intersect((array)$filter['ID'], $res);
		}
		
		// Call get function to do $limit at once.
		return self::get($filter, $fieldsRequired, $limit);
	}
	
	/**
	 * @private
	 * Retrieve all ID till the top of the node tree, as a flat array.
	 */
	private static function getDescendantsID($ancestorNodes)
	{
		// Get all parents ID.
		$res = Implode(', ', Array_Fill(0, Count($ancestorNodes), '?'));
		$res = ' WHERE `ObjectID` IN (' . $res . ')';
		$res = Database::select(RELATION_TABLENAME, 'parentID', $res, $ancestorNodes, PDO::FETCH_COLUMN, 0);
		
		if ( Count($res) > 0 ) {
			$descendantsID = self::getDescendantsID($res);
			
			// This pack has children, add it and go on the recursion.
			$res = Array_Merge($res, $descendantsID);
			
			// Take any ID once, in case of common parents.
			$res = Array_Unique($res);
		}
		
		return $res;
	}
	
	/**
	 *  Remove all parent relations from this node.
	 */
	static function purgeParents($childNodes)
	{
		$childNodes = (array)$childNodes;
		
		if ( Count($childNodes) == 0 ) {
			return NULL;
		}
		
		foreach ($childNodes as &$childNode) {
			$childNode = $childNode['ID'];
		} unset($childNode);
		
		$res = Implode(', ', Array_Fill(0, Count($childNodes), '?'));
		$res = ' WHERE `parentID` IN (' . $res . ')';
		return Database::query('DELETE FROM `' . RELATION_TABLENAME . '`' . $res, Array($childNodes));
	}
	
	/**
	 *  Remove all child relations from this node.
	 */
	static function purgeChildren($parentNodes)
	{
		$parentNodes = (array)$parentNodes;
		
		if ( Count($parentNodes) == 0 ) {
			return NULL;
		}
		
		foreach ($parentNodes as &$parentNode) {
			$parentNode = $parentNode['ID'];
		} unset($parentNode);
		
		$res = Implode(', ', Array_Fill(0, Count($parentNodes), '?'));
		$res = ' WHERE `parentID` IN (' . $res . ')';
		return Database::query('DELETE FROM `' . RELATION_TABLENAME . '`' . $res, Array($parentNodes));
	}
	
	/**
	 *  Add a relation between specified nodes.
	 *
	 *  Another call on existing relations will have no effect.
	 *
	 *  Note that this function forbids recursive relations, 
	 *  calling with $parentNode that is already a descendant of 
	 *  $childNode will result an error.
	 *
	 *  @param $parentNode Parent node.
	 *  @param $childNode Child node.
	 *
	 *  @returns ID of the child node will be returned on success, 
	 *           TRUE will be returned on duplicate calls.
	 */
	static function setRelation($parentNode, $childNode)
	{
		if ( Is_Array($parentNode) ) {
			if ( !isset($parentNode['ID']) ) {
				throw new ServiceException('Both of the nodes must contain an ID.');
			}
			else {
				$parentNode = $parentNode['ID'];
			}
		}
		
		if ( Is_Array($childNode) ) {
			if ( !isset($parentNode['ID']) ) {
				throw new ServiceException('Both of the nodes must contain an ID.');
			}
			else {
				$childNode = $childNode['ID'];
			}
		}
		
		if ( $parentNode == $childNode ) {
			throw new ServiceException('Parent and child cannot contains the same ID.');
		}
			
		// Ensure parentNode is not already a descendant of childNode.
		$descendantsID = self::getDescendantsID((array)$childNode);
		
		if ( In_Array($parentNode, $descendantsID) ) {
			throw new ServiceException('Recursive relation is not allowed! $parentNode is already a descendant of $childNode.');
		}
		
		return Database::upsert(RELATION_TABLENAME, 
			Array('ObjectID' => $childNode, 'parentID' => $parentNode));
	}
	
	/**
	 *  Removes a specific node relation.
	 *
	 *  @param $parentNode Target parent node.
	 *  @param $childNode Target child node.
	 */
	static function deleteRelation($parentNode, $childNode)
	{
		if ( Is_Array($parentNode) ) {
			if ( !isset($parentNode['ID']) ) {
				throw new ServiceException('Both of the nodes must contain an ID.');
			}
			else {
				$parentNode = $parentNode['ID'];
			}
		}
		
		if ( Is_Array($childNode) ) {
			if ( !isset($parentNode['ID']) ) {
				throw new ServiceException('Both of the nodes must contain an ID.');
			}
			else {
				$childNode = $childNode['ID'];
			}
		}
		
		return Database::delete(RELATION_TABLENAME, Array( Array($childNode, $parentNode) ));
	}
}