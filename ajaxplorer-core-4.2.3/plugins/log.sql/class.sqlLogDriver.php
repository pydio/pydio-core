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
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * SQL Logging Plugin
 * Requires php bcmath (for inet_dtop/inet_ptod) enabled and php version 5.1 (for DateTime class) minimum
 */
class sqlLogDriver extends AbstractLogDriver {
	
	/**
	 * Initialise the driver.
	 *
	 * Gives the driver a chance to set up it's connection / file resource etc..
	 * 
	 * @param Array $options array of options specific to the logger driver.
	 * @access public
	 */
	function init($options){
		parent::init($options);
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
		$this->sqlDriver = $options["SQL_DRIVER"];
		try {
			dibi::connect($this->sqlDriver);		
		} catch (DibiException $e) {
			echo get_class($e), ': ', $e->getMessage(), "\n";
			exit(1);
		}
	}
	
	/**
	 * Simple function to format Date objects to fit MySQL expected where condition
	 *
	 * @param Integer $unix_time timestamp to convert
	 * @return String Date string formatted to fit a MySQL where condition.
	 */
	function toMysqlDateTime($unix_time) {
		return date('Y-m-d G:i:s', $unix_time);
	}

	/**
	 * formats the error message in representable manner
	 *
	 * For the SQL driver we will normalise the information into our table row format.
	 *
	 * @param $message String this is the message to be formatted
	 * @param $severity Severity level of the message: one of LOG_LEVEL_* (DEBUG,INFO,NOTICE,WARNING,ERROR)
	 * @return String the formatted message.
	 */
	function formatMessage($message, $severity) {
		
		// Get the user if it exists
		$user = "No User";
		if(AuthService::usersEnabled()){
			$logged = AuthService::getLoggedUser();
			if($logged != null){
				$user = $logged->getId();
			}else{
				$user = "shared";
			}
		}
		
		$message_parts = explode("\t", $message);
		$severity = strtoupper((string)$severity);
		
		$log_row = Array(
			'logdate' => $this->toMysqlDateTime(strtotime('NOW')), // DateTime Constant introduced in PHP 5.1
			'remote_ip' => $this->inet_ptod($_SERVER['REMOTE_ADDR']),
			'severity' => $severity,
			'user' => $user,
			'message' => $message_parts[0],
			'params' => $message_parts[1]
		);
		
		return $log_row;
	}
	
	/**
	 * Format a table row into an xml list of nodes for the log treeview
	 *
	 * @param String $node Name of the xml node
	 * @param String $icon Icon to use for the list item
	 * @param String $dateattrib 
	 * @param String $display
	 * @param String $text Text displayed in the listview but not the treeview.
	 * @param String $filename
	 * @param Integer $is_file 0|1 to indicate whether this list item is a file or not.
	 *
	 * @return String Formatted XML node for insertion into the treeview.
	 */
	function formatXmlLogList($node, $icon, $dateattrib, $display, $text, $filename, $is_file = 0) {
		return "<$node icon=\"{$icon}\" date=\"{$dateattrib}\" display=\"{$display}\" text=\"{$text}\" is_file=\"{$is_file}\" filename=\"{$filename}\"/>";
	}
	
	/**
	 * Format a table row into an xml list of nodes for the log reader
	 *
	 * @param String $node Name of the xml node
	 * @param String $icon Icon to use for the list item
	 * @param String $dateattrib 
	 * @param String $filename Source of the list, usually a filename
	 * @param String $remote_ip Client IP that was logged 
	 * @param String $log_level Log level of the item
	 * @param String $user User who was logged in
	 * @param String $action The action the user performed.
	 * @param String $params Parameters to the action
	 * @param Integer $is_file 0|1 to indicate whether this list item is a file or not.
	 *
	 * @return String Formatted XML node for insertion into the log reader
	 */	
	function formatXmlLogItem($node, $icon, $dateattrib, $filename, $remote_ip, $log_level, $user, $action, $params, $is_file = 1, $rootPath = "/logs") {
		$remote_ip = $this->inet_dtop($remote_ip);
		$log_unixtime = strtotime($dateattrib);
		$log_datetime = date("m-d-y", $log_unixtime) . " " . date("G:i:s", $log_unixtime);
		$log_year = date('Y', $log_unixtime);
		$log_month = date('m', $log_unixtime);
		$log_date = date("m-d-y", $log_unixtime);
		
		// Some actions or parameters can contain characters that need to be encoded, especially when a piece of code raises a notification or error.
		$action = htmlentities($action);
		$params = htmlentities($params);

		return "<$node icon=\"{$icon}\" date=\"{$log_datetime}\" ajxp_modiftime=\"{$log_unixtime}\" is_file=\"{$is_file}\" filename=\"{$rootPath}/{$log_year}/{$log_month}/{$log_date}/{$log_datetime}\" ajxp_mime=\"log\" ip=\"{$remote_ip}\" level=\"{$log_level}\" user=\"{$user}\" action=\"{$action}\" params=\"{$params}\"/>";
	}
	
	/**
	 * Write an entry to the log.
	 *
	 * @param String $textMessage The message to log
	 * @param String $severityLevel The severity level, see LOG_LEVEL_ constants
	 * 
	 */
	function write($textMessage, $severityLevel = LOG_LEVEL_DEBUG) {
		
		$log_row = $this->formatMessage($textMessage, $severityLevel);
		
		try {
			dibi::query('INSERT INTO [ajxp_log]', $log_row);
		} catch (DibiException $e) {
			echo get_class($e), ': ', $e->getMessage(), "\n";
			exit(1);
		}
	}
	
	/**
	 * List available log files in XML
	 *
	 * @param String [optional] $nodeName
	 * @param String [optional] $year
	 * @param String [optional] $month
	 */
	function xmlListLogFiles($nodeName="file", $year=null, $month=null, $rootPath = "/logs") {

		$xml_strings = array();
		
		try {
			if ($month != null) { // Get days
				
				//cal_days_in_month(CAL_GREGORIAN, $month, $year)
				$start_time = mktime(0,0,0,$month,1,$year);
				$end_time = mktime(0,0,0,$month+1,1,$year);
			
				$q = 'SELECT 
					DISTINCT DATE([logdate]) AS logdate 
					FROM [ajxp_log] 
					WHERE [logdate] >= %s AND [logdate] < %s';
				$result = dibi::query($q, $this->toMysqlDateTime($start_time), $this->toMysqlDateTime($end_time));
			
				foreach ($result as $r) {
					$log_time = strtotime($r['logdate']);
				
					$fullYear = date('Y', $log_time);
					$fullMonth = date('F', $log_time);
					$logM = date('m', $log_time);
					$date = $r['logdate'];
				
					$xml_strings[$r['logdate']] = $this->formatXmlLogList($nodeName, 'toggle_log.png', $display, $display, $date, "$rootPath/$fullYear/$logM/$date");
					//"<$nodeName icon=\"toggle_log.png\" date=\"$display\" display=\"$display\" text=\"$date\" is_file=\"0\" filename=\"/logs/$fullYear/$fullMonth/$date\"/>";
				}
			
			} else if ($year != null) { // Get months
				$year_start_time = mktime(0,0,0,1,1,$year);
				$year_end_time = mktime(0,0,0,1,1,$year+1);
			
				$q = 'SELECT 
					DISTINCT YEAR([logdate]) AS year,
					MONTH([logdate]) AS month 
					FROM [ajxp_log] 
					WHERE [logdate] >= %s AND [logdate] < %s';
				$result = dibi::query($q, $this->toMysqlDateTime($year_start_time), $this->toMysqlDateTime($year_end_time));

				foreach ($result as $r) {
					/* We always recreate a unix timestamp while looping because it provides us with a uniform way to format the date.
					 * The month returned by the database will not be zero-padded and causes problems down the track when DateTime zero pads things */
					$month_time = mktime(0,0,0,$r['month'],1,$r['year']); 
				
					$fullYear = date('Y', $month_time);
					$fullMonth = date('F', $month_time);
					$logM = date('m', $month_time);
				
					$xml_strings[$r['month']] = $this->formatXmlLogList($nodeName, 'x-office-calendar.png', $logM, $logM, $logM, "$rootPath/$fullYear/$logM");
					//"<$nodeName icon=\"x-office-calendar.png\" date=\"$fullMonth\" display=\"$logM\" text=\"$fullMonth\" is_file=\"0\" filename=\"/logs/$fullYear/$fullMonth\"/>";
				}
						
			} else { // Get years
				$q = 'SELECT 
					DISTINCT YEAR([logdate]) AS year 
					FROM [ajxp_log]';
				$result = dibi::query($q);
			
				foreach ($result as $r) {
					$year_time = mktime(0,0,0,1,1,$r['year']);
					$fullYear = $r['year'];
				
					$xml_strings[$r['year']] = $this->formatXmlLogList($nodeName, 'x-office-calendar.png', $fullYear, $fullYear, $fullYear, "$rootPath/$fullYear");
					//"<$nodeName icon=\"x-office-calendar.png\" date=\"$fullYear\" display=\"$fullYear\" text=\"$fullYear\" is_file=\"0\" filename=\"/logs/$fullYear\"/>";
				}	
			}
		} catch (DibiException $e) {
			echo get_class($e), ': ', $e->getMessage(), "\n";
			exit(1);			
		}
		
		foreach ($xml_strings as $s) {
			print($s);
		}
		
		return ;
	}
	
	/**
	 * List log contents in XML
	 *
	 * @param String $date Assumed to be m-d-y format.
	 * @param String [optional] $nodeName
	 */
	function xmlLogs($parentDir, $date, $nodeName = "log", $rootPath = "/logs") {
		$start_time = strtotime($date);
		$end_time = mktime(0,0,0,date('m', $start_time), date('d', $start_time) + 1, date('Y', $start_time));
		
		try {
			$q = 'SELECT 
				*
				FROM [ajxp_log]
				WHERE [logdate] >= %d AND [logdate] < %d';
			//dibi::test($q, $this->toMysqlDateTime($start_time), $this->toMysqlDateTime($end_time));
			$result = dibi::query($q, $this->toMysqlDateTime($start_time), $this->toMysqlDateTime($end_time));
		
			$log_items = "";
		
			foreach ($result as $r) {
				$log_items .= SystemTextEncoding::toUTF8($this->formatXmlLogItem($nodeName, 'toggle_log.png', $r['logdate'], $date, $r['remote_ip'], $r['severity'], $r['user'], $r['message'], $r['params'], $rootPath));
			}
		
			print($log_items);
			
		} catch (DibiException $e) {
			echo get_class($e), ': ', $e->getMessage(), "\n";
			exit(1);			
		}
	}
	
	// IPV4/6 <--> DEC Lovingly lifted from stackoverflow, credit to Sander Marechal
	// Requires bcmath
	
	/**
	 * Convert an IP address from presentation to decimal(39,0) format suitable for storage in MySQL
	 *
	 * @param string $ip_address An IP address in IPv4, IPv6 or decimal notation
	 * @return string The IP address in decimal notation
	 */
	function inet_ptod($ip_address)
	{		
		return $ip_address;
	    // IPv4 address
	    if (strpos($ip_address, ':') === false && strpos($ip_address, '.') !== false) {
	        $ip_address = '::' . $ip_address;
	    }

	    // IPv6 address
	    if (strpos($ip_address, ':') !== false) {
	        $network = inet_pton($ip_address);
	        $parts = unpack('N*', $network);

	        foreach ($parts as &$part) {
	                if ($part < 0) {
	                        $part = bcadd((string) $part, '4294967296');
	                }

	                if (!is_string($part)) {
	                        $part = (string) $part;
	                }
	        }

	        $decimal = $parts[4];
	        $decimal = bcadd($decimal, bcmul($parts[3], '4294967296'));
	        $decimal = bcadd($decimal, bcmul($parts[2], '18446744073709551616'));
	        $decimal = bcadd($decimal, bcmul($parts[1], '79228162514264337593543950336'));

	        return $decimal;
	    }

	    // Decimal address
	    return $ip_address;
	}

	/**
	 * Convert an IP address from decimal format to presentation format
	 *
	 * @param string $decimal An IP address in IPv4, IPv6 or decimal notation
	 * @return string The IP address in presentation format
	 */
	function inet_dtop($decimal)
	{
		return $decimal;
	    // IPv4 or IPv6 format
	    if (strpos($decimal, ':') !== false || strpos($decimal, '.') !== false) {
	        return $decimal;
	    }

	    // Decimal format
	    $parts = array();
	    $parts[1] = bcdiv($decimal, '79228162514264337593543950336', 0);
	    $decimal = bcsub($decimal, bcmul($parts[1], '79228162514264337593543950336'));
	    $parts[2] = bcdiv($decimal, '18446744073709551616', 0);
	    $decimal = bcsub($decimal, bcmul($parts[2], '18446744073709551616'));
	    $parts[3] = bcdiv($decimal, '4294967296', 0);
	    $decimal = bcsub($decimal, bcmul($parts[3], '4294967296'));
	    $parts[4] = $decimal;

	    foreach ($parts as &$part) {
	        if (bccomp($part, '2147483647') == 1) {
	                $part = bcsub($part, '4294967296');
	        }

	        $part = (int) $part;
	    }

	    $network = pack('N4', $parts[1], $parts[2], $parts[3], $parts[4]);
	    $ip_address = inet_ntop($network);

	    // Turn IPv6 to IPv4 if it's IPv4
	    if (preg_match('/^::\d+.\d+.\d+.\d+$/', $ip_address)) {
	        return substr($ip_address, 2);
	    }

	    return $ip_address;
	}
}