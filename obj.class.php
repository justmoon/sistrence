<?php
$dir = dirname(__FILE__);
require_once($dir.'/set.class.php');
abstract class SisObject implements ArrayAccess
{
	const TABLE = 'no_table_set_check_your_db_object';
	const ID_FIELD = 'id';

	protected $id;
	private $entry;

	public $ignore_missing = false;

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
			trigger_error('Database error while getting entry for table '.$this::TABLE.' with &rsquo;'.$this::ID_FIELD.'&rsquo; = '.var_export($this->id, true), E_USER_WARNING);
			$this->entry = array();
			return false;
		}

		if ($this->entry === false) {
			if (!$this->ignore_missing) {
				trigger_error('Entry for table '.$this::TABLE.' with &rsquo;'.$this::ID_FIELD.'&rsquo; = '.var_export($this->id, true).' not found', E_USER_WARNING);
				return false;
			}
			$this->entry = array();
		}

		return $this->entry;
	}

	static public function getBy($field, $value)
	{
		$class = get_called_class();

		$op = Sis::op($class::TABLE);
		$op->eq($field, $value);
		$entry = $op->doGetOne();

		return self::objectify($entry);
	}

	static public function getAll()
	{
		$class = get_called_class();

		$op = Sis::op($class::TABLE);
		return self::objectify($op->doGet());
	}

	static public function create($data)
	{
		$class = get_called_class();

		$op = Sis::op($class::TABLE);
		$id = $op->doInsert($data);

		// Add id to data
		$data[$class::ID_FIELD] = $id;

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
	 * Returns a database operation for this object's table.
	 */
	static public function op()
	{
		$class = get_called_class();
		return Sis::op($class::TABLE);
	}

	/**
	 * Returns a database operation, prepped and ready to go.
	 */
	public function getOp()
	{
		$op = Sis::op($this::TABLE);
		if ($this->id !== null) $op->eq($this::ID_FIELD, $this->id);

		if ($this->customFields !== null) {
			call_user_func(array($op, 'fields'), implode(', ', $this->customFields));
		}

		return $op;
	}

	public function getRowOp()
	{
		$op = Sis::op($this::TABLE);
		if ($this->id !== null) {
			$op->eq($this::ID_FIELD, $this->id);
		} else trigger_error('Cannot create row op: Object has no ID', E_USER_ERROR);

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

		if ($this->id === null) {
			trigger_error('Cannot commit: Object has no ID', E_USER_WARNING);
			return false;
		}

		// Commit changes to database
		if ($this->remoteCommit) {
			// Get the data and fields to update
			$data = $this->update;
			$fields = array_keys($data);

			// Add the ID field in case we need to create the row
			$idField = $this::ID_FIELD;
			$data[$idField] = $this->id;

			$op = $this->getRowOp();
			$result = $op->doUpdateOrInsert(
				// This array contains the new data and the ID field
				$data,
				// But on updates we only use the new data (not the ID field)
				$fields
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

		// Ensure entry is loaded
		$this->getEntry();

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
	static public function objectify($array)
	{
		$class = get_called_class();

		$idField = $class::ID_FIELD;
		if (is_array($array) && isset($array[$idField])) {
			return new $class($array[$idField], $array);
		} elseif (is_array($array) && isset($array[0][$idField])) {
			return array_map(array($class, 'objectify'), $array);
		} elseif (@get_class($array) == $class) {
			// Work is already done...
			return $array;
		} elseif ($array === false) {
			return false;
		} elseif (is_numeric($array) && ((int) $array) == $array) {
			return new $class($array);
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
