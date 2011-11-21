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

require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/access.ftp/class.ftpAccessWrapper.php");

class ftpSonWrapper extends ftpAccessWrapper {
	public function initUrl($url){
		$this->parseUrl($url);
	}
}

/**
 * @package info.ajaxplorer.plugins
 * Authenticate users against an FTP server
 */
class ftpAuthDriver extends AbstractAuthDriver {
	
	var $driverName = "ftp";
	
	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if($contribNode->nodeName != "actions") return ;
		$actionXpath=new DOMXPath($contribNode->ownerDocument);
		if(!isset($this->options["FTP_LOGIN_SCREEN"]) || $this->options["FTP_LOGIN_SCREEN"] != "TRUE"){
			// Remove "ftp_login" && "ftp_set_data" actions
			$nodeList = $actionXpath->query('action[@name="dynamic_login"]', $contribNode);
			if(!$nodeList->length) return ;
			unset($this->actions["dynamic_login"]);
			$contribNode->removeChild($nodeList->item(0));
					
			$nodeList = $actionXpath->query('action[@name="ftp_set_data"]', $contribNode);
			if(!$nodeList->length) return ;
			unset($this->actions["ftp_set_data"]);			
			$contribNode->removeChild($node = $nodeList->item(0));
		}else{
			// Replace "login" by "dynamic_login"
			$loginList = $actionXpath->query('action[@name="login"]', $contribNode);			
			if($loginList->length){
				unset($this->actions["login"]);
				$contribNode->removeChild($loginList->item(0));									
			}
			$dynaLoginList = $actionXpath->query('action[@name="dynamic_login"]', $contribNode);
			if($dynaLoginList->length){
				$dynaLoginList->item(0)->setAttribute("name", "login");
				$this->actions["login"] = $this->actions["dynamic_login"];
			}
		}
	}

	
	function listUsers(){
		$adminUser = $this->options["ADMIN_USER"];
		return array($adminUser => $adminUser);
	}
	
	function userExists($login){
		return true;
	}	
	
	function logoutCallback($actionName, $httpVars, $fileVars){		
		$safeCredentials = AJXP_Safe::loadCredentials();
		$crtUser = $safeCredentials["user"];
		if(isSet($_SESSION["AJXP_DYNAMIC_FTP_DATA"])){
			unset($_SESSION["AJXP_DYNAMIC_FTP_DATA"]);
		}
		AJXP_Safe::clearCredentials();
		$adminUser = $this->options["ADMIN_USER"];
		$subUsers = array();
		if($crtUser != $adminUser && $crtUser!=""){
			AJXP_User::deleteUser($crtUser, $subUsers);
		}
		AuthService::disconnect();
		session_write_close();
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::loggingResult(2);
		AJXP_XMLWriter::close();
	}
	
	function setFtpDataCallback($actionName, $httpVars, $fileVars){
		$options = array("CHARSET", "FTP_DIRECT", "FTP_HOST", "FTP_PORT", "FTP_SECURE", "PATH");
		$ftpOptions = array();
		foreach ($options as $option){
			if(isSet($httpVars[$option])){
				$ftpOptions[$option] = $httpVars[$option];
			}
		}
		$_SESSION["AJXP_DYNAMIC_FTP_DATA"] = $ftpOptions;
	}
		
	function checkPassword($login, $pass, $seed){
		$adminUser = $this->options["ADMIN_USER"];
		$wrapper = new ftpSonWrapper();
		$repoId = $this->options["REPOSITORY_ID"];
		try{
            $wrapper->initUrl("ajxp.ftp://".rawurlencode($login).":".rawurlencode($pass)."@$repoId/");
			AJXP_Safe::storeCredentials($login, $pass);
		}catch(Exception $e){
			return false;
		}
		return true;		
	}
	
	function usersEditable(){
		return false;
	}
	function passwordsEditable(){
		return false;
	}
	
	function createUser($login, $passwd){
	}	
	function changePassword($login, $newPass){
	}	
	function deleteUser($login){
	}

	function getUserPass($login){
		return "";
	}

}
?>