<?php
/**
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : main script called at initialisation.
 */
require_once("server/classes/class.AJXP_Utils.php");
require_once("server/classes/class.HTMLWriter.php");
require_once("server/classes/class.Repository.php");
require_once("server/classes/class.ConfService.php");
require_once("server/classes/class.AuthService.php");
require_once("server/classes/class.AJXP_Logger.php");
require_once("server/classes/class.AJXP_Plugin.php");
require_once("server/classes/class.AJXP_PluginsService.php");
require_once("server/classes/class.AbstractDriver.php");
require_once("server/classes/class.AbstractAccessDriver.php");
HTMLWriter::charsetHeader();
$confFile = "server/conf/conf.php";
include_once($confFile);
$pServ = AJXP_PluginsService::getInstance();
$pServ->loadPluginsRegistry(INSTALL_PATH."/plugins", INSTALL_PATH."/server/conf");
ConfService::init($confFile);
$confStorageDriver = ConfService::getConfStorageImpl();
include_once($confStorageDriver->getUserClassFileName());
session_name("AjaXplorer");
session_start();

$outputArray = array();
$testedParams = array();
$passed = true;
if(!is_file(TESTS_RESULT_FILE)){
	$passed = AJXP_Utils::runTests($outputArray, $testedParams);
	if(!$passed && !isset($_GET["ignore_tests"])){
		die(AJXP_Utils::testResultsToTable($outputArray, $testedParams));
	}else{
		AJXP_Utils::testResultsToFile($outputArray, $testedParams);
	}
}

$START_PARAMETERS = array("ALERT"=>"");
if(AuthService::usersEnabled())
{
	AuthService::preLogUser((isSet($_GET["remote_session"])?$_GET["remote_session"]:""));
	if(!is_readable(USERS_DIR)) $START_PARAMETERS["ALERT"] = "Warning, the users directory is not readable!";
	else if(!is_writeable(USERS_DIR)) $START_PARAMETERS["ALERT"] = "Warning, the users directory is not writeable!";
	if(AuthService::countAdminUsers() == 0){
		$authDriver = ConfService::getAuthDriverImpl();
		$adminPass = ADMIN_PASSWORD;
		if($authDriver->getOption("TRANSMIT_CLEAR_PASS") !== true){
			$adminPass = md5(ADMIN_PASSWORD);
		}
		 AuthService::createUser("admin", $adminPass, true);
		 if(ADMIN_PASSWORD == INITIAL_ADMIN_PASSWORD)
		 {
			 $START_PARAMETERS["ALERT"] .= "Warning! User 'admin' was created with the initial common password 'admin'. \\nPlease log in as admin and change the password now!";
		 }
	}else if(AuthService::countAdminUsers() == -1){
		// Here we may come from a previous version! Check the "admin" user and set its right as admin.
		$confStorage = ConfService::getConfStorageImpl();
		$adminUser = $confStorage->createUserObject("admin"); 
		$adminUser->setAdmin(true);
		$adminUser->save();
		$START_PARAMETERS["ALERT"] .= "You may come from a previous version. Now any user can have the administration rights, \\n your 'admin' user was set with the admin rights. Please check that this suits your security configuration.";
	}
	if(AuthService::getLoggedUser() != null || AuthService::logUser(null, null) == 1)
	{
		$loggedUser = AuthService::getLoggedUser();
		if(!$loggedUser->canRead(ConfService::getCurrentRootDirIndex()) 
				&& AuthService::getDefaultRootId() != ConfService::getCurrentRootDirIndex())
		{
			ConfService::switchRootDir(AuthService::getDefaultRootId());
		}
	}
}

$START_PARAMETERS["EXT_REP"] = "/";

$loggedUser = AuthService::getLoggedUser();
if($loggedUser != null && $loggedUser->getId() != "guest")
{
	if($loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
}
else
{	
	if(isSet($_COOKIE["AJXP_lang"])) ConfService::setLanguage($_COOKIE["AJXP_lang"]);
}
if(isSet($_GET["repository_id"]) && isSet($_GET["folder"])){
	require_once("server/classes/class.SystemTextEncoding.php");
	if(AuthService::usersEnabled()){
		if($loggedUser!= null && $loggedUser->canSwitchTo($_GET["repository_id"])){			
			$START_PARAMETERS["EXT_REP"] = SystemTextEncoding::toUTF8(urldecode($_GET["folder"]));
			$loggedUser->setArrayPref("history", "last_repository", $_GET["repository_id"]);
			$loggedUser->setArrayPref("history", $_GET["repository_id"], $_GET["folder"]);
			$loggedUser->save();
		}else{
			$_SESSION["PENDING_REPOSITORY_ID"] = $_GET["repository_id"];
			$_SESSION["PENDING_FOLDER"] = $_GET["folder"];
		}
	}else{
		ConfService::switchRootDir($_GET["repository_id"]);
		$START_PARAMETERS["EXT_REP"] = SystemTextEncoding::toUTF8(urldecode($_GET["folder"]));
	}
}


$JS_DEBUG = false;
if(isSet($_GET["skipDebug"])) $JS_DEBUG = false;
if($JS_DEBUG && isSet($_GET["compile"])){
	require_once(SERVER_RESOURCES_FOLDER."/class.AJXP_JSPacker.php");
	AJXP_JSPacker::pack();
}

if($JS_DEBUG && isSet($_GET["update_i18n"])){
	AJXP_Utils::updateI18nFiles();
}

if(isSet($_GET["external_selector_type"])){
	$START_PARAMETERS["SELECTOR_DATA"] = array("type" => $type, "data" => $data);
}

$mess = ConfService::getMessages();
$JSON_START_PARAMETERS = json_encode($START_PARAMETERS);
if($JS_DEBUG){
	include_once(CLIENT_RESOURCES_FOLDER."/html/gui_debug.html");
}else{
	include_once(CLIENT_RESOURCES_FOLDER."/html/gui.html");
}

HTMLWriter::closeBodyAndPage();
?>