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
 * Description : Abstract representation of an action driver. Must be implemented.
 */
class AbstractDriver extends AJXP_Plugin {
	
	/**
	 * @var String
	 */
	var $driverName = "";
	
	/**
	 *
	 * @var String
	 */
	var $driverType = "";
	
	/**
	 * @var String
	 */
	var $xmlFilePath;
	/**
	 * @var array
	 */
	var $actions;
	var $driverConf = array();
	/*
	function AbstractDriver($driverName){
		// Load config file if there is one
		if(is_file(INSTALL_PATH."/server/conf/conf.".$this->driverType.".".$driverName.".inc")){
			include_once(INSTALL_PATH."/server/conf/conf.".$this->driverType.".".$driverName.".inc");
			if(isSet($DRIVER_CONF)){
				$this->driverConf = $DRIVER_CONF;
			}
		}
		$this->driverName = $driverName;
		$id = $this->driverType.".".$driverName;
		parent::__construct($id, INSTALL_PATH."/plugins/$id");
	}
	*/	
	
	function initXmlActionsFile($filePath){		
		$this->xmlFilePath = $filePath;
		parent::initXmlActionsFile($filePath);
		$this->actions["get_driver_actions"] = array();
	}
		
	function hasAction($actionName){
		return isSet($this->actions[$actionName]);
	}
	
	function actionNeedsRight($actionName, $right){
		if($right == 'r') $rString = "READ";
		else $rString = "WRITE";
		$action = $this->actions[$actionName];
		if(isSet($action["rights"]) && isSet($action["rights"][$rString]) && $action["rights"][$rString] == "true"){
			return true;
		}
		if(isSet($action["rights"]) && isSet($action["rights"][strtolower($rString)]) && $action["rights"][strtolower($rString)] == "true"){
			return true;
		}
		return false;
	}
	
	function applyAction($actionName, $httpVars, $filesVar)
	{
		if($actionName == "get_driver_actions"){
			AJXP_XMLWriter::header();
			$this->sendActionsToClient(false, null, null);
			AJXP_XMLWriter::close();
			exit(1);
		}
		if(isSet($this->actions[$actionName])){
			// use callback;
			$action = $this->actions[$actionName];
			$callBack = $action["callback"];
			try{
				return call_user_func(array(&$this, $callBack), $actionName, $httpVars, $filesVar);
			}catch (Exception $e){
				return AJXP_XMLWriter::sendMessage(null, SystemTextEncoding::toUTF8($e->getMessage())." (".basename($e->getFile())." - L.".$e->getLine().")", false);
			}
		}
	}
	
	function applyIfExistsAndExit($action,  $httpVars, $filesVar){
		if($this->hasAction($action)){
			$xmlBuffer = $this->applyAction($action, $httpVars, $filesVar);
			if($xmlBuffer != ""){
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::write($xmlBuffer, true);
				AJXP_XMLWriter::close();
				exit(1);
			}
		}		
	}
	
	/**
	 * Print the XML for actions
	 *
	 * @param boolean $filterByRight
	 * @param User $user
	 */
	function sendActionsToClient($filterByRight, $user, $repository){
		foreach($this->actions as $name => $action){
			if($name == "get_driver_actions" || $name == "get_ajxp_actions") continue;
			if($filterByRight && $this->actionNeedsRight($name, "r")){
				if($user==null || !$user->canRead($repository->getId())) continue;
			}
			if($filterByRight && $this->actionNeedsRight($name, "w")){
				if($user==null || !$user->canWrite($repository->getId())) continue;
			}
			if(isSet($action["XML"])){
				$xml = $action["XML"];
				$xml = AJXP_XMLWriter::replaceAjxpXmlKeywords($xml, true);
				AJXP_XMLWriter::write($xml, true);
			}
		}
	}
			
	
}

?>