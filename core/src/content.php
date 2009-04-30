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

require_once("server/classes/class.Utils.php");
require_once("server/classes/class.SystemTextEncoding.php");
require_once("server/classes/class.Repository.php");
require_once("server/classes/class.AJXP_Exception.php");
require_once("server/classes/class.AbstractDriver.php");
require_once("server/classes/class.AbstractAccessDriver.php");
require_once("server/classes/class.AJXP_ClientDriver.php");
require_once("server/classes/class.ConfService.php");
require_once("server/classes/class.AuthService.php");
require_once("server/classes/class.UserSelection.php");
require_once("server/classes/class.HTMLWriter.php");
require_once("server/classes/class.AJXP_XMLWriter.php");
require_once("server/classes/class.RecycleBinManager.php");
if(isSet($_GET["ajxp_sessid"]))
{
	$_COOKIE["PHPSESSID"] = $_GET["ajxp_sessid"];
}
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
require_once("server/classes/class.AJXP_Logger.php");
ConfService::init("server/conf/conf.php");
$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());
session_start();

if(AuthService::usersEnabled())
{
	$rememberLogin = "";
	$rememberPass = "";
	if(isset($_GET["get_action"]) && $_GET["get_action"] == "get_seed"){
		header("Content-type:text/plain; charset:UTF-8");
		print AuthService::generateSeed();				
		exit(0);
	}	
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "logout")
	{
		AuthService::disconnect();
		$loggingResult = 2;
	}	//AuthService::disconnect();
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "login")
	{
		$userId = (isSet($_GET["userid"])?$_GET["userid"]:null);
		$userPass = (isSet($_GET["password"])?$_GET["password"]:null);
		$rememberMe = ((isSet($_GET["remember_me"]) && $_GET["remember_me"] == "on")?true:false);
		$cookieLogin = (isSet($_GET["cookie_login"])?true:false); 
		$loggingResult = AuthService::logUser($userId, $userPass, false, $cookieLogin, $_GET["login_seed"]);
		if($rememberMe && $loggingResult == 1){
			$rememberLogin = $userId;
			$loggedUser = AuthService::getLoggedUser();
			$rememberPass =  $loggedUser->getCookieString();
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
		if(!$loggedUser->canRead(ConfService::getCurrentRootDirIndex()))
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
		if(isSet($loggingResult)) AJXP_XMLWriter::loggingResult($loggingResult, $rememberLogin, $rememberPass);
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

// Look for the action in the "fixed" drivers : AjxpClient, Auth & Conf
$ajxpDriver = new AJXP_ClientDriver(ConfService::getRepository());
$ajxpDriver->applyIfExistsAndExit($action, array_merge($_GET, $_POST), $_FILES);

$authDriver = ConfService::getAuthDriverImpl();
$authDriver->applyIfExistsAndExit($action, array_merge($_GET, $_POST), $_FILES);

$confDriver = ConfService::getConfStorageImpl();
$confDriver->applyIfExistsAndExit($action, array_merge($_GET, $_POST), $_FILES);


// TRYING TO GET A DRIVER WHEN NO USER IS LOGGED
if(AuthService::usersEnabled() && AuthService::getLoggedUser()==null && !ALLOW_GUEST_BROWSING){
	AJXP_XMLWriter::header();
	AJXP_XMLWriter::requireAuth(true);
	AJXP_XMLWriter::close();
	exit(1);
}

// INIT DRIVER
$Driver = ConfService::getRepositoryDriver();
if($Driver == null || !is_a($Driver, "AbstractDriver")){
	AJXP_XMLWriter::header();
	if(is_a($Driver, "AJXP_Exception")){
		AJXP_XMLWriter::sendMessage(null, "Cannot initialize driver : ".$Driver->getMessage());
	}else{
		AJXP_XMLWriter::sendMessage(null, "Cannot find driver!");
	}
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
