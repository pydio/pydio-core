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
 * Description : main script called by any Ajax query. Will dispatch the actions on the plugins.
 */
if(!defined("STDIN")){
	die("This is the command line version of the framework, you are not allowed to access this page");
}

include_once("conf/base.conf.php");

require_once(AJXP_BIN_FOLDER."/class.AJXP_Utils.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_VarsFilter.php");
require_once(AJXP_BIN_FOLDER."/class.SystemTextEncoding.php");
require_once(AJXP_BIN_FOLDER."/class.Repository.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_Exception.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_Plugin.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_PluginsService.php");
require_once(AJXP_BIN_FOLDER."/class.AbstractAccessDriver.php");
require_once(AJXP_BIN_FOLDER."/class.AjxpRole.php");
require_once(AJXP_BIN_FOLDER."/class.ConfService.php");
require_once(AJXP_BIN_FOLDER."/class.AuthService.php");
require_once(AJXP_BIN_FOLDER."/class.UserSelection.php");
require_once(AJXP_BIN_FOLDER."/class.HTMLWriter.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_XMLWriter.php");
require_once(AJXP_BIN_FOLDER."/class.RecycleBinManager.php");

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
require_once(AJXP_BIN_FOLDER."/class.AJXP_Logger.php");
set_error_handler(array("AJXP_XMLWriter", "catchError"), E_ALL & ~E_NOTICE );
set_exception_handler(array("AJXP_XMLWriter", "catchException"));
$pServ = AJXP_PluginsService::getInstance();
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", AJXP_INSTALL_PATH."/conf");
ConfService::init("conf/conf.php");

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());
session_name("AjaXplorer");
session_start();


$optArgs = array();
$options = array();
$regex = '/^-(-?)([a-zA-z0-9_]*)=(.*)/';
foreach ($argv as $key => $argument){
	if(preg_match($regex, $argument, $matches)){
		if($matches[1] == "-"){
			$optArgs[trim($matches[2])] = SystemTextEncoding::toUTF8(trim($matches[3]));
		}else{
			$options[trim($matches[2])] = SystemTextEncoding::toUTF8(trim($matches[3]));
		}
	}
}
$optUser = $options["u"];
if(isSet($options["p"])){
	$optPass = $options["p"];
}else{
	// Consider "u" is a crypted version of u:p
	$optToken = $options["t"];
	$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
    $optUser = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($optToken."\1CDAFx¨op#"), base64_decode($optUser), MCRYPT_MODE_ECB, $iv));
}

$optAction = $options["a"];
$optRepoId = $options["r"] OR false;

if($optRepoId !== false){
	$repository = ConfService::getRepositoryById($optRepoId);
	if($repository == null){
		$repository = ConfService::getRepositoryByAlias($optRepoId);
		if($repository != null){
			$optRepoId =($repository->isWriteable()?$repository->getUniqueId():$repository->getId());
		}
	}
	ConfService::switchRootDir($optRepoId, true);
}

if(AuthService::usersEnabled())
{
	$seed = AuthService::generateSeed();
	if($seed != -1){
		$optPass = md5(md5($optPass).$seed);
	}
	$loggingResult = AuthService::logUser($optUser, $optPass, isSet($optToken), false, $seed);
	// Check that current user can access current repository, try to switch otherwise.
	$loggedUser = AuthService::getLoggedUser();
	if($loggedUser != null)
	{
		$currentRepoId = ConfService::getCurrentRootDirIndex();
		$lastRepoId  = $loggedUser->getArrayPref("history", "last_repository");
		$defaultRepoId = AuthService::getDefaultRootId();
		if($defaultRepoId == -1){
			AuthService::disconnect();
			$loggingResult = -3;
		}else {
			if($lastRepoId != "" && $lastRepoId!=$currentRepoId && $optRepoId===false && $loggedUser->canSwitchTo($lastRepoId)){
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
	if(isset($loggingResult) && $loggingResult != 1)
	{
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::loggingResult($loggingResult, false, false, "");
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
$mess = ConfService::getMessages();

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
$xmlResult = AJXP_Controller::findActionAndApply($optAction, $optArgs, array());
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
