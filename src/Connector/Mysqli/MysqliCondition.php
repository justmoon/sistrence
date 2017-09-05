<?php
namespace Sistrence\Connector\Mysqli;

use Sistrence\Sistrence;

class MysqliCondition
{
	private $type;
	private $op;
	private $params;

	public function __construct($type, $op, $params)
	{
		if (!self::isValid($type)) {
			Sistrence::error('Invalid condition type!', array('type' => $type));
		}

		$this->type = self::resolveAliases($type);
		$this->op = $op;
		$this->params = $params;

		// Operations starting with "field" accept an array shorthand for
		// operating on multiple fields.
		if (substr($this->type, 0, 6) == 'field_' && is_array($params[0])) {

			$subConds = array();
			foreach ($params[0] as $fieldName => $value) {
				$subConds[] = new MysqliCondition($this->type, $op,
				                                  array($fieldName, $value));
			}

			$this->type = 'merge_and';
			$this->op = $op;
			$this->params = $subConds;

			// This is an optimization - we skip prepare_merge_and because
			// we already know our subconditions are not registered with
			// the operation object.
			return;
		}

		// some conditions need preparation
		$method = 'prepare_'.$this->type;

		if (method_exists(__CLASS__, $method)) {
			self::$method($this->op, $this->params);
		}
	}

	public function toSql()
	{
		$method = 'sql_'.$this->type;

		return '('.self::$method($this->op, $this->params).')';
	}

	////////////////////////////////////////////////////////////////////////////
	// BEGIN Condition Library

	private static $aliases = array(
		'and' => 'merge_and',
		'or' => 'merge_or',

		'not' => 'bool_not',

		'eq' => 'field_equals',
		'ne' => 'field_nequals',
		'contains' =>'field_contains',
		'starts' => 'field_starts',
		'ends' => 'field_ends',
    'like' => 'field_like',
		'gt' =>'field_greater',
		'lt' =>'field_less',
		'gtoe' => 'field_greater_or_equal',
		'ltoe' => 'field_less_or_equal',

		'null' => 'isnull',

		'field_bigger' => 'field_greater',
		'field_bigger_or_equal' => 'field_greater_or_equal',
		'field_smaller' => 'field_less',
		'field_smaller_or_equal' => 'field_less_or_equal',

		'regexp' => 'field_regexp'
	);

	static public function isValid($type)
	{
		$type = strtolower($type);
		return method_exists(__CLASS__, 'sql_'.$type) || isset(self::$aliases[$type]);
	}

	static public function resolveAliases($type)
	{
		$type = strtolower($type);
		if (isset(self::$aliases[$type])) {
			$type = self::$aliases[$type];
		}
		return $type;
	}

	static private function prepare_merge_or($op, $params)
	{
		self::util_filter_subconditions($op, $params);
	}

	static private function prepare_merge_and($op, $params)
	{
		self::util_filter_subconditions($op, $params);
	}

	static private function prepare_bool_not($op, $params)
	{
		self::util_filter_subconditions($op, $params);
	}

	static private function sql_merge_and($op, $params)
	{
		$sqls = array();
		foreach($params as $param) {
			$sqls[] = $param->toSql();
		}
		return implode(' AND ', $sqls);
	}

	static private function sql_merge_or($op, $params)
	{
		$sqls = array();
		foreach($params as $param) {
			$sqls[] = $param->toSql();
		}
		return implode(' OR ', $sqls);
	}

	static private function sql_bool_not($op, $params)
	{
		return '!'.$params[0]->toSql();
	}

	static private function sql_field_equals($op, $params)
	{
		$value = $op->prepareValue($params[1]);
		return $op->prepareField($params[0]).' = '.$value;
	}

	static private function sql_field_nequals($op, $params)
	{
		$value = $op->prepareValue($params[1]);
		return $op->prepareField($params[0]).' != '.$value;
	}

	static private function sql_field_contains($op, $params)
	{
		$value = "'%".addslashes((string)$params[1])."%'";
		return $op->prepareField($params[0]).' LIKE '.$value;
	}

	static private function sql_field_starts($op, $params)
	{
		$value = "'".addslashes((string)$params[1])."%'";
		return $op->prepareField($params[0]).' LIKE '.$value;
	}

	static private function sql_field_ends($op, $params)
	{
		$value = "'%".addslashes((string)$params[1])."'";
		return $op->prepareField($params[0]).' LIKE '.$value;
	}

	static private function sql_field_like($op, $params)
	{
		$value = $op->prepareValue($params[1]);
		$sql = $op->prepareField($params[0]).' LIKE '.$value;

    // If LIKE escape character given
    if (count($params) >= 3 &&
        is_string($params[2]) &&
        strlen($params[2]) == 1) {
      $sql .= ' ESCAPE '.$op->prepareValue($params[2]);
    }

    return $sql;
	}

	static private function sql_field_greater($op, $params)
	{
		return $op->prepareField($params[0]).' > '.$op->prepareValue($params[1]);
	}

	static private function sql_field_greater_or_equal($op, $params)
	{
		return $op->prepareField($params[0]).' >= '.$op->prepareValue($params[1]);
	}

	static private function sql_field_less($op, $params)
	{
		return $op->prepareField($params[0]).' < '.$op->prepareValue($params[1]);
	}

	static private function sql_field_less_or_equal($op, $params)
	{
		return $op->prepareField($params[0]).' <= '.$op->prepareValue($params[1]);
	}

	static private function sql_field_regexp($op, $params)
	{
		return $op->prepareField($params[0]).' REGEXP '.$op->prepareValue($params[1]);
	}

	static private function sql_isnull($op, $params)
	{
		return $op->prepareField($params[0]).' IS NULL';
	}

	static private function sql_notnull($op, $params)
	{
		return $op->prepareField($params[0]).' IS NOT NULL';
	}

	static private function util_filter_subconditions($op, $conds)
	{
		$cond = end($conds);
		do {
			$op->dropCond($cond);
		} while (($cond = prev($conds)) !== false);
	}
}
