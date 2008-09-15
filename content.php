<?php
//---------------------------------------------------------------------------------------------------
//
//	AjaXplorer v2.3
//
//	Charles du Jeu
//	http://sourceforge.net/projects/ajaxplorer
//  http://www.almasound.com
//
//---------------------------------------------------------------------------------------------------

//require_once("classes/class.BookmarksManager.php");
require_once("server/classes/class.Utils.php");
require_once("server/classes/class.SystemTextEncoding.php");
require_once("server/classes/class.Repository.php");
require_once("server/classes/class.AJXP_Exception.php");
require_once("server/classes/class.AbstractDriver.php");
require_once("server/classes/class.AJXP_ClientDriver.php");
require_once("server/classes/class.ConfService.php");
require_once("server/classes/class.AuthService.php");
require_once("server/classes/class.UserSelection.php");
require_once("server/classes/class.HTMLWriter.php");
require_once("server/classes/class.AJXP_XMLWriter.php");
require_once("server/classes/class.AJXP_User.php");
require_once("server/classes/class.RecycleBinManager.php");
if(isSet($_GET["ajxp_sessid"]))
{
	$_COOKIE["PHPSESSID"] = $_GET["ajxp_sessid"];
}
session_start();
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
ConfService::init("server/conf/conf.php");
require_once("server/classes/class.AJXP_Logger.php");
$baspage=ConfService::getConf("BOTTOM_PAGE");

if(AuthService::usersEnabled())
{
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "logout")
	{
		AuthService::disconnect();
		$loggingResult = 2;
	}	//AuthService::disconnect();
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "login")
	{
		$userId = (isSet($_GET["userid"])?$_GET["userid"]:null);
		$userPass = (isSet($_GET["password"])?$_GET["password"]:null);
		$loggingResult = AuthService::logUser($userId, $userPass);
	}
	else 
	{
		AuthService::logUser(null, null);	
	}
	// Check that current user can access current repository, try to switch otherwise.
	$loggedUser = AuthService::getLoggedUser();
	if($loggedUser != null)
	{
		if(!$loggedUser->canRead(ConfService::getCurrentRootDirIndex()) && AuthService::getDefaultRootId() != ConfService::getCurrentRootDirIndex())
		{
			ConfService::switchRootDir(AuthService::getDefaultRootId());
		}
	}
	if($loggedUser == null)
	{
		$requireAuth = true;
	}
	if(isset($loggingResult) || (isSet($_GET["get_action"]) && $_GET["get_action"] == "logged_user"))
	{
		AJXP_XMLWriter::header();
		if(isSet($loggingResult)) AJXP_XMLWriter::loggingResult($loggingResult);
		AJXP_XMLWriter::sendUserData();
		AJXP_XMLWriter::close();
		exit(1);
	}
}

$loggedUser = AuthService::getLoggedUser();
if($loggedUser != null)
{
	if($loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
}
$mess = ConfService::getMessages();

foreach($_GET as $getName=>$getValue)
{
	$$getName = Utils::securePath($getValue);
}
foreach($_POST as $getName=>$getValue)
{
	$$getName = Utils::securePath($getValue);
}

$selection = new UserSelection();
$selection->initFromHttpVars();

if(isSet($action) || isSet($get_action)) $action = (isset($get_action)?$get_action:$action);
else $action = "";

if(isSet($dir) && $action != "upload") $dir = SystemTextEncoding::fromUTF8($dir);
if(isSet($dest)) $dest = SystemTextEncoding::fromUTF8($dest);

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

// FIRST INIT STD DRIVER
$ajxpDriver = new AJXP_ClientDriver(ConfService::getRepository());
if($ajxpDriver->hasAction($action)){
	$xmlBuffer = $ajxpDriver->applyAction($action, array_merge($_GET, $_POST), $_FILES);
	if($xmlBuffer != ""){
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::write($xmlBuffer, true);
		AJXP_XMLWriter::close();
		exit(1);
	}
}

// INIT DRIVER
$Driver = ConfService::getRepositoryDriver();
if($Driver == null || !is_a($Driver, "AbstractDriver")){
	AJXP_XMLWriter::header();
	AJXP_XMLWriter::sendMessage(null, "Cannot find driver!");
	AJXP_XMLWriter::close();
	exit(1);
}
if($Driver->hasAction($action)){
	// CHECK RIGHTS
	if(AuthService::usersEnabled()){
		$loggedUser = AuthService::getLoggedUser();
		if( $Driver->actionNeedsRight($action, "r") && 
			($loggedUser == null || !$loggedUser->canRead(ConfService::getCurrentRootDirIndex().""))){
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, $mess[208]);
				AJXP_XMLWriter::requireAuth();
				AJXP_XMLWriter::close();
				exit(1);
			}
		if( $Driver->actionNeedsRight($action, "w") && 
			($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex().""))){
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, $mess[207]);
				AJXP_XMLWriter::requireAuth();
				AJXP_XMLWriter::close();
				exit(1);
			}
	}
	
	$xmlResult = $Driver->applyAction($action, array_merge($_GET, $_POST), $_FILES);
	if($xmlResult != ""){
		AJXP_XMLWriter::header();
		print($xmlResult);
		AJXP_XMLWriter::close();
		exit(1);
	}
}


AJXP_XMLWriter::header();
if(isset($logMessage) || isset($errorMessage))
{
	AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null));
}
if(isset($requireAuth))
{
	AJXP_XMLWriter::requireAuth();
}
if(isset($reload_current_node) && $reload_current_node == "true")
{
	AJXP_XMLWriter::reloadCurrentNode();
}
if(isset($reload_dest_node) && $reload_dest_node != "")
{
	AJXP_XMLWriter::reloadNode($reload_dest_node);
}
if(isset($reload_file_list))
{
	AJXP_XMLWriter::reloadFileList($reload_file_list);
}
AJXP_XMLWriter::close();


session_write_close();
?>
