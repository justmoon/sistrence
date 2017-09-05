<?php
namespace Sistrence\Connector\Mysqli;

use Sistrence\Query\Join;

class MysqliJoin extends Join
{
	public function __call($method, $args)
	{
		if (MysqliCondition::isValid($method)) {
			$cond = new MysqliCondition($method, $this, $args);
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
			$master_condition = new MysqliCondition('and', $this, $this->on);
			$sql .= $master_condition->toSql();
		}

		return $sql;
	}

	public function prepareField($field)
	{
		return '`'.$this->table.'`.`'.addslashes($field).'`';
	}
}
