<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 *
 * Various functions definitions when they are not existing in the current
 * PHP installation
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

if ( !function_exists('sys_get_temp_dir')) {
	function sys_get_temp_dir() {
		if( $temp=getenv('TMP') )        return $temp;
		if( $temp=getenv('TEMP') )        return $temp;
		if( $temp=getenv('TMPDIR') )    return $temp;
		$temp=tempnam(__FILE__,'');
		if (file_exists($temp)) {
			unlink($temp);
			return dirname($temp);
		}
		return null;
	}
}

if( !function_exists('json_encode')){
	
	function json_encode($val)
	{
		// indexed array
		if (is_array($val) && (!$val
			|| array_keys($val) === range(0, count($val) - 1))) {
			return '[' . implode(',', array_map('json_encode', $val)) . ']';
		}

		// associative array
		if (is_array($val) || is_object($val)) {
			$tmp = array();
			foreach ($val as $k => $v) {
				$tmp[] = json_encode((string) $k) . ':' . json_encode($v);
			}
			return '{' . implode(',', $tmp) . '}';
		}

		if (is_string($val)) {
			$val = str_replace(array("\\", "\x00"), array("\\\\", "\\u0000"), $val); // due to bug #40915
			return '"' . addcslashes($val, "\x8\x9\xA\xC\xD/\"") . '"';
		}

		if (is_int($val) || is_float($val)) {
			return rtrim(rtrim(number_format($val, 5, '.', ''), '0'), '.');
		}

		if (is_bool($val)) {
			return $val ? 'true' : 'false';
		}

		return 'null';
	}
	
	
}


if ( !function_exists('json_decode') ){
	function json_decode($json, $opt)
	{
		// Author: walidator.info 2009
		$comment = false;
		$out = '$x=';

		for ($i=0; $i<strlen($json); $i++)
		{
			if (!$comment)
			{
				if ($json[$i] == '{')        $out .= ' array(';
				else if ($json[$i] == '}')    $out .= ')';
				else if ($json[$i] == ':')    $out .= '=>';
				else                         $out .= $json[$i];
			}
			else $out .= $json[$i];
			if ($json[$i] == '"')    $comment = !$comment;
		}
		eval($out . ';');
		return $x;
	}
}

if (!class_exists('DateTime')) {
	class DateTime {
	    public $date;
	    
	    public function __construct($date) {
	        $this->date = strtotime($date);
	    }
	    
	    public function setTimeZone($timezone) {
	        return;
	    }
	    
	    private function __getDate() {
	        return date(DATE_ATOM, $this->date);    
	    }
	    
	    public function modify($multiplier) {
	        $this->date = strtotime($this->__getDate() . ' ' . $multiplier);
	    }
	    
	    public function format($format) {
	        return date($format, $this->date);
	    }
	}
}

?>