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
require_once(INSTALL_PATH."/server/classes/class.AbstractAuthDriver.php");
class serialAuthDriver extends AbstractAuthDriver {
	
	var $usersSerFile;
	var $driverName = "serial";
	
	function init($options){
		parent::init($options);
		$this->usersSerFile = $options["USERS_FILEPATH"];
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
	
	function usersEditable(){
		return true;
	}
	function passwordsEditable(){
		return true;
	}
	
	function createUser($login, $passwd){
		$users = $this->listUsers();
		if(!is_array($users)) $users = array();
		if(array_key_exists($login, $users)) return "exists";
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$users[$login] = md5($passwd);
		}else{
			$users[$login] = $passwd;
		}
		Utils::saveSerialFile($this->usersSerFile, $users);		
	}	
	function changePassword($login, $newPass){
		$users = $this->listUsers();
		if(!is_array($users) || !array_key_exists($login, $users)) return ;
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$users[$login] = md5($newPass);
		}else{
			$users[$login] = $newPass;
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

}
?>