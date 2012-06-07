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
 * Store authentication data in an SQL database
 */
class sqlAuthDriver extends AbstractAuthDriver {
	
	var $sqlDriver;
	var $driverName = "sql";	
	
	function init($options){
		parent::init($options);
		require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
		$this->sqlDriver = $options["SQL_DRIVER"];
		try {
			dibi::connect($this->sqlDriver);		
		} catch (DibiException $e) {
			echo get_class($e), ': ', $e->getMessage(), "\n";
			exit(1);
		}		
	}

    function supportsUsersPagination(){
        return true;
    }
    function listUsersPaginated($regexp, $offset, $limit){
        if($regexp != null){
            if($regexp[0]=="^") $regexp = ltrim($regexp, "^")."%";
            else if($regexp[strlen($regexp)-1] == "$") $regexp = "%".rtrim($regexp, "$");
            $res = dibi::query("SELECT * FROM [ajxp_users] WHERE [login] LIKE '".$regexp."' ORDER BY [login] ASC") ;
        }else if($offset != -1 || $limit != -1){
            $res = dibi::query("SELECT * FROM [ajxp_users]  ORDER BY [login] ASC LIMIT $offset,$limit");
        }else{
            $res = dibi::query("SELECT * FROM [ajxp_users] ORDER BY [login] ASC");
        }
        $pairs = $res->fetchPairs('login', 'password');
   		return $pairs;
    }
    function getUsersCount(){
        $res = dibi::query("SELECT [login] FROM [ajxp_users]") ;
        return $res->getRowCount();
    }

	function listUsers(){
		$res = dibi::query("SELECT * FROM [ajxp_users] ORDER BY [login] ASC");
		$pairs = $res->fetchPairs('login', 'password');
		return $pairs;
	}
	
	function userExists($login){
		$res = dibi::query("SELECT * FROM [ajxp_users] WHERE [login]=%s", $login);
		return($res->getRowCount());
	}	
	
	function checkPassword($login, $pass, $seed){
		$userStoredPass = $this->getUserPass($login);
		if(!$userStoredPass) return false;		
		
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){ // Seed = -1 means that password is not encoded.
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
		$users = $this->listUsers();
		if(!is_array($users)) $users = array();
		if(array_key_exists($login, $users)) return "exists";
		$userData = array("login" => $login);
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$userData["password"] = md5($passwd);
		}else{
			$userData["password"] = $passwd;
		}
		dibi::query('INSERT INTO [ajxp_users]', $userData);
	}	
	function changePassword($login, $newPass){
		$users = $this->listUsers();
		if(!is_array($users) || !array_key_exists($login, $users)) return ;
		$userData = array("login" => $login);
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$userData["password"] = md5($newPass);
		}else{
			$userData["password"] = $newPass;
		}
		dibi::query("UPDATE [ajxp_users] SET ", $userData, "WHERE `login`=%s", $login);
	}	
	function deleteUser($login){
		dibi::query("DELETE FROM [ajxp_users] WHERE `login`=%s", $login);
	}

	function getUserPass($login){
		$res = dibi::query("SELECT [password] FROM [ajxp_users] WHERE [login]=%s", $login);
		$pass = $res->fetchSingle();		
		return $pass;
	}

}
?>