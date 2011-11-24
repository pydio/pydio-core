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
 * @package info.ajaxplorer.auth
 * @class AbstractAuthDriver
 * Abstract representation of an authentication driver. Must be implemented by the auth plugin
 */
class AbstractAuthDriver extends AJXP_Plugin {
	
	var $options;
	var $driverName = "abstract";
	var $driverType = "auth";
					
	public function switchAction($action, $httpVars, $fileVars)	{
		if(!isSet($this->actions[$action])) return;
		$mess = ConfService::getMessages();
		
		switch ($action){			
			//------------------------------------
			//	CHANGE USER PASSWORD
			//------------------------------------	
			case "pass_change":
							
				$userObject = AuthService::getLoggedUser();
				if($userObject == null || $userObject->getId() == "guest"){
					header("Content-Type:text/plain");
					print "SUCCESS";
				}
				$oldPass = $httpVars["old_pass"];
				$newPass = $httpVars["new_pass"];
				$passSeed = $httpVars["pass_seed"];
				if(strlen($newPass) < ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth")){
					header("Content-Type:text/plain");
					print "PASS_ERROR";
				}
				if(AuthService::checkPassword($userObject->getId(), $oldPass, false, $passSeed)){
					AuthService::updatePassword($userObject->getId(), $newPass);
				}else{
					header("Content-Type:text/plain");
					print "PASS_ERROR";
				}
				header("Content-Type:text/plain");
				print "SUCCESS";
				
			break;					
					
			default;
			break;
		}				
		return "";
	}
	
	
	public function getRegistryContributions( $extendedVersion = true ){
        if(!$extendedVersion) return $this->registryContributions;
        
		$logged = AuthService::getLoggedUser();
        if(AuthService::usersEnabled()) {
            if($logged == null){
                return $this->registryContributions;
            }else{
                $xmlString = AJXP_XMLWriter::getUserXml($logged, false);
            }
        }else{
            $xmlString = AJXP_XMLWriter::getUserXml(null, false);
        }
		$dom = new DOMDocument();
		$dom->loadXML($xmlString);
		$this->registryContributions[]=$dom->documentElement;				
		return $this->registryContributions;
	}
	
	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if($contribNode->nodeName != "actions") return ;
		if(AuthService::usersEnabled() && $this->passwordsEditable()) return ;
		// Disable password change action
		$actionXpath=new DOMXPath($contribNode->ownerDocument);
		$passChangeNodeList = $actionXpath->query('action[@name="pass_change"]', $contribNode);
		if(!$passChangeNodeList->length) return ;
		unset($this->actions["pass_change"]);
		$passChangeNode = $passChangeNodeList->item(0);
		$contribNode->removeChild($passChangeNode);
	}
	
	function preLogUser($sessionId){}	

	function listUsers(){}
	function userExists($login){}	
	function checkPassword($login, $pass, $seed){}
	function createCookieString($login){}
	
	
	function usersEditable(){}
	function passwordsEditable(){}
	
	function createUser($login, $passwd){}	
	function changePassword($login, $newPass){}	
	function deleteUser($login){}
	
	function getLoginRedirect(){
		if(isSet($this->options["LOGIN_REDIRECT"])){
			return $this->options["LOGIN_REDIRECT"];
		}else{
			return false;
		}
	}

	function getLogoutRedirect(){
        return false;
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
	
	function filterCredentials($userId, $pwd){
		return array($userId, $pwd);
	}
		
}
?>