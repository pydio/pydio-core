<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * Profiler & logger event.
 *
 * @author     David Grudl
 * @package    dibi
 */
class DibiEvent
{
	/** event type */
	const CONNECT = 1,
		SELECT = 4,
		INSERT = 8,
		DELETE = 16,
		UPDATE = 32,
		QUERY = 60, // SELECT | INSERT | DELETE | UPDATE
		BEGIN = 64,
		COMMIT = 128,
		ROLLBACK = 256,
		TRANSACTION = 448, // BEGIN | COMMIT | ROLLBACK
		ALL = 1023;

	/** @var DibiConnection */
	public $connection;

	/** @var int */
	public $type;

	/** @var string */
	public $sql;

	/** @var DibiResult|DibiDriverException|NULL */
	public $result;

	/** @var float */
	public $time;

	/** @var int */
	public $count;

	/** @var array */
	public $source;


	public function __construct(DibiConnection $connection, $type, $sql = NULL)
	{
		$this->connection = $connection;
		$this->type = $type;
		$this->sql = trim($sql);
		$this->time = -microtime(TRUE);

		if ($type === self::QUERY && preg_match('#\(?\s*(SELECT|UPDATE|INSERT|DELETE)#iA', $this->sql, $matches)) {
			static $types = array(
				'SELECT' => self::SELECT, 'UPDATE' => self::UPDATE,
				'INSERT' => self::INSERT, 'DELETE' => self::DELETE,
			);
			$this->type = $types[strtoupper($matches[1])];
		}

		$rc = new ReflectionClass('dibi');
		$dibiDir = dirname($rc->getFileName()) . DIRECTORY_SEPARATOR;
		foreach (debug_backtrace(FALSE) as $row) {
			if (isset($row['file']) && is_file($row['file']) && strpos($row['file'], $dibiDir) !== 0) {
				$this->source = array($row['file'], (int) $row['line']);
				break;
			}
		}

		dibi::$elapsedTime = FALSE;
		dibi::$numOfQueries++;
		dibi::$sql = $sql;
	}


	public function done($result = NULL)
	{
		$this->result = $result;
		try {
			$this->count = $result instanceof DibiResult ? count($result) : NULL;
		} catch (DibiException $e) {
			$this->count = NULL;
		}

		$this->time += microtime(TRUE);
		dibi::$elapsedTime = $this->time;
		dibi::$totalTime += $this->time;
		return $this;
	}

}
