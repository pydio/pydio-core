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
 * AJXP_Plugin to authenticate users against the Basic-HTTP mechanism
 */
class basic_httpAuthDriver extends serialAuthDriver  {
	
	function usersEditable(){
		return false;
	}
	function passwordsEditable(){
		return false;
	}
	
	function preLogUser($sessionId){
		$localHttpLogin = $_SERVER["REMOTE_USER"];		
		if(!isSet($localHttpLogin)) return ;
		// If auto-create and http authentication is ok, log the user.
		if($this->autoCreateUser()){
			if(!$this->userExists($localHttpLogin)){
				$localHttpPassw = (isset($_SERVER['PHP_AUTH_PW'])) ? $_SERVER['PHP_AUTH_PW'] : md5(microtime(true)) ;
				$_tvcrhtau = $this->createUser($localHttpLogin, $localHttpPassw);
			}
			AuthService::logUser($localHttpLogin, "", true);
		}else{
			// If not auto-create but the user exists, log him.
			if($this->userExists($localHttpLogin)){
				AuthService::logUser($localHttpLogin, "", true);
			}
		}
			

	}
    function getLogoutRedirect(){
    	return AJXP_VarsFilter::filter($this->getOption("LOGOUT_URL"));
    }

}
?>