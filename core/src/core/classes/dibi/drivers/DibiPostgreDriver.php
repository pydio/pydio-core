<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * The dibi driver for PostgreSQL database.
 *
 * Driver options:
 *   - host, hostaddr, port, dbname, user, password, connect_timeout, options, sslmode, service => see PostgreSQL API
 *   - string => or use connection string
 *   - schema => the schema search path
 *   - charset => character encoding to set (default is utf8)
 *   - persistent (bool) => try to find a persistent link?
 *   - resource (resource) => existing connection resource
 *   - lazy, profiler, result, substitutes, ... => see DibiConnection options
 *
 * @author     David Grudl
 * @package    dibi\drivers
 */
class DibiPostgreDriver extends DibiObject implements IDibiDriver, IDibiResultDriver, IDibiReflector
{
	/** @var resource  Connection resource */
	private $connection;

	/** @var resource  Resultset resource */
	private $resultSet;

	/** @var bool */
	private $autoFree = TRUE;

	/** @var int|FALSE  Affected rows */
	private $affectedRows = FALSE;


	/**
	 * @throws DibiNotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('pgsql')) {
			throw new DibiNotSupportedException("PHP extension 'pgsql' is not loaded.");
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
			$config += array(
				'charset' => 'utf8',
			);
			if (isset($config['string'])) {
				$string = $config['string'];
			} else {
				$string = '';
				DibiConnection::alias($config, 'user', 'username');
				DibiConnection::alias($config, 'dbname', 'database');
				foreach (array('host','hostaddr','port','dbname','user','password','connect_timeout','options','sslmode','service') as $key) {
					if (isset($config[$key])) {
						$string .= $key . '=' . $config[$key] . ' ';
					}
				}
			}

			DibiDriverException::tryError();
			if (empty($config['persistent'])) {
				$this->connection = pg_connect($string, PGSQL_CONNECT_FORCE_NEW);
			} else {
				$this->connection = pg_pconnect($string, PGSQL_CONNECT_FORCE_NEW);
			}
			if (DibiDriverException::catchError($msg)) {
				throw new DibiDriverException($msg, 0);
			}
		}

		if (!is_resource($this->connection)) {
			throw new DibiDriverException('Connecting error.');
		}

		if (isset($config['charset'])) {
			DibiDriverException::tryError();
			pg_set_client_encoding($this->connection, $config['charset']);
			if (DibiDriverException::catchError($msg)) {
				throw new DibiDriverException($msg, 0);
			}
		}

		if (isset($config['schema'])) {
			$this->query('SET search_path TO "' . $config['schema'] . '"');
		}
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		pg_close($this->connection);
	}


	/**
	 * Pings database.
	 * @return boolean
	 */
	public function ping()
	{
		return pg_ping($this->connection);
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
		$res = @pg_query($this->connection, $sql); // intentionally @

		if ($res === FALSE) {
			throw new DibiDriverException(pg_last_error($this->connection), 0, $sql);

		} elseif (is_resource($res)) {
			$this->affectedRows = pg_affected_rows($res);
			if (pg_num_fields($res)) {
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
		if ($sequence === NULL) {
			// PostgreSQL 8.1 is needed
			$res = $this->query("SELECT LASTVAL()");
		} else {
			$res = $this->query("SELECT CURRVAL('$sequence')");
		}

		if (!$res) {
			return FALSE;
		}

		$row = $res->fetch(FALSE);
		return is_array($row) ? $row[0] : FALSE;
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
	 * Is in transaction?
	 * @return bool
	 */
	public function inTransaction()
	{
		return !in_array(pg_transaction_status($this->connection), array(PGSQL_TRANSACTION_UNKNOWN, PGSQL_TRANSACTION_IDLE), TRUE);
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
				if (!is_resource($this->connection)) {
					throw new DibiException('Lost connection to server.');
				}
				return "'" . pg_escape_string($this->connection, $value) . "'";

			case dibi::BINARY:
				if (!is_resource($this->connection)) {
					throw new DibiException('Lost connection to server.');
				}
				return "'" . pg_escape_bytea($this->connection, $value) . "'";

			case dibi::IDENTIFIER:
				// @see http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
				return '"' . str_replace('"', '""', $value) . '"';

			case dibi::BOOL:
				return $value ? 'TRUE' : 'FALSE';

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
		$bs = pg_escape_string($this->connection, '\\'); // standard_conforming_strings = on/off
		$value = pg_escape_string($this->connection, $value);
		$value = strtr($value, array('%' => $bs . '%', '_' => $bs . '_', '\\' => '\\\\'));
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
			return pg_unescape_bytea($value);
		}
		throw new InvalidArgumentException('Unsupported type.');
	}


	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 * @return void
	 */
	public function applyLimit(& $sql, $limit, $offset)
	{
		if ($limit >= 0) {
			$sql .= ' LIMIT ' . (int) $limit;
		}

		if ($offset > 0) {
			$sql .= ' OFFSET ' . (int) $offset;
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
		return pg_num_rows($this->resultSet);
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		return pg_fetch_array($this->resultSet, NULL, $assoc ? PGSQL_ASSOC : PGSQL_NUM);
	}


	/**
	 * Moves cursor position without fetching row.
	 * @param  int      the 0-based cursor pos to seek to
	 * @return boolean  TRUE on success, FALSE if unable to seek to specified record
	 */
	public function seek($row)
	{
		return pg_result_seek($this->resultSet, $row);
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	public function free()
	{
		pg_free_result($this->resultSet);
		$this->resultSet = NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 */
	public function getResultColumns()
	{
		$count = pg_num_fields($this->resultSet);
		$columns = array();
		for ($i = 0; $i < $count; $i++) {
			$row = array(
				'name'      => pg_field_name($this->resultSet, $i),
				'table'     => pg_field_table($this->resultSet, $i),
				'nativetype'=> pg_field_type($this->resultSet, $i),
			);
			$row['fullname'] = $row['table'] ? $row['table'] . '.' . $row['name'] : $row['name'];
			$columns[] = $row;
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
		$version = pg_parameter_status($this->resource, 'server_version');
		if ($version < 7.4) {
			throw new DibiDriverException('Reflection requires PostgreSQL 7.4 and newer.');
		}

		$query = "
			SELECT
				table_name AS name,
				CASE table_type
					WHEN 'VIEW' THEN 1
					ELSE 0
				END AS view
			FROM
				information_schema.tables
			WHERE
				table_schema = ANY (current_schemas(false))";

		if ($version >= 9.3) {
			$query .= "
				UNION ALL
				SELECT
					matviewname, 1
				FROM
					pg_matviews
				WHERE
					schemaname = ANY (current_schemas(false))";
		}

		$res = $this->query($query);
		$tables = pg_fetch_all($res->resultSet);
		return $tables ? $tables : array();
	}


	/**
	 * Returns metadata for all columns in a table.
	 * @param  string
	 * @return array
	 */
	public function getColumns($table)
	{
		$_table = $this->escape($this->escape($table, dibi::IDENTIFIER), dibi::TEXT);
		$res = $this->query("
			SELECT indkey
			FROM pg_class
			LEFT JOIN pg_index on pg_class.oid = pg_index.indrelid AND pg_index.indisprimary
			WHERE pg_class.oid = $_table::regclass
		");
		$primary = (int) pg_fetch_object($res->resultSet)->indkey;

		$res = $this->query("
			SELECT *
			FROM information_schema.columns c
			JOIN pg_class ON pg_class.relname = c.table_name
			JOIN pg_namespace nsp ON nsp.oid = pg_class.relnamespace AND nsp.nspname = c.table_schema
			WHERE pg_class.oid = $_table::regclass
			ORDER BY c.ordinal_position
		");

		if (!$res->getRowCount()) {
			$res = $this->query("
				SELECT
					a.attname AS column_name,
					pg_type.typname AS udt_name,
					a.attlen AS numeric_precision,
					a.atttypmod-4 AS character_maximum_length,
					NOT a.attnotnull AS is_nullable,
					a.attnum AS ordinal_position,
					adef.adsrc AS column_default
				FROM
					pg_attribute a
					JOIN pg_type ON a.atttypid = pg_type.oid
					JOIN pg_class cls ON a.attrelid = cls.oid
					LEFT JOIN pg_attrdef adef ON adef.adnum = a.attnum AND adef.adrelid = a.attrelid
				WHERE
					cls.relkind IN ('r', 'v', 'mv')
					AND a.attrelid = $_table::regclass
					AND a.attnum > 0
					AND NOT a.attisdropped
				ORDER BY ordinal_position
			");
		}

		$columns = array();
		while ($row = $res->fetch(TRUE)) {
			$size = (int) max($row['character_maximum_length'], $row['numeric_precision']);
			$columns[] = array(
				'name' => $row['column_name'],
				'table' => $table,
				'nativetype' => strtoupper($row['udt_name']),
				'size' => $size > 0 ? $size : NULL,
				'nullable' => $row['is_nullable'] === 'YES' || $row['is_nullable'] === 't',
				'default' => $row['column_default'],
				'autoincrement' => (int) $row['ordinal_position'] === $primary && substr($row['column_default'], 0, 7) === 'nextval',
				'vendor' => $row,
			);
		}
		return $columns;
	}


	/**
	 * Returns metadata for all indexes in a table.
	 * @param  string
	 * @return array
	 */
	public function getIndexes($table)
	{
		$_table = $this->escape($this->escape($table, dibi::IDENTIFIER), dibi::TEXT);
		$res = $this->query("
			SELECT
				a.attnum AS ordinal_position,
				a.attname AS column_name
			FROM
				pg_attribute a
				JOIN pg_class cls ON a.attrelid = cls.oid
			WHERE
				a.attrelid = $_table::regclass
				AND a.attnum > 0
				AND NOT a.attisdropped
			ORDER BY ordinal_position
		");

		$columns = array();
		while ($row = $res->fetch(TRUE)) {
			$columns[$row['ordinal_position']] = $row['column_name'];
		}

		$res = $this->query("
			SELECT pg_class2.relname, indisunique, indisprimary, indkey
			FROM pg_class
			LEFT JOIN pg_index on pg_class.oid = pg_index.indrelid
			INNER JOIN pg_class as pg_class2 on pg_class2.oid = pg_index.indexrelid
			WHERE pg_class.oid = $_table::regclass
		");

		$indexes = array();
		while ($row = $res->fetch(TRUE)) {
			$indexes[$row['relname']]['name'] = $row['relname'];
			$indexes[$row['relname']]['unique'] = $row['indisunique'] === 't';
			$indexes[$row['relname']]['primary'] = $row['indisprimary'] === 't';
			foreach (explode(' ', $row['indkey']) as $index) {
				$indexes[$row['relname']]['columns'][] = $columns[$index];
			}
		}
		return array_values($indexes);
	}


	/**
	 * Returns metadata for all foreign keys in a table.
	 * @param  string
	 * @return array
	 */
	public function getForeignKeys($table)
	{
		$_table = $this->escape($this->escape($table, dibi::IDENTIFIER), dibi::TEXT);

		$res = $this->query("
			SELECT
				c.conname AS name,
				lt.attname AS local,
				c.confrelid::regclass AS table,
				ft.attname AS foreign,

				CASE c.confupdtype
					WHEN 'a' THEN 'NO ACTION'
					WHEN 'r' THEN 'RESTRICT'
					WHEN 'c' THEN 'CASCADE'
					WHEN 'n' THEN 'SET NULL'
					WHEN 'd' THEN 'SET DEFAULT'
					ELSE 'UNKNOWN'
				END AS \"onUpdate\",

				CASE c.confdeltype
					WHEN 'a' THEN 'NO ACTION'
					WHEN 'r' THEN 'RESTRICT'
					WHEN 'c' THEN 'CASCADE'
					WHEN 'n' THEN 'SET NULL'
					WHEN 'd' THEN 'SET DEFAULT'
					ELSE 'UNKNOWN'
				END AS \"onDelete\",

				c.conkey,
				lt.attnum AS lnum,
				c.confkey,
				ft.attnum AS fnum
			FROM
				pg_constraint c
				JOIN pg_attribute lt ON c.conrelid = lt.attrelid AND lt.attnum = ANY (c.conkey)
				JOIN pg_attribute ft ON c.confrelid = ft.attrelid AND ft.attnum = ANY (c.confkey)
			WHERE
				c.contype = 'f'
				AND
				c.conrelid = $_table::regclass
		");

		$fKeys = $references = array();
		while ($row = $res->fetch(TRUE)) {
			if (!isset($fKeys[$row['name']])) {
				$fKeys[$row['name']] = array(
					'name' => $row['name'],
					'table' => $row['table'],
					'local' => array(),
					'foreign' => array(),
					'onUpdate' => $row['onUpdate'],
					'onDelete' => $row['onDelete'],
				);

				$l = explode(',', trim($row['conkey'], '{}'));
				$f = explode(',', trim($row['confkey'], '{}'));

				$references[$row['name']] = array_combine($l, $f);
			}

			if (isset($references[$row['name']][$row['lnum']]) && $references[$row['name']][$row['lnum']] === $row['fnum']) {
				$fKeys[$row['name']]['local'][] = $row['local'];
				$fKeys[$row['name']]['foreign'][] = $row['foreign'];
			}
		}

		return $fKeys;
	}

}
