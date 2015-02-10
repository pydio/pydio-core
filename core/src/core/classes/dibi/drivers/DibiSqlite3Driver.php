<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


require_once dirname(__FILE__) . '/DibiSqliteReflector.php';


/**
 * The dibi driver for SQLite3 database.
 *
 * Driver options:
 *   - database (or file) => the filename of the SQLite3 database
 *   - formatDate => how to format date in SQL (@see date)
 *   - formatDateTime => how to format datetime in SQL (@see date)
 *   - dbcharset => database character encoding (will be converted to 'charset')
 *   - charset => character encoding to set (default is UTF-8)
 *   - resource (SQLite3) => existing connection resource
 *   - lazy, profiler, result, substitutes, ... => see DibiConnection options
 *
 * @author     David Grudl
 * @package    dibi\drivers
 */
class DibiSqlite3Driver extends DibiObject implements IDibiDriver, IDibiResultDriver
{
	/** @var SQLite3  Connection resource */
	private $connection;

	/** @var SQLite3Result  Resultset resource */
	private $resultSet;

	/** @var bool */
	private $autoFree = TRUE;

	/** @var string  Date and datetime format */
	private $fmtDate, $fmtDateTime;

	/** @var string  character encoding */
	private $dbcharset, $charset;


	/**
	 * @throws DibiNotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('sqlite3')) {
			throw new DibiNotSupportedException("PHP extension 'sqlite3' is not loaded.");
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

		if (isset($config['resource']) && $config['resource'] instanceof SQLite3) {
			$this->connection = $config['resource'];
		} else try {
			$this->connection = new SQLite3($config['database']);

		} catch (Exception $e) {
			throw new DibiDriverException($e->getMessage(), $e->getCode());
		}

		$this->dbcharset = empty($config['dbcharset']) ? 'UTF-8' : $config['dbcharset'];
		$this->charset = empty($config['charset']) ? 'UTF-8' : $config['charset'];
		if (strcasecmp($this->dbcharset, $this->charset) === 0) {
			$this->dbcharset = $this->charset = NULL;
		}

		// enable foreign keys support (defaultly disabled; if disabled then foreign key constraints are not enforced)
		$version = SQLite3::version();
		if ($version['versionNumber'] >= '3006019') {
			$this->query("PRAGMA foreign_keys = ON");
		}
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		$this->connection->close();
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

		$res = @$this->connection->query($sql); // intentionally @
		if ($this->connection->lastErrorCode()) {
			throw new DibiDriverException($this->connection->lastErrorMsg(), $this->connection->lastErrorCode(), $sql);

		} elseif ($res instanceof SQLite3Result) {
			return $this->createResultDriver($res);
		}
	}


	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
	 * @return int|FALSE  number of rows or FALSE on error
	 */
	public function getAffectedRows()
	{
		return $this->connection->changes();
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @return int|FALSE  int on success or FALSE on failure
	 */
	public function getInsertId($sequence)
	{
		return $this->connection->lastInsertRowID();
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function begin($savepoint = NULL)
	{
		$this->query($savepoint ? "SAVEPOINT $savepoint" : 'BEGIN');
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
	 * @return mixed
	 */
	public function getResource()
	{
		return $this->connection;
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
	 * @param  SQLite3Result
	 * @return IDibiResultDriver
	 */
	public function createResultDriver(SQLite3Result $resource)
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
				return "'" . $this->connection->escapeString($value) . "'";

			case dibi::BINARY:
				return "X'" . bin2hex((string) $value) . "'";

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
		$value = addcslashes($this->connection->escapeString($value), '%_\\');
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'") . " ESCAPE '\\'";
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
	 * Automatically frees the resources allocated for this result set.
	 * @return void
	 */
	public function __destruct()
	{
		$this->autoFree && $this->resultSet && @$this->free();
	}


	/**
	 * Returns the number of rows in a result set.
	 * @return int
	 * @throws DibiNotSupportedException
	 */
	public function getRowCount()
	{
		throw new DibiNotSupportedException('Row count is not available for unbuffered queries.');
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		$row = $this->resultSet->fetchArray($assoc ? SQLITE3_ASSOC : SQLITE3_NUM);
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
	 * @throws DibiNotSupportedException
	 */
	public function seek($row)
	{
		throw new DibiNotSupportedException('Cannot seek an unbuffered result set.');
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	public function free()
	{
		$this->resultSet->finalize();
		$this->resultSet = NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 */
	public function getResultColumns()
	{
		$count = $this->resultSet->numColumns();
		$columns = array();
		static $types = array(SQLITE3_INTEGER => 'int', SQLITE3_FLOAT => 'float', SQLITE3_TEXT => 'text', SQLITE3_BLOB => 'blob', SQLITE3_NULL => 'null');
		for ($i = 0; $i < $count; $i++) {
			$columns[] = array(
				'name'  => $this->resultSet->columnName($i),
				'table' => NULL,
				'fullname' => $this->resultSet->columnName($i),
				'nativetype' => $types[$this->resultSet->columnType($i)],
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
		$this->autoFree = FALSE;
		return $this->resultSet;
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
		$this->connection->createFunction($name, $callback, $numArgs);
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
		$this->connection->createAggregate($name, $rowCallback, $agrCallback, $numArgs);
	}

}
