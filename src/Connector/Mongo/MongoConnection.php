<?php
namespace Sistrence\Connector\Mongo;

use Sistrence\Sistrence;

class ConnectionMongo
{
	private $mongo;
	private $mongodb;

	private $server;
	private $database;
	private $options;

	public function __construct($database, $server, $options)
	{
		$this->database = $database;
		$this->server = $server;
		$this->options = $options;
	}

	public function __destruct()
	{
		$this->close();
	}

	public function connect()
	{
		try {
			$this->mongo = new Mongo($this->server, $this->options);
			$this->mongodb = $this->mongo->selectDB($this->database);
		} catch (MongoException $e) {
			Sistrence::error('Error connecting to MongoDB database.', array('db_error' => $e->getMessage(), 'db_errno' => 0));
			return;
		}
	}

	public function close()
	{
		if ($this->mongodb) {
			$this->mongo->close();
			$this->mongo = null;
			$this->mongodb = null;
		}
	}

	public function op($collection)
	{
		return new MongoOperation($collection, $this);
	}

	public function selectCollection($collection)
	{
		if (!$this->mongodb) $this->connect();

		return $this->mongodb->selectCollection($collection);
	}

	public function query($js)
	{
		if (!$this->mongodb) $this->connect();

		// in debug mode, we will output all queries launched to the database
		Sistrence::DEBUG AND Sistrence::debugEcho('Mongo Execute: '.$js);

		// deploy the query, return the result
		return $this->mongodb->execute($js);
	}

	public function ping()
	{
		if (!$this->mongodb) $this->connect();

		$this->mongodb->execute('1;');
	}

	public function reconnect()
	{
		$this->close();
		$this->connect();
	}

	public function getDebugInfo($data = array())
	{
		if ($this->mongodb) {
			return $this->mongodb->lastError();
		} else {
			return array(
				'db_error' => 'Not connected',
				'db_errno' => 0
			);
		}
	}

	public function getMongo()
	{
		if (!$this->mongodb) $this->connect();

		return $this->mongo;
	}

	public function getMongoDB()
	{
		if (!$this->mongodb) $this->connect();

		return $this->mongodb;
	}

	public function getDatabaseName()
	{
		return $this->database;
	}
}


?>
