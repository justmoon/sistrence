<?php
namespace Sistrence\Connector\Mysqli;

use Sistrence\Sistrence;
use Sistrence\Query\Operation;
use Sistrence\Query\Field;
use Sistrence\Query\UpdateRuleset;

class MysqliOperation extends Operation
{
	private $groupby;

	/**
	 * Takes condition parameters, turns them into a Condition object and returns it.
	 */
	public function cond()
	{
		$cond = $this->createCondition(func_get_args());
		$this->options['conditions'][] = $cond;
		return $cond;
	}

	public function __call($method, $args)
	{
		if (MysqliCondition::isValid($method)) {
			$cond = new MysqliCondition($method, $this, $args);
			$this->options['conditions'][] = $cond;
			return $cond;
		} else {
			trigger_error(sprintf('Call to undefined function: %s::%s()', get_class($this), $method), E_USER_ERROR);
		}
	}

	/**
	 * Drop a condition.
	 *
	 * Will look through the condition starting at the end and drop the first
	 * one that matches the parameter.
	 *
	 * Returns true if the condition was found.
	 */
	public function dropCond(MysqliCondition $doomedCond)
	{
		$cond = end($this->options['conditions']);
		do {
			if ($cond === $doomedCond) {
				unset($this->options['conditions'][key($this->options['conditions'])]);
				return true;
			}
		} while (($cond = prev($this->options['conditions'])) !== false);
		return false;
	}

	public function eqAll($data)
	{
		foreach ($data as $field => $value) {
			$this->cond('field_equals', $field, $value);
		}
	}

	/**
	 * Takes condition parameters as an array, turns them into a Condition object and saves it.
	 */
	private function createCondition($params)
	{
		$type = array_shift($params);
		return new MysqliCondition($type, $this, $params);
	}

	public function sort($by, $order = 1)
	{
		$this->options['sort'][] = array($by, $order);
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
		$join = new MysqliJoin($this, $second_table);
		$this->options['join'][] = $join;
		return $join;
	}

	public function doSql($sql, $with_result = false)
	{
		if (!$result = $this->c->query($sql)) {
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $sql)));
			return false;
		} else {
			return $with_result ? new MysqliResult($result) : true;
		}
	}

	public function doSqlMulti($sql)
	{
		if (!$this->c->queryMulti($sql)) {
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $sql)));
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
				$sortClauses = array();
				foreach ($sort as $sortEntry) {
					$sortClauses[] = '`'.$sortEntry[0].'` '.(($sortEntry[1] == -1) ? 'DESC' : 'ASC');
				}
				if (count($sortClauses)) {
					$query .= ' ORDER BY '.implode(', ', $sortClauses);
				}
			}
		}

		if (isset($this->options['range'])) {
			$range = $this->options['range'];
			$query .= " LIMIT {$range[0]}, {$range[1]}";
		}

		if (!$result = $this->c->query($query)) {
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
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
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
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
		if ($data instanceof UpdateRuleset) {
			// TODO: well, implement this
		} elseif (is_array($data)) {
			$query = 'UPDATE `'.$this->table.'` SET ';
			$query .= $this->createInsertDefinition($data, $fields);
			$query .= $this->createWhereDefinition();

			if ($this->c->query($query)) {
				return true;
			} else {
				Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
				return false;
			}
		} else {
			Sistrence::error('Invalid data for update', array('data' => $data));
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
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
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
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}

	public function doCountSql($sql)
	{
		if ($result = $this->c->query($sql)) {
			list($count) = $result->fetch_array();
			return $count;
		} else {
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
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
			$sqls = array();
			foreach($this->options['conditions']  as $param) {
				$sqls[] = $param->toSql();
			}
			$query .= implode(' AND ', $sqls);
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
				Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
				return false;
			}
		} else {
			Sistrence::error('Invalid data for insert', array('data' => $data));
			return false;
		}
	}

	public function doUpdateOrInsert($data, $updateFields = false, $insertFields = false)
	{
		if ($this->doCount()) {
			$this->doUpdate($data, $updateFields);
		} else {
			$this->doInsert($data, $insertFields);
		}
	}

	public function doTruncate()
	{
		$query = 'TRUNCATE '.$this->table;

		if ($this->c->query($query)) {
			return true;
		} else {
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}

	private function createInsertDefinition($data, $fields = false)
	{
		$string = '';

		if (is_array($fields)) {
			foreach ($fields as $field) {
				if (isset($data[$field])) {
					$value = $this->prepareValue($data[$field]);
					if ($value === false) {
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
		$field = new MysqliField($this, $field);
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
		if ($field instanceof Field) {
			$query = "ALTER TABLE `{$this->table}` ADD ";
			$query .= $this->createFieldDefinition($field);
		} elseif (is_array($field)) {
			$definitions = array();
			foreach($field as $single_field) {
				if($single_field instanceof Field) {
					$definitions[] = $this->createFieldDefinition($single_field);
				} else {
					Sistrence::error('Invalid field specification for field creation', array('field' => $single_field));
					return false;
				}
			}

			$query = "ALTER TABLE `{$this->table}` ADD ";
			$query = implode(", ", $definitions);
		} else {
			Sistrence::error('Invalid field specification for field creation', array('field' => $field));
			return false;
		}

		if ($this->c->query($query)) {
			return true;
		} else {
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}

	public function changeField($oldname, $field)
	{
		if ($field instanceof Field) {
			$query = "ALTER TABLE `{$this->table}` CHANGE `$oldname` ";
			$query .= $this->createFieldDefinition($field);
			if ($this->c->query($query)) {
				return true;
			} else {
				Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
				return false;
			}
		} else {
			Sistrence::error('Invalid field specification for field modification', array('field' => $field));
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
			if ($field instanceof Field) {
				$definitions[] = $this->createFieldDefinition($field);
			} else {
				Sistrence::error('Invalid field specification for table creation', array('field' => $field));
				return false;
			}
		}


		$query = "CREATE TABLE `{$this->table}` (".implode(', ', $definitions).")";
		if ($this->c->query($query)) {
			return true;
		} else {
			Sistrence::error('Invalid MySQL-Query!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}

	private function createFieldDefinition(Field $field)
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

	public function getAffectedRows()
	{
		return $this->c->getAffectedRows();
	}

	public function prepareField($field)
	{
		return '`'.addslashes($field).'`';
	}
}
