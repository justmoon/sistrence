<?php
namespace Sistrence\Query;

use Sistrence\Sistrence;

/**
 * This class implements certain features every Operation object needs
 */
abstract class Operation
{
	// The Connection we'll use
	protected $c;

	// Table to operate on
	protected $table;

	// Options for operation
	protected $options = array();

	public function __construct($table, $c)
	{
		$this->c = $c;
		$this->table = $table;
	}

	public function __destruct()
	{
		unset($this->c);
		unset($this->options);
	}

	public function getTable()
	{
		return $this->table;
	}

	public function prepareValue($value)
	{
		if (is_bool($value)) {
			return $value ? 1 : 0;
		} elseif (is_int($value)) {
			return $value;
		} elseif (is_float($value)) {
			// convert the decimal separator back to english notation if changed by locale
			$localeconv = localeconv();
			return str_replace($localeconv['decimal_point'], '.', $value);
		} elseif (is_string($value)) {
			return "'".$this->escapeString($value)."'";
		} elseif ($value === null) {
			return 'NULL';
		} elseif ($value instanceof Field) {
			return $value->getFullName();
		} else {
			Sistrence::error('Invalid data type!', array('value' => $value));
			return false;
		}
	}

	public function escapeString($string)
	{
		return $this->c->escapeString($string);
	}
}
