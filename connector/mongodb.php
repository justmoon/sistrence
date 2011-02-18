<?php

class SisConnectionMongo
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
			Sis::error('Error connecting to MongoDB database.', array('db_error' => $e->getMessage(), 'db_errno' => 0));
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
		return new SisOperationMongo($collection, $this);
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
		Sis::DEBUG AND Sis::debugEcho('Mongo Execute: '.$js);
		
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

class SisOperationMongo extends SisOperation
{
	private $groupby;
	
	/**
	 * Takes condition parameters, turns them into a SisCondition object and returns it.
	 */
	public function cond()
	{
		$cond = $this->createCondition(func_get_args());
		$this->options['conditions'][] = $cond;
		return $cond;
	}
	
	public function __call($method, $args)
	{
		if (SisConditionMongo::isValid($method)) {
			$cond = new SisConditionMongo($method, $this, $args);
			$this->options['conditions'][] = $cond;
			return $cond;
		} else {
			trigger_error(sprintf('Call to undefined function: %s::%s()', get_class($this), $method), E_USER_ERROR);
		}
	}
	
	/**
	 * Drop a condition.
	 *
	 * Will look through the condition starting at the end and drop the first
	 * one that matches the parameter.
	 *
	 * Returns true if the condition was found.
	 */
	public function dropCond(SisConditionMongo $doomedCond)
	{
		$cond = end($this->options['conditions']);
		do {
			if ($cond === $doomedCond) {
				unset($this->options['conditions'][key($this->options['conditions'])]);
				return true;
			}
		} while (($cond = prev($this->options['conditions'])) !== false);
		return false;
	}
	
	public function eqAll($data)
	{
		foreach ($data as $field => $value) {
			$this->cond('field_equals', $field, $value);
		}
	}
	
	/**
	 * Takes condition parameters as an array, turns them into a SisCondition object and saves it.
	 */
	private function createCondition($params)
	{
		$type = array_shift($params);
		return new SisConditionMysqli($type, $this, $params);
	}
	
	public function sort($by, $order = 1)
	{
		$this->options['sort'][] = array($by, $order);
	}
	
	public function randomize()
	{
		$this->options['sort'] = 'randomize';
	}
	
	public function range($start, $count)
	{
		$this->options['range'] = array($start, $count);
	}
	
	public function fields($fields)
	{
		if (!is_array($fields)) {
			Sis::error('Custom fields must be provided as array in MongoDB connector.');
		}
		$this->options['custom_fields'] = $fields;
	}

	public function groupby($group)
	{
		$this->options['groupby'] = $group;
	}
	
	public function doJs($js)
	{
		return $this->c->getMongoDB()->execute($js);
	}

	public function doGet()
	{
		$query = $this->createQueryArray();
		$fields = (isset($this->options['custom_fields'])) ? $this->options['custom_fields'] : array();
		$collection = $this->c->selectCollection($this->table);

		Sis::DEBUG AND $DEBUG = array('.find(',$query,', ',$fields,')');
		
		$cursor = $collection->find($query, $fields);
		
		if (isset($this->options['sort'])) {
			$sort = $this->options['sort'];
			if ($sort == 'randomize') {
				Sis::error('MongoDB connector does not yet support random ordering', $this->c->getDebugInfo(array('query' => $query)));
				return false;
			} else {
				$sortClauses = array();
				foreach ($sort as $sortEntry) {
					$sortClauses[$sortEntry[0]] = ($sortEntry[1] == -1) ? -1 : 1;
				}
				if (count($sortClauses)) {
					$cursor->sort($sortClauses);

					Sis::DEBUG AND array_merge($DEBUG, array('.sort(',$sortClauses,')'));
				}
			}
		}
		
		if (isset($this->options['range'])) {
			$range = $this->options['range'];
			if ($range[0] > 0) $cursor->skip($range[0]);
			$cursor->limit($range[1]);

			Sis::DEBUG AND array_merge($DEBUG, array('.skip(',$range[0],').limit(',$range[1],')'));
		}

		Sis::DEBUG AND $this->debugEcho($DEBUG);

		if ($cursor->count()) {
			// prepare an array consisting of the results
			$data = array();
			while ($cursor->hasNext()) {
				$data[] = $cursor->getNext();
			}
			return $data;
		} else {
			// no error, but no results: return an empty array
			return array();
		}
	}
	
	public function doGetOne()
	{
		$query = $this->createQueryArray();
		$fields = (isset($this->options['custom_fields'])) ? $this->options['custom_fields'] : array();
		$collection = $this->c->selectCollection($this->table);
		
		Sis::DEBUG AND $this->debugEcho(array('.findOne(',$query,', ',$fields,')'));
		
		return $collection->findOne($query, $fields);
	}
	
	public function doUpdate($data, $fields = false)
	{
		if ($data instanceof SisUpdateRuleset) {
			// TODO: well, implement this
		} elseif (is_array($data)) {
			$query = $this->createQueryArray();
			$collection = $this->c->selectCollection($this->table);
			
			if (is_array($fields) && count($fields)) {
				$data = $this->filterDataByFieldnames($data, $fields);
			}

			if (count($array) == 0) return true;

			Sis::DEBUG AND $this->debugEcho(array('.update(',$query,', ',$data,')'));

			if ($collection->update($query, $data)) {
				return true;
			} else {
				Sis::error('Update request failed!', $this->c->getDebugInfo(array('query' => $query, 'data' => $data)));
				return false;
			}
		} else {
			Sis::error('Invalid data for update', array('data' => $data));
			return false;
		}
	}
	
	public function doDelete()
	{
		$query = $this->createQueryArray();
		$collection = $this->c->selectCollection($this->table);

		Sis::DEBUG AND $this->debugEcho(array('.remove(',$query,')'));

		if ($collection->remove($query)) {
			return true;
		} else {
			Sis::error('Could not delete elements!', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}
	
	public function doCount()
	{
		$query = $this->createQueryArray();
		$fields = (isset($this->options['custom_fields'])) ? $this->options['custom_fields'] : array();
		$collection = $this->c->selectCollection($this->table);
		
		Sis::DEBUG AND $this->debugEcho(array('.find(',$query,').count()'));

		$cursor = $collection->find($query);

		return $cursor->count();
	}
	
	private function createQueryArray()
	{
		$query = array();
		if (isset($this->options['conditions']) && !empty($this->options['conditions'])) {
			foreach($this->options['conditions'] as $param) {
				list($key, $value) = $param->toPair();
				$query[$key] = $value;
			}
		}

		// TODO: Re-implement aggregation functionality

		return $query;
	}
	
	public function doInsert($data, $fields = false)
	{
		if (is_array($data) && count($data)) {
			if (is_array($fields) && count($fields)) {
				$data = $this->filterDataByFieldnames($data, $fields);
			}
			$collection = $this->c->selectCollection($this->table);

			Sis::DEBUG AND $this->debugEcho(array('.insert(',$data,')'));

			return $collection->insert($data);
		} else {
			Sis::error('Invalid data for insert', array('data' => $data));
			return false;
		}
	}
	
	public function doUpdateOrInsert($data, $updateFields = false, $insertFields = false)
	{
		if ($this->doCount()) {
			$this->doUpdate($data, $updateFields);
		} else {
			$this->doInsert($data, $insertFields);
		}
	}
	
	public function doTruncate()
	{
		$collection = $this->c->selectCollection($this->table);

		Sis::DEBUG AND $this->debugEcho(array('.remove()'));
	
		if ($collection->remove($query)) {
			return true;
		} else {
			Sis::error('Could not truncate table', $this->c->getDebugInfo(array('query' => $query)));
			return false;
		}
	}
	
	private function filterDataByFieldnames($data, $fields)
	{
		$filteredData = array();
		foreach ($fields as $field) {
			if (isset($data[$field])) {
				$filteredData[$field] = $data[$field];
			} else {
				// just ignore this case
				continue;
			}
		}
		return $filteredData;
	}
	
	public function tableExists()
	{
		$query = array('name' => $this->c->getDatabaseName().'.'.$this->table);
		
		Sis::DEBUG AND $this->debugEcho(array('.find(',$query,').count()'), 'system.namespaces');
		                                      
		return !!$this->c->selectCollection('system.namespaces')->find($query)->count();
	}
	
	public function getInsertId()
	{
		// TODO: We could implement this when the "safe" option is used
		Sis::error('Insert ID is not implemented in MongoDB connector.');
	}

	public function getAffectedRows()
	{
		// TODO: We could implement this when the "safe" option is used
		Sis::error('Affected rows is not implemented in MongoDB connector.');
	}

	protected function debugEcho($arr, $table = null)
	{
		$msg = 'MongoDB Debug: ';

		if ($table === null) {
			$table = $this->c->getDatabaseName();
			$table .= '.';
			$table .= $this->table;
		}

		$msg .= $table;

		foreach ($arr as $el) {
			if (is_string($el)) {
				$msg .= $el;
			} else {
				$msg .= json_encode($el);
			}
		}

		$msg .= ';';

		Sis::DEBUG AND Sis::debugEcho($msg);
	}
}

class SisConditionMongo
{
	private $type;
	private $op;
	private $params;
	
	public function __construct($type, $op, $params)
	{
		if (!self::isValid($type)) {
			Sis::error('Invalid condition type!', array('type' => $type));
		}
	
		$this->type = self::resolveAliases($type);
		$this->op = $op;
		$this->params = $params;

		// Operations starting with "field" accept an array shorthand for
		// operating on multiple fields.
		if (substr($this->type, 0, 6) == 'field_' && is_array($params[0])) {

			$subConds = array();
			foreach ($params[0] as $fieldName => $value) {
				$subConds[] = new self($this->type, $op,
				                                     array($fieldName, $value));
			}
			
			$this->type = 'merge_and';
			$this->op = $op;
			$this->params = $subConds;

			// This is an optimization - we skip prepare_merge_and because
			// we already know our subconditions are not registered with
			// the operation object.
			return;
		}
		
		// some conditions need preparation
		$method = 'prepare_'.$this->type;
		
		if (method_exists(__CLASS__, $method)) {
			self::$method($this->op, $this->params);
		}
	}
	
	public function toPair()
	{
		$method = 'pair_'.$this->type;
		
		return self::$method($this->op, $this->params);
	}
	
	////////////////////////////////////////////////////////////////////////////
	// BEGIN Condition Library
	
	private static $aliases = array(
		'and' => 'merge_and',
		'or' => 'merge_or',
		
		'not' => 'bool_not',
		
		'eq' => 'field_equals',
		'ne' => 'field_nequals',
		'contains' =>'field_contains',
		'starts' => 'field_starts',
		'ends' => 'field_ends',
		'gt' =>'field_greater',
		'lt' =>'field_less',
		'gtoe' => 'field_greater_or_equal',
		'ltoe' => 'field_less_or_equal',
		
		'null' => 'isnull',
		
		'field_bigger' => 'field_greater',
		'field_bigger_or_equal' => 'field_greater_or_equal',
		'field_smaller' => 'field_less',
		'field_smaller_or_equal' => 'field_less_or_equal',
	);
	
	static public function isValid($type)
	{
		$type = strtolower($type);
		return method_exists(__CLASS__, 'pair_'.$type) || isset(self::$aliases[$type]);
	}
	
	static public function resolveAliases($type)
	{
		$type = strtolower($type);
		if (isset(self::$aliases[$type])) {
			$type = self::$aliases[$type];
		}
		return $type;
	}
	
	static private function prepare_merge_or($op, $params)
	{
		// TODO: Implement
	}
	
	static private function prepare_merge_and($op, $params)
	{
		// TODO: Implement
	}
	
	static private function prepare_bool_not($op, $params)
	{
		// TODO: Implement
	}

	static private function pair_merge_and($op, $params)
	{
		// TODO: Implement
	}
	
	static private function pair_merge_or($op, $params)
	{
		// TODO: Implement
	}
	
	static private function pair_bool_not($op, $params)
	{
		// TODO: Implement
	}
				
	static private function pair_field_equals($op, $params)
	{
		return array($params[0], $params[1]);
	}
	
	static private function pair_field_nequals($op, $params)
	{
		// TODO: Implement
	}
	
	static private function pair_field_contains($op, $params)
	{
		// TODO: Implement
	}
			
	static private function pair_field_starts($op, $params)
	{
		// TODO: Implement
	}
	
	static private function pair_field_ends($op, $params)
	{
		// TODO: Implement
	}
	
	static private function pair_field_greater($op, $params)
	{
		// TODO: Implement
	}
	
	static private function pair_field_greater_or_equal($op, $params)
	{
		// TODO: Implement
	}
		
	static private function pair_field_less($op, $params)
	{
		// TODO: Implement
	}
		
	static private function pair_field_less_or_equal($op, $params)
	{
		// TODO: Implement
	}
		
	static private function pair_isnull($op, $params)
	{
		// TODO: Implement
	}
		
	static private function pair_notnull($op, $params)
	{
		// TODO: Implement
	}
	
	static private function util_filter_subconditions($op, $conds)
	{
		$cond = end($conds);
		do {
			$op->dropCond($cond);
		} while (($cond = prev($conds)) !== false);
	}
}

class SisFieldMysqli extends SisField
{
}
?>
