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
 * Authenticates user against an SMB server
 */
class smbAuthDriver extends AbstractAuthDriver {
	
	var $driverName = "smb";
		
	function listUsers(){
		$adminUser = $this->options["ADMIN_USER"];
		return array($adminUser => $adminUser);
	}
	
	function userExists($login){
		return true;
	}	
	
	function logoutCallback($actionName, $httpVars, $fileVars){		
		AJXP_Safe::clearCredentials();
		$adminUser = $this->options["ADMIN_USER"];
		$subUsers = array();
		unset($_SESSION["COUNT"]); 
		unset($_SESSION["disk"]); 
		AuthService::disconnect();
		session_write_close();
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::loggingResult(2);
		AJXP_XMLWriter::close();
	}
			
	function checkPassword($login, $pass, $seed){
        require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/access.smb/smb.php");

		$_SESSION["AJXP_SESSION_REMOTE_PASS"] = $pass;
		$repoId = $this->options["REPOSITORY_ID"];
    	$repoObject = ConfService::getRepositoryById($repoId);
    	if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);
		$path = "";
		$basePath = $repoObject->getOption("PATH", true);
		$basePath = str_replace("AJXP_USER", $login, $basePath);
		$host = $repoObject->getOption("HOST");
		$url = "smb://$login:$pass@".$host."/".$basePath."/";
		try{
			if(!is_dir($url)){
				AJXP_Logger::debug("SMB Login failure"); 
				$_SESSION["AJXP_SESSION_REMOTE_PASS"] = ''; 
				unset($_SESSION["COUNT"]); 
				unset($_SESSION["disk"]); 
				return false;
			}
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
