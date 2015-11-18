<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * dibi result set.
 *
 * <code>
 * $result = dibi::query('SELECT * FROM [table]');
 *
 * $row   = $result->fetch();
 * $value = $result->fetchSingle();
 * $table = $result->fetchAll();
 * $pairs = $result->fetchPairs();
 * $assoc = $result->fetchAssoc('id');
 * $assoc = $result->fetchAssoc('active,#,id');
 *
 * unset($result);
 * </code>
 *
 * @author     David Grudl
 * @package    dibi
 *
 * @property-read mixed $resource
 * @property-read IDibiResultDriver $driver
 * @property-read int $rowCount
 * @property-read DibiResultIterator $iterator
 * @property string $rowClass
 * @property-read DibiResultInfo $info
 */
class DibiResult extends DibiObject implements IDataSource
{
	/** @var array  IDibiResultDriver */
	private $driver;

	/** @var array  Translate table */
	private $types = array();

	/** @var DibiResultInfo */
	private $meta;

	/** @var bool  Already fetched? Used for allowance for first seek(0) */
	private $fetched = FALSE;

	/** @var string  returned object class */
	private $rowClass = 'DibiRow';

	/** @var Callback  returned object factory*/
	private $rowFactory;

	/** @var array  format */
	private $formats = array();


	/**
	 * @param  IDibiResultDriver
	 */
	public function __construct($driver)
	{
		$this->driver = $driver;
		$this->detectTypes();
	}


	/**
	 * @deprecated
	 */
	final public function getResource()
	{
		return $this->getResultDriver()->getResultResource();
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	final public function free()
	{
		if ($this->driver !== NULL) {
			$this->driver->free();
			$this->driver = $this->meta = NULL;
		}
	}


	/**
	 * Safe access to property $driver.
	 * @return IDibiResultDriver
	 * @throws RuntimeException
	 */
	final public function getResultDriver()
	{
		if ($this->driver === NULL) {
			throw new RuntimeException('Result-set was released from memory.');
		}

		return $this->driver;
	}


	/********************* rows ****************d*g**/


	/**
	 * Moves cursor position without fetching row.
	 * @param  int      the 0-based cursor pos to seek to
	 * @return boolean  TRUE on success, FALSE if unable to seek to specified record
	 * @throws DibiException
	 */
	final public function seek($row)
	{
		return ($row !== 0 || $this->fetched) ? (bool) $this->getResultDriver()->seek($row) : TRUE;
	}


	/**
	 * Required by the Countable interface.
	 * @return int
	 */
	final public function count()
	{
		return $this->getResultDriver()->getRowCount();
	}


	/**
	 * Returns the number of rows in a result set.
	 * @return int
	 */
	final public function getRowCount()
	{
		return $this->getResultDriver()->getRowCount();
	}


	/**
	 * Required by the IteratorAggregate interface.
	 * @return DibiResultIterator
	 */
	final public function getIterator()
	{
		return new DibiResultIterator($this);
	}


	/********************* fetching rows ****************d*g**/


	/**
	 * Set fetched object class. This class should extend the DibiRow class.
	 * @param  string
	 * @return self
	 */
	public function setRowClass($class)
	{
		$this->rowClass = $class;
		return $this;
	}


	/**
	 * Returns fetched object class name.
	 * @return string
	 */
	public function getRowClass()
	{
		return $this->rowClass;
	}


	/**
	 * Set a factory to create fetched object instances. These should extend the DibiRow class.
	 * @param  callback
	 * @return self
	 */
	public function setRowFactory($callback)
	{
		$this->rowFactory = $callback;
		return $this;
	}


	/**
	 * Fetches the row at current position, process optional type conversion.
	 * and moves the internal cursor to the next position
	 * @return DibiRow|FALSE  array on success, FALSE if no next record
	 */
	final public function fetch()
	{
		$row = $this->getResultDriver()->fetch(TRUE);
		if (!is_array($row)) {
			return FALSE;
		}
		$this->fetched = TRUE;
		$this->normalize($row);
		if ($this->rowFactory) {
			return call_user_func($this->rowFactory, $row);
		} elseif ($this->rowClass) {
			$row = new $this->rowClass($row);
		}
		return $row;
	}


	/**
	 * Like fetch(), but returns only first field.
	 * @return mixed  value on success, FALSE if no next record
	 */
	final public function fetchSingle()
	{
		$row = $this->getResultDriver()->fetch(TRUE);
		if (!is_array($row)) {
			return FALSE;
		}
		$this->fetched = TRUE;
		$this->normalize($row);
		return reset($row);
	}


	/**
	 * Fetches all records from table.
	 * @param  int  offset
	 * @param  int  limit
	 * @return DibiRow[]
	 */
	final public function fetchAll($offset = NULL, $limit = NULL)
	{
		$limit = $limit === NULL ? -1 : (int) $limit;
		$this->seek((int) $offset);
		$row = $this->fetch();
		if (!$row) {
			return array();  // empty result set
		}

		$data = array();
		do {
			if ($limit === 0) {
				break;
			}
			$limit--;
			$data[] = $row;
		} while ($row = $this->fetch());

		return $data;
	}


	/**
	 * Fetches all records from table and returns associative tree.
	 * Examples:
	 * - associative descriptor: col1[]col2->col3
	 *   builds a tree:          $tree[$val1][$index][$val2]->col3[$val3] = {record}
	 * - associative descriptor: col1|col2->col3=col4
	 *   builds a tree:          $tree[$val1][$val2]->col3[$val3] = val4
	 * @param  string  associative descriptor
	 * @return DibiRow
	 * @throws InvalidArgumentException
	 */
	final public function fetchAssoc($assoc)
	{
		if (strpos($assoc, ',') !== FALSE) {
			return $this->oldFetchAssoc($assoc);
		}

		$this->seek(0);
		$row = $this->fetch();
		if (!$row) {
			return array();  // empty result set
		}

		$data = NULL;
		$assoc = preg_split('#(\[\]|->|=|\|)#', $assoc, NULL, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		// check columns
		foreach ($assoc as $as) {
			// offsetExists ignores NULL in PHP 5.2.1, isset() surprisingly NULL accepts
			if ($as !== '[]' && $as !== '=' && $as !== '->' && $as !== '|' && !property_exists($row, $as)) {
				throw new InvalidArgumentException("Unknown column '$as' in associative descriptor.");
			}
		}

		if ($as === '->') { // must not be last
			array_pop($assoc);
		}

		if (empty($assoc)) {
			$assoc[] = '[]';
		}

		// make associative tree
		do {
			$x = & $data;

			// iterative deepening
			foreach ($assoc as $i => $as) {
				if ($as === '[]') { // indexed-array node
					$x = & $x[];

				} elseif ($as === '=') { // "value" node
					$x = $row->{$assoc[$i+1]};
					continue 2;

				} elseif ($as === '->') { // "object" node
					if ($x === NULL) {
						$x = clone $row;
						$x = & $x->{$assoc[$i+1]};
						$x = NULL; // prepare child node
					} else {
						$x = & $x->{$assoc[$i+1]};
					}

				} elseif ($as !== '|') { // associative-array node
					$x = & $x[$row->$as];
				}
			}

			if ($x === NULL) { // build leaf
				$x = $row;
			}

		} while ($row = $this->fetch());

		unset($x);
		return $data;
	}


	/**
	 * @deprecated
	 */
	private function oldFetchAssoc($assoc)
	{
		$this->seek(0);
		$row = $this->fetch();
		if (!$row) {
			return array();  // empty result set
		}

		$data = NULL;
		$assoc = explode(',', $assoc);

		// strip leading = and @
		$leaf = '@';  // gap
		$last = count($assoc) - 1;
		while ($assoc[$last] === '=' || $assoc[$last] === '@') {
			$leaf = $assoc[$last];
			unset($assoc[$last]);
			$last--;

			if ($last < 0) {
				$assoc[] = '#';
				break;
			}
		}

		do {
			$x = & $data;

			foreach ($assoc as $i => $as) {
				if ($as === '#') { // indexed-array node
					$x = & $x[];

				} elseif ($as === '=') { // "record" node
					if ($x === NULL) {
						$x = $row->toArray();
						$x = & $x[ $assoc[$i+1] ];
						$x = NULL; // prepare child node
					} else {
						$x = & $x[ $assoc[$i+1] ];
					}

				} elseif ($as === '@') { // "object" node
					if ($x === NULL) {
						$x = clone $row;
						$x = & $x->{$assoc[$i+1]};
						$x = NULL; // prepare child node
					} else {
						$x = & $x->{$assoc[$i+1]};
					}


				} else { // associative-array node
					$x = & $x[$row->$as];
				}
			}

			if ($x === NULL) { // build leaf
				if ($leaf === '=') {
					$x = $row->toArray();
				} else {
					$x = $row;
				}
			}

		} while ($row = $this->fetch());

		unset($x);
		return $data;
	}


	/**
	 * Fetches all records from table like $key => $value pairs.
	 * @param  string  associative key
	 * @param  string  value
	 * @return array
	 * @throws InvalidArgumentException
	 */
	final public function fetchPairs($key = NULL, $value = NULL)
	{
		$this->seek(0);
		$row = $this->fetch();
		if (!$row) {
			return array();  // empty result set
		}

		$data = array();

		if ($value === NULL) {
			if ($key !== NULL) {
				throw new InvalidArgumentException("Either none or both columns must be specified.");
			}

			// autodetect
			$tmp = array_keys($row->toArray());
			$key = $tmp[0];
			if (count($row) < 2) { // indexed-array
				do {
					$data[] = $row[$key];
				} while ($row = $this->fetch());
				return $data;
			}

			$value = $tmp[1];

		} else {
			if (!property_exists($row, $value)) {
				throw new InvalidArgumentException("Unknown value column '$value'.");
			}

			if ($key === NULL) { // indexed-array
				do {
					$data[] = $row[$value];
				} while ($row = $this->fetch());
				return $data;
			}

			if (!property_exists($row, $key)) {
				throw new InvalidArgumentException("Unknown key column '$key'.");
			}
		}

		do {
			$data[ (string) $row[$key] ] = $row[$value];
		} while ($row = $this->fetch());

		return $data;
	}


	/********************* column types ****************d*g**/


	/**
	 * Autodetect column types.
	 * @return void
	 */
	private function detectTypes()
	{
		$cache = DibiColumnInfo::getTypeCache();
		try {
			foreach ($this->getResultDriver()->getResultColumns() as $col) {
				$this->types[$col['name']] = $cache->{$col['nativetype']};
			}
		} catch (DibiNotSupportedException $e) {}
	}


	/**
	 * Converts values to specified type and format.
	 * @param  array
	 * @return void
	 */
	private function normalize(array & $row)
	{
		foreach ($this->types as $key => $type) {
			if (!isset($row[$key])) { // NULL
				continue;
			}
			$value = $row[$key];
			if ($value === FALSE || $type === dibi::TEXT) {

			} elseif ($type === dibi::INTEGER) {
				$row[$key] = is_float($tmp = $value * 1) ? $value : $tmp;

			} elseif ($type === dibi::FLOAT) {
				$row[$key] = str_replace(',', '.', ltrim((string) ($tmp = (float) $value), '0')) === ltrim(rtrim(rtrim($value, '0'), '.'), '0') ? $tmp : $value;

			} elseif ($type === dibi::BOOL) {
				$row[$key] = ((bool) $value) && $value !== 'f' && $value !== 'F';

			} elseif ($type === dibi::DATE || $type === dibi::DATETIME) {
				if ((int) $value !== 0 || substr((string) $value, 0, 3) === '00:') { // '', NULL, FALSE, '0000-00-00', ...
					$value = new DibiDateTime($value);
					$row[$key] = empty($this->formats[$type]) ? $value : $value->format($this->formats[$type]);
				}

			} elseif ($type === dibi::BINARY) {
				$row[$key] = $this->getResultDriver()->unescape($value, $type);
			}
		}
	}


	/**
	 * Define column type.
	 * @param  string  column
	 * @param  string  type (use constant Dibi::*)
	 * @return self
	 */
	final public function setType($col, $type)
	{
		$this->types[$col] = $type;
		return $this;
	}


	/**
	 * Returns column type.
	 * @return string
	 */
	final public function getType($col)
	{
		return isset($this->types[$col]) ? $this->types[$col] : NULL;
	}


	/**
	 * Sets data format.
	 * @param  string  type (use constant Dibi::*)
	 * @param  string  format
	 * @return self
	 */
	final public function setFormat($type, $format)
	{
		$this->formats[$type] = $format;
		return $this;
	}


	/**
	 * Returns data format.
	 * @return string
	 */
	final public function getFormat($type)
	{
		return isset($this->formats[$type]) ? $this->formats[$type] : NULL;
	}


	/********************* meta info ****************d*g**/


	/**
	 * Returns a meta information about the current result set.
	 * @return DibiResultInfo
	 */
	public function getInfo()
	{
		if ($this->meta === NULL) {
			$this->meta = new DibiResultInfo($this->getResultDriver());
		}
		return $this->meta;
	}


	/**
	 * @deprecated
	 */
	final public function getColumns()
	{
		return $this->getInfo()->getColumns();
	}


	/********************* misc tools ****************d*g**/


	/**
	 * Displays complete result set as HTML or text table for debug purposes.
	 * @return void
	 */
	final public function dump()
	{
		$i = 0;
		$this->seek(0);
		if (PHP_SAPI === 'cli') {
			$hasColors = (substr(getenv('TERM'), 0, 5) === 'xterm');
			$maxLen = 0;
			while ($row = $this->fetch()) {
				if ($i === 0) {
					foreach ($row as $col => $foo) {
						$len = mb_strlen($col);
						$maxLen = max($len, $maxLen);
					}
				}

				if ($hasColors) {
					echo "\033[1;37m#row: $i\033[0m\n";
				} else {
					echo "#row: $i\n";
				}

				foreach ($row as $col => $val) {
					$spaces = $maxLen - mb_strlen($col) + 2;
					echo "$col" . str_repeat(" ", $spaces) .  "$val\n";
				}

				echo "\n";
				$i++;
			}

			if ($i === 0) {
				echo "empty result set\n";
			}
			echo "\n";

		} else {
			while ($row = $this->fetch()) {
				if ($i === 0) {
					echo "\n<table class=\"dump\">\n<thead>\n\t<tr>\n\t\t<th>#row</th>\n";

					foreach ($row as $col => $foo) {
						echo "\t\t<th>" . htmlSpecialChars($col) . "</th>\n";
					}

					echo "\t</tr>\n</thead>\n<tbody>\n";
				}

				echo "\t<tr>\n\t\t<th>", $i, "</th>\n";
				foreach ($row as $col) {
					//if (is_object($col)) $col = $col->__toString();
					echo "\t\t<td>", htmlSpecialChars($col), "</td>\n";
				}
				echo "\t</tr>\n";
				$i++;
			}

			if ($i === 0) {
				echo '<p><em>empty result set</em></p>';
			} else {
				echo "</tbody>\n</table>\n";
			}
		}
	}

}
