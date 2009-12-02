<?php

class SisConnectionMysqli
{
	private $mysqli;
	
	private $user;
	private $password;
	private $host;
	private $database;
	
	public function __construct($user, $password, $host, $database)
	{
		$this->user = $user;
		$this->password = $password;
		$this->host = $host;
		$this->database = $database;
	}
	
	public function __destruct()
	{
		$this->close();
	}
	
	public function op($table)
	{
		return new SisOperationMysqli($table, $this);
	}
	
	public function query($sql)
	{
		if (!$this->mysqli) $this->connect();

		// in debug mode, we will output all queries launched to the database
		Sis::DEBUG AND Sis::debugEcho('SQL Query: '.$sql);
		
		// deploy the query, return the result
		return $this->mysqli->query($sql);
	}
	
	public function ping()
	{
		if (!$this->mysqli) $this->connect();
		
		$this->mysqli->ping();
	}
	
	public function close()
	{
		if ($this->mysqli) {
			$this->mysqli->close();
			$this->mysqli = null;
		}
	}
	
	public function reconnect()
	{
		$this->close();
		$this->connect();
	}
	
	public function getDebugInfo($data = array())
	{
		if (!$this->mysqli) {
			return array(
				'db_error' => mysqli_connect_error(),
				'db_errno' => mysqli_connect_errno()
			);
		} else {
			return array(
				'db_error' => $this->mysqli->error,
				'db_errno' => $this->mysqli->errno
			);
		}
	}
	
	public function getInsertId()
	{
		if (!$this->mysqli) return 0;
		
		return $this->mysqli->insert_id;
	}
	
	public function getMysqli()
	{
		if (!$this->mysqli) $this->connect();
		
		return $this->mysqli;
	}
	
	public function escapeString($string)
	{
		if (!$this->mysqli) $this->connect();
		
		return $this->mysqli->escape_string($string);
	}
	
	public function connect()
	{
		$this->mysqli = new mysqli($this->host, $this->user, $this->password, $this->database);
		
		if (!$this->mysqli) {
			Sis::error('Error connecting to MySQL database.', array('db_error' => mysqli_connect_error(), 'db_errno' => mysqli_connect_errno()));
			return;
		}
		
		$this->mysqli->set_charset('utf8');
	}
}

class SisOperationMysqli extends SisOperation
{
	private $groupby;
	
	/**
	 * Takes condition parameters, turns them into a SisCondition object and returns it.
	 */
	public function cond()
	{
		$cond = $this->createCondition(func_get_args());
		$this->options['conditions'][] = $cond;
		return $cond;
	}
	
	public function __call($method, $args)
	{
		if (SisConditionLibMysqli::isValid($method)) {
			$cond = new SisConditionMysqli($method, $this, $args);
			$this->options['conditions'][] = $cond;
			return $cond;
		} else {
			trigger_error(sprintf('Call to undefined function: %s::%s()', get_class($this), $method), E_USER_ERROR);
		}
	}
	
	public function eqAll($data)
	{
		foreach ($data as $field => $value) {
			$this->cond('field_equals', $field, $value);
		}
	}
	
	/**
	 * Takes condition parameters as an array, turns them into a SisCondition object and saves it.
	 */
	private function createCondition($params)
	{
		$type = array_shift($params);
		return new SisConditionMysqli($type, $this, $params);
	}
	
	public function sort($by, $order = 1)
	{
		$this->options['sort'] = array($by, $order);
	}
	
	public function randomize()
	{
		$this->options['sort'] = 'randomize';
	}
	
	public function range($start, $count)
	{
		$this->options['range'] = array($start, $count);
	}
	
	public function fields($fields)
	{
		$this->options['custom_fields'] = $fields;
	}

	public function groupby($group)
	{
		$this->options['groupby'] = $group;
	}

	public function join($second_table)
	{
		$join = new SisJoinMysqli($this, $second_table);
		$this->options['join'][] = $join;
		return $join;
	}
	
	public function doSql($sql)
	{
		if (!$result = $this->c->query($sql)) {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		} else {
			return true;
		}
	}

	public function doGet()
	{
		$fields = (isset($this->options['custom_fields'])) ? $this->options['custom_fields'] : '*';
		
		$query = 'SELECT '.$fields.' FROM `'.$this->table.'` ';
		
		$query .= $this->createJoinDefinition();
		
		$query .= $this->createWhereDefinition();
		
		if (isset($this->options['sort'])) {
			$sort = $this->options['sort'];
			if ($sort == 'randomize') {
				$query .= " ORDER BY RAND()";
			} else {
				$sort[1] = ($sort[1] == -1) ? 'DESC' : 'ASC';
				$query .= " ORDER BY `{$sort[0]}` {$sort[1]}";
			}
		}
		
		if (isset($this->options['range'])) {
			$range = $this->options['range'];
			$query .= " LIMIT {$range[0]}, {$range[1]}";
		}
		
		if (!$result = $this->c->query($query)) {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
		
		if ($result->num_rows) {
			// prepare an array consisting of the results
			$data = array();
			while ($row = $result->fetch_assoc()) {
				$data[] = $row;
			}
			return $data;
		} else {
			// no error, but no results: return an empty array
			return array();
		}
	}
	
	public function doGetOne()
	{
		// Ensure only one row is retrieved
		if (isset($this->options['range'])) {
			$this->options['range'][1] = 1;
		} else {
			$this->options['range'] = array(0,1);
		}
		
		$result = $this->doGet();
		
		// Return first result
		return (is_array($result)) ? current($result) : null;
	}
	
	public function doGetSql($query)
	{
		if (!$result = $this->c->query($query)) {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
		
		if ($result === true) return true;
		
		if ($result->num_rows) {
			// prepare an array consisting of the results
			$data = array();
			while ($row = $result->fetch_assoc()) {
				$data[] = $row;
			}
			return $data;
		} else {
			// no error, but no results: return an empty array
			return array();
		}
	}
	
	public function doUpdate($data, $fields = false)
	{
		if ($data instanceof SisUpdateRuleset) {
			// TODO: well, implement this
		} elseif (is_array($data)) {
			$query = 'UPDATE `'.$this->table.'` SET ';
			$query .= $this->createInsertDefinition($data, $fields);
			$query .= $this->createWhereDefinition();
			
			if ($this->c->query($query)) {
				return true;
			} else {
				Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
				return false;
			}
		} else {
			Sis::error('Invalid data for update', array('data' => $data));
			return false;
		}
	}
	
	public function doDelete()
	{
		$query = 'DELETE FROM `'.$this->table.'` ';
		$query .= $this->createWhereDefinition();
		
		if ($this->c->query($query)) {
			return true;
		} else {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}
	
	public function doCount()
	{
		$query = 'SELECT COUNT(*) AS count FROM `'.$this->table.'`';
		$query .= $this->createWhereDefinition();
		
		if ($result = $this->c->query($query)) {
			$row = $result->fetch_assoc();
			return $row['count'];
		} else {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}
	
	public function doCountSql($sql)
	{
		if ($result = $this->c->query($sql)) {
			list($count) = $result->fetch_array();
			return $count;
		} else {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}
	
	private function createJoinDefinition()
	{
		$query = '';
		if (isset($this->options['join']) && is_array($this->options['join']) && count($this->options['join'])) {
			foreach ($this->options['join'] as $join) {
				$query .= $join->toSql();
			}
		}
		return $query;
	}
	
	private function createWhereDefinition()
	{
		$query = '';
		if (isset($this->options['conditions']) && !empty($this->options['conditions'])) {
			$query = ' WHERE ';
			$master_condition = new SisConditionMysqli('and', $this, $this->options['conditions']);
			$query .= $master_condition->toSql();
		}

		if (isset($this->options['groupby']) && !empty($this->options['groupby']))
			$query .= ' GROUP BY ' . $this->options['groupby'];

		return $query;
	}
	
	public function doInsert($data, $fields = false)
	{
		if (is_array($data) && count($data)) {
			$query = 'INSERT INTO `'.$this->table.'` SET ';
			$query .= $this->createInsertDefinition($data, $fields);
			if ($this->c->query($query)) {
				return $this->c->getInsertId();
			} else {
				Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
				return false;
			}
		} else {
			Sis::error('Invalid data for insert', array('data' => $data));
			return false;
		}
	}
	
	public function doTruncate()
	{
		$query = 'TRUNCATE '.$this->table;
		
		if ($this->c->query($query)) {
			return true;
		} else {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}
	
	private function createInsertDefinition($data, $fields = false)
	{
		$string = '';
		
		if (is_array($fields)) {
			foreach ($fields as $field) {
				if (isset($data[$field])) {
					if (!$value = $this->prepareValue($data[$field])) {
						return false;
					}
					$string .= ', `'.addslashes($field).'`='.$value;
				} else {
					// just ignore this case
					continue;
				}
			}
		} else {
			foreach ($data as $key => $value) {
				if (($value = $this->prepareValue($value)) === false) {
					continue;
				}
				$string .= ', `'.addslashes($key).'`='.$value;
			}
		}
		$string = ltrim($string, ', ');
		return $string;
	}
	
	public function tableExists()
	{
		$query = "SHOW TABLES LIKE '{$this->table}'";
		
		$result = $this->c->query($query);
		return ($result->num_rows) ? true : false;
	}
	
	public function field($field)
	{
		$field = new SisFieldMysqli($this, $field);
		$this->options['field'][] = $field;
		return $field;
	}
	
	public function fieldExists($field)
	{
		$query = "SHOW COLUMNS FROM `{$this->table}` LIKE '$field'";
		
		$result = $this->c->query($query);
		return ($result->num_rows) ? true : false;
	}
	
	public function addField($field)
	{
		var_dump($field);
		if ($field instanceof SisField) {
			$query = "ALTER TABLE `{$this->table}` ADD ";
			$query .= $this->createFieldDefinition($field);
		} elseif (is_array($field)) {
			$definitions = array();
			foreach($field as $single_field) {
				if($single_field instanceof SisField) {
					$definitions[] = $this->createFieldDefinition($single_field);
				} else {
					Sis::error('Invalid field specification for field creation', array('field' => $single_field));
					return false;
				}
			}
			
			$query = "ALTER TABLE `{$this->table}` ADD ";
			$query = implode(", ", $definitions);
		} else {
			Sis::error('Invalid field specification for field creation', array('field' => $field));
			return false;
		}
		
		if ($this->c->query($query)) {
			return true;
		} else {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}
	
	public function changeField($oldname, $field)
	{
		if ($field instanceof SisField) {
			$query = "ALTER TABLE `{$this->table}` CHANGE `$oldname` ";
			$query .= $this->createFieldDefinition($field);
			if ($this->c->query($query)) {
				return true;
			} else {
				Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
				return false;
			}
		} else {
			Sis::error('Invalid field specification for field modification', array('field' => $field));
			return false;
		}
	}
	
	public function doCreate($fields)
	{
		if (!is_array($fields)) {
			$fields = array($fields);
		}
		
		$definitions = array();
		foreach ($fields as $field) {
			if ($field instanceof SisField) {
				$definitions[] = $this->createFieldDefinition($field);
			} else {
				Sis::error('Invalid field specification for table creation', array('field' => $field));
				return false;
			}
		}
		
		
		$query = "CREATE TABLE `{$this->table}` (".implode(', ', $definitions).")";
		if ($this->c->query($query)) {
			return true;
		} else {
			Sis::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}
	
	private function createFieldDefinition(SisField $field)
	{
		if (empty($field->name) || empty($field->type)) {
			die('Invalid column name or type');
		}
		
		$def = "`{$field->name}` ";
		
		switch ($field->type) {
			case DB_FT_BOOL:
				$field->default = ($field->default) ? 1 : 0;
				$def .= "TINYINT(1) DEFAULT '{$field->default}' NOT NULL";
				break;
			case DB_FT_INT:
				$def .= "INT({$field->size}) DEFAULT '{$field->default}' NOT NULL";
				break;
			case DB_FT_FLOAT:
				$def .= "FLOAT({$field->size}) DEFAULT '{$field->default}' NOT NULL";
				break;
			case DB_FT_BLOB:
				$def .= "FLOAT({$field->size}) DEFAULT '{$field->default}' NOT NULL";
				break;
			case DB_FT_CHAR:
				$def .= "CHAR({$field->size}) DEFAULT '{$field->default}' NOT NULL";
				break;
			case DB_FT_VARCHAR:
				$def .= "VARCHAR({$field->size}) DEFAULT '{$field->default}' NOT NULL";
				break;
			case DB_FT_TEXT:
				$def .= "FLOAT({$field->size}) DEFAULT '{$field->default}' NOT NULL";
				break;
			case DB_FT_AUTOKEY:
				$def .= "INT(8) NOT NULL PRIMARY KEY AUTO_INCREMENT";
				break;
			default:
				die('Unknown field type!');
		}
		
		return $def;
	}

	public function getInsertId()
	{
		return $this->c->getInsertId();
	}
	
	public function prepareField($field)
	{
		return '`'.addslashes($field).'`';
	}
}

class SisConditionMysqli
{
	private $type;
	private $op;
	private $params;
	
	public function __construct($type, $op, $params)
	{
		if (!SisConditionLibMysqli::isValid($type)) {
			Sis::error('Invalid condition type!', array('type' => $type));
		}
	
		$this->type = $type;
		$this->op = $op;
		$this->params = $params;
	}
	
	public function toSql()
	{
		return '('.SisConditionLibMysqli::sql($this->type, $this->op, $this->params).')';
	}
}

class SisConditionLibMysqli
{
	private static $aliases = array(
		'and' => 'merge_and',
		'or' => 'merge_or',
		
		'eq' => 'field_equals',
		'ne' => 'field_nequals',
		'contains' =>'field_contains',
		'starts' => 'field_starts',
		'ends' => 'field_ends',
		'gt' =>'field_greater',
		'lt' =>'field_less',
		'gtoe' => 'field_greater_or_equal',
		'ltoe' => 'field_less_or_equal',
		
		'null' => 'isnull',
		
		'field_bigger' => 'field_greater',
		'field_bigger_or_equal' => 'field_greater_or_equal',
		'field_smaller' => 'field_less',
		'field_smaller_or_equal' => 'field_less_or_equal',
	);
	
	static public function isValid($type)
	{
		$type = strtolower($type);
		return method_exists(__CLASS__, $type) || isset(self::$aliases[$type]);
	}
	
	static public function sql($type, $op, $params)
	{
		$type = strtolower($type);
		if (isset(self::$aliases[$type])) {
			$type = self::$aliases[$type];
		}
		
		return self::$type($op, $params);
	}

	static private function merge_and($op, $params)
	{
		$sqls = array();
		foreach($params as $param) {
			$sqls[] = $param->toSql();
		}
		return implode(' AND ', $sqls);
	}
	
	static private function merge_or($op, $params)
	{
		$sqls = array();
		foreach($params as $param) {
			$sqls[] = $param->toSql();
		}
		return implode(' OR ', $sqls);
	}
				
	static private function field_equals($op, $params)
	{
		$value = $op->prepareValue($params[1]);
		return $op->prepareField($params[0]).' = '.$value;
	}
	
	static private function field_nequals($op, $params)
	{
		$value = $op->prepareValue($params[1]);
		return $op->prepareField($params[0]).' != '.$value;
	}
	
	static private function field_contains($op, $params)
	{
		$value = "'%".addslashes((string)$params[1])."%'";
		return $op->prepareField($params[0]).' LIKE '.$value;
	}
			
	static private function field_starts($op, $params)
	{
		$value = "'".addslashes((string)$params[1])."%'";
		return $op->prepareField($params[0]).' LIKE '.$value;
	}
	
	static private function field_ends($op, $params)
	{
		$value = "'%".addslashes((string)$params[1])."'";
		return $op->prepareField($params[0]).' LIKE '.$value;
	}
	
	static private function field_greater($op, $params)
	{
		return $op->prepareField($params[0]).' > '.$op->prepareValue($params[1]);
	}
	
	static private function field_greater_or_equal($op, $params)
	{
		return $op->prepareField($params[0]).' >= '.$op->prepareValue($params[1]);
	}
		
	static private function field_less($op, $params)
	{
		return $op->prepareField($params[0]).' < '.$op->prepareValue($params[1]);
	}
		
	static private function field_less_or_equal($op, $params)
	{
		return $op->prepareField($params[0]).' <= '.$op->prepareValue($params[1]);
	}
		
	static private function isnull($op, $params)
	{
		return $op->prepareField($params[0]).' IS NULL';
	}
		
	static private function notnull($op, $params)
	{
		return $op->prepareField($params[0]).' IS NOT NULL';
	}
}

class SisJoinMysqli extends SisJoin
{
	public function __call($method, $args)
	{
		if (SisConditionLibMysqli::isValid($method)) {
			$cond = new SisConditionMysqli($method, $this, $args);
			$this->on[] = $cond;
			return $cond;
		} elseif (method_exists($this->op, $method)) {
			return call_user_func_array(array($this->op, $method), $args);
		} else {
			trigger_error(sprintf('Call to undefined function: %s::%s().', get_class($this), $method), E_USER_ERROR);
		}
	}
	
	public function toSql()
	{
		$sql = ' JOIN '.$this->table;
		
		if (count($this->on)) {
			$sql .= ' ON ';
			$master_condition = new SisConditionMysqli('and', $this, $this->on);
			$sql .= $master_condition->toSql();
		}

		return $sql;
	}
	
	public function prepareField($field)
	{
		return '`'.$this->table.'`.`'.addslashes($field).'`';
	}
}

class SisFieldMysqli extends SisField
{
}
?>
