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
 * Description : main access point of the application, this script is called by any Ajax query.
 * Will dispatch the actions on the plugins.
 */
include_once("base.conf.php");

if(isSet($_GET["ajxp_sessid"]))
{
    // Don't overwrite cookie
    if (!isSet($_COOKIE["AjaXplorer"]))
    	$_COOKIE["AjaXplorer"] = $_GET["ajxp_sessid"];
}
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
require_once(AJXP_BIN_FOLDER."/class.AJXP_Logger.php");
set_error_handler(array("AJXP_XMLWriter", "catchError"), E_ALL & ~E_NOTICE );
set_exception_handler(array("AJXP_XMLWriter", "catchException"));
$pServ = AJXP_PluginsService::getInstance();
ConfService::init();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", $confPlugin);
ConfService::start();

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());
session_name("AjaXplorer");
session_start();

if(isSet($_GET["tmp_repository_id"])){
	ConfService::switchRootDir($_GET["tmp_repository_id"], true);
}else if(isSet($_SESSION["SWITCH_BACK_REPO_ID"])){
	ConfService::switchRootDir($_SESSION["SWITCH_BACK_REPO_ID"]);
	unset($_SESSION["SWITCH_BACK_REPO_ID"]);
}
$action = "get_boot_gui";
if(isSet($_GET["action"]) || isSet($_GET["get_action"])) $action = (isset($_GET["get_action"])?$_GET["get_action"]:$_GET["action"]);
else if(isSet($_POST["action"]) || isSet($_POST["get_action"])) $action = (isset($_POST["get_action"])?$_POST["get_action"]:$_POST["action"]);

$pluginsUnSecureActions = ConfService::getDeclaredUnsecureActions();
$unSecureActions = array_merge($pluginsUnSecureActions, array("get_secure_token"));
if(!in_array($action, $unSecureActions) && AuthService::getSecureToken()){
	$token = "";
	if(isSet($_GET["secure_token"])) $token = $_GET["secure_token"];
	else if(isSet($_POST["secure_token"])) $token = $_POST["secure_token"];
	if( $token == "" || !AuthService::checkSecureToken($token)){
		throw new Exception("You are not allowed to access this resource.");
	}
}

if(AuthService::usersEnabled())
{
	$httpVars = array_merge($_GET, $_POST);

	$rememberLogin = "";
	$rememberPass = "";
	$secureToken = "";
	if($action == "get_seed"){
		$seed = AuthService::generateSeed();
		if(AuthService::suspectBruteForceLogin()){
			HTMLWriter::charsetHeader('application/json');
			print json_encode(array("seed" => $seed, "captcha" => true));
		}else{
			HTMLWriter::charsetHeader("text/plain");
			print $seed;		
		}
		exit(0);
	}else if($action == "get_secure_token"){
		HTMLWriter::charsetHeader("text/plain");
		print AuthService::generateSecureToken();
		exit(0);
	}else if($action == "get_captcha"){
		include_once(AJXP_BIN_FOLDER."/class.CaptchaProvider.php");
		CaptchaProvider::sendCaptcha();
		exit(0) ;
	}else if($action == "logout"){
		AuthService::disconnect();
		$loggingResult = 2;
		session_destroy();
	}else if($action == "back"){
		AJXP_XMLWriter::header("url");
        echo AuthService::getLogoutAddress(false);
        AJXP_XMLWriter::close("url");
		exit(1);
    }else if($action == "login"){
		include_once(AJXP_BIN_FOLDER."/class.CaptchaProvider.php");
		if(AuthService::suspectBruteForceLogin() && (!isSet($httpVars["captcha_code"]) || !CaptchaProvider::checkCaptchaResult($httpVars["captcha_code"]))){
			$loggingResult = -4;
		}else{
			$userId = (isSet($httpVars["userid"])?$httpVars["userid"]:null);
			$userPass = (isSet($httpVars["password"])?$httpVars["password"]:null);
			$rememberMe = ((isSet($httpVars["remember_me"]) && $httpVars["remember_me"] == "true")?true:false);
			$cookieLogin = (isSet($httpVars["cookie_login"])?true:false);
			$loggingResult = AuthService::logUser($userId, $userPass, false, $cookieLogin, $httpVars["login_seed"]);
			if($rememberMe && $loggingResult == 1){
				$rememberLogin = "notify";
				$rememberPass = "notify";
				$loggedUser = AuthService::getLoggedUser();
			}
			if($loggingResult == 1){
				session_regenerate_id(true);
				$secureToken = AuthService::generateSecureToken();
			}
			if($loggingResult < 1 && AuthService::suspectBruteForceLogin()){
				$loggingResult = -4; // Force captcha reload
			}
		}
	}
	else 
	{
		AuthService::logUser(null, null);	
	}
	// Check that current user can access current repository, try to switch otherwise.
	$loggedUser = AuthService::getLoggedUser();
	if($loggedUser != null)
	{
		if(isSet($_SESSION["PENDING_REPOSITORY_ID"]) && isSet($_SESSION["PENDING_FOLDER"])){
			$loggedUser->setArrayPref("history", "last_repository", $_SESSION["PENDING_REPOSITORY_ID"]);
			$loggedUser->setPref("pending_folder", $_SESSION["PENDING_FOLDER"]);
			$loggedUser->save();
			AuthService::updateUser($loggedUser);
			unset($_SESSION["PENDING_REPOSITORY_ID"]);
			unset($_SESSION["PENDING_FOLDER"]);
		}
		$currentRepoId = ConfService::getCurrentRootDirIndex();
		$lastRepoId  = $loggedUser->getArrayPref("history", "last_repository");
		$defaultRepoId = AuthService::getDefaultRootId();
		if($defaultRepoId == -1){
			AuthService::disconnect();
			$loggingResult = -3;
		}else {
			if($lastRepoId !== "" && $lastRepoId!==$currentRepoId && !isSet($httpVars["tmp_repository_id"]) && $loggedUser->canSwitchTo($lastRepoId)){
				ConfService::switchRootDir($lastRepoId);
			}else if(!$loggedUser->canSwitchTo($currentRepoId)){
				ConfService::switchRootDir($defaultRepoId);
			}
		}
	}
	if($loggedUser == null)
	{
		// Try prelogging user if the session expired but the logging data is in fact still present
		// For example, for basic_http auth.
		AuthService::preLogUser((isSet($httpVars["remote_session"])?$httpVars["remote_session"]:""));
		$loggedUser = AuthService::getLoggedUser();
		if($loggedUser == null) $requireAuth = true;
	}
	if(isset($loggingResult))
	{
        if($loggedUser != null && (AuthService::hasRememberCookie() || (isSet($rememberMe) && $rememberMe ==true))){
            AuthService::refreshRememberCookie($loggedUser);
        }
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::loggingResult($loggingResult, $rememberLogin, $rememberPass, $secureToken);
		AJXP_XMLWriter::close();
		exit(1);
	}
}else{
	AJXP_Logger::debug(ConfService::getCurrentRootDirIndex());	
}

//Set language
$loggedUser = AuthService::getLoggedUser();
if($loggedUser != null && $loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
else if(isSet($_COOKIE["AJXP_lang"])) ConfService::setLanguage($_COOKIE["AJXP_lang"]);

//------------------------------------------------------------
// SPECIAL HANDLING FOR FANCY UPLOADER RIGHTS FOR THIS ACTION
//------------------------------------------------------------
if(AuthService::usersEnabled())
{
	$loggedUser = AuthService::getLoggedUser();	
	if($action == "upload" && ($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex()."")) && isSet($_FILES['Filedata']))
	{
		header('HTTP/1.0 ' . '410 Not authorized');
		die('Error 410 Not authorized!');
	}
}

// THIS FIRST DRIVERS DO NOT NEED ID CHECK
$ajxpDriver = AJXP_PluginsService::findPlugin("gui", "ajax");
$ajxpDriver->init(ConfService::getRepository());
$authDriver = ConfService::getAuthDriverImpl();
// DRIVERS BELOW NEED IDENTIFICATION CHECK
if(!AuthService::usersEnabled() || ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") || AuthService::getLoggedUser()!=null){
	$confDriver = ConfService::getConfStorageImpl();
	$Driver = ConfService::loadRepositoryDriver();
}
ConfService::initActivePlugins();
require_once(AJXP_BIN_FOLDER."/class.AJXP_Controller.php");
$xmlResult = AJXP_Controller::findActionAndApply($action, array_merge($_GET, $_POST), $_FILES);
if($xmlResult !== false && $xmlResult != ""){
	AJXP_XMLWriter::header();
	print($xmlResult);
	AJXP_XMLWriter::close();
}else if(isset($requireAuth) && AJXP_Controller::$lastActionNeedsAuth){
	AJXP_XMLWriter::header();
	AJXP_XMLWriter::requireAuth();
	AJXP_XMLWriter::close();
}
session_write_close();
?>