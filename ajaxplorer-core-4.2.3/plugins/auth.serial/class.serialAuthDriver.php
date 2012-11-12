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
 * Standard auth implementation, stores the data in serialized files
 */
class serialAuthDriver extends AbstractAuthDriver {
	
	var $usersSerFile;
	var $driverName = "serial";
	
	function init($options){
		parent::init($options);
		$this->usersSerFile = AJXP_VarsFilter::filter($this->getOption("USERS_FILEPATH"));
	}

	function performChecks(){
        if(!isset($this->options)) return;
        if(isset($this->options["FAST_CHECKS"]) && $this->options["FAST_CHECKS"] === true){
            return;
        }
		$usersDir = dirname($this->usersSerFile);
		if(!is_dir($usersDir) || !is_writable($usersDir)){
			throw new Exception("Parent folder for users file is either inexistent or not writeable.");
		}
		if(is_file($this->usersSerFile) && !is_writable($this->usersSerFile)){
			throw new Exception("Users file exists but is not writeable!");
		}
	}
	
	function listUsers(){
		$users = AJXP_Utils::loadSerialFile($this->usersSerFile);
        if(AuthService::ignoreUserCase()){
            $users = array_combine(array_map("strtolower", array_keys($users)), array_values($users));
        }
        return $users;
	}

    function supportsUsersPagination(){
        return true;
    }
    function listUsersPaginated($regexp, $offset = -1 , $limit = -1){
        $users = $this->listUsers();
        $result = array();
        $index = 0;
        foreach($users as $usr => $pass){
            if(!empty($regexp) && !preg_match("/$regexp/i", $usr)){
                continue;
            }
            if($offset != -1 && $index < $offset) {
                $index ++;
                continue;
            }
            $result[$usr] = $pass;
            $index ++;
            if($limit != -1 && count($result) >= $limit) break;
        }
        return $result;
    }
    function getUsersCount(){
        return count($this->listUsers());
    }

	
	function userExists($login){
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		$users = $this->listUsers();
		if(!is_array($users) || !array_key_exists($login, $users)) return false;
		return true;
	}	
	
	function checkPassword($login, $pass, $seed){
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		$userStoredPass = $this->getUserPass($login);
		if(!$userStoredPass) return false;
		if($seed == "-1"){ // Seed = -1 means that password is not encoded.
			return ($userStoredPass == md5($pass));
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
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		$users = $this->listUsers();
		if(!is_array($users)) $users = array();
		if(array_key_exists($login, $users)) return "exists";
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$users[$login] = md5($passwd);
		}else{
			$users[$login] = $passwd;
		}
		AJXP_Utils::saveSerialFile($this->usersSerFile, $users);		
	}	
	function changePassword($login, $newPass){
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
		$users = $this->listUsers();
		if(!is_array($users) || !array_key_exists($login, $users)) return ;
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$users[$login] = md5($newPass);
		}else{
			$users[$login] = $newPass;
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

}
?>