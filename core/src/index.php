<?php
require_once("server/classes/class.HTMLWriter.php");
require_once("server/classes/class.Repository.php");
require_once("server/classes/class.ConfService.php");
require_once("server/classes/class.AJXP_User.php");
require_once("server/classes/class.AuthService.php");
session_start();
ConfService::init("server/conf/conf.php");
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
}
else 
{
	$ROOT_DIR_NAME = ConfService::getCurrentRootDirDisplay();
	$ROOT_DIR_ID = ConfService::getCurrentRootDirIndex();
	$ROOT_DIRS_LIST = HTMLWriter::writeRootDirListAsJsString(ConfService::getRootDirsList());
}
$EXT_REP = "/";
if(isSet($_GET["folder"])) $EXT_REP = $_GET["folder"];
$CRT_USER = "shared_bookmarks";
if(isSet($_GET["user"])) $CRT_USER = $_GET["user"];

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


$JS_DEBUG = true;
if(ereg('Mac',$_SERVER['HTTP_USER_AGENT']) || ereg('Safari',$_SERVER['HTTP_USER_AGENT']))
{
	$JS_DEBUG = true; // DISABLE DYNAMIC LOADING ON MAC ANYWAY!
}
$mess = ConfService::getMessages();
include_once(CLIENT_RESOURCES_FOLDER."/html/gui.html");
HTMLWriter::writeI18nMessagesClass($mess);
include_once(ConfService::getConf("BOTTOM_PAGE"));
?>