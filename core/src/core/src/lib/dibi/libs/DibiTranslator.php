<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * dibi SQL translator.
 *
 * @author     David Grudl
 * @package    dibi
 */
final class DibiTranslator extends DibiObject
{
	/** @var DibiConnection */
	private $connection;

	/** @var IDibiDriver */
	private $driver;

	/** @var int */
	private $cursor;

	/** @var array */
	private $args;

	/** @var bool */
	private $hasError;

	/** @var bool */
	private $comment;

	/** @var int */
	private $ifLevel;

	/** @var int */
	private $ifLevelStart;

	/** @var int */
	private $limit;

	/** @var int */
	private $offset;

	/** @var DibiHashMap */
	private $identifiers;


	public function __construct(DibiConnection $connection)
	{
		$this->connection = $connection;
		$this->identifiers = new DibiHashMap(array($this, 'delimite'));
	}


	/**
	 * Generates SQL.
	 * @param  array
	 * @return string
	 * @throws DibiException
	 */
	public function translate(array $args)
	{
		if (!$this->driver) {
			$this->driver = $this->connection->getDriver();
		}

		$args = array_values($args);
		while (count($args) === 1 && is_array($args[0])) { // implicit array expansion
			$args = array_values($args[0]);
		}
		$this->args = $args;

		$this->limit = -1;
		$this->offset = 0;
		$this->hasError = FALSE;
		$commandIns = NULL;
		$lastArr = NULL;
		// shortcuts
		$cursor = & $this->cursor;
		$cursor = 0;

		// conditional sql
		$this->ifLevel = $this->ifLevelStart = 0;
		$comment = & $this->comment;
		$comment = FALSE;

		// iterate
		$sql = array();
		while ($cursor < count($this->args)) {
			$arg = $this->args[$cursor];
			$cursor++;

			// simple string means SQL
			if (is_string($arg)) {
				// speed-up - is regexp required?
				$toSkip = strcspn($arg, '`[\'":%?');

				if (strlen($arg) === $toSkip) { // needn't be translated
					$sql[] = $arg;
				} else {
					$sql[] = substr($arg, 0, $toSkip)
/*
					. preg_replace_callback('/
					(?=[`[\'":%?])                    ## speed-up
					(?:
						`(.+?)`|                     ## 1) `identifier`
						\[(.+?)\]|                   ## 2) [identifier]
						(\')((?:\'\'|[^\'])*)\'|     ## 3,4) 'string'
						(")((?:""|[^"])*)"|          ## 5,6) "string"
						(\'|")|                      ## 7) lone quote
						:(\S*?:)([a-zA-Z0-9._]?)|    ## 8,9) :substitution:
						%([a-zA-Z~][a-zA-Z0-9~]{0,5})|## 10) modifier
						(\?)                         ## 11) placeholder
					)/xs',
*/                  // note: this can change $this->args & $this->cursor & ...
					. preg_replace_callback('/(?=[`[\'":%?])(?:`(.+?)`|\[(.+?)\]|(\')((?:\'\'|[^\'])*)\'|(")((?:""|[^"])*)"|(\'|")|:(\S*?:)([a-zA-Z0-9._]?)|%([a-zA-Z~][a-zA-Z0-9~]{0,5})|(\?))/s',
							array($this, 'cb'),
							substr($arg, $toSkip)
					);
					if (preg_last_error()) {
						throw new DibiPcreException;
					}
				}
				continue;
			}

			if ($comment) {
				$sql[] = '...';
				continue;
			}

			if ($arg instanceof Traversable) {
				$arg = iterator_to_array($arg);
			}

			if (is_array($arg)) {
				if (is_string(key($arg))) {
					// associative array -> autoselect between SET or VALUES & LIST
					if ($commandIns === NULL) {
						$commandIns = strtoupper(substr(ltrim($this->args[0]), 0, 6));
						$commandIns = $commandIns === 'INSERT' || $commandIns === 'REPLAC';
						$sql[] = $this->formatValue($arg, $commandIns ? 'v' : 'a');
					} else {
						if ($lastArr === $cursor - 1) {
							$sql[] = ',';
						}
						$sql[] = $this->formatValue($arg, $commandIns ? 'l' : 'a');
					}
					$lastArr = $cursor;
					continue;
				}
			}

			// default processing
			$sql[] = $this->formatValue($arg, FALSE);
		} // while


		if ($comment) {
			$sql[] = "*/";
		}

		$sql = implode(' ', $sql);

		if ($this->hasError) {
			throw new DibiException('SQL translate error', 0, $sql);
		}

		// apply limit
		if ($this->limit > -1 || $this->offset > 0) {
			$this->driver->applyLimit($sql, $this->limit, $this->offset);
		}

		return $sql;
	}


	/**
	 * Apply modifier to single value.
	 * @param  mixed
	 * @param  string
	 * @return string
	 */
	public function formatValue($value, $modifier)
	{
		if ($this->comment) {
			return "...";
		}

		if (!$this->driver) {
			$this->driver = $this->connection->getDriver();
		}

		// array processing (with or without modifier)
		if ($value instanceof Traversable) {
			$value = iterator_to_array($value);
		}

		if (is_array($value)) {
			$vx = $kx = array();
			switch ($modifier) {
				case 'and':
				case 'or':  // key=val AND key IS NULL AND ...
					if (empty($value)) {
						return '1=1';
					}

					foreach ($value as $k => $v) {
						if (is_string($k)) {
							$pair = explode('%', $k, 2); // split into identifier & modifier
							$k = $this->identifiers->{$pair[0]} . ' ';
							if (!isset($pair[1])) {
								$v = $this->formatValue($v, FALSE);
								$vx[] = $k . ($v === 'NULL' ? 'IS ' : '= ') . $v;

							} elseif ($pair[1] === 'ex') { // TODO: this will be removed
								$vx[] = $k . $this->formatValue($v, 'ex');

							} else {
								$v = $this->formatValue($v, $pair[1]);
								if ($pair[1] === 'l' || $pair[1] === 'in') {
									$op = 'IN ';
								} elseif (strpos($pair[1], 'like') !== FALSE) {
									$op = 'LIKE ';
								} elseif ($v === 'NULL') {
									$op = 'IS ';
								} else {
									$op = '= ';
								}
								$vx[] = $k . $op . $v;
							}

						} else {
							$vx[] = $this->formatValue($v, 'ex');
						}
					}
					return '(' . implode(') ' . strtoupper($modifier) . ' (', $vx) . ')';

				case 'n':  // key, key, ... identifier names
					foreach ($value as $k => $v) {
						if (is_string($k)) {
							$vx[] = $this->identifiers->$k . (empty($v) ? '' : ' AS ' . $this->identifiers->$v);
						} else {
							$pair = explode('%', $v, 2); // split into identifier & modifier
							$vx[] = $this->identifiers->{$pair[0]};
						}
					}
					return implode(', ', $vx);


				case 'a': // key=val, key=val, ...
					foreach ($value as $k => $v) {
						$pair = explode('%', $k, 2); // split into identifier & modifier
						$vx[] = $this->identifiers->{$pair[0]} . '='
							. $this->formatValue($v, isset($pair[1]) ? $pair[1] : (is_array($v) ? 'ex' : FALSE));
					}
					return implode(', ', $vx);


				case 'in':// replaces scalar %in modifier!
				case 'l': // (val, val, ...)
					foreach ($value as $k => $v) {
						$pair = explode('%', $k, 2); // split into identifier & modifier
						$vx[] = $this->formatValue($v, isset($pair[1]) ? $pair[1] : (is_array($v) ? 'ex' : FALSE));
					}
					return '(' . (($vx || $modifier === 'l') ? implode(', ', $vx) : 'NULL') . ')';


				case 'v': // (key, key, ...) VALUES (val, val, ...)
					foreach ($value as $k => $v) {
						$pair = explode('%', $k, 2); // split into identifier & modifier
						$kx[] = $this->identifiers->{$pair[0]};
						$vx[] = $this->formatValue($v, isset($pair[1]) ? $pair[1] : (is_array($v) ? 'ex' : FALSE));
					}
					return '(' . implode(', ', $kx) . ') VALUES (' . implode(', ', $vx) . ')';

				case 'm': // (key, key, ...) VALUES (val, val, ...), (val, val, ...), ...
					foreach ($value as $k => $v) {
						if (is_array($v)) {
							if (isset($proto)) {
								if ($proto !== array_keys($v)) {
									$this->hasError = TRUE;
									return '**Multi-insert array "' . $k . '" is different.**';
								}
							} else {
								$proto = array_keys($v);
							}
						} else {
							$this->hasError = TRUE;
							return '**Unexpected type ' . gettype($v) . '**';
						}

						$pair = explode('%', $k, 2); // split into identifier & modifier
						$kx[] = $this->identifiers->{$pair[0]};
						foreach ($v as $k2 => $v2) {
							$vx[$k2][] = $this->formatValue($v2, isset($pair[1]) ? $pair[1] : (is_array($v2) ? 'ex' : FALSE));
						}
					}
					foreach ($vx as $k => $v) {
						$vx[$k] = '(' . implode(', ', $v) . ')';
					}
					return '(' . implode(', ', $kx) . ') VALUES ' . implode(', ', $vx);

				case 'by': // key ASC, key DESC
					foreach ($value as $k => $v) {
						if (is_array($v)) {
							$vx[] = $this->formatValue($v, 'ex');
						} elseif (is_string($k)) {
							$v = (is_string($v) && strncasecmp($v, 'd', 1)) || $v > 0 ? 'ASC' : 'DESC';
							$vx[] = $this->identifiers->$k . ' ' . $v;
						} else {
							$vx[] = $this->identifiers->$v;
						}
					}
					return implode(', ', $vx);

				case 'ex':
				case 'sql':
					$translator = new self($this->connection);
					return $translator->translate($value);

				default:  // value, value, value - all with the same modifier
					foreach ($value as $v) {
						$vx[] = $this->formatValue($v, $modifier);
					}
					return implode(', ', $vx);
			}
		}


		// with modifier procession
		if ($modifier) {
			if ($value !== NULL && !is_scalar($value) && !$value instanceof DateTime && !$value instanceof DateTimeInterface) {  // array is already processed
				$this->hasError = TRUE;
				return '**Unexpected type ' . gettype($value) . '**';
			}

			switch ($modifier) {
				case 's':  // string
				case 'bin':// binary
				case 'b':  // boolean
					return $value === NULL ? 'NULL' : $this->driver->escape($value, $modifier);

				case 'sN': // string or NULL
				case 'sn':
					return $value == '' ? 'NULL' : $this->driver->escape($value, dibi::TEXT); // notice two equal signs

				case 'iN': // signed int or NULL
				case 'in': // deprecated
					if ($value == '') {
						$value = NULL;
					}
					// intentionally break omitted

				case 'i':  // signed int
				case 'u':  // unsigned int, ignored
					// support for long numbers - keep them unchanged
					if (is_string($value) && preg_match('#[+-]?\d++(e\d+)?\z#A', $value)) {
						return $value;
					} else {
						return $value === NULL ? 'NULL' : (string) (int) ($value + 0);
					}

				case 'f':  // float
					// support for extreme numbers - keep them unchanged
					if (is_string($value) && is_numeric($value) && strpos($value, 'x') === FALSE) {
						return $value; // something like -9E-005 is accepted by SQL, HEX values are not
					} else {
						return $value === NULL ? 'NULL' : rtrim(rtrim(number_format($value + 0, 10, '.', ''), '0'), '.');
					}

				case 'd':  // date
				case 't':  // datetime
					if ($value === NULL) {
						return 'NULL';
					} else {
						if (is_numeric($value)) {
							$value = (int) $value; // timestamp

						} elseif (is_string($value)) {
							$value = new DateTime($value);
						}
						return $this->driver->escape($value, $modifier);
					}

				case 'by':
				case 'n':  // identifier name
					return $this->identifiers->$value;

				case 'ex':
				case 'sql': // preserve as dibi-SQL  (TODO: leave only %ex)
					$value = (string) $value;
					// speed-up - is regexp required?
					$toSkip = strcspn($value, '`[\'":');
					if (strlen($value) !== $toSkip) {
						$value = substr($value, 0, $toSkip)
						. preg_replace_callback(
							'/(?=[`[\'":])(?:`(.+?)`|\[(.+?)\]|(\')((?:\'\'|[^\'])*)\'|(")((?:""|[^"])*)"|(\'|")|:(\S*?:)([a-zA-Z0-9._]?))/s',
							array($this, 'cb'),
							substr($value, $toSkip)
						);
						if (preg_last_error()) {
							throw new DibiPcreException;
						}
					}
					return $value;

				case 'SQL': // preserve as real SQL (TODO: rename to %sql)
					return (string) $value;

				case 'like~':  // LIKE string%
					return $this->driver->escapeLike($value, 1);

				case '~like':  // LIKE %string
					return $this->driver->escapeLike($value, -1);

				case '~like~': // LIKE %string%
					return $this->driver->escapeLike($value, 0);

				case 'and':
				case 'or':
				case 'a':
				case 'l':
				case 'v':
					$this->hasError = TRUE;
					return '**Unexpected type ' . gettype($value) . '**';

				default:
					$this->hasError = TRUE;
					return "**Unknown or invalid modifier %$modifier**";
			}
		}


		// without modifier procession
		if (is_string($value)) {
			return $this->driver->escape($value, dibi::TEXT);

		} elseif (is_int($value)) {
			return (string) $value;

		} elseif (is_float($value)) {
			return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

		} elseif (is_bool($value)) {
			return $this->driver->escape($value, dibi::BOOL);

		} elseif ($value === NULL) {
			return 'NULL';

		} elseif ($value instanceof DateTime || $value instanceof DateTimeInterface) {
			return $this->driver->escape($value, dibi::DATETIME);

		} elseif ($value instanceof DibiLiteral) {
			return (string) $value;

		} else {
			$this->hasError = TRUE;
			return '**Unexpected ' . gettype($value) . '**';
		}
	}


	/**
	 * PREG callback from translate() or formatValue().
	 * @param  array
	 * @return string
	 */
	private function cb($matches)
	{
		//    [1] => `ident`
		//    [2] => [ident]
		//    [3] => '
		//    [4] => string
		//    [5] => "
		//    [6] => string
		//    [7] => lone-quote
		//    [8] => substitution
		//    [9] => substitution flag
		//    [10] => modifier (when called from self::translate())
		//    [11] => placeholder (when called from self::translate())


		if (!empty($matches[11])) { // placeholder
			$cursor = & $this->cursor;

			if ($cursor >= count($this->args)) {
				$this->hasError = TRUE;
				return "**Extra placeholder**";
			}

			$cursor++;
			return $this->formatValue($this->args[$cursor - 1], FALSE);
		}

		if (!empty($matches[10])) { // modifier
			$mod = $matches[10];
			$cursor = & $this->cursor;

			if ($cursor >= count($this->args) && $mod !== 'else' && $mod !== 'end') {
				$this->hasError = TRUE;
				return "**Extra modifier %$mod**";
			}

			if ($mod === 'if') {
				$this->ifLevel++;
				$cursor++;
				if (!$this->comment && !$this->args[$cursor - 1]) {
					// open comment
					$this->ifLevelStart = $this->ifLevel;
					$this->comment = TRUE;
					return "/*";
				}
				return '';

			} elseif ($mod === 'else') {
				if ($this->ifLevelStart === $this->ifLevel) {
					$this->ifLevelStart = 0;
					$this->comment = FALSE;
					return "*/";
				} elseif (!$this->comment) {
					$this->ifLevelStart = $this->ifLevel;
					$this->comment = TRUE;
					return "/*";
				}

			} elseif ($mod === 'end') {
				$this->ifLevel--;
				if ($this->ifLevelStart === $this->ifLevel + 1) {
					// close comment
					$this->ifLevelStart = 0;
					$this->comment = FALSE;
					return "*/";
				}
				return '';

			} elseif ($mod === 'ex') { // array expansion
				array_splice($this->args, $cursor, 1, $this->args[$cursor]);
				return '';

			} elseif ($mod === 'lmt') { // apply limit
				$arg = $this->args[$cursor++];
				if ($arg === NULL) {
				} elseif ($this->comment) {
					return "(limit $arg)";
				} else {
					$this->limit = (int) $arg;
				}
				return '';

			} elseif ($mod === 'ofs') { // apply offset
				$arg = $this->args[$cursor++];
				if ($arg === NULL) {
				} elseif ($this->comment) {
					return "(offset $arg)";
				} else {
					$this->offset = (int) $arg;
				}
				return '';

			} else { // default processing
				$cursor++;
				return $this->formatValue($this->args[$cursor - 1], $mod);
			}
		}

		if ($this->comment) {
			return '...';
		}

		if ($matches[1]) { // SQL identifiers: `ident`
			return $this->identifiers->{$matches[1]};

		} elseif ($matches[2]) { // SQL identifiers: [ident]
			return $this->identifiers->{$matches[2]};

		} elseif ($matches[3]) { // SQL strings: '...'
			return $this->driver->escape( str_replace("''", "'", $matches[4]), dibi::TEXT);

		} elseif ($matches[5]) { // SQL strings: "..."
			return $this->driver->escape( str_replace('""', '"', $matches[6]), dibi::TEXT);

		} elseif ($matches[7]) { // string quote
			$this->hasError = TRUE;
			return '**Alone quote**';
		}

		if ($matches[8]) { // SQL identifier substitution
			$m = substr($matches[8], 0, -1);
			$m = $this->connection->getSubstitutes()->$m;
			return $matches[9] == '' ? $this->formatValue($m, FALSE) : $m . $matches[9]; // value or identifier
		}

		die('this should be never executed');
	}


	/**
	 * Apply substitutions to indentifier and delimites it.
	 * @param  string indentifier
	 * @return string
	 * @internal
	 */
	public function delimite($value)
	{
		$value = $this->connection->substitute($value);
		$parts = explode('.', $value);
		foreach ($parts as & $v) {
			if ($v !== '*') {
				$v = $this->driver->escape($v, dibi::IDENTIFIER);
			}
		}
		return implode('.', $parts);
	}

}
