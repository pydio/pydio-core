<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Standard text logging plugin.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(INSTALL_PATH. "/server/classes/class.AbstractLogDriver.php");

class textLogDriver extends AbstractLogDriver {
	
	/**
	 * @var Integer Default permissions, in chmod format.
	 */
	var $USER_GROUP_RIGHTS = 0770;
	
	/**
	 * @var Integer File handle to currently open log file.
	 */
	var $fileHandle;
	
	/**
	 * @var Array stack of log messages to be written when file becomes available.
	 */
	var $stack;
	
	/**
	 * @var String full path to the directory where logs will be kept, with trailing slash.
	 */
	var $storageDir = "";
	
	/**
	 * @var String name of the log file to write.
	 */
	var $logFileName = "";
	
	
	/**
	 * Close file handle on objects destructor.
	 */
	function __destruct(){
		if($this->fileHandle !== false);
		$this->close();
	}
	
	/**
	 * Initialise storage: check and/or make log folder and file.
	 */
	function initStorage(){
		$storageDir = $this->storageDir;
		if (!file_exists($storageDir)) {
			@mkdir($storageDir, LOG_GROUP_RIGHTS);
		}
		$this->open();
	}
	
	/**
	 * Open log file for append, and flush out buffered messages to the file.
	 */
	function open(){
		if($this->storageDir!=""){
			$this->fileHandle = @fopen($this->storageDir . $this->logFileName, "at+");
			if($this->fileHandle !== false && count($this->stack)){
				$this->stackFlush();
			}		
		}		
	}
	
	/**
	 * Initialise the text log driver.
	 *
	 * Sets the user defined options.
	 * Makes sure that the folder and file exist, and makes them if they don't.
	 * 
	 * @param Array $options array of options specific to the logger driver.
	 * @access public
	 * @return null
	 */
	function init($options) {
		
		$this->severityDescription = 0;
		$this->stack = array();
		$this->fileHandle = false;
		
		
		$this->storageDir = isset($options['LOG_PATH']) ? $options['LOG_PATH'] : "";
		$this->storageDir = str_replace("AJXP_INSTALL_PATH", INSTALL_PATH, $this->storageDir);
		$this->logFileName = isset($options['LOG_FILE_NAME']) ? $options['LOG_FILE_NAME'] : 'log_' . date('m-d-y') . '.txt';
		$this->USER_GROUP_RIGHTS = isset($options['LOG_CHMOD']) ? $options['LOG_CHMOD'] : 0770;
		
		$this->initStorage();
	}
	
	/**
	 * Write text to the log file.
	 * 
	 * If write is not allowed because the file is not yet open, the message is buffered until
	 * file becomes available.
	 *
	 * @param String $textMessage The message to write.
	 * @param Integer $severityLevel Log severity: one of LOG_LEVEL_* (DEBUG,INFO,NOTICE,WARNING,ERROR)
	 */
	function write($textMessage, $severityLevel = LOG_LEVEL_DEBUG) {
		$textMessage = $this->formatMessage($textMessage, $severityLevel);

		if ($this->fileHandle !== false) {
			if(count($this->stack)) $this->stackFlush();						
			if (@fwrite($this->fileHandle, $textMessage) === false) {
				//print "There was an error writing to log file.";
			}
		}else{			
			$this->stack[] = $textMessage;
		}
		
	}
	
	/**
	 * Flush the stack/buffer of messages that couldn't be written earlier.
	 *
	 */
	function stackFlush(){
		// Flush stack for messages that could have been written before the file opening.
		foreach ($this->stack as $message){
			@fwrite($this->fileHandle, $message);
		}
		$this->stack = array();
	}
	
	/**
	 * closes the handle to the log file
	 *
	 * @access public
	 */
	function close() {
		$success = @fclose($this->fileHandle);
		if ($success === false) {
			// Failure to close the log file
			$this->write("AJXP_Logger failed to close the handle to the log file", LOG_LEVEL_ERROR);
		}
		
	}
	
	/**
	 * formats the error message in representable manner
	 *
	 * @param $message String this is the message to be formatted
	 * @param $severity Severity level of the message: one of LOG_LEVEL_* (DEBUG,INFO,NOTICE,WARNING,ERROR)
	 * @return String the formatted message.
	 */
	function formatMessage($message, $severity) {
		$msg = date("m-d-y") . " " . date("G:i:s") . "\t"; 
		$msg .= $_SERVER['REMOTE_ADDR'];
		
		$msg .= "\t".strtoupper($severity)."\t";
		
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
		$msg .= "$user\t";
		
		//$msg .= $severity;
		$msg .= "" . $message . "\n";		
		
		return $msg;			
	}
	
	/**
	 * List available logs in XML format.
	 * 
	 * This method prints the response.
	 *
	 * @param String $nodeName Name of the XML node to use as response.
	 * @param Integer $year The year to list.
	 * @param Integer $month The month to list.
	 * @return null
	 */
	function xmlListLogFiles($nodeName="file", $year=null, $month=null){
		$dir = $this->storageDir;
		if(!is_dir($this->storageDir)) return ;
		$logs = array();
		$years = array();
		$months = array();
		if(($handle = opendir($this->storageDir))!==false){
			while($file = readdir($handle)){
				if($file == "index.html") continue;
				$split = explode(".", $file);
				if(!count($split) || $split[0] == "") continue;
				$split2 = explode("_", $split[0]);
				$date = $split2[1];
				$dSplit = explode("-", $date);
				$logY = $dSplit[2];
				$logM = $dSplit[0];
				$time = mktime(0,0,1,intval($dSplit[0]), intval($dSplit[1]), intval($dSplit[2]));
				$display = date("l d", $time);
				$fullYear = date("Y", $time);
				$fullMonth = date("F", $time);
				if($year != null && $fullYear != $year) continue;
				if($month != null && $fullMonth != $month) continue;
				$logs[$time] = "<$nodeName icon=\"toggle_log.png\" date=\"$display\" display=\"$display\" text=\"$date\" is_file=\"0\" filename=\"/logs/$fullYear/$fullMonth/$date\"/>";
				$years[$logY] = "<$nodeName icon=\"x-office-calendar.png\" date=\"$fullYear\" display=\"$fullYear\" text=\"$fullYear\" is_file=\"0\" src=\"content.php?get_action=ls&amp;dir=%2Flogs%2F$fullYear\" filename=\"/logs/$fullYear\"/>";
				$months[$logM] = "<$nodeName icon=\"x-office-calendar.png\" date=\"$fullMonth\" display=\"$logM\" text=\"$fullMonth\" is_file=\"0\" src=\"content.php?get_action=ls&amp;dir=%2Flogs%2F$fullYear%2F$fullMonth\" filename=\"/logs/$fullYear/$fullMonth\"/>";
			}
			closedir($handle);	
		}
		$result = $years;
		if($year != null){
			$result = $months;
			if($month != null){
				$result = $logs;
			}
		}
		krsort($result);
		foreach($result as $log) print($log);
		return ;		
	}
	
	/**
	 * Get a log in XML format.
	 *
	 * @param String $date Date in m-d-y format.
	 * @param String $nodeName The name of the node to use for each log item.
	 * @return null
	 */
	function xmlLogs($parentDir, $date, $nodeName = "log"){
				
		$fName = $this->storageDir."log_".$date.".txt";

		if(!is_file($fName) || !is_readable($fName)) return;
		
		$res = "";
		$lines = file($fName);
		foreach ($lines as $line){
			$line = AJXP_Utils::xmlEntities($line);
			$matches = array();
			if(preg_match("/(.*)\t(.*)\t(.*)\t(.*)\t(.*)\t(.*)$/", $line, $matches)!==false){
				$fileName = $parentDir."/".$matches[1];
				foreach ($matches as $key => $match){
					$match = AJXP_Utils::xmlEntities($match);
					$match = str_replace("\"", "'", $match);
					$matches[$key] = $match;
				}
				print(SystemTextEncoding::toUTF8("<$nodeName is_file=\"1\" filename=\"$fileName\" ajxp_mime=\"log\" date=\"$matches[1]\" ip=\"$matches[2]\" level=\"$matches[3]\" user=\"$matches[4]\" action=\"$matches[5]\" params=\"$matches[6]\" icon=\"toggle_log.png\" />"));
			}
		}
		return ;
	}
}