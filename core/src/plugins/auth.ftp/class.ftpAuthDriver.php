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
 * Description : Authentication against an FTP server, using a defined FTP Repository.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(INSTALL_PATH."/server/classes/class.AbstractAuthDriver.php");
require_once(INSTALL_PATH."/plugins/access.ftp/class.ftpAccessWrapper.php");

class ftpSonWrapper extends ftpAccessWrapper {
	public function initUrl($url){
		$this->parseUrl($url);
	}
}

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
		$crtUser = $_SESSION["AJXP_SESSION_REMOTE_USER"];
		if(isSet($_SESSION["AJXP_DYNAMIC_FTP_DATA"])){
			unset($_SESSION["AJXP_DYNAMIC_FTP_DATA"]);
		}
		unset($_SESSION["AJXP_SESSION_REMOTE_USER"]);
		unset($_SESSION["AJXP_SESSION_REMOTE_PASS"]);
		$adminUser = $this->options["ADMIN_USER"];
		$subUsers = array();
		if($login != $adminUser && $crtUser!=""){
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
			$wrapper->initUrl("ajxp.ftp://$login:$pass@$repoId/");
			$_SESSION["AJXP_SESSION_REMOTE_USER"] = $login;
			$_SESSION["AJXP_SESSION_REMOTE_PASS"] = $pass;
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