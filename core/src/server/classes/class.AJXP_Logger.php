<?php
/**
 * @package info.ajaxplorer
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
 * Description : A simple logger class
 */
define("LOG_FILE_NAME", 'log_' . date('m-d-y') . '.txt');		// The name of the log file
define("LOG_GROUP_RIGHTS", 0770);
define("LOG_LEVEL_DEBUG", "Debug");
define("LOG_LEVEL_INFO", "Info");
define("LOG_LEVEL_NOTICE", "Notice");
define("LOG_LEVEL_WARNING", "Warning");
define("LOG_LEVEL_ERROR", "Error");
global $AJXP_LOGGER;
	/**
	 * This class is very helpful to write a Log in PHP
	 * just define the severity and the message
	 * You need to intialize the AJXP_Logger class and call it in the function
	 * This class will help you a lot in debugging the application.
	 * If practiced regularlly, this debug information will also solve the long run application errors
	 *
	 * The way to use this Logger is defined in testLogger.php file
	 *
	 * In case of any errors please report to singhgurdeep@gmail.com
	 */
class AJXP_Logger {
	var $USER_GROUP_RIGHTS = 0770;
	var $fileHandle;
	var $stack;
	var $storageDir = "";
	/*
	var $severityDescription = Array(
			DEBUG => "Debug", 
	 		INFO => "Info",
	 		NOTICE => "Notice",
	 		WARNING => "Warning",
	 		ERROR => "Error");
	*/
	/**
	 * AJXP_Logger constructor
	 *
	 * @access private
	 */
	function AJXP_Logger() {

		$this->severityDescription = 0;
		$this->stack = array();
		$this->fileHandle = false;
	}
	
	function __destruct(){
		if($this->fileHandle !== false);
		$this->close();
	}
	
	function initStorage($storageDir){
		$this->storageDir = $storageDir;
		if (!file_exists($storageDir)) {
			@mkdir($storageDir, LOG_GROUP_RIGHTS);
		}
		$this->open();
	}
	
	function open(){
		if($this->storageDir!=""){
			$this->fileHandle = @fopen($this->storageDir . LOG_FILE_NAME, "at+");
			if($this->fileHandle !== false && count($this->stack)){
				$this->stackFlush();
			}		
		}		
	}

	function logAction($action, $params=array()){
		$logger = AJXP_Logger::getInstance();		
		$message = "$action\t";		
		if(count($params)){
			$message.=$logger->arrayToString($params);
		}		
		$logger->write($message, LOG_LEVEL_INFO);		
	}
	
	function arrayToString($params){
		$st = "";	
		$index=0;	
		foreach ($params as $key=>$value){
			$index++;
			if(!is_numeric($key)){
				$st.="$key=";
			}
			if(is_string($value)){				
				$st.=$value;
			}else if(is_array($value)){
				$st.=$this->arrayToString($value);
			}else if(is_bool($value)){
				$st.=($value?"true":"false");
			}else if(is_a($value, "UserSelection")){
				$st.=$this->arrayToString($value->getFiles());
			}
			
			if($index < count($params)){
				if(is_numeric($key)){
					$st.=",";
				}else{
					$st.=";";
				}
			}
		}
		return $st;
		
	}
	
	/**
	 * returns an instance of the AJXP_Logger object
	 *
	 * @access public
	 * @static
	 *
	 * @return AJXP_Logger an instance of the AJXP_Logger object
	 */
	function getInstance() {
		global $AJXP_LOGGER;
		if (!isset($AJXP_LOGGER)) {
			$AJXP_LOGGER = new AJXP_Logger();
		}
		return $AJXP_LOGGER;
	}

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
	 * @param message this is the message to be formatted
	 *
	 * @return $severity Severity level of the message
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
	
	function xmlListLogFiles($nodeName="file", $year=null, $month=null){
		$dir = $this->storageDir;
		if(!is_dir($this->storageDir)) return ;
		$logs = array();
		$years = array();
		$months = array();
		if(($handle = opendir($this->storageDir))!==false){
			while($file = readdir($handle)){				
				$split = split("\.", $file);
				if(!count($split) || $split[0] == "") continue;
				$split2 = split("_", $split[0]);
				$date = $split2[1];
				$dSplit = split("-", $date);
				$logY = $dSplit[2];
				$logM = $dSplit[0];
				$time = mktime(0,0,1,$dSplit[0], $dSplit[1], $dSplit[2]);
				$display = date("l d", $time);
				$fullYear = date("Y", $time);
				$fullMonth = date("F", $time);
				if($year != null && $fullYear != $year) continue;
				if($month != null && $fullMonth != $month) continue;
				$logs[$time] = "<$nodeName icon=\"toggle_log.png\" date=\"$display\" display=\"$display\" text=\"$date\" is_file=\"0\" filename=\"/logs/$fullYear/$fullMonth/$date\"/>";
				$years[$logY] = "<$nodeName icon=\"x-office-calendar.png\" date=\"$fullYear\" display=\"$fullYear\" text=\"$fullYear\" is_file=\"0\" src=\"content.php?dir=%2Flogs%2F$fullYear\" filename=\"/logs/$fullYear\"/>";
				$months[$logM] = "<$nodeName icon=\"x-office-calendar.png\" date=\"$fullMonth\" display=\"$logM\" text=\"$fullMonth\" is_file=\"0\" src=\"content.php?dir=%2Flogs%2F$fullYear%2F$fullMonth\" filename=\"/logs/$fullYear/$fullMonth\"/>";
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
	
	function xmlLogs($date, $nodeName = "log"){
				
		$fName = $this->storageDir."log_".$date.".txt";
		if(!is_file($fName) || !is_readable($fName)) return;
		
		$res = "";
		$lines = file($fName);
		foreach ($lines as $line){
			$line = Utils::xmlEntities($line);
			$matches = array();
			if(preg_match("/(.*)\t(.*)\t(.*)\t(.*)\t(.*)\t(.*)$/", $line, $matches)!==false){
				print(utf8_encode("<$nodeName is_file=\"1\" ajxp_mime=\"log\" date=\"$matches[1]\" ip=\"$matches[2]\" level=\"$matches[3]\" user=\"$matches[4]\" action=\"$matches[5]\" params=\"$matches[6]\"/>"));
			}
		}
		return ;
	}
}


?>