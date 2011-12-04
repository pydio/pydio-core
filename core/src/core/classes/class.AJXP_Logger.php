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

define("LOG_LEVEL_DEBUG", "Debug");
define("LOG_LEVEL_INFO", "Info");
define("LOG_LEVEL_NOTICE", "Notice");
define("LOG_LEVEL_WARNING", "Warning");
define("LOG_LEVEL_ERROR", "Error");
/**
 * @package info.ajaxplorer.core
 * @static
 */
/**
 * Provides static access to the logging mechanism
 */
class AJXP_Logger {

    /**
     * Use current logger instance and write a debug message
     * @static
     * @param string $message
     * @param array $params
     * @return
     */
	public static function debug($message, $params = array()){
		if(!class_exists("ConfService")) return ;
		if(!ConfService::getConf("SERVER_DEBUG")) return ;
		$logger = self::getInstance();
		if($logger == null) return ;
		$message .= "\t";
		if(is_string($params)){
			$message .= $params;
		}
		else if(is_array($params) && count($params)){
			$message.=$logger->arrayToString($params);
		}else if(!empty($params)) {
			$message .= print_r($params, true);
		}		
		$logger->write($message, LOG_LEVEL_DEBUG);				
	}

    /**
     * Send an action log message to the current logger instance.
     * @static
     * @param string $action
     * @param array $params
     * @return
     */
	public static function logAction($action, $params=array()){
		$logger = self::getInstance();		
		if($logger == null) return ;
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