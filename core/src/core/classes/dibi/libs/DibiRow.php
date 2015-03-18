<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * Result set single row.
 *
 * @author     David Grudl
 * @package    dibi
 */
class DibiRow implements ArrayAccess, IteratorAggregate, Countable
{

	public function __construct($arr)
	{
		foreach ($arr as $k => $v) {
			$this->$k = $v;
		}
	}


	public function toArray()
	{
		return (array) $this;
	}


	/**
	 * Converts value to DateTime object.
	 * @param  string key
	 * @param  string format
	 * @return DateTime
	 */
	public function asDateTime($key, $format = NULL)
	{
		$time = $this[$key];
		if (!$time instanceof DibiDateTime) {
			if ((int) $time === 0 && substr((string) $time, 0, 3) !== '00:') { // '', NULL, FALSE, '0000-00-00', ...
				return NULL;
			}
			$time = new DibiDateTime($time);
		}
		return $format === NULL ? $time : $time->format($format);
	}


	/********************* interfaces ArrayAccess, Countable & IteratorAggregate ****************d*g**/


	final public function count()
	{
		return count((array) $this);
	}


	final public function getIterator()
	{
		return new ArrayIterator($this);
	}


	final public function offsetSet($nm, $val)
	{
		$this->$nm = $val;
	}


	final public function offsetGet($nm)
	{
		return $this->$nm;
	}


	final public function offsetExists($nm)
	{
		return isset($this->$nm);
	}


	final public function offsetUnset($nm)
	{
		unset($this->$nm);
	}

}
