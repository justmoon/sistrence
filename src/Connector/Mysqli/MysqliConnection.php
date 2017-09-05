<?php
namespace Sistrence\Connector\Mysqli;

use Sistrence\Sistrence;
use \mysqli;

class MysqliConnection
{
	private $mysqli;

	private $user;
	private $password;
	private $host;
	private $database;

	public function __construct($user, $password, $host, $database)
	{
		$this->user = $user;
		$this->password = $password;
		$this->host = $host;
		$this->database = $database;
	}

	public function __destruct()
	{
		$this->close();
	}

	public function op($table)
	{
		return new MysqliOperation($table, $this);
	}

	public function query($sql)
	{
		if (!$this->mysqli) $this->connect();

		// in debug mode, we will output all queries launched to the database
		Sistrence::DEBUG AND Sistrence::debugEcho('SQL Query: '.$sql);

		// deploy the query, return the result
		return $this->mysqli->query($sql);
	}

	public function queryMulti($sql)
	{
		if (!$this->mysqli) $this->connect();

		if (is_array($sql)) $sql = implode(PHP_EOL, $sql);

		// in debug mode, we will output all queries launched to the database
		Sistrence::DEBUG AND Sistrence::debugEcho('SQL Multi Query: '.$sql);

		// deploy the query
		$result = $this->mysqli->multi_query($sql);

		if (!$result) return false;

		while ($this->mysqli->next_result()) {}

		return true;
	}

	public function ping()
	{
		if (!$this->mysqli) $this->connect();

		$this->mysqli->ping();
	}

	public function close()
	{
		if ($this->mysqli) {
			$this->mysqli->close();
			$this->mysqli = null;
		}
	}

	public function reconnect()
	{
		$this->close();
		$this->connect();
	}

	public function getDebugInfo($data = array())
	{
		if (!$this->mysqli) {
			return array(
				'db_error' => mysqli_connect_error(),
				'db_errno' => mysqli_connect_errno()
			);
		} else {
			return array(
				'db_error' => $this->mysqli->error,
				'db_errno' => $this->mysqli->errno
			);
		}
	}

	public function getInsertId()
	{
		if (!$this->mysqli) return 0;

		return $this->mysqli->insert_id;
	}

	public function getAffectedRows()
	{
		if (!$this->mysqli) return 0;

		return $this->mysqli->affected_rows;
	}

	public function getMysqli()
	{
		if (!$this->mysqli) $this->connect();

		return $this->mysqli;
	}

	public function escapeString($string)
	{
		if (!$this->mysqli) $this->connect();

		return $this->mysqli->escape_string($string);
	}

	public function connect()
	{
		$this->mysqli = new mysqli($this->host, $this->user, $this->password, $this->database);

		if (!$this->mysqli) {
			Sistrence::error('Error connecting to MySQL database.', array('db_error' => mysqli_connect_error(), 'db_errno' => mysqli_connect_errno()));
			return;
		}

		$this->mysqli->set_charset('utf8');
	}
}
?>
