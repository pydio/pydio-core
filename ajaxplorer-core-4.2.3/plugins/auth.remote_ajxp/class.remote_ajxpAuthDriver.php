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
 * Authenticates against a remote server implementing ajxp API
 */
class remote_ajxpAuthDriver extends serialAuthDriver  {
	
	function usersEditable(){
		return false;
	}
	function passwordsEditable(){
		return false;
	}
	
	function preLogUser($sessionId){
		
		require_once(AJXP_BIN_FOLDER."/class.HttpClient.php");
		$client = new HttpClient($this->getOption("REMOTE_SERVER"), $this->getOption("REMOTE_PORT"));
		$client->setDebug(false);
		if($this->getOption("REMOTE_USER") != ""){
			$client->setAuthorization($this->getOption("REMOTE_USER"), $this->getOption("REMOTE_PASSWORD"));
		}						
		$client->setCookies(array(($this->getOption("REMOTE_SESSION_NAME") ? $this->getOption("REMOTE_SESSION_NAME") : "PHPSESSID") => $sessionId));
		$result = $client->get($this->getOption("REMOTE_URL"), array("session_id"=>$sessionId));
		if($result)
		{
			$user = $client->getContent();
			if($this->autoCreateUser()){
				AuthService::logUser($user, "", true);
			}else{
				// If not auto-create but the user exists, log him.
				if($this->userExists($user)){
					AuthService::logUser($user, "", true);
				}
			}
		}
					
	}	

}
?>
