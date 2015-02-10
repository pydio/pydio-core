<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * Provides an interface between a dataset and data-aware components.
 * @package    dibi
 */
interface IDataSource extends Countable, IteratorAggregate
{
	//function IteratorAggregate::getIterator();
	//function Countable::count();
}


/**
 * dibi driver interface.
 * @package    dibi
 */
interface IDibiDriver
{

	/**
	 * Connects to a database.
	 * @param  array
	 * @return void
	 * @throws DibiException
	 */
	function connect(array & $config);

	/**
	 * Disconnects from a database.
	 * @return void
	 * @throws DibiException
	 */
	function disconnect();

	/**
	 * Internal: Executes the SQL query.
	 * @param  string      SQL statement.
	 * @return IDibiResultDriver|NULL
	 * @throws DibiDriverException
	 */
	function query($sql);

	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
	 * @return int|FALSE  number of rows or FALSE on error
	 */
	function getAffectedRows();

	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @return int|FALSE  int on success or FALSE on failure
	 */
	function getInsertId($sequence);

	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	function begin($savepoint = NULL);

	/**
	 * Commits statements in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	function commit($savepoint = NULL);

	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws DibiDriverException
	 */
	function rollback($savepoint = NULL);

	/**
	 * Returns the connection resource.
	 * @return mixed
	 */
	function getResource();

	/**
	 * Returns the connection reflector.
	 * @return IDibiReflector
	 */
	function getReflector();

	/**
	 * Encodes data for use in a SQL statement.
	 * @param  string    value
	 * @param  string    type (dibi::TEXT, dibi::BOOL, ...)
	 * @return string    encoded value
	 * @throws InvalidArgumentException
	 */
	function escape($value, $type);

	/**
	 * Encodes string for use in a LIKE statement.
	 * @param  string
	 * @param  int
	 * @return string
	 */
	function escapeLike($value, $pos);

	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 * @return void
	 */
	function applyLimit(& $sql, $limit, $offset);

}


/**
 * dibi result set driver interface.
 * @package    dibi
 */
interface IDibiResultDriver
{

	/**
	 * Returns the number of rows in a result set.
	 * @return int
	 */
	function getRowCount();

	/**
	 * Moves cursor position without fetching row.
	 * @param  int      the 0-based cursor pos to seek to
	 * @return boolean  TRUE on success, FALSE if unable to seek to specified record
	 * @throws DibiException
	 */
	function seek($row);

	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 * @internal
	 */
	function fetch($type);

	/**
	 * Frees the resources allocated for this result set.
	 * @param  resource  result set resource
	 * @return void
	 */
	function free();

	/**
	 * Returns metadata for all columns in a result set.
	 * @return array of {name, nativetype [, table, fullname, (int) size, (bool) nullable, (mixed) default, (bool) autoincrement, (array) vendor ]}
	 */
	function getResultColumns();

	/**
	 * Returns the result set resource.
	 * @return mixed
	 */
	function getResultResource();

	/**
	 * Decodes data from result set.
	 * @param  string    value
	 * @param  string    type (dibi::BINARY)
	 * @return string    decoded value
	 * @throws InvalidArgumentException
	 */
	function unescape($value, $type);

}


/**
 * dibi driver reflection.
 *
 * @author     David Grudl
 * @package    dibi
 */
interface IDibiReflector
{

	/**
	 * Returns list of tables.
	 * @return array of {name [, (bool) view ]}
	 */
	function getTables();

	/**
	 * Returns metadata for all columns in a table.
	 * @param  string
	 * @return array of {name, nativetype [, table, fullname, (int) size, (bool) nullable, (mixed) default, (bool) autoincrement, (array) vendor ]}
	 */
	function getColumns($table);

	/**
	 * Returns metadata for all indexes in a table.
	 * @param  string
	 * @return array of {name, (array of names) columns [, (bool) unique, (bool) primary ]}
	 */
	function getIndexes($table);

	/**
	 * Returns metadata for all foreign keys in a table.
	 * @param  string
	 * @return array
	 */
	function getForeignKeys($table);

}
