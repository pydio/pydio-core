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
 * Hook to apply to the demo version to avoid users changing the demo password
 */
class demoAuthHook extends AJXP_Plugin 
{
		
	function filterUsersPref($action, $httpVars, $fileVars){
		if($action != "save_user_pref") return ;
		$loggedUser = AuthService::getLoggedUser()->getId();
		if($loggedUser != "demo") return ;
		$i = 0;
		while(isSet($_GET["pref_name_".$i]) && isSet($_GET["pref_value_".$i]))
		{
			$prefName = $_GET["pref_name_".$i];
			$prefValue = stripslashes($_GET["pref_value_".$i]);
			if($prefName == "password"){
				throw new Exception("You are not allowed to change the password");
			}
			$i++;			
		}
	}
		    
}

?>
