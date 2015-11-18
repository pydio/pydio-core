<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * The dibi driver for Firebird/InterBase database.
 *
 * Driver options:
 *   - database => the path to database file (server:/path/database.fdb)
 *   - username (or user)
 *   - password (or pass)
 *   - charset => character encoding to set
 *   - buffers (int) => buffers is the number of database buffers to allocate for the server-side cache. If 0 or omitted, server chooses its own default.
 *   - resource (resource) => existing connection resource
 *   - lazy, profiler, result, substitutes, ... => see DibiConnection options
 *
 * @author     Tomáš Kraina, Roman Sklenář
 * @package    dibi\drivers
 */
class DibiFirebirdDriver extends DibiObject implements IDibiDriver, IDibiResultDriver, IDibiReflector
{
	const ERROR_EXCEPTION_THROWN = -836;

	/** @var resource  Connection resource */
	private $connection;

	/** @var resource  Resultset resource */
	private $resultSet;

	/** @var bool */
	private $autoFree = TRUE;

	/** @var resource  Resultset resource */
	private $transaction;

	/** @var bool */
	private $inTransaction = FALSE;


	/**
	 * @throws DibiNotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('interbase')) {
			throw new DibiNotSupportedException("PHP extension 'interbase' is not loaded.");
		}
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws DibiException
	 */
	public function connect(array & $config)
	{
		DibiConnection::alias($config, 'database', 'db');

		if (isset($config['resource'])) {
			$this->connection = $config['resource'];

		} else {
			// default values
			$config += array(
				'username' => ini_get('ibase.default_password'),
				'password' => ini_get('ibase.default_user'),
				'database' => ini_get('ibase.default_db'),
				'charset' => ini_get('ibase.default_charset'),
				'buffers' => 0,
			);

			DibiDriverException::tryError();
			if (empty($config['persistent'])) {
				$this->connection = ibase_connect($config['database'], $config['username'], $config['password'], $config['charset'], $config['buffers']); // intentionally @
			} else {
				$this->connection = ibase_pconnect($config['database'], $config['username'], $config['password'], $config['charset'], $config['buffers']); // intentionally @
			}
			if (DibiDriverException::catchError($msg)) {
				throw new DibiDriverException($msg, ibase_errcode());
			}

			if (!is_resource($this->connection)) {
				throw new DibiDriverException(ibase_errmsg(), ibase_errcode());
			}
		}

	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		ibase_close($this->connection);
	}


	/**
	 * Executes the SQL query.
	 * @param  string      SQL statement.
	 * @return IDibiResultDriver|NULL
	 * @throws DibiDriverException|DibiException
	 */
	public function query($sql)
	{
		DibiDriverException::tryError();
		$resource = $this->inTransaction ? $this->transaction : $this->connection;
		$res = ibase_query($resource, $sql);

		if (DibiDriverException::catchError($msg)) {
			if (ibase_errcode() == self::ERROR_EXCEPTION_THROWN) {
				preg_match('/exception (\d+) (\w+) (.*)/i', ibase_errmsg(), $match);
				throw new DibiProcedureException($match[3], $match[1], $match[2], dibi::$sql);

			} else {
				throw new DibiDriverException(ibase_errmsg(), ibase_errcode(), dibi::$sql);
			}
		}

		if ($res === FALSE) {
			throw new DibiDriverException(ibase_errmsg(), ibase_errcode(), $sql);

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
		return ibase_affected_rows($this->connection);
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @param  string     generator name
	 * @return int|FALSE  int on success or FALSE on failure
	 */
	public function getInsertId($sequence)
	{
		return ibase_gen_id($sequence, 0, $this->connection);
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function begin($savepoint = NULL)
	{
		if ($savepoint !== NULL) {
			throw new DibiNotSupportedException('Savepoints are not supported in Firebird/Interbase.');
		}
		$this->transaction = ibase_trans($this->resource);
		$this->inTransaction = TRUE;
	}


	/**
	 * Commits statements in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function commit($savepoint = NULL)
	{
		if ($savepoint !== NULL) {
			throw new DibiNotSupportedException('Savepoints are not supported in Firebird/Interbase.');
		}

		if (!ibase_commit($this->transaction)) {
			throw new DibiDriverException('Unable to handle operation - failure when commiting transaction.');
		}

		$this->inTransaction = FALSE;
	}


	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	public function rollback($savepoint = NULL)
	{
		if ($savepoint !== NULL) {
			throw new DibiNotSupportedException('Savepoints are not supported in Firebird/Interbase.');
		}

		if (!ibase_rollback($this->transaction)) {
			throw new DibiDriverException('Unable to handle operation - failure when rolbacking transaction.');
		}

		$this->inTransaction = FALSE;
	}


	/**
	 * Is in transaction?
	 * @return bool
	 */
	public function inTransaction()
	{
		return $this->inTransaction;
	}


	/**
	 * Returns the connection resource.
	 * @return resource
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


	/********************* SQL ********************/


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
				return $value;

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
		throw new DibiNotImplementedException;
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
		if ($limit >= 0 && $offset > 0) {
			// see http://scott.yang.id.au/2004/01/limit-in-select-statements-in-firebird/
			$sql = 'SELECT FIRST ' . (int) $limit . ($offset > 0 ? ' SKIP ' . (int) $offset : '') . ' * FROM (' . $sql . ')';
		}
	}


	/********************* result set ********************/


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
		throw new DibiNotSupportedException("Firebird/Interbase do not support returning number of rows in result set.");
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		DibiDriverException::tryError();
		$result = $assoc ? ibase_fetch_assoc($this->resultSet, IBASE_TEXT) : ibase_fetch_row($this->resultSet, IBASE_TEXT); // intentionally @

		if (DibiDriverException::catchError($msg)) {
			if (ibase_errcode() == self::ERROR_EXCEPTION_THROWN) {
				preg_match('/exception (\d+) (\w+) (.*)/is', ibase_errmsg(), $match);
				throw new DibiProcedureException($match[3], $match[1], $match[2], dibi::$sql);

			} else {
				throw new DibiDriverException($msg, ibase_errcode(), dibi::$sql);
			}
		}

		return $result;
	}


	/**
	 * Moves cursor position without fetching row.
	 * @param  int      the 0-based cursor pos to seek to
	 * @return boolean  TRUE on success, FALSE if unable to seek to specified record
	 * @throws DibiException
	 */
	public function seek($row)
	{
		throw new DibiNotSupportedException("Firebird/Interbase do not support seek in result set.");
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	public function free()
	{
		ibase_free_result($this->resultSet);
		$this->resultSet = NULL;
	}


	/**
	 * Returns the result set resource.
	 * @return resource
	 */
	public function getResultResource()
	{
		$this->autoFree = FALSE;
		return is_resource($this->resultSet) ? $this->resultSet : NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 */
	public function getResultColumns()
	{
		$count = ibase_num_fields($this->resultSet);
		$columns = array();
		for ($i = 0; $i < $count; $i++) {
			$row = (array) ibase_field_info($this->resultSet, $i);
			$columns[] = array(
				'name' => $row['name'],
				'fullname' => $row['name'],
				'table' => $row['relation'],
				'nativetype' => $row['type'],
			);
		}
		return $columns;
	}


	/********************* IDibiReflector ********************/


	/**
	 * Returns list of tables.
	 * @return array
	 */
	public function getTables()
	{
		$res = $this->query("
			SELECT TRIM(RDB\$RELATION_NAME),
				CASE RDB\$VIEW_BLR WHEN NULL THEN 'TRUE' ELSE 'FALSE' END
			FROM RDB\$RELATIONS
			WHERE RDB\$SYSTEM_FLAG = 0;"
		);
		$tables = array();
		while ($row = $res->fetch(FALSE)) {
			$tables[] = array(
				'name' => $row[0],
				'view' => $row[1] === 'TRUE',
			);
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
		$table = strtoupper($table);
		$res = $this->query("
			SELECT TRIM(r.RDB\$FIELD_NAME) AS FIELD_NAME,
				CASE f.RDB\$FIELD_TYPE
					WHEN 261 THEN 'BLOB'
					WHEN 14 THEN 'CHAR'
					WHEN 40 THEN 'CSTRING'
					WHEN 11 THEN 'D_FLOAT'
					WHEN 27 THEN 'DOUBLE'
					WHEN 10 THEN 'FLOAT'
					WHEN 16 THEN 'INT64'
					WHEN 8 THEN 'INTEGER'
					WHEN 9 THEN 'QUAD'
					WHEN 7 THEN 'SMALLINT'
					WHEN 12 THEN 'DATE'
					WHEN 13 THEN 'TIME'
					WHEN 35 THEN 'TIMESTAMP'
					WHEN 37 THEN 'VARCHAR'
					ELSE 'UNKNOWN'
				END AS FIELD_TYPE,
				f.RDB\$FIELD_LENGTH AS FIELD_LENGTH,
				r.RDB\$DEFAULT_VALUE AS DEFAULT_VALUE,
				CASE r.RDB\$NULL_FLAG
					WHEN 1 THEN 'FALSE' ELSE 'TRUE'
				END AS NULLABLE
			FROM RDB\$RELATION_FIELDS r
				LEFT JOIN RDB\$FIELDS f ON r.RDB\$FIELD_SOURCE = f.RDB\$FIELD_NAME
			WHERE r.RDB\$RELATION_NAME = '$table'
			ORDER BY r.RDB\$FIELD_POSITION;"

		);
		$columns = array();
		while ($row = $res->fetch(TRUE)) {
			$key = $row['FIELD_NAME'];
			$columns[$key] = array(
				'name' => $key,
				'table' => $table,
				'nativetype' => trim($row['FIELD_TYPE']),
				'size' => $row['FIELD_LENGTH'],
				'nullable' => $row['NULLABLE'] === 'TRUE',
				'default' => $row['DEFAULT_VALUE'],
				'autoincrement' => FALSE,
			);
		}
		return $columns;
	}


	/**
	 * Returns metadata for all indexes in a table (the constraints are included).
	 * @param  string
	 * @return array
	 */
	public function getIndexes($table)
	{
		$table = strtoupper($table);
		$res = $this->query("
			SELECT TRIM(s.RDB\$INDEX_NAME) AS INDEX_NAME,
				TRIM(s.RDB\$FIELD_NAME) AS FIELD_NAME,
				i.RDB\$UNIQUE_FLAG AS UNIQUE_FLAG,
				i.RDB\$FOREIGN_KEY AS FOREIGN_KEY,
				TRIM(r.RDB\$CONSTRAINT_TYPE) AS CONSTRAINT_TYPE,
				s.RDB\$FIELD_POSITION AS FIELD_POSITION
			FROM RDB\$INDEX_SEGMENTS s
				LEFT JOIN RDB\$INDICES i ON i.RDB\$INDEX_NAME = s.RDB\$INDEX_NAME
				LEFT JOIN RDB\$RELATION_CONSTRAINTS r ON r.RDB\$INDEX_NAME = s.RDB\$INDEX_NAME
			WHERE UPPER(i.RDB\$RELATION_NAME) = '$table'
			ORDER BY s.RDB\$FIELD_POSITION"
		);
		$indexes = array();
		while ($row = $res->fetch(TRUE)) {
			$key = $row['INDEX_NAME'];
			$indexes[$key]['name'] = $key;
			$indexes[$key]['unique'] = $row['UNIQUE_FLAG'] === 1;
			$indexes[$key]['primary'] = $row['CONSTRAINT_TYPE'] === 'PRIMARY KEY';
			$indexes[$key]['table'] = $table;
			$indexes[$key]['columns'][$row['FIELD_POSITION']] = $row['FIELD_NAME'];
		}
		return $indexes;
	}


	/**
	 * Returns metadata for all foreign keys in a table.
	 * @param  string
	 * @return array
	 */
	public function getForeignKeys($table)
	{
		$table = strtoupper($table);
		$res = $this->query("
			SELECT TRIM(s.RDB\$INDEX_NAME) AS INDEX_NAME,
				TRIM(s.RDB\$FIELD_NAME) AS FIELD_NAME,
			FROM RDB\$INDEX_SEGMENTS s
				LEFT JOIN RDB\$RELATION_CONSTRAINTS r ON r.RDB\$INDEX_NAME = s.RDB\$INDEX_NAME
			WHERE UPPER(i.RDB\$RELATION_NAME) = '$table'
				AND r.RDB\$CONSTRAINT_TYPE = 'FOREIGN KEY'
			ORDER BY s.RDB\$FIELD_POSITION"
		);
		$keys = array();
		while ($row = $res->fetch(TRUE)) {
			$key = $row['INDEX_NAME'];
			$keys[$key] = array(
				'name' => $key,
				'column' => $row['FIELD_NAME'],
				'table' => $table,
			);
		}
		return $keys;
	}


	/**
	 * Returns list of indices in given table (the constraints are not listed).
	 * @param  string
	 * @return array
	 */
	public function getIndices($table)
	{
		$res = $this->query("
			SELECT TRIM(RDB\$INDEX_NAME)
			FROM RDB\$INDICES
			WHERE RDB\$RELATION_NAME = UPPER('$table')
				AND RDB\$UNIQUE_FLAG IS NULL
				AND RDB\$FOREIGN_KEY IS NULL;"
		);
		$indices = array();
		while ($row = $res->fetch(FALSE)) {
			$indices[] = $row[0];
		}
		return $indices;
	}


	/**
	 * Returns list of constraints in given table.
	 * @param  string
	 * @return array
	 */
	public function getConstraints($table)
	{
		$res = $this->query("
			SELECT TRIM(RDB\$INDEX_NAME)
			FROM RDB\$INDICES
			WHERE RDB\$RELATION_NAME = UPPER('$table')
				AND (
					RDB\$UNIQUE_FLAG IS NOT NULL
					OR RDB\$FOREIGN_KEY IS NOT NULL
			);"
		);
		$constraints = array();
		while ($row = $res->fetch(FALSE)) {
			$constraints[] = $row[0];
		}
		return $constraints;
	}


	/**
	 * Returns metadata for all triggers in a table or database.
	 * (Only if user has permissions on ALTER TABLE, INSERT/UPDATE/DELETE record in table)
	 * @param  string
	 * @param  string
	 * @return array
	 */
	public function getTriggersMeta($table = NULL)
	{
		$res = $this->query("
			SELECT TRIM(RDB\$TRIGGER_NAME) AS TRIGGER_NAME,
				TRIM(RDB\$RELATION_NAME) AS TABLE_NAME,
				CASE RDB\$TRIGGER_TYPE
					WHEN 1 THEN 'BEFORE'
					WHEN 2 THEN 'AFTER'
					WHEN 3 THEN 'BEFORE'
					WHEN 4 THEN 'AFTER'
					WHEN 5 THEN 'BEFORE'
					WHEN 6 THEN 'AFTER'
				END AS TRIGGER_TYPE,
				CASE RDB\$TRIGGER_TYPE
					WHEN 1 THEN 'INSERT'
					WHEN 2 THEN 'INSERT'
					WHEN 3 THEN 'UPDATE'
					WHEN 4 THEN 'UPDATE'
					WHEN 5 THEN 'DELETE'
					WHEN 6 THEN 'DELETE'
				END AS TRIGGER_EVENT,
				CASE RDB\$TRIGGER_INACTIVE
					WHEN 1 THEN 'FALSE' ELSE 'TRUE'
				END AS TRIGGER_ENABLED
			FROM RDB\$TRIGGERS
			WHERE RDB\$SYSTEM_FLAG = 0"
			. ($table === NULL ? ";" : " AND RDB\$RELATION_NAME = UPPER('$table');")
		);
		$triggers = array();
		while ($row = $res->fetch(TRUE)) {
			$triggers[$row['TRIGGER_NAME']] = array(
				'name' => $row['TRIGGER_NAME'],
				'table' => $row['TABLE_NAME'],
				'type' => trim($row['TRIGGER_TYPE']),
				'event' => trim($row['TRIGGER_EVENT']),
				'enabled' => trim($row['TRIGGER_ENABLED']) === 'TRUE',
			);
		}
		return $triggers;
	}


	/**
	 * Returns list of triggers for given table.
	 * (Only if user has permissions on ALTER TABLE, INSERT/UPDATE/DELETE record in table)
	 * @param  string
	 * @return array
	 */
	public function getTriggers($table = NULL)
	{
		$q = "SELECT TRIM(RDB\$TRIGGER_NAME)
			FROM RDB\$TRIGGERS
			WHERE RDB\$SYSTEM_FLAG = 0";
		$q .= $table === NULL ? ";" : " AND RDB\$RELATION_NAME = UPPER('$table')";

		$res = $this->query($q);
		$triggers = array();
		while ($row = $res->fetch(FALSE)) {
			$triggers[] = $row[0];
		}
		return $triggers;
	}


	/**
	 * Returns metadata from stored procedures and their input and output parameters.
	 * @param  string
	 * @return array
	 */
	public function getProceduresMeta()
	{
		$res = $this->query("
			SELECT
				TRIM(p.RDB\$PARAMETER_NAME) AS PARAMETER_NAME,
				TRIM(p.RDB\$PROCEDURE_NAME) AS PROCEDURE_NAME,
				CASE p.RDB\$PARAMETER_TYPE
					WHEN 0 THEN 'INPUT'
					WHEN 1 THEN 'OUTPUT'
					ELSE 'UNKNOWN'
				END AS PARAMETER_TYPE,
				CASE f.RDB\$FIELD_TYPE
					WHEN 261 THEN 'BLOB'
					WHEN 14 THEN 'CHAR'
					WHEN 40 THEN 'CSTRING'
					WHEN 11 THEN 'D_FLOAT'
					WHEN 27 THEN 'DOUBLE'
					WHEN 10 THEN 'FLOAT'
					WHEN 16 THEN 'INT64'
					WHEN 8 THEN 'INTEGER'
					WHEN 9 THEN 'QUAD'
					WHEN 7 THEN 'SMALLINT'
					WHEN 12 THEN 'DATE'
					WHEN 13 THEN 'TIME'
					WHEN 35 THEN 'TIMESTAMP'
					WHEN 37 THEN 'VARCHAR'
					ELSE 'UNKNOWN'
				END AS FIELD_TYPE,
				f.RDB\$FIELD_LENGTH AS FIELD_LENGTH,
				p.RDB\$PARAMETER_NUMBER AS PARAMETER_NUMBER
			FROM RDB\$PROCEDURE_PARAMETERS p
				LEFT JOIN RDB\$FIELDS f ON f.RDB\$FIELD_NAME = p.RDB\$FIELD_SOURCE
			ORDER BY p.RDB\$PARAMETER_TYPE, p.RDB\$PARAMETER_NUMBER;"
		);
		$procedures = array();
		while ($row = $res->fetch(TRUE)) {
			$key = $row['PROCEDURE_NAME'];
			$io = trim($row['PARAMETER_TYPE']);
			$num = $row['PARAMETER_NUMBER'];
			$procedures[$key]['name'] = $row['PROCEDURE_NAME'];
			$procedures[$key]['params'][$io][$num]['name'] = $row['PARAMETER_NAME'];
			$procedures[$key]['params'][$io][$num]['type'] = trim($row['FIELD_TYPE']);
			$procedures[$key]['params'][$io][$num]['size'] = $row['FIELD_LENGTH'];
		}
		return $procedures;
	}


	/**
	 * Returns list of stored procedures.
	 * @return array
	 */
	public function getProcedures()
	{
		$res = $this->query("
			SELECT TRIM(RDB\$PROCEDURE_NAME)
			FROM RDB\$PROCEDURES;"
		);
		$procedures = array();
		while ($row = $res->fetch(FALSE)) {
			$procedures[] = $row[0];
		}
		return $procedures;
	}


	/**
	 * Returns list of generators.
	 * @return array
	 */
	public function getGenerators()
	{
		$res = $this->query("
			SELECT TRIM(RDB\$GENERATOR_NAME)
			FROM RDB\$GENERATORS
			WHERE RDB\$SYSTEM_FLAG = 0;"
		);
		$generators = array();
		while ($row = $res->fetch(FALSE)) {
			$generators[] = $row[0];
		}
		return $generators;
	}


	/**
	 * Returns list of user defined functions (UDF).
	 * @return array
	 */
	public function getFunctions()
	{
		$res = $this->query("
			SELECT TRIM(RDB\$FUNCTION_NAME)
			FROM RDB\$FUNCTIONS
			WHERE RDB\$SYSTEM_FLAG = 0;"
		);
		$functions = array();
		while ($row = $res->fetch(FALSE)) {
			$functions[] = $row[0];
		}
		return $functions;
	}

}


/**
 * Database procedure exception.
 *
 * @author     Roman Sklenář
 * @copyright  Copyright (c) 2010
 * @package    dibi\drivers
 */
class DibiProcedureException extends DibiException
{
	/** @var string */
	protected $severity;


	/**
	 * Construct the exception.
	 * @param  string  Message describing the exception
	 * @param  int     Some code
	 * @param  string SQL command
	 */
	public function __construct($message = NULL, $code = 0, $severity = NULL, $sql = NULL)
	{
		parent::__construct($message, (int) $code, $sql);
		$this->severity = $severity;
	}


	/**
	 * Gets the exception severity.
	 * @return string
	 */
	public function getSeverity()
	{
		$this->severity;
	}

}
