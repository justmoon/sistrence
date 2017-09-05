<?php
namespace Sistrence\Query;

abstract class Field
{
	// Associated operation
	protected $op;

	// Name of the field
	protected $name = null;

	public function __construct(Operation $op, $fieldName)
	{
		$this->op = $op;
		$this->name = $fieldName;
	}

	public function getName()
	{
		return $this->name;
	}

	public function getFullName()
	{
		return '`'.$this->op->getTable() . '`.`' . $this->name . '`';
	}

	/*
	// data type
	public $type = null;

	// the (maximum) size
	public $size = 8;

	// default value for the column
	public $default = '';
	*/
}
