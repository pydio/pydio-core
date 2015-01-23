<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


require_once dirname(__FILE__) . '/DibiMySqlReflector.php';


/**
 * The dibi driver for MySQL database via improved extension.
 *
 * Driver options:
 *   - host => the MySQL server host name
 *   - port (int) => the port number to attempt to connect to the MySQL server
 *   - socket => the socket or named pipe
 *   - username (or user)
 *   - password (or pass)
 *   - database => the database name to select
 *   - options (array) => array of driver specific constants (MYSQLI_*) and values {@see mysqli_options}
 *   - flags (int) => driver specific constants (MYSQLI_CLIENT_*) {@see mysqli_real_connect}
 *   - charset => character encoding to set (default is utf8)
 *   - persistent (bool) => try to find a persistent link?
 *   - unbuffered (bool) => sends query without fetching and buffering the result rows automatically?
 *   - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
 *   - resource (mysqli) => existing connection resource
 *   - lazy, profiler, result, substitutes, ... => see DibiConnection options
 *
 * @author     David Grudl
 * @package    dibi\drivers
 */
class DibiMySqliDriver extends DibiObject implements IDibiDriver, IDibiResultDriver
{
	const ERROR_ACCESS_DENIED = 1045;
	const ERROR_DUPLICATE_ENTRY = 1062;
	const ERROR_DATA_TRUNCATED = 1265;

	/** @var mysqli  Connection resource */
	private $connection;

	/** @var mysqli_result  Resultset resource */
	private $resultSet;

	/** @var bool */
	private $autoFree = TRUE;

	/** @var bool  Is buffered (seekable and countable)? */
	private $buffered;


	/**
	 * @throws DibiNotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('mysqli')) {
			throw new DibiNotSupportedException("PHP extension 'mysqli' is not loaded.");
		}
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws DibiException
	 */
	public function connect(array & $config)
	{
		mysqli_report(MYSQLI_REPORT_OFF);
		if (isset($config['resource'])) {
			$this->connection = $config['resource'];

		} else {
			// default values
			$config += array(
				'charset' => 'utf8',
				'timezone' => date('P'),
				'username' => ini_get('mysqli.default_user'),
				'password' => ini_get('mysqli.default_pw'),
				'socket' => (string) ini_get('mysqli.default_socket'),
				'port' => NULL,
			);
			if (!isset($config['host'])) {
				$host = ini_get('mysqli.default_host');
				if ($host) {
					$config['host'] = $host;
					$config['port'] = ini_get('mysqli.default_port');
				} else {
					$config['host'] = NULL;
					$config['port'] = NULL;
				}
			}

			$foo = & $config['flags'];
			$foo = & $config['database'];

			$this->connection = mysqli_init();
			if (isset($config['options'])) {
				if (is_scalar($config['options'])) {
					$config['flags'] = $config['options']; // back compatibility
					trigger_error(__CLASS__ . ": configuration item 'options' must be array; for constants MYSQLI_CLIENT_* use 'flags'.", E_USER_NOTICE);
				} else {
					foreach ((array) $config['options'] as $key => $value) {
						mysqli_options($this->connection, $key, $value);
					}
				}
			}
			@mysqli_real_connect($this->connection, (empty($config['persistent']) ? '' : 'p:') . $config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $config['socket'], $config['flags']); // intentionally @

			if ($errno = mysqli_connect_errno()) {
				throw new DibiDriverException(mysqli_connect_error(), $errno);
			}
		}

		if (isset($config['charset'])) {
			if (!@mysqli_set_charset($this->connection, $config['charset'])) {
				$this->query("SET NAMES '$config[charset]'");
			}
		}

		if (isset($config['sqlmode'])) {
			$this->query("SET sql_mode='$config[sqlmode]'");
		}

		if (isset($config['timezone'])) {
			$this->query("SET time_zone='$config[timezone]'");
		}

		$this->buffered = empty($config['unbuffered']);
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		mysqli_close($this->connection);
	}


	/**
	 * Executes the SQL query.
	 * @param  string      SQL statement.
	 * @return IDibiResultDriver|NULL
	 * @throws DibiDriverException
	 */
	public function query($sql)
	{
		$res = @mysqli_query($this->connection, $sql, $this->buffered ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT); // intentionally @

		if (mysqli_errno($this->connection)) {
			throw new DibiDriverException(mysqli_error($this->connection), mysqli_errno($this->connection), $sql);

		} elseif (is_object($res)) {
			return $this->createResultDriver($res);
		}
	}


	/**
	 * Retrieves information about the most recently executed query.
	 * @return array
	 */
	public function getInfo()
	{
		$res = array();
		preg_match_all('#(.+?): +(\d+) *#', mysqli_info($this->connection), $matches, PREG_SET_ORDER);
		if (preg_last_error()) {
			throw new DibiPcreException;
		}

		foreach ($matches as $m) {
			$res[$m[1]] = (int) $m[2];
		}
		return $res;
	}


	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
	 * @return int|FALSE  number of rows or FALSE on error
	 */
	public function getAffectedRows()
	{
		return mysqli_affected_rows($this->connection) === -1 ? FALSE : mysqli_affected_rows($this->connection);
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @return int|FALSE  int on success or FALSE on failure
	 */
	public function getInsertId($sequence)
	{
		return mysqli_insert_id($this->connection);
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function begin($savepoint = NULL)
	{
		$this->query($savepoint ? "SAVEPOINT $savepoint" : 'START TRANSACTION');
	}


	/**
	 * Commits statements in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function commit($savepoint = NULL)
	{
		$this->query($savepoint ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
	}


	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function rollback($savepoint = NULL)
	{
		$this->query($savepoint ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
	}


	/**
	 * Returns the connection resource.
	 * @return mysqli
	 */
	public function getResource()
	{
		return @$this->connection->thread_id ? $this->connection : NULL;
	}


	/**
	 * Returns the connection reflector.
	 * @return IDibiReflector
	 */
	public function getReflector()
	{
		return new DibiMySqlReflector($this);
	}


	/**
	 * Result set driver factory.
	 * @param  mysqli_result
	 * @return IDibiResultDriver
	 */
	public function createResultDriver(mysqli_result $resource)
	{
		$res = clone $this;
		$res->resultSet = $resource;
		return $res;
	}


	/********************* SQL ****************d*g**/


	/**
	 * Encodes data for use in a SQL statement.
	 * @param  mixed     value
	 * @param  string    type (dibi::TEXT, dibi::BOOL, ...)
	 * @return string    encoded value
	 * @throws InvalidArgumentException
	 */
	public function escape($value, $type)
	{
		switch ($type) {
			case dibi::TEXT:
				return "'" . mysqli_real_escape_string($this->connection, $value) . "'";

			case dibi::BINARY:
				return "_binary'" . mysqli_real_escape_string($this->connection, $value) . "'";

			case dibi::IDENTIFIER:
				return '`' . str_replace('`', '``', $value) . '`';

			case dibi::BOOL:
				return $value ? 1 : 0;

			case dibi::DATE:
			case dibi::DATETIME:
				if (!$value instanceof DateTime && !$value instanceof DateTimeInterface) {
					$value = new DibiDateTime($value);
				}
				return $value->format($type === dibi::DATETIME ? "'Y-m-d H:i:s'" : "'Y-m-d'");

			default:
				throw new InvalidArgumentException('Unsupported type.');
		}
	}


	/**
	 * Encodes string for use in a LIKE statement.
	 * @param  string
	 * @param  int
	 * @return string
	 */
	public function escapeLike($value, $pos)
	{
		$value = addcslashes(str_replace('\\', '\\\\', $value), "\x00\n\r\\'%_");
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	/**
	 * Decodes data from result set.
	 * @param  string    value
	 * @param  string    type (dibi::BINARY)
	 * @return string    decoded value
	 * @throws InvalidArgumentException
	 */
	public function unescape($value, $type)
	{
		if ($type === dibi::BINARY) {
			return $value;
		}
		throw new InvalidArgumentException('Unsupported type.');
	}


	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 * @return void
	 */
	public function applyLimit(& $sql, $limit, $offset)
	{
		if ($limit >= 0 || $offset > 0) {
			// see http://dev.mysql.com/doc/refman/5.0/en/select.html
			$sql .= ' LIMIT ' . ($limit < 0 ? '18446744073709551615' : (int) $limit)
				. ($offset > 0 ? ' OFFSET ' . (int) $offset : '');
		}
	}


	/********************* result set ****************d*g**/


	/**
	 * Automatically frees the resources allocated for this result set.
	 * @return void
	 */
	public function __destruct()
	{
		$this->autoFree && $this->getResultResource() && @$this->free();
	}


	/**
	 * Returns the number of rows in a result set.
	 * @return int
	 */
	public function getRowCount()
	{
		if (!$this->buffered) {
			throw new DibiNotSupportedException('Row count is not available for unbuffered queries.');
		}
		return mysqli_num_rows($this->resultSet);
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		return mysqli_fetch_array($this->resultSet, $assoc ? MYSQLI_ASSOC : MYSQLI_NUM);
	}


	/**
	 * Moves cursor position without fetching row.
	 * @param  int      the 0-based cursor pos to seek to
	 * @return boolean  TRUE on success, FALSE if unable to seek to specified record
	 * @throws DibiException
	 */
	public function seek($row)
	{
		if (!$this->buffered) {
			throw new DibiNotSupportedException('Cannot seek an unbuffered result set.');
		}
		return mysqli_data_seek($this->resultSet, $row);
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	public function free()
	{
		mysqli_free_result($this->resultSet);
		$this->resultSet = NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 */
	public function getResultColumns()
	{
		static $types;
		if ($types === NULL) {
			$consts = get_defined_constants(TRUE);
			$types = array();
			foreach (isset($consts['mysqli']) ? $consts['mysqli'] : array() as $key => $value) {
				if (strncmp($key, 'MYSQLI_TYPE_', 12) === 0) {
					$types[$value] = substr($key, 12);
				}
			}
			$types[MYSQLI_TYPE_TINY] = $types[MYSQLI_TYPE_SHORT] = $types[MYSQLI_TYPE_LONG] = 'INT';
		}

		$count = mysqli_num_fields($this->resultSet);
		$columns = array();
		for ($i = 0; $i < $count; $i++) {
			$row = (array) mysqli_fetch_field_direct($this->resultSet, $i);
			$columns[] = array(
				'name' => $row['name'],
				'table' => $row['orgtable'],
				'fullname' => $row['table'] ? $row['table'] . '.' . $row['name'] : $row['name'],
				'nativetype' => isset($types[$row['type']]) ? $types[$row['type']] : $row['type'],
				'vendor' => $row,
			);
		}
		return $columns;
	}


	/**
	 * Returns the result set resource.
	 * @return mysqli_result
	 */
	public function getResultResource()
	{
		$this->autoFree = FALSE;
		return $this->resultSet;
	}

}
