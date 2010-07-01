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
 * Description : Simple interface to the underlaying Logger mechanism.
 */
define("LOG_LEVEL_DEBUG", "Debug");
define("LOG_LEVEL_INFO", "Info");
define("LOG_LEVEL_NOTICE", "Notice");
define("LOG_LEVEL_WARNING", "Warning");
define("LOG_LEVEL_ERROR", "Error");

class AJXP_Logger {
	
	public static function debug($message, $params = array()){
		if(!class_exists("ConfService")) return ;
		if(!ConfService::getConf("SERVER_DEBUG")) return ;
		$logger = self::getInstance();
		$message .= "\t";
		if(is_string($params)){
			$message .= $params;
		}
		else if(is_array($params) && count($params)){
			$message.=$logger->arrayToString($params);
		}		
		$logger->write($message, LOG_LEVEL_DEBUG);				
	}
	
	public static function logAction($action, $params=array()){
		$logger = self::getInstance();		
		$message = "$action\t";		
		if(count($params)){
			$message.=$logger->arrayToString($params);
		}		
		$logger->write($message, LOG_LEVEL_INFO);		
	}
	
	
	/**
	 * returns an instance of the AJXP_Logger object
	 *
	 * @access public
	 * @static
	 *
	 * @return AJXP_Logger an instance of the AJXP_Logger object
	 */
 	public static function getInstance()
 	{
 		$logger = ConfService::getLogDriverImpl();
 		return $logger;
 	}
		
}

?>