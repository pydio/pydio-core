<?php

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

?>