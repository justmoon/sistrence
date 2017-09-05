<?php
namespace Sistrence\Connector\Mongo;

use Sistrence\Sistrence;

class ConditionMongo
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
				$subConds[] = new self($this->type, $op,
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

	public function toPair()
	{
		$method = 'pair_'.$this->type;

		return self::$method($this->op, $this->params);
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
		return method_exists(__CLASS__, 'pair_'.$type) || isset(self::$aliases[$type]);
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
		// TODO: Implement
	}

	static private function prepare_merge_and($op, $params)
	{
		// TODO: Implement
	}

	static private function prepare_bool_not($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_merge_and($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_merge_or($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_bool_not($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_field_equals($op, $params)
	{
		return array($params[0], $params[1]);
	}

	static private function pair_field_nequals($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_field_contains($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_field_starts($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_field_ends($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_field_greater($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_field_greater_or_equal($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_field_less($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_field_less_or_equal($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_isnull($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_notnull($op, $params)
	{
		// TODO: Implement
	}

	static private function util_filter_subconditions($op, $conds)
	{
		$cond = end($conds);
		do {
			$op->dropCond($cond);
		} while (($cond = prev($conds)) !== false);
	}
}
