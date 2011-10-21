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
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * @class demoAccessDriver
 * AJXP_Plugin to access a filesystem with all write actions disabled
 */
class demoAccessDriver extends fsAccessDriver 
{
	/**
	* @var Repository
	*/
	var $repository;
		
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		$errorMessage = "This is a demo, all 'write' actions are disabled!";
		switch($action)
		{			
			//------------------------------------
			//	WRITE ACTIONS
			//------------------------------------
			case "put_content":
			case "copy":
			case "move":
			case "rename":
			case "delete":
			case "mkdir":
			case "mkfile":
			case "chmod":
			case "compress":
				return AJXP_XMLWriter::sendMessage(null, $errorMessage, false);
			break;
			
			//------------------------------------
			//	UPLOAD
			//------------------------------------	
			case "upload":
				
				return array("ERROR" => array("CODE" => "", "MESSAGE" => $errorMessage));				
				
			break;			
			
			default:
			break;
		}

		return parent::switchAction($action, $httpVars, $fileVars);
		
	}
	    
}

?>
