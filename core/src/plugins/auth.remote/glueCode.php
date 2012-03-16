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
 *
 * Description : Interface between AjaXplorer and external software. Handle with care!
 * Take care when using this file. It can't be included anywhere, as it's doing global scope pollution.
 *    Typically, this is used as glue code from your CMS frontend and AJXP code.
 *    This example file switches sessions (close CMS session, open AJXP session, modify AJXP's
 *    session value so the users actions are performed as if they were done locally by AJXP, and then
 *    reopen your CMS session).
 *    This is typically used by Wordpress as the plugin mechanism is hook based.
 *
 *    The idea is: this script is require()'d by the CMS script.
 */
  
global $AJXP_GLUE_GLOBALS;
if(!isSet($AJXP_GLUE_GLOBALS)){
	$AJXP_GLUE_GLOBALS = array();
}
if (!isSet($CURRENTPATH)) {
    $CURRENTPATH=realpath(dirname(__FILE__));
    $FRAMEWORK_PATH = realpath($CURRENTPATH."/../../");
}

include_once($FRAMEWORK_PATH."/base.conf.php");

if (!class_exists("SessionSwitcher")) {
    require_once("$CURRENTPATH/sessionSwitcher.php");
}
$pServ = AJXP_PluginsService::getInstance();
ConfService::init();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
$pServ->loadPluginsRegistry("$FRAMEWORK_PATH/plugins", $confPlugin);
require_once("$FRAMEWORK_PATH/plugins/conf.".$confPlugin->getName()."/class.AJXP_User.php");
ConfService::start();

$plugInAction = $AJXP_GLUE_GLOBALS["plugInAction"];
$secret = $AJXP_GLUE_GLOBALS["secret"];

$confPlugs = ConfService::getConf("PLUGINS");
$authPlug = ConfService::getAuthDriverImpl();
if ($authPlug->getOption("SECRET") == "")
{
    if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])){
       die("This file must be included and cannot be called directly");
    }
    if ($_SERVER['PHP_SELF'] != $authPlug->getOption("LOGIN_URL")){
       $plugInAction = "WRONG_URL";
    }
} else if ($secret != $authPlug->getOption("SECRET")){
    $plugInAction = "WRONG_SECRET";
}

switch($plugInAction)
{
	case 'login':
	    $login = $AJXP_GLUE_GLOBALS["login"]; $autoCreate = $AJXP_GLUE_GLOBALS["autoCreate"];
	    if (is_array($login))
	    {
	        $newSession = new SessionSwitcher("AjaXplorer");
	        if($autoCreate && !AuthService::userExists($login["name"])){
		        $isAdmin = (isSet($login["right"]) && $login["right"] == "admin");
	        	AuthService::createUser($login["name"], $login["password"], $isAdmin);
	        }
	        if(isSet($AJXP_GLUE_GLOBALS["checkPassord"]) && $AJXP_GLUE_GLOBALS["checkPassord"] === TRUE){
		        $result = AuthService::logUser($login["name"], $login["password"], false, false, -1);
	        }else{
	        	$result = AuthService::logUser($login["name"], $login["password"], true);
	        }
		   	// Update default rights (this could go in the trunk...)
		   	if($result == 1){
			   	$userObject = AuthService::getLoggedUser();
			   	if($userObject->isAdmin()){
			   		AuthService::updateAdminRights($userObject);
			   	}else{
					AuthService::updateDefaultRights($userObject);
			   	}
				$userObject->save("superuser");
		   	}	        
	    }
	    break;
	case 'logout':
	    $newSession = new SessionSwitcher("AjaXplorer");
	    global $_SESSION;
	    $_SESSION = array();
	    $result = TRUE;
	    break;
	case 'addUser':
	    $user = $AJXP_GLUE_GLOBALS["user"];
	    if (is_array($user))
	    {
	        $isAdmin = (isSet($user["right"]) && $user["right"] == "admin");
	        AuthService::createUser($user["name"], $user["password"], $isAdmin);
	        $result = TRUE;
	    }
	    break;
	case 'delUser':
	    $userName = $AJXP_GLUE_GLOBALS["userName"];
	    if (strlen($userName))
	    {	        
	        AuthService::deleteUser($userName);
	        $result = TRUE;
	    }
	    break;
	case 'updateUser':
	    $user = $AJXP_GLUE_GLOBALS["user"];
	    if (is_array($user))
	    {
	        if (AuthService::updatePassword($user["name"], $user["password"]))
	        {
	        	$isAdmin =  (isSet($user["right"]) && $user["right"] == "admin");
				$confDriver = ConfService::getConfStorageImpl();
				$user = $confDriver->createUserObject($user["name"]);
				$user->setAdmin($isAdmin);
				$user->save("superuser");
	            $result = TRUE;
	        }
	        else $result = FALSE;
	    }
	    break;
	case 'installDB':
	    $user = $AJXP_GLUE_GLOBALS["user"]; $reset = $AJXP_GLUE_GLOBALS["reset"];
	    $result = TRUE;
	    break;            
	default:
	    $result = FALSE;
}

$AJXP_GLUE_GLOBALS["result"] = $result;
    
?>