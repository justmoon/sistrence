<?php
/**
 * Represents a list of database results.
 */
class Set extends ArrayObject
{
	public function __call($name, $args)
	{
		if ($this->count() == 0) return;
		
		if (is_object($this[0]) && method_exists($this[0], $name)) {
			foreach ($this as $row) {
				call_user_func_array(array($row, $name), $args);
			}
		}
	}
}
?>