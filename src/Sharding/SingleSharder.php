<?php
namespace Sistrence\Sharding;

class SingleSharder extends Sharder
{
	public function getLink($table)
	{
		return 0;
	}
}
