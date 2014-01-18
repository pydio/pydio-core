<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
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
$pServ = AJXP_PluginsService::getInstance();
ConfService::init();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
$pServ->loadPluginsRegistry("$FRAMEWORK_PATH/plugins", $confPlugin);
$userClassName = $confPlugin->getUserClassFileName();
require_once($userClassName);
ConfService::start();

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
       $plugInAction = "WRONG_URL";
    }
} else if ($secret != $authPlug->getOption("SECRET")) {
    $plugInAction = "WRONG_SECRET";
}

/**
 * @param array $loginData
 * @param AbstractAjxpUser $userObject
 */
if(!function_exists("ajxp_gluecode_updateRole")){

    function ajxp_gluecode_updateRole($loginData, &$userObject)
    {
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
            $roleObject = AuthService::getRole($roleId);
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
        $login = $AJXP_GLUE_GLOBALS["login"]; $autoCreate = $AJXP_GLUE_GLOBALS["autoCreate"];
        if (is_array($login)) {
            $newSession = new SessionSwitcher("AjaXplorer");
            $creation = false;
            if ($autoCreate && !AuthService::userExists($login["name"], "w")) {
                $creation = true;
                $isAdmin = (isSet($login["right"]) && $login["right"] == "admin");
                AuthService::createUser($login["name"], $login["password"], $isAdmin);
            }
            if (isSet($AJXP_GLUE_GLOBALS["checkPassword"]) && $AJXP_GLUE_GLOBALS["checkPassword"] === TRUE) {
                $result = AuthService::logUser($login["name"], $login["password"], false, false, -1);
            } else {
                $result = AuthService::logUser($login["name"], $login["password"], true);
            }
               // Update default rights (this could go in the trunk...)
               if ($result == 1) {
                   $userObject = AuthService::getLoggedUser();
                   if ($userObject->isAdmin()) {
                       AuthService::updateAdminRights($userObject);
                   } else {
                    AuthService::updateDefaultRights($userObject);
                   }
                if($creation) ajxp_gluecode_updateRole($login, $userObject);
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
        if (is_array($user)) {
            $isAdmin = (isSet($user["right"]) && $user["right"] == "admin");
            AuthService::createUser($user["name"], $user["password"], $isAdmin);
            if (isSet($user["roles"])) {
                $confDriver = ConfService::getConfStorageImpl();
                $userObject = $confDriver->createUserObject($user["name"]);
                ajxp_gluecode_updateRole($user, $userObject);
                $userObject->save("superuser");
            }
            $result = TRUE;
        }
        break;
    case 'delUser':
        $userName = $AJXP_GLUE_GLOBALS["userName"];
        if (strlen($userName)) {
            AuthService::deleteUser($userName);
            $result = TRUE;
        }
        break;
    case 'updateUser':
        $user = $AJXP_GLUE_GLOBALS["user"];
        if (is_array($user)) {
            if (AuthService::userExists($user["name"]) && AuthService::updatePassword($user["name"], $user["password"])) {
                $isAdmin =  (isSet($user["right"]) && $user["right"] == "admin");
                $confDriver = ConfService::getConfStorageImpl();
                $userObject = $confDriver->createUserObject($user["name"]);
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
