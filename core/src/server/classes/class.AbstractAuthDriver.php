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
 * Description : Abstract representation of an access to an authentication system (ajxp, ldap, etc).
 */
class AbstractAuthDriver extends AbstractDriver {
	
	var $options;
	var $driverName = "abstract";
	var $driverType = "auth";
	
	function init($options){
		$this->options = $options;
		$this->initXmlActionsFile(CLIENT_RESOURCES_FOLDER."/xml/standard_auth_actions.xml");
		unset($this->actions["get_driver_actions"]);
	}
				
	function preLogUser($sessionId){}	

	function listUsers(){}
	function userExists($login){}	
	function checkPassword($login, $pass, $seed){}
	function createCookieString($login){}
	
	
	function usersEditable(){}
	function passwordsEditable(){}
	
	function createUser($login, $passwd){}	
	function changePassword($login, $oldPass, $newPass){}	
	function deleteUser($login){}
	
	function getLoginRedirect(){
		if(isSet($this->options["LOGIN_REDIRECT"])){
			return $this->options["LOGIN_REDIRECT"];
		}else{
			return false;
		}
	}
	
	function replaceAjxpXmlKeywords($xml){	
		$xml = parent::replaceAjxpXmlKeywords($xml);
		$loginRedirect = $this->getLoginRedirect();		
		$xml = str_replace("AJXP_LOGIN_REDIRECT", ($loginRedirect!==false?"'".$loginRedirect."'":"false"), $xml);
		return $xml;
	}
	
	function getOption($optionName){	
		return (isSet($this->options[$optionName])?$this->options[$optionName]:"");	
	}
	
	function isAjxpAdmin($login){
		return ($this->getOption("AJXP_ADMIN_LOGIN") === $login);
	}
	
	function autoCreateUser(){
		$opt = $this->getOption("AUTOCREATE_AJXPUSER");
		if($opt === true) return true;
		return false;
	}

	function getSeed($new=true){
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true) return -1;
		if($new){
			$seed = md5(time());
			$_SESSION["AJXP_CURRENT_SEED"] = $seed;	
			return $seed;		
		}else{
			return (isSet($_SESSION["AJXP_CURRENT_SEED"])?$_SESSION["AJXP_CURRENT_SEED"]:0);
		}
	}	
		
}
?>