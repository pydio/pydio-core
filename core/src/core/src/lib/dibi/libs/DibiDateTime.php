<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (http://davidgrudl.com)
 */


/**
 * DateTime with serialization and timestamp support for PHP 5.2.
 *
 * @author     David Grudl
 * @package    dibi
 */
class DibiDateTime extends DateTime
{

	public function __construct($time = 'now', DateTimeZone $timezone = NULL)
	{
		if (is_numeric($time)) {
			parent::__construct('@' . $time);
			$this->setTimeZone($timezone ? $timezone : new DateTimeZone(date_default_timezone_get()));
		} elseif ($timezone === NULL) {
			parent::__construct($time);
		} else {
			parent::__construct($time, $timezone);
		}
	}


	public function modifyClone($modify = '')
	{
		$dolly = clone($this);
		return $modify ? $dolly->modify($modify) : $dolly;
	}


	public function modify($modify)
	{
		parent::modify($modify);
		return $this;
	}


	public function setTimestamp($timestamp)
	{
		$zone = PHP_VERSION_ID === 50206 ? new DateTimeZone($this->getTimezone()->getName()) : $this->getTimezone();
		$this->__construct('@' . $timestamp);
		$this->setTimeZone($zone);
		return $this;
	}


	public function getTimestamp()
	{
		$ts = $this->format('U');
		return is_float($tmp = $ts * 1) ? $ts : $tmp;
	}


	public function __toString()
	{
		return $this->format('Y-m-d H:i:s');
	}


	public function __sleep()
	{
		$zone = $this->getTimezone()->getName();
		if ($zone[0] === '+') {
			$this->fix = array($this->format('Y-m-d H:i:sP'));
		} else {
			$this->fix = array($this->format('Y-m-d H:i:s'), $zone);
		}
		return array('fix');
	}


	public function __wakeup()
	{
		if (isset($this->fix[1])) {
			$this->__construct($this->fix[0], new DateTimeZone($this->fix[1]));
		} else {
			$this->__construct($this->fix[0]);
		}
		unset($this->fix);
	}

}
