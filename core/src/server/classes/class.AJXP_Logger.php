<?php

define("LOCAL_STORAGE_DIR", INSTALL_PATH."/server/logs/");
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

		if (!file_exists(LOCAL_STORAGE_DIR)) {
			@mkdir(LOCAL_STORAGE_DIR, LOG_GROUP_RIGHTS);
		}

		$this->fileHandle = fopen(LOCAL_STORAGE_DIR . LOG_FILE_NAME, "at+");
		
		if ($this->fileHandle === false) {
			//print "Failed to obtain a handle to log file '" . LOG_FILE_NAME . "'";
		}

	}

	function logAction($action, $params=array()){
		$logger = AJXP_Logger::getInstance();
		$message = "$action\t";		
		if(count($params)){
			$message.=$logger->arrayToString($params);
		}
		$logger->write($message, LOG_LEVEL_INFO);
		$logger->close();
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
			
			if (@fwrite($this->fileHandle, $textMessage) === false) {
				//print "There was an error writing to log file.";
			}
		}
		
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
	
	function xmlListLogFiles(){
		$dir = LOCAL_STORAGE_DIR;
		if(!is_dir(LOCAL_STORAGE_DIR)) return ;
		$logs = array();
		if(($handle = opendir(LOCAL_STORAGE_DIR))!==false){
			while($file = readdir($handle)){				
				$split = split("\.", $file);
				if(!count($split) || $split[0] == "") continue;
				$split2 = split("_", $split[0]);
				$date = $split2[1];
				$dSplit = split("-", $date);
				$time = mktime(0,0,1,$dSplit[0], $dSplit[1], $dSplit[2]);
				$display = date("D. d M. Y", $time);
				$logs[$time] = "<file date=\"$date\" display=\"$display\"/>";
			}
			closedir($handle);	
		}
		ksort($logs);
		foreach($logs as $log) print($log);
		return ;		
	}
	
	function xmlLogs($date){
				
		$fName = LOCAL_STORAGE_DIR."log_".$date.".txt";
		if(!is_file($fName) || !is_readable($fName)) return;
		
		$res = "";
		$lines = file($fName);
		foreach ($lines as $line){
			$line = str_replace("&", "&amp;", $line);
			$matches = array();
			if(preg_match("/(.*)\t(.*)\t(.*)\t(.*)\t(.*)\t(.*)$/", $line, $matches)!==false){
				print(utf8_encode("<log date=\"$matches[1]\" ip=\"$matches[2]\" level=\"$matches[3]\" user=\"$matches[4]\" action=\"$matches[5]\" params=\"$matches[6]\"/>"));
			}
		}
		return ;
	}
}


?>