<?php
require_once('sistrence/set.class.php');
abstract class SisObject implements ArrayAccess
{
	const TABLE = 'no_table_set_check_your_db_object';
	const ID_FIELD = 'id';

	protected $id;
	private $entry;
	
	public function __construct($id = null, $entry = null)
	{
		$this->id = $id;
		$this->entry = $entry;
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function getEntry()
	{
		if ($this->entry !== null) {
			 return $this->entry;
		}
		
		$op = $this->getOp();
		$this->entry = $op->doGetOne();
		
		if ($this->entry === null) {
			trigger_error('Database error while getting entry for table '.constant(get_class($this).'::TABLE').' with &rsquo;'.constant(get_class($this).'::ID_FIELD').'&rsquo; = '.var_export($this->id, true), E_USER_WARNING);
			$this->entry = array();
			return false;
		}
	
		if ($this->entry === false) {
			trigger_error('Entry for table '.constant(get_class($this).'::TABLE').' with &rsquo;'.constant(get_class($this).'::ID_FIELD').'&rsquo; = '.var_export($this->id, true).' not found', E_USER_WARNING);
			$this->entry = array();
			return false;
		}
		
		return $this->entry;
	}
	
	public function getBy($field, $value)
	{
		$class = get_class($this);
		
		$op = Sis::op(constant($class.'::TABLE'));
		$op->eq($field, $value);
		$entry = $op->doGetOne();
		
		return self::objectify($entry);
	}
	
	public function create($data)
	{
		$class = get_class($this);
		
		$op = Sis::op(constant($class.'::TABLE'));
		$id = $op->doInsert($data);
		
		// Add id to data
		$data[constant($class.'::ID_FIELD')] = $id;
		
		return self::objectify($data);
	}
	
	private $customFields = null;
	
	/**
	 * Specify what fields to retrieve during any future database query.
	 * 
	 * By default all fields are retrieved. Call without arguments to set back
	 * to default.
	 */ 
	public function setFields()
	{
		$args = func_get_args();
		
		if (count($args) == 0) {
			$this->customFields = null;
		} else {
			$this->customFields = $args;
		}
	}
	
	/**
	 * Returns a database operation, prepped and ready to go.
	 */
	public function getOp()
	{
		$op = Sis::op(constant(get_class($this).'::TABLE'));
		if ($this->id !== null) $op->eq(constant(get_class($this).'::ID_FIELD'), $this->id);
	
		if ($this->customFields !== null) {
			call_user_func(array($op, 'fields'), implode(', ', $this->customFields));
		}
		
		return $op;
	}
	
	/**
	 * Delete this object from the database.
	 */
	public function delete()
	{
		if ($this->id === null) trigger_error('SisObject::delete() can only delete individual objects.');
	
		$this->getOp()->doDelete();
	}
	
	/**
	 * Temporary cache for fields that are going to be updated.
	 */
	protected $update;
	
	/**
	 * Whether to commit changes to the database (or just to the local array.)
	 */
	protected $remoteCommit = true;
	
	/**
	 * Start an update for multiple fields at once.
	 */
	public function startUpdate()
	{
		$this->update = array();
	}
	
	/**
	 * Commit any pending changes (such as multi-field updates.)
	 */
	public function commit()
	{
		if (!is_array($this->update)) {
			trigger_error('Cannot commit: No update started', E_USER_WARNING);
			return false;
		}
		
		// Commit changes to database
		if ($this->remoteCommit) {
			$op = $this->getOp();
			$idField = constant($class.'::ID_FIELD');
			$result = $op->doUpdateOrInsert(
				// Gather the ID and the new field
				array($idField => $this->id, $offset => $value),
				// On updates only update the new field (not the ID field)
				array($offset)
			);
		}
		
		// And to active data
		$this->localCommit();
		
		return $result;
	}
	
	/**
	 * Commit pending changes to active data only, not to the database.
	 */
	public function localCommit()
	{
		if (!is_array($this->update)) {
			trigger_error('Cannot commit: No update started', E_USER_WARNING);
			return false;
		}
		
		$this->entry = array_merge($this->entry, $this->update);
		$this->update = null;
	}
	
	/**
	 * Changes the remote commit setting for this object.
	 */
	public function setRemoteCommit($bool)
	{
		$this->remoteCommit = $bool;
	}
	
	/**
	 * Turn associative arrays into objects.
	 * 
	 * You can either pass a single array or an array of arrays.
	 */
	public function objectify($array)
	{
		$class = get_class($this);
		
		// TODO: PHP 5.3: Replace "constant($class.'::ID_FIELD')" by $class::ID_FIELD;
		$idField = constant($class.'::ID_FIELD');
		if (isset($array[$idField])) {
    		return new $class($array[$idField], $array);
		} elseif (isset($array[0][$idField])) {
			return array_map(array($this, 'objectify'), $array);
		} elseif (get_class($array) == $class) {
			// Work is already done...
			return $array;
		} elseif ($array === false) {
			return false;
		} elseif (!is_array($array)) {
			trigger_error('Invalid value for objectification', E_USER_WARNING);
			return $array;
		} elseif (count($array) == 0) {
			return array();
		} else {
			trigger_error('Could not find id field during objectification', E_USER_WARNING);
		}
	}

	/// Array access interface
	////////////////////////////////////////////////////////////////////////////
	
	public function offsetSet($offset, $value)
	{
		if (is_array($this->update)) {
			$this->update[$offset] = $value;
		} else {
			$this->update = array($offset => $value);
			$this->commit();
		}
	}
	
	public function offsetExists($offset)
	{
		$this->getEntry();
		return isset($this->entry[$offset]);
	}
	
	public function offsetUnset($offset)
	{
		trigger_error('Database fields cannot be unset.', E_USER_WARNING);
	}
	
	public function offsetGet($offset)
	{
		$this->getEntry();
		return isset($this->entry[$offset]) ? $this->entry[$offset] : null;
	}
}
?>
