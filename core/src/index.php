<?php
require_once("server/classes/class.Utils.php");
require_once("server/classes/class.HTMLWriter.php");
require_once("server/classes/class.Repository.php");
require_once("server/classes/class.ConfService.php");
require_once("server/classes/class.AJXP_User.php");
require_once("server/classes/class.AuthService.php");
session_start();
ConfService::init("server/conf/conf.php");

if(AUTH_MODE == "wordpress"){
	require_once("../../../wp-config.php");
	require_once("../../../wp-includes/capabilities.php");
	require_once("../../../wp-includes/user.php");
	require_once("../../../wp-includes/plugin.php");
	require_once("../../../wp-includes/pluggable.php");
}

require_once("server/classes/class.AJXP_Logger.php");
$USERS_ENABLED = "false";
$LOGGED_USER = "false";
$BEGIN_MESSAGE = "";
if(AuthService::usersEnabled())
{
	AuthService::preLogUser((isSet($_GET["remote_session"])?$_GET["remote_session"]:""));
	if(!is_readable(USERS_DIR)) $BEGIN_MESSAGE = "Warning, the users directory is not readable!";
	else if(!is_writeable(USERS_DIR)) $BEGIN_MESSAGE = "Warning, the users directory is not writeable!";
	if(!AuthService::userExists("admin")){
		 AuthService::createUser("admin", ADMIN_PASSWORD);
		 if(ADMIN_PASSWORD == INITIAL_ADMIN_PASSWORD)
		 {
			 $BEGIN_MESSAGE .= "Warning! User 'admin' was created with the initial common password 'admin'. \\nPlease log in as admin and change the password now!";
		 }
	}
	$USERS_ENABLED = "true";	
	if(AuthService::getLoggedUser() != null || AuthService::logUser(null, null) == 1)
	{
		$LOGGED_USER = "true";
		$loggedUser = AuthService::getLoggedUser();
		if(!$loggedUser->canRead(ConfService::getCurrentRootDirIndex()) 
				&& AuthService::getDefaultRootId() != ConfService::getCurrentRootDirIndex())
		{
			ConfService::switchRootDir(AuthService::getDefaultRootId());
		}
	}
	$ROOT_DIR_NAME = "null";
	$ROOT_DIR_ID = "null";
	$ROOT_DIRS_LIST = "null";
	$ROOT_DIRS_SETTINGS = "null";
}
else 
{
	$ROOT_DIR_NAME = ConfService::getCurrentRootDirDisplay();
	$ROOT_DIR_ID = ConfService::getCurrentRootDirIndex();
	$ROOT_DIRS_LIST = HTMLWriter::writeRootDirListAsJsString(ConfService::getRootDirsList());
	$ROOT_DIRS_SETTINGS = HTMLWriter::writeRepoSettingsAsJS(ConfService::getRootDirsList());
}
$EXT_REP = "/";
if(isSet($_GET["folder"])) $EXT_REP = urldecode($_GET["folder"]);
$CRT_USER = "shared_bookmarks";
if(isSet($_GET["user"])) $CRT_USER = $_GET["user"];

$ZIP_ENABLED = (ConfService::zipEnabled()?"true":"false");

$loggedUser = AuthService::getLoggedUser();
$DEFAULT_DISPLAY = "list";
if($loggedUser != null && $loggedUser->getId() != "guest")
{
	if($loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
	if($loggedUser->getPref("display") != "") $DEFAULT_DISPLAY = $loggedUser->getPref("display");
}
else
{	
	if(isSet($_COOKIE["AJXP_lang"])) ConfService::setLanguage($_COOKIE["AJXP_lang"]);
	if(isSet($_COOKIE["AJXP_display"]) 
	&& ($_COOKIE["AJXP_display"]=="list" || $_COOKIE["AJXP_display"]=="thumb")) $DEFAULT_DISPLAY = $_COOKIE["AJXP_display"];
}

if(isSet($_GET["compile"])){
	require_once(SERVER_RESOURCES_FOLDER."/class.AJXP_JSPacker.php");
	AJXP_JSPacker::pack();
}

$JS_DEBUG = false;
$mess = ConfService::getMessages();
if($JS_DEBUG){
	include_once(CLIENT_RESOURCES_FOLDER."/html/gui.html");
}else{
	include_once(CLIENT_RESOURCES_FOLDER."/html/gui-z.html");
}
HTMLWriter::writeI18nMessagesClass($mess);
include_once(ConfService::getConf("BOTTOM_PAGE"));
?>
