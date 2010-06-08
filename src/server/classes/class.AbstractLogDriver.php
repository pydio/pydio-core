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
 * Description : Abstract representation of Ajaxplorer Logger
 */
define("LOG_LEVEL_DEBUG", "Debug");
define("LOG_LEVEL_INFO", "Info");
define("LOG_LEVEL_NOTICE", "Notice");
define("LOG_LEVEL_WARNING", "Warning");
define("LOG_LEVEL_ERROR", "Error");

/**
 * Abstract log driver provides an abstract class for the logging facility.
 *
 * The output stream/file/device will be implemented by the plugin which extends this class.
 * The object has a chance to open its stream or file from the init() method. all subsequent calls assume
 * the availability of the stream or file.
 * 
 * @author mosen
 * @abstract
 */
class AbstractLogDriver extends AJXP_Plugin {

	/**
	 * Driver type
	 *
	 * @var String type of driver
	 */
	var $driverType = "log";
	
	/**
	 * Initialise the driver.
	 *
	 * Gives the driver a chance to set up it's connection / file resource etc..
	 * 
	 * @param Array $options array of options specific to the logger driver.
	 * @access public
	 */
	function init($options) {}
	
	/**
	 * Write an entry to the log.
	 *
	 * @param String $textMessage The message to log
	 * @param String $severityLevel The severity level, see LOG_LEVEL_ constants
	 * 
	 */
	function write($textMessage, $severityLevel = LOG_LEVEL_DEBUG) {}
	
	
	/**
	 * Format an array as a readable string
	 * 
	 * Base implementation which can be used by other loggers to format arrays of parameters
	 * nicely.
	 *
	 * @param Array $params
	 * @return String readable list of parameters.
	 */
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
	 * List available log files in XML
	 *
	 * @param String [optional] $nodeName
	 * @param String [optional] $year
	 * @param String [optional] $month
	 */
	function xmlListLogFiles($nodeName="file", $year=null, $month=null){}
	
	/**
	 * List log contents in XML
	 *
	 * @param String $date Assumed to be m-d-y format.
	 * @param String [optional] $nodeName
	 */
	function xmlLogs($parentDir, $date, $nodeName = "log"){}
}