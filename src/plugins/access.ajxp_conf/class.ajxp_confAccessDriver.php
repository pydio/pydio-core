<?php
/**
 * @package info.ajaxplorer.plugins
 * 
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
 * Description : The "admin" driver, to make use of the GUI to manage AjaXplorer settings.
 */
class ajxp_confAccessDriver extends AbstractAccessDriver 
{	
	function ajxp_confAccessDriver($driverName){
		parent::AbstractAccessDriver($driverName, INSTALL_PATH."/plugins/access.ajxp_conf/ajxp_confActions.xml", null);
	}
	
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		$loggedUser = AuthService::getLoggedUser();
		if(ENABLE_USERS && !$loggedUser->isAdmin()) return ;
		
		if($action == "edit"){
			if(isSet($httpVars["sub_action"])){
				$action = $httpVars["sub_action"];
			}
		}
		
		switch($action)
		{			
			//------------------------------------
			//	BASIC LISTING
			//------------------------------------
			case "ls":
				$rootNodes = array(
					"users" => array("LABEL" => "Users", "ICON" => "yast_kuser.png"),
					"repositories" => array("LABEL" => "Repositories", "ICON" => "folder_red.png"),
					"logs" => array("LABEL" => "Logs", "ICON" => "toggle_log.png"),
					"diagnostic" => array("LABEL" => "Diagnostic", "ICON" => "susehelpcenter.png")
				);
				$dir = (isset($httpVars["dir"])?$httpVars["dir"]:"");
				$splits = split("/", $dir);
				if(count($splits)){
					if($splits[0] == "") array_shift($splits);
					$strippedDir = strtolower(urldecode($splits[0]));
				}				
				if(array_key_exists($strippedDir, $rootNodes)){
					AJXP_XMLWriter::header();
					if($strippedDir == "users"){
						$this->listUsers();
					}else if($strippedDir == "repositories"){
						$this->listRepositories();
					}else if($strippedDir == "logs"){
						$this->listLogFiles($dir);
					}else if($strippedDir == "diagnostic"){
						$this->printDiagnostic();
					}
					AJXP_XMLWriter::close();
					exit(1);
				}else{
					AJXP_XMLWriter::header();
					print('<columns switchGridMode="filelist"><column messageString="Configuration Data" attributeName="ajxp_label" sortType="String"/></columns>');
					foreach ($rootNodes as $key => $data){
						$src = '';
						if($key == "logs"){
							$src = 'src="content.php?dir='.$key.'"';
						}
						print '<tree text="'.$data["LABEL"].'" icon="'.$data["ICON"].'" filename="/'.$key.'" parentname="/" '.$src.' />';
					}
					AJXP_XMLWriter::close();
					exit(1);
				}
			break;
			
			case "edit_user" : 
				$confStorage = ConfService::getConfStorageImpl();	
				$userId = $httpVars["user_id"];	
				$userObject = $confStorage->createUserObject($userId);		
				//print_r($userObject);
				AJXP_XMLWriter::header("admin_data");
				AJXP_XMLWriter::sendUserData($userObject, true);
				
				// Add WALLET DATA : DEFINITIONS AND VALUES
				print("<drivers>");
				print(ConfService::availableDriversToXML("user_param"));
				print("</drivers>");				
				$wallet = $userObject->getPref("AJXP_WALLET");
				if(is_array($wallet) && count($wallet)>0){
					print("<user_wallet>");
					foreach($wallet as $repoId => $options){
						foreach ($options as $optName=>$optValue){
							print("<wallet_data repo_id=\"$repoId\" option_name=\"$optName\" option_value=\"$optValue\"/>");
						}
					}
					print("</user_wallet>");
				}
				$editPass = ($userId!="guest"?"1":"0");
				$authDriver = ConfService::getAuthDriverImpl();
				if(!$authDriver->passwordsEditable()){
					$editPass = "0";
				}
				print("<edit_options edit_pass=\"".$editPass."\" edit_admin_right=\"".(($userId!="guest"&&$userId!=$loggedUser->getId())?"1":"0")."\" edit_delete=\"".(($userId!="guest"&&$userId!=$loggedUser->getId()&&$authDriver->usersEditable())?"1":"0")."\"/>");
				AJXP_XMLWriter::close("admin_data");
				exit(1) ;
			break;
			
			case "create_user" :
				if(!isset($_GET["new_user_login"]) || $_GET["new_user_login"] == "" ||!isset($_GET["new_user_pwd"]) || $_GET["new_user_pwd"] == "")
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, "Wrong Arguments!");
					AJXP_XMLWriter::close();
					exit(1);						
				}
				$forbidden = array("guest", "share");
				if(AuthService::userExists($_GET["new_user_login"]) || in_array($_GET["new_user_login"], $forbidden))
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, "User already exists, please choose another login!");
					AJXP_XMLWriter::close();
					exit(1);									
				}
				if(get_magic_quotes_gpc()) $_GET["new_user_login"] = stripslashes($_GET["new_user_login"]);
				$_GET["new_user_login"] = str_replace("'", "", $_GET["new_user_login"]);
				
				$confStorage = ConfService::getConfStorageImpl();		
				$newUser = $confStorage->createUserObject($_GET["new_user_login"]);
				$newUser->save();
				AuthService::createUser($_GET["new_user_login"], $_GET["new_user_pwd"]);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage("User created successfully", null);
				AJXP_XMLWriter::reloadFileList($_GET["new_user_login"]);
				AJXP_XMLWriter::close();
				exit(1);										
			break;
								
			case "change_admin_right" :
				$userId = $_GET["user_id"];
				$confStorage = ConfService::getConfStorageImpl();		
				$user = $confStorage->createUserObject($userId);
				$user->setAdmin(($_GET["right_value"]=="1"?true:false));
				$user->save();
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage("Changed admin right for user ".$_GET["user_id"], null);
				AJXP_XMLWriter::reloadFileList(false);
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
				$loggedUser = AuthService::getLoggedUser();
				if($loggedUser->getId() == $user->getId()){
					AuthService::updateUser($user);
				}
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage("Changed right for user ".$_GET["user_id"], null);
				print("<update_checkboxes user_id=\"".$_GET["user_id"]."\" repository_id=\"".$_GET["repository_id"]."\" read=\"".$user->canRead($_GET["repository_id"])."\" write=\"".$user->canWrite($_GET["repository_id"])."\"/>");
				AJXP_XMLWriter::reloadRepositoryList();
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
				$repoID = $_GET["repository_id"];
				if(!array_key_exists($repoID, $wallet)){
					$wallet[$repoID] = array();
				}
				$options = $wallet[$repoID];
				$this->parseParameters($_GET, $options, $userId);
				$wallet[$repoID] = $options;
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
	
			case  "get_drivers_definition":
				
				AJXP_XMLWriter::header("drivers");
				print(ConfService::availableDriversToXML("param"));
				AJXP_XMLWriter::close("drivers");
				exit(1);
				
			break;
			
			case "create_repository" : 
			
				$options = array();
				$repDef = $_GET;
				unset($repDef["get_action"]);
				$this->parseParameters($repDef, $options);
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
					AJXP_XMLWriter::reloadFileList($newRep->getDisplay());
					AJXP_XMLWriter::reloadRepositoryList();
				}
				AJXP_XMLWriter::close();
				exit(1);
				
			
			break;
			
			case "edit_repository" : 
				$repId = $httpVars["repository_id"];
				$repList = ConfService::getRootDirsList();
				//print_r($repList);
				AJXP_XMLWriter::header("admin_data");		
				if(!isSet($repList[$repId])){
					AJXP_XMLWriter::close("admin_data");
					exit(1);
				}
				$repository = $repList[$repId];				
				$nested = array();
				print("<repository index=\"$repId\"");
				foreach ($repository as $name => $option){
					if(!is_array($option)){					
						if(is_bool($option)){
							$option = ($option?"true":"false");
						}
						print(" $name=\"".SystemTextEncoding::toUTF8(Utils::xmlEntities($option))."\" ");
					}else if(is_array($option)){
						$nested[] = $option;
					}
				}
				if(count($nested)){
					print(">");
					foreach ($nested as $option){
						foreach ($option as $key => $optValue){
							if(is_bool($optValue)){
								$optValue = ($optValue?"true":"false");
							}
							print("<param name=\"$key\" value=\"$optValue\"/>");
						}
					}
					print("</repository>");
				}else{
					print("/>");
				}
				print(ConfService::availableDriversToXML("param", $repository->accessType));
				AJXP_XMLWriter::close("admin_data");
				exit(1);
			break;
			
			case "edit_repository_label" : 
			case "edit_repository_data" : 
				$repId = $_GET["repository_id"];
				$repo = ConfService::getRepositoryById($repId);
				$res = 0;
				if(isSet($_GET["newLabel"])){
					$repo->setDisplay(SystemTextEncoding::fromPostedFileName($_GET["newLabel"]));
					$res = ConfService::replaceRepository($repId, $repo);
				}else{
					$options = array();
					$this->parseParameters($_GET, $options);
					if(count($options)){
						foreach ($options as $key=>$value) $repo->addOption($key, $value);
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
					AJXP_XMLWriter::reloadFileList((isSet($_GET["newLabel"])?SystemTextEncoding::fromPostedFileName($_GET["newLabel"]):false));
					AJXP_XMLWriter::reloadRepositoryList();
				}
				AJXP_XMLWriter::close();		
				exit(1);
			
			case "delete" :
				if(isSet($httpVars["repository_id"])){
					$repId = $httpVars["repository_id"];
					//if(get_magic_quotes_gpc()) $repLabel = stripslashes($repLabel);
					$res = ConfService::deleteRepository($repId);
					AJXP_XMLWriter::header();
					if($res == -1){
						AJXP_XMLWriter::sendMessage(null, "The conf directory is not writeable");
					}else{
						AJXP_XMLWriter::sendMessage("Successfully deleted repository", null);						
						AJXP_XMLWriter::reloadFileList(false);
						AJXP_XMLWriter::reloadRepositoryList();
					}
					AJXP_XMLWriter::close();		
					exit(1);
				}else{
					$forbidden = array("guest", "share");
					if(!isset($httpVars["user_id"]) || $httpVars["user_id"]=="" 
						|| in_array($_GET["user_id"], $forbidden)
						|| $loggedUser->getId() == $httpVars["user_id"])
					{
						AJXP_XMLWriter::header();
						AJXP_XMLWriter::sendMessage(null, "Wrong Arguments!");
						AJXP_XMLWriter::close();
						exit(1);									
					}
					$res = AuthService::deleteUser($httpVars["user_id"]);
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage("User successfully erased", null);
					AJXP_XMLWriter::reloadFileList($httpVars["user_id"]);
					AJXP_XMLWriter::close();
					exit(1);
					
				}
			break;
			
			
			default:
			break;
		}

		return;
	}
	
	
	function listUsers(){
		print '<columns switchGridMode="filelist"><column messageString="User Name" attributeName="ajxp_label" sortType="String"/><column messageString="Is Admin" attributeName="isAdmin" sortType="String"/></columns>';		
		if(!ENABLE_USERS) return ;
		$users = AuthService::listUsers();
		$loggedUser = AuthService::getLoggedUser();		
		foreach ($users as $userObject){
			$isAdmin = $userObject->isAdmin();
			$userId = $userObject->getId();
			$icon = "user".($userId=="guest"?"_guest":($isAdmin?"_admin":""));
			print '<tree 
				text="'.$userId.'"
				isAdmin="'.($isAdmin?"True":"False").'" 
				icon="'.$icon.'.png" 
				openicon="'.$icon.'.png" 
				filename="/users/'.$userId.'" 
				parentname="/users" 
				is_file="1" 
				ajxp_mime="user'.(($userId!="guest"&&$userId!=$loggedUser->getId())?"_editable":"").'"
				/>';
		}
	}
	
	function listRepositories(){
		print '<columns switchGridMode="filelist"><column messageString="Repository Label" attributeName="ajxp_label" sortType="String"/><column messageString="Access Type" attributeName="accessType" sortType="String"/></columns>';		
		$repos = ConfService::getRepositoriesList();
		foreach ($repos as $repoIndex => $repoObject){
			if($repoObject->getAccessType() == "ajxp_conf") continue;
			print '<tree 
				text="'.SystemTextEncoding::toUTF8($repoObject->getDisplay()).'" 
				is_file="1" 
				repository_id="'.$repoIndex.'" 
				accessType="'.$repoObject->getAccessType().'" 
				icon="folder_red.png" 
				openicon="folder_red.png" 
				filename="/users/'.SystemTextEncoding::toUTF8($repoObject->getDisplay()).'" 
				parentname="/users" 
				src="content.php?dir=%2Fusers%2F'.SystemTextEncoding::toUTF8($repoObject->getDisplay()).'" 
				ajxp_mime="repository'.($repoObject->isWriteable()?"_editable":"").'"
				/>';
		}
		
	}
	
	function listLogFiles($dir){	
		$logger = AJXP_Logger::getInstance();
		$parts = split("/", $dir);
		if(count($parts)>4){
			print '<columns switchGridMode="grid">
				<column messageString="Date" attributeName="date" sortType="Date" width="10%"/>
				<column messageString="I.P." attributeName="ip" sortType="String"/>
				<column messageString="Level" attributeName="level" sortType="String"/>
				<column messageString="User" attributeName="user" sortType="String"/>
				<column messageString="Action" attributeName="action" sortType="String"/>
				<column messageString="Params" attributeName="params" sortType="String"/>
			</columns>';				
			$date = $parts[count($parts)-1];
			$logger->xmlLogs($date, "tree");
		}else{
			print '<columns switchGridMode="filelist"><column messageString="File Date" attributeName="ajxp_label" sortType="String"/></columns>';				
			$logger->xmlListLogFiles("tree", (count($parts)>2?$parts[2]:null), (count($parts)>3?$parts[3]:null));
		}
	}
	
	function printDiagnostic(){
		$outputArray = array();
		$testedParams = array();
		$passed = Utils::runTests($outputArray, $testedParams);
		Utils::testResultsToFile($outputArray, $testedParams);		
		print '<columns switchGridMode="filelist"><column messageString="Test Name" attributeName="ajxp_label" sortType="String"/><column messageString="Test Data" attributeName="data" sortType="String"/></columns>';		
		if(is_file(TESTS_RESULT_FILE)){
			include_once(TESTS_RESULT_FILE);			
			foreach ($diagResults as $id => $value){
				print "<tree icon=\"susehelpcenter.png\" is_file=\"1\" filename=\"$id\" text=\"$id\" data=\"$value\" ajxp_mime=\"testResult\"/>";
			}
		}		
	}
	
	function parseParameters(&$repDef, &$options, $userId = null){
		
		foreach ($repDef as $key => $value)
		{
			$value = SystemTextEncoding::magicDequote($value);
			if(strpos($key, "DRIVER_OPTION_")!== false && strpos($key, "DRIVER_OPTION_")==0 && strpos($key, "ajxptype") === false){
				if(isSet($repDef[$key."_ajxptype"])){
					$type = $repDef[$key."_ajxptype"];
					if($type == "boolean"){
						$value = ($value == "true"?true:false);
					}else if($type == "integer"){
						$value = intval($value);
					}else if($type == "password" && $userId!=null){
	                    if (function_exists('mcrypt_encrypt'))
	                    {
	                        // The initialisation vector is only required to avoid a warning, as ECB ignore IV
	                        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
	                        // We encode as base64 so if we need to store the result in a database, it can be stored in text column
	                        $value = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($userId."\1CDAFx$¨op#"), $value, MCRYPT_MODE_ECB, $iv));
	                    }						
					}
					unset($repDef[$key."_ajxptype"]);
				}
				$options[substr($key, strlen("DRIVER_OPTION_"))] = $value;
				unset($repDef[$key]);
			}else{
				if($key == "DISPLAY"){
					$value = SystemTextEncoding::fromPostedFileName($value);
				}
				$repDef[$key] = $value;		
			}
		}		
	}
	    
}

?>
