<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * The dibi driver interacting with databases via ODBC connections.
 *
 * Driver options:
 *   - dsn => driver specific DSN
 *   - username (or user)
 *   - password (or pass)
 *   - persistent (bool) => try to find a persistent link?
 *   - resource (resource) => existing connection resource
 *   - lazy, profiler, result, substitutes, ... => see DibiConnection options
 *
 * @author     David Grudl
 * @package    dibi\drivers
 */
class DibiOdbcDriver extends DibiObject implements IDibiDriver, IDibiResultDriver, IDibiReflector
{
	/** @var resource  Connection resource */
	private $connection;

	/** @var resource  Resultset resource */
	private $resultSet;

	/** @var bool */
	private $autoFree = TRUE;

	/** @var int|FALSE  Affected rows */
	private $affectedRows = FALSE;

	/** @var int  Cursor */
	private $row = 0;


	/**
	 * @throws DibiNotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('odbc')) {
			throw new DibiNotSupportedException("PHP extension 'odbc' is not loaded.");
		}
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws DibiException
	 */
	public function connect(array & $config)
	{
		if (isset($config['resource'])) {
			$this->connection = $config['resource'];
		} else {
			// default values
			$config += array(
				'username' => ini_get('odbc.default_user'),
				'password' => ini_get('odbc.default_pw'),
				'dsn' => ini_get('odbc.default_db'),
			);

			if (empty($config['persistent'])) {
				$this->connection = @odbc_connect($config['dsn'], $config['username'], $config['password']); // intentionally @
			} else {
				$this->connection = @odbc_pconnect($config['dsn'], $config['username'], $config['password']); // intentionally @
			}
		}

		if (!is_resource($this->connection)) {
			throw new DibiDriverException(odbc_errormsg() . ' ' . odbc_error());
		}
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		odbc_close($this->connection);
	}


	/**
	 * Executes the SQL query.
	 * @param  string      SQL statement.
	 * @return IDibiResultDriver|NULL
	 * @throws DibiDriverException
	 */
	public function query($sql)
	{
		$this->affectedRows = FALSE;
		$res = @odbc_exec($this->connection, $sql); // intentionally @

		if ($res === FALSE) {
			throw new DibiDriverException(odbc_errormsg($this->connection) . ' ' . odbc_error($this->connection), 0, $sql);

		} elseif (is_resource($res)) {
			$this->affectedRows = odbc_num_rows($res);
			return $this->createResultDriver($res);
		}
	}


	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
	 * @return int|FALSE  number of rows or FALSE on error
	 */
	public function getAffectedRows()
	{
		return $this->affectedRows;
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @return int|FALSE  int on success or FALSE on failure
	 */
	public function getInsertId($sequence)
	{
		throw new DibiNotSupportedException('ODBC does not support autoincrementing.');
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function begin($savepoint = NULL)
	{
		if (!odbc_autocommit($this->connection, FALSE)) {
			throw new DibiDriverException(odbc_errormsg($this->connection) . ' ' . odbc_error($this->connection));
		}
	}


	/**
	 * Commits statements in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function commit($savepoint = NULL)
	{
		if (!odbc_commit($this->connection)) {
			throw new DibiDriverException(odbc_errormsg($this->connection) . ' ' . odbc_error($this->connection));
		}
		odbc_autocommit($this->connection, TRUE);
	}


	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function rollback($savepoint = NULL)
	{
		if (!odbc_rollback($this->connection)) {
			throw new DibiDriverException(odbc_errormsg($this->connection) . ' ' . odbc_error($this->connection));
		}
		odbc_autocommit($this->connection, TRUE);
	}


	/**
	 * Is in transaction?
	 * @return bool
	 */
	public function inTransaction()
	{
		return !odbc_autocommit($this->connection);
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
		return $this;
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
				return "'" . str_replace("'", "''", $value) . "'";

			case dibi::IDENTIFIER:
				return '[' . str_replace(array('[', ']'), array('[[', ']]'), $value) . ']';

			case dibi::BOOL:
				return $value ? 1 : 0;

			case dibi::DATE:
			case dibi::DATETIME:
				if (!$value instanceof DateTime && !$value instanceof DateTimeInterface) {
					$value = new DibiDateTime($value);
				}
				return $value->format($type === dibi::DATETIME ? "#m/d/Y H:i:s#" : "#m/d/Y#");

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
		$value = strtr($value, array("'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]'));
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
		// offset support is missing
		if ($limit >= 0) {
			$sql = 'SELECT TOP ' . (int) $limit . ' * FROM (' . $sql . ') t';
		}

		if ($offset) {
			throw new DibiNotSupportedException('Offset is not implemented in driver odbc.');
		}
	}


	/********************* result set ****************d*g**/


	/**
	 * Automatically frees the resources allocated for this result set.
	 * @return void
	 */
	public function __destruct()
	{
		$this->autoFree && $this->getResultResource() && $this->free();
	}


	/**
	 * Returns the number of rows in a result set.
	 * @return int
	 */
	public function getRowCount()
	{
		// will return -1 with many drivers :-(
		return odbc_num_rows($this->resultSet);
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		if ($assoc) {
			return odbc_fetch_array($this->resultSet, ++$this->row);
		} else {
			$set = $this->resultSet;
			if (!odbc_fetch_row($set, ++$this->row)) {
				return FALSE;
			}
			$count = odbc_num_fields($set);
			$cols = array();
			for ($i = 1; $i <= $count; $i++) $cols[] = odbc_result($set, $i);
			return $cols;
		}
	}


	/**
	 * Moves cursor position without fetching row.
	 * @param  int      the 0-based cursor pos to seek to
	 * @return boolean  TRUE on success, FALSE if unable to seek to specified record
	 */
	public function seek($row)
	{
		$this->row = $row;
		return TRUE;
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	public function free()
	{
		odbc_free_result($this->resultSet);
		$this->resultSet = NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 */
	public function getResultColumns()
	{
		$count = odbc_num_fields($this->resultSet);
		$columns = array();
		for ($i = 1; $i <= $count; $i++) {
			$columns[] = array(
				'name'      => odbc_field_name($this->resultSet, $i),
				'table'     => NULL,
				'fullname'  => odbc_field_name($this->resultSet, $i),
				'nativetype'=> odbc_field_type($this->resultSet, $i),
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
		return is_resource($this->resultSet) ? $this->resultSet : NULL;
	}


	/********************* IDibiReflector ****************d*g**/


	/**
	 * Returns list of tables.
	 * @return array
	 */
	public function getTables()
	{
		$res = odbc_tables($this->connection);
		$tables = array();
		while ($row = odbc_fetch_array($res)) {
			if ($row['TABLE_TYPE'] === 'TABLE' || $row['TABLE_TYPE'] === 'VIEW') {
				$tables[] = array(
					'name' => $row['TABLE_NAME'],
					'view' => $row['TABLE_TYPE'] === 'VIEW',
				);
			}
		}
		odbc_free_result($res);
		return $tables;
	}


	/**
	 * Returns metadata for all columns in a table.
	 * @param  string
	 * @return array
	 */
	public function getColumns($table)
	{
		$res = odbc_columns($this->connection);
		$columns = array();
		while ($row = odbc_fetch_array($res)) {
			if ($row['TABLE_NAME'] === $table) {
				$columns[] = array(
					'name' => $row['COLUMN_NAME'],
					'table' => $table,
					'nativetype' => $row['TYPE_NAME'],
					'size' => $row['COLUMN_SIZE'],
					'nullable' => (bool) $row['NULLABLE'],
					'default' => $row['COLUMN_DEF'],
				);
			}
		}
		odbc_free_result($res);
		return $columns;
	}


	/**
	 * Returns metadata for all indexes in a table.
	 * @param  string
	 * @return array
	 */
	public function getIndexes($table)
	{
		throw new DibiNotImplementedException;
	}


	/**
	 * Returns metadata for all foreign keys in a table.
	 * @param  string
	 * @return array
	 */
	public function getForeignKeys($table)
	{
		throw new DibiNotImplementedException;
	}

}
