<?php
namespace Sistrence\Query;

abstract class Join
{
	protected $op;
	protected $table;

	protected $on = array();

	public function __construct(Operation $op, $table)
	{
		$this->op = $op;
		$this->table = $table;
	}
}
