<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 *
 * Description : Interface between Pydio and external software. Handle with care!
 * Take care when using this file. It can't be included anywhere, as it's doing global scope pollution.
 *    Typically, this is used as glue code from your CMS frontend and AJXP code.
 *    This example file switches sessions (close CMS session, open AJXP session, modify AJXP's
 *    session value so the users actions are performed as if they were done locally by AJXP, and then
 *    reopen your CMS session).
 *    This is typically used by Wordpress as the plugin mechanism is hook based.
 *
 *    The idea is: this script is require()'d by the CMS script.
 */

/**
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
use Pydio\Conf\Core\AbstractUser;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;

global $AJXP_GLUE_GLOBALS;
if (!isSet($AJXP_GLUE_GLOBALS)) {
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
if (!function_exists("auth_remote_debug")){
    /**
     * @param $str
     */
    function auth_remote_debug($str){
        if(AJXP_SERVER_DEBUG){
            error_log('[Pydio Auth Remote] '.$str);
        }
    }
}
$pServ = PluginsService::getInstance();
ConfService::init($FRAMEWORK_PATH);
ConfService::start();
$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());

$plugInAction = $AJXP_GLUE_GLOBALS["plugInAction"];
$secret = $AJXP_GLUE_GLOBALS["secret"];

$confPlugs = ConfService::getConf("PLUGINS");
$authPlug = ConfService::getAuthDriverImpl();
if(property_exists($authPlug, "drivers") && is_array($authPlug->drivers) && $authPlug->drivers["remote"]){
    $authPlug = $authPlug->drivers["remote"];
}
if ($authPlug->getOption("SECRET") == "") {
    if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
       die("This file must be included and cannot be called directly");
    }
    if ($_SERVER['PHP_SELF'] != $authPlug->getOption("LOGIN_URL")) {
        auth_remote_debug("No secret provided, comparing current URL and login URL parameter is wrong. Please set up a secret key.");
        $plugInAction = "WRONG_URL";
    }
} else if ($secret != $authPlug->getOption("SECRET")) {
    auth_remote_debug("Secret keys are not corresponding. Make sure to setup secret in both CMS plugin and Pydio plugin.");
    $plugInAction = "WRONG_SECRET";
}

/**
 * @param array $loginData
 * @param AbstractUser $userObject
 */
if(!function_exists("ajxp_gluecode_updateRole")){

    /**
     * @param $loginData
     * @param $userObject
     */
    function ajxp_gluecode_updateRole($loginData, &$userObject)
    {
        auth_remote_debug("Updating user roles based on mappings");
        $authPlug = ConfService::getAuthDriverImpl();
        if(property_exists($authPlug, "drivers") && is_array($authPlug->drivers) && $authPlug->drivers["remote"]){
            $authPlug = $authPlug->drivers["remote"];
        }
        $rolesMap = $authPlug->getOption("ROLES_MAP");
        if(!isSet($rolesMap) || strlen($rolesMap) == 0) return;
        // String like {key:value,key2:value2,key3:value3}
        $rolesMap = explode(",", $rolesMap);
        $newMap = array();
        foreach ($rolesMap as $value) {
            $parts = explode(":", trim($value));
            $roleId = trim($parts[1]);
            $roleObject = RolesService::getRole($roleId);
            if ($roleObject != null) {
                $newMap[trim($parts[0])] = $roleObject;
                $userObject->removeRole($roleId);
            }
        }
        $rolesMap = $newMap;
        if (isset($loginData["roles"]) && is_array($loginData["roles"])) {
            foreach ($loginData["roles"] as $role) {
                if (isSet($rolesMap[$role])) {
                    $userObject->addRole($rolesMap[$role]);
                }
            }
        }
    }

}


switch ($plugInAction) {
    case 'login':
        auth_remote_debug("Entering 'login' case in glueCode");
        $login = $AJXP_GLUE_GLOBALS["login"]; $autoCreate = $AJXP_GLUE_GLOBALS["autoCreate"];
        if (is_array($login)) {
            $newSession = new SessionSwitcher("AjaXplorer");
            auth_remote_debug("Entering 'login' case in glueCode");
            $creation = false;
            if ($autoCreate && !UsersService::userExists($login["name"], "w")) {
                auth_remote_debug("Automatically creating user in Pydio");
                $creation = true;
                $isAdmin = (isSet($login["right"]) && $login["right"] == "admin");
                UsersService::createUser($login["name"], $login["password"], $isAdmin);
            }
            try{
                if (isSet($AJXP_GLUE_GLOBALS["checkPassword"]) && $AJXP_GLUE_GLOBALS["checkPassword"] === TRUE) {
                    $userObject = AuthService::logUser($login["name"], $login["password"], false, false);
                } else {
                    $userObject = AuthService::logUser($login["name"], $login["password"], true);
                }
                auth_remote_debug("User logged to pydio succesfully");
                if ($userObject->isAdmin()) {
                    auth_remote_debug("User is admin, updating admin rights");
                    RolesService::updateAdminRights($userObject);
                } else {
                    auth_remote_debug("User is standard, updating default rights");
                    RolesService::updateDefaultRights($userObject);
                }
                if($creation) ajxp_gluecode_updateRole($login, $userObject);
                $userObject->save("superuser");
                AuthService::updateSessionUser($userObject);
            }catch (\Pydio\Core\Exception\LoginException $l){

            }
        }
        break;
    case 'logout':
        auth_remote_debug("Entering 'logout' case in glueCode. Should kill pydio session");
        $newSession = new SessionSwitcher("AjaXplorer");
        global $_SESSION;
        $_SESSION = array();
        $result = TRUE;
        break;
    case 'addUser':
        auth_remote_debug("Entering 'addUser' case in glueCode. Create user in pydio");
        $user = $AJXP_GLUE_GLOBALS["user"];
        if (is_array($user)) {
            $isAdmin = (isSet($user["right"]) && $user["right"] == "admin");
            $userObject = UsersService::createUser($user["name"], $user["password"], $isAdmin);
            if (isSet($user["roles"])) {
                ajxp_gluecode_updateRole($user, $userObject);
                $userObject->save("superuser");
            }
            $result = TRUE;
        }
        break;
    case 'delUser':
        auth_remote_debug("Entering 'delUser' case in glueCode. Delete user from pydio");
        $userName = $AJXP_GLUE_GLOBALS["userName"];
        if (strlen($userName)) {
            UsersService::deleteUser($userName);
            $result = TRUE;
        }
        break;
    case 'updateUser':
        auth_remote_debug("Entering 'updateUser' case in glueCode. Update user in pydio");
        $user = $AJXP_GLUE_GLOBALS["user"];
        if (is_array($user)) {
            if (UsersService::userExists($user["name"]) && UsersService::updatePassword($user["name"], $user["password"])) {
                $isAdmin =  (isSet($user["right"]) && $user["right"] == "admin");
                $confDriver = ConfService::getConfStorageImpl();
                $userObject = UsersService::getUserById($user["name"], false);
                $userObject->setAdmin($isAdmin);
                ajxp_gluecode_updateRole($user, $userObject);
                $userObject->save("superuser");
                $result = TRUE;
            } else $result = FALSE;
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
