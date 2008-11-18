<?php
//---------------------------------------------------------------------------------------------------
//
//	AjaXplorer v2.2
//
//	Charles du Jeu
//	http://sourceforge.net/projects/ajaxplorer
//  http://www.almasound.com
//
//---------------------------------------------------------------------------------------------------

require_once("server/classes/class.Utils.php");
require_once("server/classes/class.Repository.php");
require_once("server/classes/class.ConfService.php");
require_once("server/classes/class.AuthService.php");
require_once("server/classes/class.HTMLWriter.php");
require_once("server/classes/class.AJXP_XMLWriter.php");
require_once("server/classes/class.AJXP_User.php");


header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
session_start();
ConfService::init("server/conf/conf.php");
require_once("server/classes/class.AJXP_Logger.php");
if(!AuthService::usersEnabled())
{
	print("Forbidden");
	exit(0);
}
if(AuthService::getLoggedUser() == null)
{
	print("Forbidden");
	exit(0);
}
$loggedUser = AuthService::getLoggedUser();
if($loggedUser->getId() != "admin")
{
	print("Forbidden");
	exit(0);	
}

$action = "";
if(isSet($_GET["get_action"])) $action = $_GET["get_action"];

switch ($action)
{
	
	case "update_user_right" :
		if(!isSet($_GET["user_id"]) 
			|| !isSet($_GET["repository_id"]) 
			|| !isSet($_GET["right"])
			|| !AuthService::userExists($_GET["user_id"]))
		{
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::sendMessage(null, "Wrong arguments");
			print("<update_checkboxes user_id=\"".$_GET["user_id"]."\" repository_id=\"".$_GET["repository_id"]."\" read=\"old\" write=\"old\"/>");
			AJXP_XMLWriter::close();
			exit(1);
		}
		$user = new AJXP_User($_GET["user_id"]);
		$user->setRight($_GET["repository_id"], $_GET["right"]);
		$user->save();
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("Changed right for user ".$_GET["user_id"], null);
		print("<update_checkboxes user_id=\"".$_GET["user_id"]."\" repository_id=\"".$_GET["repository_id"]."\" read=\"".$user->canRead($_GET["repository_id"])."\" write=\"".$user->canWrite($_GET["repository_id"])."\"/>");
		AJXP_XMLWriter::close();
		exit(1);
	break;

	case "update_user_pwd" : 
		if(!isSet($_GET["user_id"]) || !isSet($_GET["user_pwd"]) || !AuthService::userExists($_GET["user_id"]) || trim($_GET["user_pwd"]) == "")
		{
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::sendMessage(null, "Wrong Arguments!");
			AJXP_XMLWriter::close();
			exit(1);			
		}
		$res = AuthService::updatePassword($_GET["user_id"], $_GET["user_pwd"]);
		AJXP_XMLWriter::header();
		if($res === true)
		{
			AJXP_XMLWriter::sendMessage("Password changed successfully for user ".$_GET["user_id"], null);
		}
		else 
		{
			AJXP_XMLWriter::sendMessage(null, "Cannot update password : $res");
		}
		AJXP_XMLWriter::close();
		exit(1);						
	break;
	
	case "create_user" :
		if(!isset($_GET["new_login"]) || $_GET["new_login"] == "" ||!isset($_GET["new_pwd"]) || $_GET["new_pwd"] == "")
		{
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::sendMessage(null, "Wrong Arguments!");
			AJXP_XMLWriter::close();
			exit(1);						
		}
		$forbidden = array("guest", "admin", "share");
		if(AuthService::userExists($_GET["new_login"]) || in_array($_GET["new_login"], $forbidden))
		{
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::sendMessage(null, "User already exists, please choose another login!");
			AJXP_XMLWriter::close();
			exit(1);									
		}
		$newUser = new AJXP_User($_GET["new_login"]);
		$newUser->save();
		//AuthService::updatePassword($_GET["new_login"], $_GET["new_pwd"]);
		AuthService::createUser($_GET["new_login"], $_GET["new_pwd"]);
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("User created successfully", null);
		print("<refresh_user_list/>");
		AJXP_XMLWriter::close();
		exit(1);										
	break;
	
	case "delete_user" : 
		$forbidden = array("guest", "admin", "share");
		if(!isset($_GET["user_id"]) || $_GET["user_id"]=="" || in_array($_GET["user_id"], $forbidden))
		{
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::sendMessage(null, "Wrong Arguments!");
			AJXP_XMLWriter::close();
			exit(1);									
		}
		$res = AuthService::deleteUser($_GET["user_id"]);
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("User successfully erased", null);
		print("<refresh_user_list/>");
		AJXP_XMLWriter::close();
		exit(1);

	case "users_list":
		$allUsers = AuthService::listUsers();
		$userObject = new AJXP_User("");
		//print("<div id=\"users_list\">");
		foreach ($allUsers as $userId => $userObject)
		{
			if($userId == "shared") continue;
			print("<div class=\"user\" id=\"user_block_$userId\">");
			$imgSrc = "user_normal.png";
			if($userId == "admin") $imgSrc = "user_sysadmin.png";
			else if($userId == "guest") $imgSrc = "user_guest.png";
			
			print("<div class=\"user_id\" onclick=\"manager.toggleUser('".$userId."');\"><img align=\"absmiddle\" src=\"".CLIENT_RESOURCES_FOLDER."/images/crystal/actions/32/$imgSrc\" width=\"32\" height=\"32\">User <b>$userId</b></div>");
			print("<div class=\"user_data\" id=\"user_data_".$userId."\" style=\"display: none;\">");
			print("<fieldset><legend>Repositories Rights</legend><table class=\"repository\">");
			foreach (ConfService::getRootDirsList() as $rootDirId => $rootDirObject)
			{
				print("<tr><td style=\"width: 45%;\">. ".$rootDirObject->getDisplay()." : </td>");
				print("<td style=\"width: 55%;\">");
				$disabledString = "";
				if($userId == "admin") $disabledString = "disabled";
				print("Read <input type=\"checkbox\" id=\"chck_".$userId."_".$rootDirId."_read\" onclick=\"manager.changeUserRight(this, '$userId', '$rootDirId', 'read');\" $disabledString ".($userObject->canRead($rootDirId)?"checked":"").">");
				print("&nbsp;&nbsp;&nbsp;&nbsp;Write <input type=\"checkbox\" id=\"chck_".$userId."_".$rootDirId."_write\" onclick=\"manager.changeUserRight(this, '$userId', '$rootDirId', 'write');\" $disabledString ".($userObject->canWrite($rootDirId)?"checked":"").">");
				print("</td></tr>");
			}
			print("</table></fieldset>");
			if($userId != "guest")
			{
				print("<fieldset><legend>Modify Password</legend><table class=\"password\">");
				print("<tr><td>New Password <input type=\"password\" id=\"new_pass_$userId\"></td>");
				print("<td>Confirm Password <input type=\"password\"  id=\"new_pass_confirm_$userId\"></td>");
				print("<td><input name=\"new_pass\" type=\"submit\" value=\"OK\"  class=\"submit_button\" onclick=\"manager.changePassword('$userId'); return false;\"></td></tr>");
				print("</table></fieldset>");
			}
			if($userId != "admin" && $userId !="guest")
			{
				print("<fieldset><legend>Delete User</legend>");
				print("To delete, check the box to confirm <input type=\"checkbox\" class=\"user_delete_confirm\" id=\"delete_confirm_$userId\"><input type=\"submit\" value=\"OK\" onclick=\"manager.deleteUser('$userId'); return false;\">");
				print("</fieldset>");		
			}
			print("</div>");
			print("</div>");
			//.$userObject->getId()."</div>");
		}
		//print("</div>");	
		exit(1);
	break;
		
	case "drivers_list":
		AJXP_XMLWriter::header("ajxpdrivers");
		print(ConfService::availableDriversToXML());
		AJXP_XMLWriter::close("ajxpdrivers");
		exit(1);
	break;
	
	case "create_repository" : 
		$options = array();
		$repDef = $_GET;
		unset($repDef["get_action"]);
		foreach ($repDef as $key => $value){
			if(strpos($key, "DRIVER_OPTION_")!== false && strpos($key, "DRIVER_OPTION_")==0){
				$options[substr($key, strlen("DRIVER_OPTION_"))] = $value;
				unset($repDef[$key]);
			}
		}
		if(count($options)){
			$repDef["DRIVER_OPTIONS"] = $options;
		}
		// NOW SAVE THIS REPOSITORY!
		$newRep = ConfService::createRepositoryFromArray(0, $repDef);
		$res = ConfService::addRepository($newRep);
		AJXP_XMLWriter::header();
		if($res == -1){
			AJXP_XMLWriter::sendMessage(null, "The conf directory is not writeable");
		}else{
			AJXP_XMLWriter::sendMessage("Successfully created repository", null);
		}
		AJXP_XMLWriter::close();
		exit(1);
	break;
	
	case "repository_list" : 
		$repList = ConfService::getRootDirsList();
		AJXP_XMLWriter::header("repositories");		
		foreach ($repList as $index => $value){			
			$nested = array();
			print("<repository index=\"$index\"");
			foreach ($value as $name => $option){
				if(!is_array($option)){
					print(" $name=\"$option\" ");
				}else if(is_array($option)){
					$nested[] = $option;
				}
			}
			if(count($nested)){
				print(">");
				foreach ($nested as $option){
					foreach ($option as $key => $optValue){
						if(strpos(strtolower($key), "auth") !== false) continue;
						print("<param name=\"$key\" value=\"$optValue\"/>");
					}
				}
				print("</repository>");
			}else{
				print("/>");
			}
		}
		AJXP_XMLWriter::close("repositories");
		exit(1);
	break;
	
	case "delete_repository" :
		$res = ConfService::deleteRepository($_GET["repo_label"]);
		AJXP_XMLWriter::header();
		if($res == -1){
			AJXP_XMLWriter::sendMessage(null, "The conf directory is not writeable");
		}else{
			AJXP_XMLWriter::sendMessage("Successfully deleted repository", null);
		}
		AJXP_XMLWriter::close();		
		exit(1);
	break;
	
	case "list_logs" : 
		AJXP_XMLWriter::header("log_files");
		AJXP_Logger::xmlListLogFiles();
		AJXP_XMLWriter::close("log_files");		
		exit(1);
	break;
	
	case "read_log" : 
		$logDate = (isSet($_GET["date"])?$_GET["date"]:date('m-d-y'));
		
		AJXP_XMLWriter::header("logs");
		AJXP_Logger::xmlLogs($logDate);
		AJXP_XMLWriter::close("logs");
		exit(1);
	break;
	
	case "install_log" : 
		$log = array();
		$log["PHP Version"] = phpversion();
		$log["AJXP Version"] = AJXP_VERSION;
		$log["Server OS"] = PHP_OS;
		require_once("server/classes/class.SystemTextEncoding.php");
		$log["Server detected encoding"] = SystemTextEncoding::getEncoding();
		$log["'server' folder writeable"] = is_writable(INSTALL_PATH."/server");
		$log["'logs' folder writeable"] = is_writable(INSTALL_PATH."/server/logs");
		$log["'conf' folder writeable"] = is_writable(INSTALL_PATH."/server/conf");
		$log["Users enabled"] = ENABLE_USERS;
		$log["Guest enabled"] = ALLOW_GUEST_BROWSING;
		$log["Gzip configuration"] = GZIP_DOWNLOAD;		
		$log["Client"] = $_SERVER['HTTP_USER_AGENT'];		
		foreach ($log as $id => $value){
			print "<div><span>$id</span> : $value</div>";
		}
		exit(1);
	break;
}

include(CLIENT_RESOURCES_FOLDER."/html/admin.html");
include(ConfService::getConf("BOTTOM_PAGE"));
session_write_close();
?>