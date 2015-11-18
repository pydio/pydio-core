<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


require_once dirname(__FILE__) . '/DibiMySqlReflector.php';
require_once dirname(__FILE__) . '/DibiSqliteReflector.php';


/**
 * The dibi driver for PDO.
 *
 * Driver options:
 *   - dsn => driver specific DSN
 *   - username (or user)
 *   - password (or pass)
 *   - options (array) => driver specific options {@see PDO::__construct}
 *   - resource (PDO) => existing connection
 *   - lazy, profiler, result, substitutes, ... => see DibiConnection options
 *
 * @author     David Grudl
 * @package    dibi\drivers
 */
class DibiPdoDriver extends DibiObject implements IDibiDriver, IDibiResultDriver
{
	/** @var PDO  Connection resource */
	private $connection;

	/** @var PDOStatement  Resultset resource */
	private $resultSet;

	/** @var int|FALSE  Affected rows */
	private $affectedRows = FALSE;

	/** @var string */
	private $driverName;


	/**
	 * @throws DibiNotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('pdo')) {
			throw new DibiNotSupportedException("PHP extension 'pdo' is not loaded.");
		}
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws DibiException
	 */
	public function connect(array & $config)
	{
		$foo = & $config['dsn'];
		$foo = & $config['options'];
		DibiConnection::alias($config, 'resource', 'pdo');

		if ($config['resource'] instanceof PDO) {
			$this->connection = $config['resource'];

		} else try {
			$this->connection = new PDO($config['dsn'], $config['username'], $config['password'], $config['options']);

		} catch (PDOException $e) {
			if ($e->getMessage() === 'could not find driver') {
				throw new DibiNotSupportedException("PHP extension for PDO is not loaded.");
			}
			throw new DibiDriverException($e->getMessage(), $e->getCode());
		}

		$this->driverName = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		$this->connection = NULL;
	}


	/**
	 * Executes the SQL query.
	 * @param  string      SQL statement.
	 * @return IDibiResultDriver|NULL
	 * @throws DibiDriverException
	 */
	public function query($sql)
	{
		// must detect if SQL returns result set or num of affected rows
		$cmd = strtoupper(substr(ltrim($sql), 0, 6));
		static $list = array('UPDATE'=>1, 'DELETE'=>1, 'INSERT'=>1, 'REPLAC'=>1);
		$this->affectedRows = FALSE;

		if (isset($list[$cmd])) {
			$this->affectedRows = $this->connection->exec($sql);

			if ($this->affectedRows === FALSE) {
				$err = $this->connection->errorInfo();
				throw new DibiDriverException("SQLSTATE[$err[0]]: $err[2]", $err[1], $sql);
			}

		} else {
			$res = $this->connection->query($sql);

			if ($res === FALSE) {
				$err = $this->connection->errorInfo();
				throw new DibiDriverException("SQLSTATE[$err[0]]: $err[2]", $err[1], $sql);
			} else {
				return $this->createResultDriver($res);
			}
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
		return $this->connection->lastInsertId();
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function begin($savepoint = NULL)
	{
		if (!$this->connection->beginTransaction()) {
			$err = $this->connection->errorInfo();
			throw new DibiDriverException("SQLSTATE[$err[0]]: $err[2]", $err[1]);
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
		if (!$this->connection->commit()) {
			$err = $this->connection->errorInfo();
			throw new DibiDriverException("SQLSTATE[$err[0]]: $err[2]", $err[1]);
		}
	}


	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function rollback($savepoint = NULL)
	{
		if (!$this->connection->rollBack()) {
			$err = $this->connection->errorInfo();
			throw new DibiDriverException("SQLSTATE[$err[0]]: $err[2]", $err[1]);
		}
	}


	/**
	 * Returns the connection resource.
	 * @return PDO
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
		switch ($this->driverName) {
			case 'mysql':
				return new DibiMySqlReflector($this);

			case 'sqlite':
			case 'sqlite2':
				return new DibiSqliteReflector($this);

			default:
				throw new DibiNotSupportedException;
		}
	}


	/**
	 * Result set driver factory.
	 * @param  PDOStatement
	 * @return IDibiResultDriver
	 */
	public function createResultDriver(PDOStatement $resource)
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
				if ($this->driverName === 'odbc') {
					return "'" . str_replace("'", "''", $value) . "'";
				} else {
					return $this->connection->quote($value, $type === dibi::TEXT ? PDO::PARAM_STR : PDO::PARAM_LOB);
				}

			case dibi::IDENTIFIER:
				switch ($this->driverName) {
					case 'mysql':
						return '`' . str_replace('`', '``', $value) . '`';

					case 'oci':
					case 'pgsql':
						return '"' . str_replace('"', '""', $value) . '"';

					case 'sqlite':
					case 'sqlite2':
						return '[' . strtr($value, '[]', '  ') . ']';

					case 'odbc':
					case 'mssql':
						return '[' . str_replace(array('[', ']'), array('[[', ']]'), $value) . ']';

					case 'sqlsrv':
						return '[' . str_replace(']', ']]', $value) . ']';

					default:
						return $value;
				}

			case dibi::BOOL:
				if ($this->driverName === 'pgsql') {
					return $value ? 'TRUE' : 'FALSE';
				} else {
					return $value ? 1 : 0;
				}

			case dibi::DATE:
			case dibi::DATETIME:
				if (!$value instanceof DateTime && !$value instanceof DateTimeInterface) {
					$value = new DibiDateTime($value);
				}
				if ($this->driverName === 'odbc') {
					return $value->format($type === dibi::DATETIME ? "#m/d/Y H:i:s#" : "#m/d/Y#");
				} else {
					return $value->format($type === dibi::DATETIME ? "'Y-m-d H:i:s'" : "'Y-m-d'");
				}

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
		switch ($this->driverName) {
			case 'mysql':
				$value = addcslashes(str_replace('\\', '\\\\', $value), "\x00\n\r\\'%_");
				return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");

			case 'oci':
				$value = addcslashes(str_replace('\\', '\\\\', $value), "\x00\\%_");
				$value = str_replace("'", "''", $value);
				return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");

			case 'pgsql':
				$bs = substr($this->connection->quote('\\', PDO::PARAM_STR), 1, -1); // standard_conforming_strings = on/off
				$value = substr($this->connection->quote($value, PDO::PARAM_STR), 1, -1);
				$value = strtr($value, array('%' => $bs . '%', '_' => $bs . '_', '\\' => '\\\\'));
				return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");

			case 'sqlite':
			case 'sqlite2':
				$value = addcslashes(substr($this->connection->quote($value, PDO::PARAM_STR), 1, -1), '%_\\');
				return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'") . " ESCAPE '\\'";

			case 'odbc':
			case 'mssql':
			case 'sqlsrv':
				$value = strtr($value, array("'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]'));
				return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");

			default:
				throw new DibiNotImplementedException;
		}
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
		if ($limit < 0 && $offset < 1) {
			return;
		}

		switch ($this->driverName) {
			case 'mysql':
				$sql .= ' LIMIT ' . ($limit < 0 ? '18446744073709551615' : (int) $limit)
					. ($offset > 0 ? ' OFFSET ' . (int) $offset : '');
				break;

			case 'pgsql':
				if ($limit >= 0) {
					$sql .= ' LIMIT ' . (int) $limit;
				}
				if ($offset > 0) {
					$sql .= ' OFFSET ' . (int) $offset;
				}
				break;

			case 'sqlite':
			case 'sqlite2':
				$sql .= ' LIMIT ' . $limit . ($offset > 0 ? ' OFFSET ' . (int) $offset : '');
				break;

			case 'oci':
				if ($offset > 0) {
					$sql = 'SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (' . $sql . ') t '
						. ($limit >= 0 ? 'WHERE ROWNUM <= ' . ((int) $offset + (int) $limit) : '')
						. ') WHERE "__rnum" > '. (int) $offset;
				} elseif ($limit >= 0) {
					$sql = 'SELECT * FROM (' . $sql . ') WHERE ROWNUM <= ' . (int) $limit;
				}
				break;

			case 'odbc':
			case 'dblib':
			case 'mssql':
			case 'sqlsrv':
				if ($offset < 1) {
					$sql = 'SELECT TOP ' . (int) $limit . ' * FROM (' . $sql . ') t';
					break;
				}
				// intentionally break omitted

			default:
				throw new DibiNotSupportedException('PDO or driver does not support applying limit or offset.');
		}
	}


	/********************* result set ****************d*g**/


	/**
	 * Returns the number of rows in a result set.
	 * @return int
	 */
	public function getRowCount()
	{
		return $this->resultSet->rowCount();
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		return $this->resultSet->fetch($assoc ? PDO::FETCH_ASSOC : PDO::FETCH_NUM);
	}


	/**
	 * Moves cursor position without fetching row.
	 * @param  int      the 0-based cursor pos to seek to
	 * @return boolean  TRUE on success, FALSE if unable to seek to specified record
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
		$this->resultSet = NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 * @throws DibiException
	 */
	public function getResultColumns()
	{
		$count = $this->resultSet->columnCount();
		$columns = array();
		for ($i = 0; $i < $count; $i++) {
			$row = @$this->resultSet->getColumnMeta($i); // intentionally @
			if ($row === FALSE) {
				throw new DibiNotSupportedException('Driver does not support meta data.');
			}
			// PHP < 5.2.3 compatibility
			// @see: http://php.net/manual/en/pdostatement.getcolumnmeta.php#pdostatement.getcolumnmeta.changelog
			$row = $row + array(
				'table' => NULL,
				'native_type' => 'VAR_STRING',
			);

			$columns[] = array(
				'name' => $row['name'],
				'table' => $row['table'],
				'nativetype' => $row['native_type'],
				'fullname' => $row['table'] ? $row['table'] . '.' . $row['name'] : $row['name'],
				'vendor' => $row,
			);
		}
		return $columns;
	}


	/**
	 * Returns the result set resource.
	 * @return PDOStatement
	 */
	public function getResultResource()
	{
		return $this->resultSet;
	}

}
