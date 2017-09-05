<?php
namespace Sistrence\Sharding;

abstract class Sharder
{
	abstract public function getLink($table);
}
