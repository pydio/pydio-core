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
 * AJXP_Plugin to bridge authentication between Ajxp and external CMS
 *  This class works in 2 modes (master / slave)
    It requires the following arguments:
       - SLAVE_MODE
       - LOGIN_URL
       - LOGOUT_URL
       - SECRET
       - USERS_FILEPATH (the users.ser filepath)

    In master mode, the login dialog is still displayed in AJXP.
       When the user attempt a login, the given credential are sent back to the given remote URL.
       The LOGIN_URL is called as GET LOGIN_URL?name=<entered_user_name>&pass=<entered_password>&key=MD5(name.password.SECRET)
       The method must return a valid PHP serialized object for us to continue (see below)

    In slave mode, the login dialog is not displayed in AJXP.
    If the user directly go to the main page, (s)he's redirected to the LOGIN_URL.
    The logout button isn't displayed either, a back button linking to LOGOUT_URL is used instead.
    The user will log in on the remote site, and the remote script will call us, as GET ajxpPath/plugins/auth.remote/login.php?object=<serialized object>&key=MD5(object.SECRET)

    The serialized object contains the same data as the serialAuthDriver.
 */
class remoteAuthDriver extends AbstractAuthDriver {
	
	var $usersSerFile;
    /** The current authentication mode */
    var $slaveMode;
    /** The current secret */
    var $secret;
    /** The current url array */
    var $urls;
	
	function init($options){
        $this->slaveMode = $options["SLAVE_MODE"] == "true";
        if($this->slaveMode && ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")){
        	// Make sure "login" is disabled, or it will re-appear if GUEST browsing is enabled!
        	// OLD WAY : unset($this->actions["login"]);
        	// NEW WAY : Modify manifest dynamically (more coplicated...)
        	$contribs = $this->xPath->query("registry_contributions/external_file");
        	foreach ($contribs as $contribNode){        		
        		if($contribNode->getAttribute('filename') == 'plugins/core.auth/standard_auth_actions.xml'){
        			$contribNode->parentNode->removeChild($contribNode);
        		}
        	}
        }
		parent::init($options);		
		$this->usersSerFile = $options["USERS_FILEPATH"];
        $this->secret = $options["SECRET"];
        $this->urls = array($options["LOGIN_URL"], $options["LOGOUT_URL"]);
	}	
			
	function listUsers(){
		$users = AJXP_Utils::loadSerialFile($this->usersSerFile);
        if(AuthService::ignoreUserCase()){
            $users = array_combine(array_map("strtolower", array_keys($users)), array_values($users));
        }
        return $users;
	}
	
	function userExists($login){
		$users = $this->listUsers();
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		if(!is_array($users) || !array_key_exists($login, $users)) return false;
		return true;
	}	
	
	function checkPassword($login, $pass, $seed){	

        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		global $AJXP_GLUE_GLOBALS;
		if(isSet($AJXP_GLUE_GLOBALS)){
			$userStoredPass = $this->getUserPass($login);
			if(!$userStoredPass) return false;
			if($seed == "-1"){ // Seed = -1 means that password is not encoded.
				return ($userStoredPass == $pass);
			}else{
				return (md5($userStoredPass.$seed) == $pass);
			}			
		}else{
			session_write_close();
			$host = "";
			if(isSet($this->options["MASTER_HOST"])){
				$host = $this->options["MASTER_HOST"];
			}else{
				$host = parse_url($_SERVER["SERVER_ADDR"], PHP_URL_HOST);
			}
			$formId = "";
			if(isSet($this->options["MASTER_AUTH_FORM_ID"])){
				$formId = $this->options["MASTER_AUTH_FORM_ID"];
			}
			$uri = $this->options["MASTER_URI"];
			$funcName = $this->options["MASTER_AUTH_FUNCTION"];
			require_once 'cms_auth_functions.php';
			if(function_exists($funcName)){
				$sessid = call_user_func($funcName, $host, $uri, $login, $pass, $formId);
				if($sessid != ""){
					session_id($sessid);
					session_start();
					return true;					
				}
			}
			return  false;
		}		
	}
	
	function createCookieString($login){
		$userPass = $this->getUserPass($login);
		return md5($login.":".$userPass.":ajxp");
	}
		
	function usersEditable(){
		return true;
	}
	function passwordsEditable(){
		return false;
	}
	
	function createUser($login, $passwd){
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		$users = $this->listUsers();
		if(!is_array($users)) $users = array();
		if(array_key_exists($login, $users)) return "exists";
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$users[$login] = $passwd;
		}else{
			$users[$login] = md5($passwd);
		}
		AJXP_Utils::saveSerialFile($this->usersSerFile, $users);		
	}	
	function changePassword($login, $newPass){
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		$users = $this->listUsers();
		if(!is_array($users) || !array_key_exists($login, $users)) return ;
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$users[$login] = $newPass;
		}else{
			$users[$login] = md5($newPass);
		}
		AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
	}	
	function deleteUser($login){
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		$users = $this->listUsers();
		if(is_array($users) && array_key_exists($login, $users))
		{
			unset($users[$login]);
			AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
		}		
	}

	function getUserPass($login){
		if(!$this->userExists($login)) return false;
		$users = $this->listUsers();
		return $users[$login];
	}
    
    function getLoginRedirect(){
        if ($this->slaveMode) {
            if (isset($_SESSION["AJXP_USER"])) return false;
            return $this->urls[0];
        } 
        return false;
    }
    
	function getLogoutRedirect(){
        if ($this->slaveMode) {
            return $this->urls[1];
        } 
        return false;
    }
    
}
?>
