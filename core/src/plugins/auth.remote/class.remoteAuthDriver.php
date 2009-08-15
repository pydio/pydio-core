<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Cyril Russo
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
require_once(INSTALL_PATH."/server/classes/class.AbstractAuthDriver.php");

/** This class works in 2 modes (master / slave)
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
		parent::init($options);
		$this->usersSerFile = $options["USERS_FILEPATH"];
        $this->slaveMode = $options["SLAVE_MODE"] == "true";
        $this->secret = $options["SECRET"];
        $this->urls = array($options["LOGIN_URL"], $options["LOGOUT_URL"]);
	}
			
	function listUsers(){
		return Utils::loadSerialFile($this->usersSerFile);
	}
	
	function userExists($login){
		$users = $this->listUsers();
		if(!is_array($users) || !array_key_exists($login, $users)) return false;
		return true;
	}	
	
	function checkPassword($login, $pass, $seed){
		$userStoredPass = $this->getUserPass($login);
		if(!$userStoredPass) return false;
		if($seed == "-1"){ // Seed = -1 means that password is not encoded.
			return ($userStoredPass == $pass);
		}else{
			return (md5($userStoredPass.$seed) == $pass);
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
		$users = $this->listUsers();
		if(!is_array($users)) $users = array();
		if(array_key_exists($login, $users)) return "exists";
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$users[$login] = $passwd;
		}else{
			$users[$login] = md5($passwd);
		}
		Utils::saveSerialFile($this->usersSerFile, $users);		
	}	
	function changePassword($login, $newPass){
		$users = $this->listUsers();
		if(!is_array($users) || !array_key_exists($login, $users)) return ;
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$users[$login] = $newPass;
		}else{
			$users[$login] = md5($newPass);
		}
		Utils::saveSerialFile($this->usersSerFile, $users);
	}	
	function deleteUser($login){
		$users = $this->listUsers();
		if(is_array($users) && array_key_exists($login, $users))
		{
			unset($users[$login]);
			Utils::saveSerialFile($this->usersSerFile, $users);
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
    
    function replaceAjxpXmlKeywords($xml){	
        $xml = str_replace("AJXP_REMOTE_AUTH", "true", $xml);
        $xml = str_replace("AJXP_NOT_REMOTE_AUTH", "false", $xml);
		$xml = parent::replaceAjxpXmlKeywords($xml);
		return $xml;
	}    

}
?>
