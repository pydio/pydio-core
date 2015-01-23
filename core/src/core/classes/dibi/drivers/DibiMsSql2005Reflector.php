<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * The dibi reflector for MSSQL2005 databases.
 *
 * @author     Daniel Kouba
 * @package    dibi\drivers
 * @internal
 */
class DibiMsSql2005Reflector extends DibiObject implements IDibiReflector
{
	/** @var IDibiDriver */
	private $driver;


	public function __construct(IDibiDriver $driver)
	{
		$this->driver = $driver;
	}


	/**
	 * Returns list of tables.
	 * @return array
	 */
	public function getTables()
	{
		$res = $this->driver->query('SELECT TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES');
		$tables = array();
		while ($row = $res->fetch(FALSE)) {
			$tables[] = array(
				'name' => $row[0],
				'view' => isset($row[1]) && $row[1] === 'VIEW',
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
		$res = $this->driver->query("
			SELECT c.name as COLUMN_NAME, c.is_identity AS AUTO_INCREMENT
			FROM sys.columns c
			INNER JOIN sys.tables t ON c.object_id = t.object_id
			WHERE t.name = {$this->driver->escape($table, dibi::TEXT)}
		");

		$autoIncrements = array();
		while ($row = $res->fetch(TRUE)) {
			$autoIncrements[$row['COLUMN_NAME']] = (bool) $row['AUTO_INCREMENT'];
		}

		$res = $this->driver->query("
			SELECT C.COLUMN_NAME, C.DATA_TYPE, C.CHARACTER_MAXIMUM_LENGTH , C.COLUMN_DEFAULT  , C.NUMERIC_PRECISION, C.NUMERIC_SCALE , C.IS_NULLABLE, Case When Z.CONSTRAINT_NAME Is Null Then 0 Else 1 End As IsPartOfPrimaryKey
			FROM INFORMATION_SCHEMA.COLUMNS As C
			Outer Apply (
				SELECT CCU.CONSTRAINT_NAME
				FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS As TC
				Join INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE As CCU
					On CCU.CONSTRAINT_NAME = TC.CONSTRAINT_NAME
				WHERE TC.TABLE_SCHEMA = C.TABLE_SCHEMA
					And TC.TABLE_NAME = C.TABLE_NAME
					And TC.CONSTRAINT_TYPE = 'PRIMARY KEY'
					And CCU.COLUMN_NAME = C.COLUMN_NAME
			) As Z
			WHERE C.TABLE_NAME = {$this->driver->escape($table, dibi::TEXT)}
		");
		$columns = array();
		while ($row = $res->fetch(TRUE)) {
			$columns[] = array(
				'name' => $row['COLUMN_NAME'],
				'table' => $table,
				'nativetype' => strtoupper($row['DATA_TYPE']),
				'size' => $row['CHARACTER_MAXIMUM_LENGTH'],
				'unsigned' => TRUE,
				'nullable' => $row['IS_NULLABLE'] === 'YES',
				'default' => $row['COLUMN_DEFAULT'],
				'autoincrement' => $autoIncrements[$row['COLUMN_NAME']],
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
		$keyUsagesRes = $this->driver->query("SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = {$this->driver->escape($table, dibi::TEXT)}");
		$keyUsages = array();
		while( $row = $keyUsagesRes->fetch(TRUE) ) {
			$keyUsages[$row['CONSTRAINT_NAME']][(int) $row['ORDINAL_POSITION'] - 1] = $row['COLUMN_NAME'];
		}

		$res = $this->driver->query("SELECT * FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_NAME = {$this->driver->escape($table, dibi::TEXT)}");
		$indexes = array();
		while ($row = $res->fetch(TRUE)) {
			$indexes[$row['CONSTRAINT_NAME']]['name'] = $row['CONSTRAINT_NAME'];
			$indexes[$row['CONSTRAINT_NAME']]['unique'] = $row['CONSTRAINT_TYPE'] === 'UNIQUE';
			$indexes[$row['CONSTRAINT_NAME']]['primary'] = $row['CONSTRAINT_TYPE'] === 'PRIMARY KEY';
			$indexes[$row['CONSTRAINT_NAME']]['columns'] = isset($keyUsages[$row['CONSTRAINT_NAME']]) ? $keyUsages[$row['CONSTRAINT_NAME']] : array();
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
		throw new DibiNotImplementedException;
	}

}
