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
 * Description : main access point of the application, this script is called by any Ajax query.
 * Will dispatch the actions on the plugins.
 */
include_once("base.conf.php");

if( !isSet($_GET["action"]) && !isSet($_GET["get_action"])
    && !isSet($_POST["action"]) && !isSet($_POST["get_action"])
    && defined("AJXP_FORCE_SSL_REDIRECT") && AJXP_FORCE_SSL_REDIRECT === true
    && $_SERVER['HTTPS'] != "on") {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    exit();
}

if (isSet($_GET["ajxp_sessid"])) {
    // Don't overwrite cookie
    if (!isSet($_COOKIE["AjaXplorer"]))
        $_COOKIE["AjaXplorer"] = $_GET["ajxp_sessid"];
}
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

if (is_file(TESTS_RESULT_FILE)) {
    set_error_handler(array("AJXP_XMLWriter", "catchError"), E_ALL & ~E_NOTICE & ~E_STRICT );
    set_exception_handler(array("AJXP_XMLWriter", "catchException"));
}

$pServ = AJXP_PluginsService::getInstance();
ConfService::init();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
try {
    $pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", $confPlugin);
} catch (Exception $e) {
    die("Severe error while loading plugins registry : ".$e->getMessage());
}
ConfService::start();

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());
//new AjxpSessionHandler();
if (!isSet($OVERRIDE_SESSION)) {
    session_name("AjaXplorer");
}
session_start();

if (isSet($_GET["tmp_repository_id"])) {
    ConfService::switchRootDir($_GET["tmp_repository_id"], true);
} else if (isSet($_SESSION["SWITCH_BACK_REPO_ID"])) {
    ConfService::switchRootDir($_SESSION["SWITCH_BACK_REPO_ID"]);
    unset($_SESSION["SWITCH_BACK_REPO_ID"]);
}
$action = "ping";
if (preg_match('/MSIE 7/',$_SERVER['HTTP_USER_AGENT']) || preg_match('/MSIE 8/',$_SERVER['HTTP_USER_AGENT'])) {
    $action = "get_boot_gui";
} else {
    $action = (strpos($_SERVER["HTTP_ACCEPT"], "text/html") !== false ? "get_boot_gui" : "ping");
}
if(isSet($_GET["action"]) || isSet($_GET["get_action"])) $action = (isset($_GET["get_action"])?$_GET["get_action"]:$_GET["action"]);
else if(isSet($_POST["action"]) || isSet($_POST["get_action"])) $action = (isset($_POST["get_action"])?$_POST["get_action"]:$_POST["action"]);

$pluginsUnSecureActions = ConfService::getDeclaredUnsecureActions();
$unSecureActions = array_merge($pluginsUnSecureActions, array("get_secure_token"));
if (!in_array($action, $unSecureActions) && AuthService::getSecureToken()) {
    $token = "";
    if(isSet($_GET["secure_token"])) $token = $_GET["secure_token"];
    else if(isSet($_POST["secure_token"])) $token = $_POST["secure_token"];
    if ( $token == "" || !AuthService::checkSecureToken($token)) {
        throw new Exception("You are not allowed to access this resource.");
    }
}

if (AuthService::usersEnabled()) {
    $httpVars = array_merge($_GET, $_POST);

    AuthService::logUser(null, null);
    // Check that current user can access current repository, try to switch otherwise.
    $loggedUser = AuthService::getLoggedUser();
    if ($loggedUser == null || $loggedUser->getId() == "guest") {
        // Try prelogging user if the session expired but the logging data is in fact still present
        // For example, for basic_http auth.
        AJXP_PluginsService::getInstance()->initActivePlugins();
        AuthService::preLogUser($httpVars);
        $loggedUser = AuthService::getLoggedUser();
        if($loggedUser == null) $requireAuth = true;
    }
    if ($loggedUser != null) {
           $res = ConfService::switchUserToActiveRepository($loggedUser, (isSet($httpVars["tmp_repository_id"])?$httpVars["tmp_repository_id"]:"-1"));
           if (!$res) {
               AuthService::disconnect();
               $requireAuth = true;
           }
       }

} else {
    AJXP_Logger::debug(ConfService::getCurrentRepositoryId());
}

//Set language
$loggedUser = AuthService::getLoggedUser();
if($loggedUser != null && $loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
else if(isSet($_COOKIE["AJXP_lang"])) ConfService::setLanguage($_COOKIE["AJXP_lang"]);

//------------------------------------------------------------
// SPECIAL HANDLING FOR FANCY UPLOADER RIGHTS FOR THIS ACTION
//------------------------------------------------------------
if (AuthService::usersEnabled()) {
    $loggedUser = AuthService::getLoggedUser();
    if ($action == "upload" && ($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRepositoryId()."")) && isSet($_FILES['Filedata'])) {
        header('HTTP/1.0 ' . '410 Not authorized');
        die('Error 410 Not authorized!');
    }
}

// THIS FIRST DRIVERS DO NOT NEED ID CHECK
//$ajxpDriver = AJXP_PluginsService::findPlugin("gui", "ajax");
$authDriver = ConfService::getAuthDriverImpl();
// DRIVERS BELOW NEED IDENTIFICATION CHECK
if (!AuthService::usersEnabled() || ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") || AuthService::getLoggedUser()!=null) {
    $confDriver = ConfService::getConfStorageImpl();
    try{
        $Driver = ConfService::loadRepositoryDriver();
    }catch(Exception $e){
        //AuthService::disconnect();
    }
}
AJXP_PluginsService::getInstance()->initActivePlugins();
require_once(AJXP_BIN_FOLDER."/class.AJXP_Controller.php");
$xmlResult = AJXP_Controller::findActionAndApply($action, array_merge($_GET, $_POST), $_FILES);
if ($xmlResult !== false && $xmlResult != "") {
    AJXP_XMLWriter::header();
    print($xmlResult);
    AJXP_XMLWriter::close();
} else if (isset($requireAuth) && AJXP_Controller::$lastActionNeedsAuth) {
    AJXP_XMLWriter::header();
    AJXP_XMLWriter::requireAuth();
    AJXP_XMLWriter::close();
}
session_write_close();
