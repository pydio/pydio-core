<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * dibi connection.
 *
 * @author     David Grudl
 * @package    dibi
 *
 * @property-read bool $connected
 * @property-read mixed $config
 * @property-read IDibiDriver $driver
 * @property-read int $affectedRows
 * @property-read int $insertId
 * @property-read DibiDatabaseInfo $databaseInfo
 */
class DibiConnection extends DibiObject
{
	/** @var array of function(DibiEvent $event); Occurs after query is executed */
	public $onEvent;

	/** @var array  Current connection configuration */
	private $config;

	/** @var IDibiDriver */
	private $driver;

	/** @var DibiTranslator */
	private $translator;

	/** @var bool  Is connected? */
	private $connected = FALSE;

	/** @var DibiHashMap Substitutes for identifiers */
	private $substitutes;


	/**
	 * Connection options: (see driver-specific options too)
	 *   - lazy (bool) => if TRUE, connection will be established only when required
	 *   - result (array) => result set options
	 *       - formatDateTime => date-time format (if empty, DateTime objects will be returned)
	 *   - profiler (array or bool)
	 *       - run (bool) => enable profiler?
	 *       - file => file to log
	 *   - substitutes (array) => map of driver specific substitutes (under development)

	 * @param  mixed   connection parameters
	 * @param  string  connection name
	 * @throws DibiException
	 */
	public function __construct($config, $name = NULL)
	{
		if (is_string($config)) {
			parse_str($config, $config);

		} elseif ($config instanceof Traversable) {
			$tmp = array();
			foreach ($config as $key => $val) {
				$tmp[$key] = $val instanceof Traversable ? iterator_to_array($val) : $val;
			}
			$config = $tmp;

		} elseif (!is_array($config)) {
			throw new InvalidArgumentException('Configuration must be array, string or object.');
		}

		self::alias($config, 'username', 'user');
		self::alias($config, 'password', 'pass');
		self::alias($config, 'host', 'hostname');
		self::alias($config, 'result|formatDate', 'resultDate');
		self::alias($config, 'result|formatDateTime', 'resultDateTime');

		if (!isset($config['driver'])) {
			$config['driver'] = dibi::$defaultDriver;
		}

		$class = preg_replace(array('#\W#', '#sql#'), array('_', 'Sql'), ucfirst(strtolower($config['driver'])));
		$class = "Dibi{$class}Driver";
		if (!class_exists($class)) {
			include_once dirname(__FILE__) . "/../drivers/$class.php";

			if (!class_exists($class, FALSE)) {
				throw new DibiException("Unable to create instance of dibi driver '$class'.");
			}
		}

		$config['name'] = $name;
		$this->config = $config;
		$this->driver = new $class;
		$this->translator = new DibiTranslator($this);

		// profiler
		$profilerCfg = & $config['profiler'];
		if (is_scalar($profilerCfg)) {
			$profilerCfg = array('run' => (bool) $profilerCfg);
		}
		if (!empty($profilerCfg['run'])) {
			$filter = isset($profilerCfg['filter']) ? $profilerCfg['filter'] : DibiEvent::QUERY;

			if (isset($profilerCfg['file'])) {
				$this->onEvent[] = array(new DibiFileLogger($profilerCfg['file'], $filter), 'logEvent');
			}

			if (DibiFirePhpLogger::isAvailable()) {
				$this->onEvent[] = array(new DibiFirePhpLogger($filter), 'logEvent');
			}

			if (!interface_exists('Tracy\IBarPanel') && interface_exists('Nette\Diagnostics\IBarPanel') && class_exists('DibiNettePanel')) {
				$panel = new DibiNettePanel(isset($profilerCfg['explain']) ? $profilerCfg['explain'] : TRUE, $filter);
				$panel->register($this);
			}
		}

		$this->substitutes = new DibiHashMap(create_function('$expr', 'return ":$expr:";'));
		if (!empty($config['substitutes'])) {
			foreach ($config['substitutes'] as $key => $value) {
				$this->substitutes->$key = $value;
			}
		}

		if (empty($config['lazy'])) {
			$this->connect();
		}
	}


	/**
	 * Automatically frees the resources allocated for this result set.
	 * @return void
	 */
	public function __destruct()
	{
		// disconnects and rolls back transaction - do not rely on auto-disconnect and rollback!
		$this->connected && $this->driver->getResource() && $this->disconnect();
	}


	/**
	 * Connects to a database.
	 * @return void
	 */
	final public function connect()
	{
		$event = $this->onEvent ? new DibiEvent($this, DibiEvent::CONNECT) : NULL;
		try {
			$this->driver->connect($this->config);
			$this->connected = TRUE;
			$event && $this->onEvent($event->done());

		} catch (DibiException $e) {
			$event && $this->onEvent($event->done($e));
			throw $e;
		}
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	final public function disconnect()
	{
		$this->driver->disconnect();
		$this->connected = FALSE;
	}


	/**
	 * Returns TRUE when connection was established.
	 * @return bool
	 */
	final public function isConnected()
	{
		return $this->connected;
	}


	/**
	 * Returns configuration variable. If no $key is passed, returns the entire array.
	 * @see self::__construct
	 * @param  string
	 * @param  mixed  default value to use if key not found
	 * @return mixed
	 */
	final public function getConfig($key = NULL, $default = NULL)
	{
		if ($key === NULL) {
			return $this->config;

		} elseif (isset($this->config[$key])) {
			return $this->config[$key];

		} else {
			return $default;
		}
	}


	/**
	 * Apply configuration alias or default values.
	 * @param  array  connect configuration
	 * @param  string key
	 * @param  string alias key
	 * @return void
	 */
	public static function alias(& $config, $key, $alias)
	{
		$foo = & $config;
		foreach (explode('|', $key) as $key) {
			$foo = & $foo[$key];
		}

		if (!isset($foo) && isset($config[$alias])) {
			$foo = $config[$alias];
			unset($config[$alias]);
		}
	}


	/**
	 * Returns the driver and connects to a database in lazy mode.
	 * @return IDibiDriver
	 */
	final public function getDriver()
	{
		$this->connected || $this->connect();
		return $this->driver;
	}


	/**
	 * Generates (translates) and executes SQL query.
	 * @param  array|mixed      one or more arguments
	 * @return DibiResult|int   result set object (if any)
	 * @throws DibiException
	 */
	final public function query($args)
	{
		$args = func_get_args();
		return $this->nativeQuery($this->translateArgs($args));
	}


	/**
	 * Generates SQL query.
	 * @param  array|mixed      one or more arguments
	 * @return string
	 * @throws DibiException
	 */
	final public function translate($args)
	{
		$args = func_get_args();
		return $this->translateArgs($args);
	}


	/**
	 * Generates and prints SQL query.
	 * @param  array|mixed  one or more arguments
	 * @return bool
	 */
	final public function test($args)
	{
		$args = func_get_args();
		try {
			dibi::dump($this->translateArgs($args));
			return TRUE;

		} catch (DibiException $e) {
			if ($e->getSql()) {
				dibi::dump($e->getSql());
			} else {
				echo get_class($e) . ': ' . $e->getMessage() . (PHP_SAPI === 'cli' ? "\n" : '<br>');
			}
			return FALSE;
		}
	}


	/**
	 * Generates (translates) and returns SQL query as DibiDataSource.
	 * @param  array|mixed      one or more arguments
	 * @return DibiDataSource
	 * @throws DibiException
	 */
	final public function dataSource($args)
	{
		$args = func_get_args();
		return new DibiDataSource($this->translateArgs($args), $this);
	}


	/**
	 * Generates SQL query.
	 * @param  array
	 * @return string
	 */
	private function translateArgs($args)
	{
		$this->connected || $this->connect();
		return $this->translator->translate($args);
	}


	/**
	 * Executes the SQL query.
	 * @param  string           SQL statement.
	 * @return DibiResult|int   result set object (if any)
	 * @throws DibiException
	 */
	final public function nativeQuery($sql)
	{
		$this->connected || $this->connect();

		dibi::$sql = $sql;
		$event = $this->onEvent ? new DibiEvent($this, DibiEvent::QUERY, $sql) : NULL;
		try {
			$res = $this->driver->query($sql);

		} catch (DibiException $e) {
			$event && $this->onEvent($event->done($e));
			throw $e;
		}

		if ($res) {
			$res = $this->createResultSet($res);
		} else {
			$res = $this->driver->getAffectedRows();
		}

		$event && $this->onEvent($event->done($res));
		return $res;
	}


	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
	 * @return int  number of rows
	 * @throws DibiException
	 */
	public function getAffectedRows()
	{
		$this->connected || $this->connect();
		$rows = $this->driver->getAffectedRows();
		if (!is_int($rows) || $rows < 0) {
			throw new DibiException('Cannot retrieve number of affected rows.');
		}
		return $rows;
	}


	/**
	 * Gets the number of affected rows. Alias for getAffectedRows().
	 * @return int  number of rows
	 * @throws DibiException
	 */
	public function affectedRows()
	{
		return $this->getAffectedRows();
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @param  string     optional sequence name
	 * @return int
	 * @throws DibiException
	 */
	public function getInsertId($sequence = NULL)
	{
		$this->connected || $this->connect();
		$id = $this->driver->getInsertId($sequence);
		if ($id < 1) {
			throw new DibiException('Cannot retrieve last generated ID.');
		}
		return (int) $id;
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column. Alias for getInsertId().
	 * @param  string     optional sequence name
	 * @return int
	 * @throws DibiException
	 */
	public function insertId($sequence = NULL)
	{
		return $this->getInsertId($sequence);
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 */
	public function begin($savepoint = NULL)
	{
		$this->connected || $this->connect();
		$event = $this->onEvent ? new DibiEvent($this, DibiEvent::BEGIN, $savepoint) : NULL;
		try {
			$this->driver->begin($savepoint);
			$event && $this->onEvent($event->done());

		} catch (DibiException $e) {
			$event && $this->onEvent($event->done($e));
			throw $e;
		}
	}


	/**
	 * Commits statements in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 */
	public function commit($savepoint = NULL)
	{
		$this->connected || $this->connect();
		$event = $this->onEvent ? new DibiEvent($this, DibiEvent::COMMIT, $savepoint) : NULL;
		try {
			$this->driver->commit($savepoint);
			$event && $this->onEvent($event->done());

		} catch (DibiException $e) {
			$event && $this->onEvent($event->done($e));
			throw $e;
		}
	}


	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 */
	public function rollback($savepoint = NULL)
	{
		$this->connected || $this->connect();
		$event = $this->onEvent ? new DibiEvent($this, DibiEvent::ROLLBACK, $savepoint) : NULL;
		try {
			$this->driver->rollback($savepoint);
			$event && $this->onEvent($event->done());

		} catch (DibiException $e) {
			$event && $this->onEvent($event->done($e));
			throw $e;
		}
	}


	/**
	 * Result set factory.
	 * @param  IDibiResultDriver
	 * @return DibiResult
	 */
	public function createResultSet(IDibiResultDriver $resultDriver)
	{
		$res = new DibiResult($resultDriver);
		return $res->setFormat(dibi::DATE, $this->config['result']['formatDate'])
			->setFormat(dibi::DATETIME, $this->config['result']['formatDateTime']);
	}


	/********************* fluent SQL builders ****************d*g**/


	/**
	 * @return DibiFluent
	 */
	public function command()
	{
		return new DibiFluent($this);
	}


	/**
	 * @param  string    column name
	 * @return DibiFluent
	 */
	public function select($args)
	{
		$args = func_get_args();
		return $this->command()->__call('select', $args);
	}


	/**
	 * @param  string   table
	 * @param  array
	 * @return DibiFluent
	 */
	public function update($table, $args)
	{
		if (!(is_array($args) || $args instanceof Traversable)) {
			throw new InvalidArgumentException('Arguments must be array or Traversable.');
		}
		return $this->command()->update('%n', $table)->set($args);
	}


	/**
	 * @param  string   table
	 * @param  array
	 * @return DibiFluent
	 */
	public function insert($table, $args)
	{
		if ($args instanceof Traversable) {
			$args = iterator_to_array($args);
		} elseif (!is_array($args)) {
			throw new InvalidArgumentException('Arguments must be array or Traversable.');
		}
		return $this->command()->insert()
			->into('%n', $table, '(%n)', array_keys($args))->values('%l', $args);
	}


	/**
	 * @param  string   table
	 * @return DibiFluent
	 */
	public function delete($table)
	{
		return $this->command()->delete()->from('%n', $table);
	}


	/********************* substitutions ****************d*g**/


	/**
	 * Returns substitution hashmap.
	 * @return DibiHashMap
	 */
	public function getSubstitutes()
	{
		return $this->substitutes;
	}


	/**
	 * Provides substitution.
	 * @return string
	 */
	public function substitute($value)
	{
		return strpos($value, ':') === FALSE ? $value : preg_replace_callback('#:([^:\s]*):#', array($this, 'subCb'), $value);
	}


	/**
	 * Substitution callback.
	 */
	private function subCb($m)
	{
		return $this->substitutes->{$m[1]};
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Executes SQL query and fetch result - shortcut for query() & fetch().
	 * @param  array|mixed    one or more arguments
	 * @return DibiRow
	 * @throws DibiException
	 */
	public function fetch($args)
	{
		$args = func_get_args();
		return $this->query($args)->fetch();
	}


	/**
	 * Executes SQL query and fetch results - shortcut for query() & fetchAll().
	 * @param  array|mixed    one or more arguments
	 * @return DibiRow[]
	 * @throws DibiException
	 */
	public function fetchAll($args)
	{
		$args = func_get_args();
		return $this->query($args)->fetchAll();
	}


	/**
	 * Executes SQL query and fetch first column - shortcut for query() & fetchSingle().
	 * @param  array|mixed    one or more arguments
	 * @return string
	 * @throws DibiException
	 */
	public function fetchSingle($args)
	{
		$args = func_get_args();
		return $this->query($args)->fetchSingle();
	}


	/**
	 * Executes SQL query and fetch pairs - shortcut for query() & fetchPairs().
	 * @param  array|mixed    one or more arguments
	 * @return string
	 * @throws DibiException
	 */
	public function fetchPairs($args)
	{
		$args = func_get_args();
		return $this->query($args)->fetchPairs();
	}


	/********************* misc ****************d*g**/


	/**
	 * Import SQL dump from file - extreme fast!
	 * @param  string  filename
	 * @return int  count of sql commands
	 */
	public function loadFile($file)
	{
		$this->connected || $this->connect();
		@set_time_limit(0); // intentionally @

		$handle = @fopen($file, 'r'); // intentionally @
		if (!$handle) {
			throw new RuntimeException("Cannot open file '$file'.");
		}

		$count = 0;
		$delimiter = ';';
		$sql = '';
		while (!feof($handle)) {
			$s = rtrim(fgets($handle));
			if (substr($s, 0, 10) === 'DELIMITER ') {
				$delimiter = substr($s, 10);

			} elseif (substr($s, -strlen($delimiter)) === $delimiter) {
				$sql .= substr($s, 0, -strlen($delimiter));
				$this->driver->query($sql);
				$sql = '';
				$count++;

			} else {
				$sql .= $s . "\n";
			}
		}
		if (trim($sql) !== '') {
			$this->driver->query($sql);
			$count++;
		}
		fclose($handle);
		return $count;
	}


	/**
	 * Gets a information about the current database.
	 * @return DibiDatabaseInfo
	 */
	public function getDatabaseInfo()
	{
		$this->connected || $this->connect();
		return new DibiDatabaseInfo($this->driver->getReflector(), isset($this->config['database']) ? $this->config['database'] : NULL);
	}


	/**
	 * Prevents unserialization.
	 */
	public function __wakeup()
	{
		throw new DibiNotSupportedException('You cannot serialize or unserialize ' . $this->getClass() . ' instances.');
	}


	/**
	 * Prevents serialization.
	 */
	public function __sleep()
	{
		throw new DibiNotSupportedException('You cannot serialize or unserialize ' . $this->getClass() . ' instances.');
	}

}
