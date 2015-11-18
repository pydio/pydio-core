<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


require_once dirname(__FILE__) . '/DibiSqliteReflector.php';


/**
 * The dibi driver for SQLite database.
 *
 * Driver options:
 *   - database (or file) => the filename of the SQLite database
 *   - persistent (bool) => try to find a persistent link?
 *   - unbuffered (bool) => sends query without fetching and buffering the result rows automatically?
 *   - formatDate => how to format date in SQL (@see date)
 *   - formatDateTime => how to format datetime in SQL (@see date)
 *   - dbcharset => database character encoding (will be converted to 'charset')
 *   - charset => character encoding to set (default is UTF-8)
 *   - resource (resource) => existing connection resource
 *   - lazy, profiler, result, substitutes, ... => see DibiConnection options
 *
 * @author     David Grudl
 * @package    dibi\drivers
 */
class DibiSqliteDriver extends DibiObject implements IDibiDriver, IDibiResultDriver
{
	/** @var resource  Connection resource */
	private $connection;

	/** @var resource  Resultset resource */
	private $resultSet;

	/** @var bool  Is buffered (seekable and countable)? */
	private $buffered;

	/** @var string  Date and datetime format */
	private $fmtDate, $fmtDateTime;

	/** @var string  character encoding */
	private $dbcharset, $charset;


	/**
	 * @throws DibiNotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('sqlite')) {
			throw new DibiNotSupportedException("PHP extension 'sqlite' is not loaded.");
		}
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws DibiException
	 */
	public function connect(array & $config)
	{
		DibiConnection::alias($config, 'database', 'file');
		$this->fmtDate = isset($config['formatDate']) ? $config['formatDate'] : 'U';
		$this->fmtDateTime = isset($config['formatDateTime']) ? $config['formatDateTime'] : 'U';

		$errorMsg = '';
		if (isset($config['resource'])) {
			$this->connection = $config['resource'];
		} elseif (empty($config['persistent'])) {
			$this->connection = @sqlite_open($config['database'], 0666, $errorMsg); // intentionally @
		} else {
			$this->connection = @sqlite_popen($config['database'], 0666, $errorMsg); // intentionally @
		}

		if (!$this->connection) {
			throw new DibiDriverException($errorMsg);
		}

		$this->buffered = empty($config['unbuffered']);

		$this->dbcharset = empty($config['dbcharset']) ? 'UTF-8' : $config['dbcharset'];
		$this->charset = empty($config['charset']) ? 'UTF-8' : $config['charset'];
		if (strcasecmp($this->dbcharset, $this->charset) === 0) {
			$this->dbcharset = $this->charset = NULL;
		}
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		sqlite_close($this->connection);
	}


	/**
	 * Executes the SQL query.
	 * @param  string      SQL statement.
	 * @return IDibiResultDriver|NULL
	 * @throws DibiDriverException
	 */
	public function query($sql)
	{
		if ($this->dbcharset !== NULL) {
			$sql = iconv($this->charset, $this->dbcharset . '//IGNORE', $sql);
		}

		DibiDriverException::tryError();
		if ($this->buffered) {
			$res = sqlite_query($this->connection, $sql);
		} else {
			$res = sqlite_unbuffered_query($this->connection, $sql);
		}
		if (DibiDriverException::catchError($msg)) {
			throw new DibiDriverException($msg, sqlite_last_error($this->connection), $sql);

		} elseif (is_resource($res)) {
			return $this->createResultDriver($res);
		}
	}


	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
	 * @return int|FALSE  number of rows or FALSE on error
	 */
	public function getAffectedRows()
	{
		return sqlite_changes($this->connection);
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @return int|FALSE  int on success or FALSE on failure
	 */
	public function getInsertId($sequence)
	{
		return sqlite_last_insert_rowid($this->connection);
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function begin($savepoint = NULL)
	{
		$this->query('BEGIN');
	}


	/**
	 * Commits statements in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function commit($savepoint = NULL)
	{
		$this->query('COMMIT');
	}


	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function rollback($savepoint = NULL)
	{
		$this->query('ROLLBACK');
	}


	/**
	 * Returns the connection resource.
	 * @return mixed
	 */
	public function getResource()
	{
		return is_resource($this->connection) ? $this->connection : NULL;
	}


	/**
	 * Returns the connection reflector.
	 * @return IDibiReflector
	 */
	public function getReflector()
	{
		return new DibiSqliteReflector($this);
	}


	/**
	 * Result set driver factory.
	 * @param  resource
	 * @return IDibiResultDriver
	 */
	public function createResultDriver($resource)
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
			case dibi::BINARY:
				return "'" . sqlite_escape_string($value) . "'";

			case dibi::IDENTIFIER:
				return '[' . strtr($value, '[]', '  ') . ']';

			case dibi::BOOL:
				return $value ? 1 : 0;

			case dibi::DATE:
			case dibi::DATETIME:
				if (!$value instanceof DateTime && !$value instanceof DateTimeInterface) {
					$value = new DibiDateTime($value);
				}
				return $value->format($type === dibi::DATETIME ? $this->fmtDateTime : $this->fmtDate);

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
		throw new DibiNotSupportedException;
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
			$sql .= ' LIMIT ' . (int) $limit . ($offset > 0 ? ' OFFSET ' . (int) $offset : '');
		}
	}


	/********************* result set ****************d*g**/


	/**
	 * Returns the number of rows in a result set.
	 * @return int
	 */
	public function getRowCount()
	{
		if (!$this->buffered) {
			throw new DibiNotSupportedException('Row count is not available for unbuffered queries.');
		}
		return sqlite_num_rows($this->resultSet);
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		$row = sqlite_fetch_array($this->resultSet, $assoc ? SQLITE_ASSOC : SQLITE_NUM);
		$charset = $this->charset === NULL ? NULL : $this->charset . '//TRANSLIT';
		if ($row && ($assoc || $charset)) {
			$tmp = array();
			foreach ($row as $k => $v) {
				if ($charset !== NULL && is_string($v)) {
					$v = iconv($this->dbcharset, $charset, $v);
				}
				$tmp[str_replace(array('[', ']'), '', $k)] = $v;
			}
			return $tmp;
		}
		return $row;
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
		return sqlite_seek($this->resultSet, $row);
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	public function free()
	{
		$this->resultSet = NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 */
	public function getResultColumns()
	{
		$count = sqlite_num_fields($this->resultSet);
		$columns = array();
		for ($i = 0; $i < $count; $i++) {
			$name = str_replace(array('[', ']'), '', sqlite_field_name($this->resultSet, $i));
			$pair = explode('.', $name);
			$columns[] = array(
				'name'  => isset($pair[1]) ? $pair[1] : $pair[0],
				'table' => isset($pair[1]) ? $pair[0] : NULL,
				'fullname' => $name,
				'nativetype' => NULL,
			);
		}
		return $columns;
	}


	/**
	 * Returns the result set resource.
	 * @return mixed
	 */
	public function getResultResource()
	{
		return is_resource($this->resultSet) ? $this->resultSet : NULL;
	}


	/********************* user defined functions ****************d*g**/


	/**
	 * Registers an user defined function for use in SQL statements.
	 * @param  string  function name
	 * @param  mixed   callback
	 * @param  int     num of arguments
	 * @return void
	 */
	public function registerFunction($name, $callback, $numArgs = -1)
	{
		sqlite_create_function($this->connection, $name, $callback, $numArgs);
	}


	/**
	 * Registers an aggregating user defined function for use in SQL statements.
	 * @param  string  function name
	 * @param  mixed   callback called for each row of the result set
	 * @param  mixed   callback called to aggregate the "stepped" data from each row
	 * @param  int     num of arguments
	 * @return void
	 */
	public function registerAggregateFunction($name, $rowCallback, $agrCallback, $numArgs = -1)
	{
		sqlite_create_aggregate($this->connection, $name, $rowCallback, $agrCallback, $numArgs);
	}

}
