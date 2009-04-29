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
 * Description : Script accessible only to user with the "admin" privilege.
 */

require_once("server/classes/class.Utils.php");
require_once("server/classes/class.SystemTextEncoding.php");
require_once("server/classes/class.Repository.php");
require_once("server/classes/class.ConfService.php");
require_once("server/classes/class.AuthService.php");
require_once("server/classes/class.HTMLWriter.php");
require_once("server/classes/class.AJXP_XMLWriter.php");


header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
require_once("server/classes/class.AJXP_Logger.php");
ConfService::init("server/conf/conf.php");
$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());
session_start();
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
if(!$loggedUser->isAdmin())
{
	print("Forbidden");
	exit(0);	
}

$action = "";
if(isSet($_GET["get_action"])) $action = $_GET["get_action"];

switch ($action)
{
	
	case "change_admin_right" :
		$userId = $_GET["user_id"];
		$confStorage = ConfService::getConfStorageImpl();		
		$user = $confStorage->createUserObject($userId);
		$user->setAdmin(($_GET["right_value"]=="1"?true:false));
		$user->save();
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("Changed admin right for user ".$_GET["user_id"], null);
		AJXP_XMLWriter::close();
		exit(1);
	break;

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
		$confStorage = ConfService::getConfStorageImpl();		
		$user = $confStorage->createUserObject($_GET["user_id"]);
		$user->setRight($_GET["repository_id"], $_GET["right"]);
		$user->save();
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("Changed right for user ".$_GET["user_id"], null);
		print("<update_checkboxes user_id=\"".$_GET["user_id"]."\" repository_id=\"".$_GET["repository_id"]."\" read=\"".$user->canRead($_GET["repository_id"])."\" write=\"".$user->canWrite($_GET["repository_id"])."\"/>");
		AJXP_XMLWriter::close();
		exit(1);
	break;

	case "save_repository_user_params" : 
		$userId = $_GET["user_id"];
		if($userId == $loggedUser->getId()){
			$user = $loggedUser;
		}else{
			$confStorage = ConfService::getConfStorageImpl();		
			$user = $confStorage->createUserObject($userId);
		}
		$wallet = $user->getPref("AJXP_WALLET");
		if(!is_array($wallet)) $wallet = array();
		if(!array_key_exists($_GET["repository_id"], $wallet)){
			$wallet[$_GET["repository_id"]] = array();
		}
		foreach ($_GET as $key=>$value){
			if(strstr($key, "DRIVER_OPTION_") !== false){
				$key = substr($key, strlen("DRIVER_OPTION_"));
				$value = trim($value);
                if (substr($key, -2) == "##")
                {   // Need to cypher the value here
                    $key = substr($key, 0, -2);                   
                    if (function_exists('mcrypt_encrypt'))
                    {
                        $users = AuthService::loadLocalUsersList();
                        // The initialisation vector is only required to avoid a warning, as ECB ignore IV
                        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
                        // We encode as base64 so if we need to store the result in a database, it can be stored in text column
                        $value = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $users[$userId], $value, MCRYPT_MODE_ECB, $iv));
                    }
                }				
				$wallet[$_GET["repository_id"]][$key] = $value;
			}
		}
		$user->setPref("AJXP_WALLET", $wallet);
		$user->save();
		
		if($loggedUser->getId() == $user->getId()){
			AuthService::updateUser($user);
		}
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("Saved data for user ".$_GET["user_id"], null);
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
		$forbidden = array("guest", "share");
		if(AuthService::userExists($_GET["new_login"]) || in_array($_GET["new_login"], $forbidden))
		{
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::sendMessage(null, "User already exists, please choose another login!");
			AJXP_XMLWriter::close();
			exit(1);									
		}
		if(get_magic_quotes_gpc()) $_GET["new_login"] = stripslashes($_GET["new_login"]);
		$_GET["new_login"] = str_replace("'", "", $_GET["new_login"]);
		
		$confStorage = ConfService::getConfStorageImpl();		
		$newUser = $confStorage->createUserObject($_GET["new_login"]);
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
		$forbidden = array("guest", "share");
		if(!isset($_GET["user_id"]) || $_GET["user_id"]=="" 
			|| in_array($_GET["user_id"], $forbidden)
			|| $loggedUser->getId() == $_GET["user_id"])
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
		$confStorage = ConfService::getConfStorageImpl();		
		$userObject = $confStorage->createUserObject("");		
		header("Content-Type:text/html;charset=UTF-8");
		foreach ($allUsers as $userId => $userObject)
		{
			if($userId == "shared") continue;
			print("<div class=\"user\" id=\"user_block_$userId\">");
			$imgSrc = "user_normal.png";
			if($userObject->isAdmin()) $imgSrc = "user_sysadmin.png";
			else if($userId == "guest") $imgSrc = "user_guest.png";
			
			print("<div class=\"user_id\" onclick=\"manager.toggleUser('".$userId."');\"><img align=\"absmiddle\" src=\"".CLIENT_RESOURCES_FOLDER."/images/crystal/actions/32/$imgSrc\" width=\"32\" height=\"32\">User <b>$userId</b></div>");
			print("<div class=\"user_data\" id=\"user_data_".$userId."\" style=\"display: none;\">");
			print("<fieldset id=\"repo_rights_$userId\"><legend>Repositories Rights</legend><table width=\"100%\" class=\"repository\">");
			foreach (ConfService::getRootDirsList() as $rootDirId => $rootDirObject)
			{
				print("<tr><td style=\"width: 45%;\">. ".$rootDirObject->getDisplay()." : </td>");
				print("<td style=\"width: 55%;\" driver_name=\"".$rootDirObject->getAccessType()."\" repository_id=\"$rootDirId\">");
				$disabledString = "";
				//if($userObject->isAdmin()) $disabledString = "disabled";
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
			if($userId !="guest" && $userId != $loggedUser->getId())
			{
				print("<fieldset><legend>Add User Admin Rights</legend>");
				print("User has admin rights : <input type=\"checkbox\" class=\"user_delete_confirm\" id=\"admin_rights_$userId\" ".($userObject->isAdmin()?"checked":"")." onclick=\"manager.changeAdminRight(this, '$userId');\">");
				print("</fieldset>");				
				print("<fieldset><legend>Delete User</legend>");
				print("To delete, check the box to confirm <input type=\"checkbox\" class=\"user_delete_confirm\" id=\"delete_confirm_$userId\"><input type=\"submit\" value=\"OK\" onclick=\"manager.deleteUser('$userId'); return false;\">");
				print("</fieldset>");		
			}
			// Add WALLET DATA
			$wallet = $userObject->getPref("AJXP_WALLET");
			if(is_array($wallet)){
				print("<wallet>");
				foreach($wallet as $repoId => $options){
					foreach ($options as $optName=>$optValue){
						print("<wallet_data repo_id=\"$repoId\" option_name=\"$optName\" option_value=\"$optValue\"/>");
					}
				}
				print("</wallet>");
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
			$value = SystemTextEncoding::magicDequote($value);
			if(strpos($key, "DRIVER_OPTION_")!== false && strpos($key, "DRIVER_OPTION_")==0){
				$options[substr($key, strlen("DRIVER_OPTION_"))] = $value;
				unset($repDef[$key]);
			}else{
				$repDef[$key] = $value;				
			}
		}
		if(count($options)){
			$repDef["DRIVER_OPTIONS"] = $options;
		}
		// NOW SAVE THIS REPOSITORY!
		$newRep = ConfService::createRepositoryFromArray(0, $repDef);
		if(is_file(INSTALL_PATH."/server/tests/plugins/test.ajxp_".$newRep->getAccessType().".php"))
		{
		    chdir(INSTALL_PATH."/server/tests/plugins");
			include(INSTALL_PATH."/server/tests/plugins/test.ajxp_".$newRep->getAccessType().".php");
			$className = "ajxp_".$newRep->getAccessType();
			$class = new $className();
			$result = $class->doRepositoryTest($newRep);
			if(!$result){
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, $class->failedInfo);
				AJXP_XMLWriter::close();
				exit(1);
			}
		}
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
	
	case "edit_repository" : 
		$repId = $_GET["repository_id"];
		$repo = ConfService::getRepositoryById($repId);
		$res = 0;
		if(isSet($_GET["newLabel"])){
			$repo->setDisplay($_GET["newLabel"]);
			$res = ConfService::replaceRepository($repId, $repo);
		}else{
			foreach ($_GET as $key => $value){
				$value = SystemTextEncoding::magicDequote($value);
				if(strpos($key, "DRIVER_OPTION_")!== false && strpos($key, "DRIVER_OPTION_")==0){
					 $repo->addOption(substr($key, strlen("DRIVER_OPTION_")), $value);
				}
			}
			if(is_file(INSTALL_PATH."/server/tests/plugins/test.ajxp_".$repo->getAccessType().".php")){
			    chdir(INSTALL_PATH."/server/tests/plugins");
				include(INSTALL_PATH."/server/tests/plugins/test.ajxp_".$repo->getAccessType().".php");
				$className = "ajxp_".$repo->getAccessType();
				$class = new $className();
				$result = $class->doRepositoryTest($repo);
				if(!$result){
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $class->failedInfo);
					AJXP_XMLWriter::close();
					exit(1);
				}
			}
			
			ConfService::replaceRepository($repId, $repo);
		}
		AJXP_XMLWriter::header();
		if($res == -1){
			AJXP_XMLWriter::sendMessage(null, "Error while trying to edit repository");
		}else{
			AJXP_XMLWriter::sendMessage("Successfully edited repository", null);
		}
		AJXP_XMLWriter::close();		
		exit(1);
	
	case "delete_repository" :
		$repId = $_GET["repository_id"];
		//if(get_magic_quotes_gpc()) $repLabel = stripslashes($repLabel);
		$res = ConfService::deleteRepository($repId);
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
		$logger = AJXP_Logger::getInstance();
		$logger->xmlListLogFiles();
		AJXP_XMLWriter::close("log_files");		
		exit(1);
	break;
	
	case "read_log" : 
		$logDate = (isSet($_GET["date"])?$_GET["date"]:date('m-d-y'));
		
		AJXP_XMLWriter::header("logs");
		$logger = AJXP_Logger::getInstance();
		$logger->xmlLogs($logDate);
		AJXP_XMLWriter::close("logs");
		exit(1);
	break;
	
	case "install_log" : 
		$log = array();
		
		$outputArray = array();
		$testedParams = array();
		Utils::runTests($outputArray, $testedParams);		
		Utils::testResultsToFile($outputArray, $testedParams);
		
		header("Content-Type:text/html;charset=UTF-8");	
		if(is_file(TESTS_RESULT_FILE)){
			include_once(TESTS_RESULT_FILE);			
			foreach ($diagResults as $id => $value){
				print "<div><span>$id</span> : $value</div>";
			}
		}else{
			print "Cannot find test result file. Please run the tests first!";
		}
		exit(1);
		/*
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
		$log["Zlib Extension"] = (function_exists('gzopen')?"1":"0");		
		$log["Gzip configuration"] = GZIP_DOWNLOAD;		
		$log["Magic Quotes Gpc"] = get_magic_quotes_gpc();
		$log["Client"] = $_SERVER['HTTP_USER_AGENT'];	
		*/
	break;
}

include(CLIENT_RESOURCES_FOLDER."/html/admin.html");
session_write_close();
?>
