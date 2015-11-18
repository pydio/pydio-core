<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * The dibi reflector for SQLite database.
 *
 * @author     David Grudl
 * @package    dibi\drivers
 * @internal
 */
class DibiSqliteReflector extends DibiObject implements IDibiReflector
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
		$res = $this->driver->query("
			SELECT name, type = 'view' as view FROM sqlite_master WHERE type IN ('table', 'view')
			UNION ALL
			SELECT name, type = 'view' as view FROM sqlite_temp_master WHERE type IN ('table', 'view')
			ORDER BY name
		");
		$tables = array();
		while ($row = $res->fetch(TRUE)) {
			$tables[] = $row;
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
		$meta = $this->driver->query("
			SELECT sql FROM sqlite_master WHERE type = 'table' AND name = {$this->driver->escape($table, dibi::TEXT)}
			UNION ALL
			SELECT sql FROM sqlite_temp_master WHERE type = 'table' AND name = {$this->driver->escape($table, dibi::TEXT)}
		")->fetch(TRUE);

		$res = $this->driver->query("PRAGMA table_info({$this->driver->escape($table, dibi::IDENTIFIER)})");
		$columns = array();
		while ($row = $res->fetch(TRUE)) {
			$column = $row['name'];
			$type = explode('(', $row['type']);
			$columns[] = array(
				'name' => $column,
				'table' => $table,
				'fullname' => "$table.$column",
				'nativetype' => strtoupper($type[0]),
				'size' => isset($type[1]) ? (int) $type[1] : NULL,
				'nullable' => $row['notnull'] == '0',
				'default' => $row['dflt_value'],
				'autoincrement' => $row['pk'] && $type[0] === 'INTEGER',
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
		$res = $this->driver->query("PRAGMA index_list({$this->driver->escape($table, dibi::IDENTIFIER)})");
		$indexes = array();
		while ($row = $res->fetch(TRUE)) {
			$indexes[$row['name']]['name'] = $row['name'];
			$indexes[$row['name']]['unique'] = (bool) $row['unique'];
		}

		foreach ($indexes as $index => $values) {
			$res = $this->driver->query("PRAGMA index_info({$this->driver->escape($index, dibi::IDENTIFIER)})");
			while ($row = $res->fetch(TRUE)) {
				$indexes[$index]['columns'][$row['seqno']] = $row['name'];
			}
		}

		$columns = $this->getColumns($table);
		foreach ($indexes as $index => $values) {
			$column = $indexes[$index]['columns'][0];
			$primary = FALSE;
			foreach ($columns as $info) {
				if ($column == $info['name']) {
					$primary = $info['vendor']['pk'];
					break;
				}
			}
			$indexes[$index]['primary'] = (bool) $primary;
		}
		if (!$indexes) { // @see http://www.sqlite.org/lang_createtable.html#rowid
			foreach ($columns as $column) {
				if ($column['vendor']['pk']) {
					$indexes[] = array(
						'name' => 'ROWID',
						'unique' => TRUE,
						'primary' => TRUE,
						'columns' => array($column['name']),
					);
					break;
				}
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
		if (!($this->driver instanceof DibiSqlite3Driver)) {
			// throw new DibiNotSupportedException; // @see http://www.sqlite.org/foreignkeys.html
		}
		$res = $this->driver->query("PRAGMA foreign_key_list({$this->driver->escape($table, dibi::IDENTIFIER)})");
		$keys = array();
		while ($row = $res->fetch(TRUE)) {
			$keys[$row['id']]['name'] = $row['id']; // foreign key name
			$keys[$row['id']]['local'][$row['seq']] = $row['from']; // local columns
			$keys[$row['id']]['table'] = $row['table']; // referenced table
			$keys[$row['id']]['foreign'][$row['seq']] = $row['to']; // referenced columns
			$keys[$row['id']]['onDelete'] = $row['on_delete'];
			$keys[$row['id']]['onUpdate'] = $row['on_update'];

			if ($keys[$row['id']]['foreign'][0] == NULL) {
				$keys[$row['id']]['foreign'] = NULL;
			}
		}
		return array_values($keys);
	}

}
