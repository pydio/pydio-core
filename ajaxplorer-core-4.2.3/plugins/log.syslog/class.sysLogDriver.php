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
 * Standard logger. Writes logs into text files
 */
class sysLogDriver extends textLogDriver {

	/**
	 * @var Integer File handle to currently open log file.
	 */
	var $fileHandle = false;
	
	/**
	 * @var Array stack of log messages to be written when file becomes available.
	 */
	var $stack;

    /**
     * @var String identifer
     */
    var $signature;

	/**
	 * Close file handle on objects destructor.
	 */
	function __destruct(){
		if($this->fileHandle !== false){
            $this->close();
        }
	}
	
	/**
	 * Initialise storage: check and/or make log folder and file.
	 */
	function initStorage(){
		$this->open();
	}
	
	/**
	 * Open log file for append, and flush out buffered messages to the file.
	 */
	function open(){
        $this->fileHandle = openlog($this->signature, LOG_ODELAY | LOG_PID, LOG_LOCAL0 );
        if($this->fileHandle !== false && count($this->stack)){
            $this->stackFlush();
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

        $this->signature = $options["IDENTIFIER"];

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
        if($severityLevel == LOG_LEVEL_DEBUG) $sysLevel = LOG_DEBUG;
        elseif($severityLevel == LOG_LEVEL_INFO) $sysLevel = LOG_INFO;
        elseif($severityLevel == LOG_LEVEL_NOTICE) $sysLevel = LOG_NOTICE;
        elseif($severityLevel == LOG_LEVEL_WARNING) $sysLevel = LOG_WARNING;
        elseif($severityLevel == LOG_LEVEL_ERROR) $sysLevel = LOG_ERR;

		if ($this->fileHandle !== false) {

			if(count($this->stack)) $this->stackFlush();
            syslog($sysLevel, $textMessage);

		}else{
			$this->stack[] = array($sysLevel, $textMessage);
		}
		
	}
	
	/**
	 * Flush the stack/buffer of messages that couldn't be written earlier.
	 *
	 */
	function stackFlush(){
		// Flush stack for messages that could have been written before the file opening.
		foreach ($this->stack as $message){
			syslog($message[0], $message[1]);
		}
		$this->stack = array();
	}
	
	/**
	 * closes the handle to the log file
	 *
	 * @access public
	 */
	function close() {
		if($this->fileHandle) closelog();
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
	function xmlListLogFiles($nodeName="file", $year=null, $month=null, $rootPath = "/logs"){
        print "<$nodeName icon=\"toggle_log.png\" date=\"\"
        display=\"Logs are not readable via this GUI, they are sent directly to your system logger daemon.\"
        text=\"Logs are not readable via this GUI, they are sent directly to your system logger daemon.\"
        is_file=\"1\"
        filename=\"$rootPath/see\"/>";
	}
	
	/**
	 * Get a log in XML format.
	 *
	 * @param String $date Date in m-d-y format.
	 * @param String $nodeName The name of the node to use for each log item.
	 * @return null
	 */
	function xmlLogs($parentDir, $date, $nodeName = "log", $rootPath = "/logs"){


	}
}