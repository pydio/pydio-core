<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * The dibi driver for Oracle database.
 *
 * Driver options:
 *   - database => the name of the local Oracle instance or the name of the entry in tnsnames.ora
 *   - username (or user)
 *   - password (or pass)
 *   - charset => character encoding to set
 *   - schema => alters session schema
 *   - formatDate => how to format date in SQL (@see date)
 *   - formatDateTime => how to format datetime in SQL (@see date)
 *   - resource (resource) => existing connection resource
 *   - persistent => Creates persistent connections with oci_pconnect instead of oci_new_connect
 *   - lazy, profiler, result, substitutes, ... => see DibiConnection options
 *
 * @author     David Grudl
 * @package    dibi\drivers
 */
class DibiOracleDriver extends DibiObject implements IDibiDriver, IDibiResultDriver, IDibiReflector
{
	/** @var resource  Connection resource */
	private $connection;

	/** @var resource  Resultset resource */
	private $resultSet;

	/** @var bool */
	private $autoFree = TRUE;

	/** @var bool */
	private $autocommit = TRUE;

	/** @var string  Date and datetime format */
	private $fmtDate, $fmtDateTime;


	/**
	 * @throws DibiNotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('oci8')) {
			throw new DibiNotSupportedException("PHP extension 'oci8' is not loaded.");
		}
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws DibiException
	 */
	public function connect(array & $config)
	{
		$foo = & $config['charset'];
		$this->fmtDate = isset($config['formatDate']) ? $config['formatDate'] : 'U';
		$this->fmtDateTime = isset($config['formatDateTime']) ? $config['formatDateTime'] : 'U';

		if (isset($config['resource'])) {
			$this->connection = $config['resource'];
		} elseif (empty($config['persistent'])) {
			$this->connection = @oci_new_connect($config['username'], $config['password'], $config['database'], $config['charset']); // intentionally @
		} else {
			$this->connection = @oci_pconnect($config['username'], $config['password'], $config['database'], $config['charset']); // intentionally @
		}

		if (!$this->connection) {
			$err = oci_error();
			throw new DibiDriverException($err['message'], $err['code']);
		}

		if (isset($config['schema'])) {
			$this->query('ALTER SESSION SET CURRENT_SCHEMA=' . $config['schema']);
		}
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		oci_close($this->connection);
	}


	/**
	 * Executes the SQL query.
	 * @param  string      SQL statement.
	 * @return IDibiResultDriver|NULL
	 * @throws DibiDriverException
	 */
	public function query($sql)
	{
		$res = oci_parse($this->connection, $sql);
		if ($res) {
			oci_execute($res, $this->autocommit ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT);
			$err = oci_error($res);
			if ($err) {
				throw new DibiDriverException($err['message'], $err['code'], $sql);

			} elseif (is_resource($res)) {
				return $this->createResultDriver($res);
			}
		} else {
			$err = oci_error($this->connection);
			throw new DibiDriverException($err['message'], $err['code'], $sql);
		}
	}


	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
	 * @return int|FALSE  number of rows or FALSE on error
	 */
	public function getAffectedRows()
	{
		throw new DibiNotImplementedException;
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @return int|FALSE  int on success or FALSE on failure
	 */
	public function getInsertId($sequence)
	{
		$row = $this->query("SELECT $sequence.CURRVAL AS ID FROM DUAL")->fetch(TRUE);
		return isset($row['ID']) ? (int) $row['ID'] : FALSE;
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 */
	public function begin($savepoint = NULL)
	{
		$this->autocommit = FALSE;
	}


	/**
	 * Commits statements in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function commit($savepoint = NULL)
	{
		if (!oci_commit($this->connection)) {
			$err = oci_error($this->connection);
			throw new DibiDriverException($err['message'], $err['code']);
		}
		$this->autocommit = TRUE;
	}


	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function rollback($savepoint = NULL)
	{
		if (!oci_rollback($this->connection)) {
			$err = oci_error($this->connection);
			throw new DibiDriverException($err['message'], $err['code']);
		}
		$this->autocommit = TRUE;
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
				return "'" . str_replace("'", "''", $value) . "'"; // TODO: not tested

			case dibi::IDENTIFIER:
				// @see http://download.oracle.com/docs/cd/B10500_01/server.920/a96540/sql_elements9a.htm
				return '"' . str_replace('"', '""', $value) . '"';

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
		$value = addcslashes(str_replace('\\', '\\\\', $value), "\x00\\%_");
		$value = str_replace("'", "''", $value);
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
		if ($offset > 0) {
			// see http://www.oracle.com/technology/oramag/oracle/06-sep/o56asktom.html
			$sql = 'SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (' . $sql . ') t '
				. ($limit >= 0 ? 'WHERE ROWNUM <= ' . ((int) $offset + (int) $limit) : '')
				. ') WHERE "__rnum" > '. (int) $offset;

		} elseif ($limit >= 0) {
			$sql = 'SELECT * FROM (' . $sql . ') WHERE ROWNUM <= ' . (int) $limit;
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
		throw new DibiNotSupportedException('Row count is not available for unbuffered queries.');
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		return oci_fetch_array($this->resultSet, ($assoc ? OCI_ASSOC : OCI_NUM) | OCI_RETURN_NULLS);
	}


	/**
	 * Moves cursor position without fetching row.
	 * @param  int      the 0-based cursor pos to seek to
	 * @return boolean  TRUE on success, FALSE if unable to seek to specified record
	 */
	public function seek($row)
	{
		throw new DibiNotImplementedException;
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	public function free()
	{
		oci_free_statement($this->resultSet);
		$this->resultSet = NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 */
	public function getResultColumns()
	{
		$count = oci_num_fields($this->resultSet);
		$columns = array();
		for ($i = 1; $i <= $count; $i++) {
			$columns[] = array(
				'name' => oci_field_name($this->resultSet, $i),
				'table' => NULL,
				'fullname' => oci_field_name($this->resultSet, $i),
				'nativetype'=> oci_field_type($this->resultSet, $i),
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
		$res = $this->query('SELECT * FROM cat');
		$tables = array();
		while ($row = $res->fetch(FALSE)) {
			if ($row[1] === 'TABLE' || $row[1] === 'VIEW') {
				$tables[] = array(
					'name' => $row[0],
					'view' => $row[1] === 'VIEW',
				);
			}
		}
		return $tables;
	}


	/**
	 * Returns metadata for all columns in a table.
	 * @param  string
	 * @return array
	 */
	public function getColumns($table)
	{
		throw new DibiNotImplementedException;
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
