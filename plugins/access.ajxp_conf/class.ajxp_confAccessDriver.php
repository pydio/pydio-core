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
defined('AJXP_EXEC') or die( 'Access not allowed');

class ajxp_confAccessDriver extends AbstractAccessDriver 
{	
	
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		parent::accessPreprocess($action, $httpVars, $fileVars);
		$loggedUser = AuthService::getLoggedUser();
		if(ENABLE_USERS && !$loggedUser->isAdmin()) return ;
		
		if($action == "edit"){
			if(isSet($httpVars["sub_action"])){
				$action = $httpVars["sub_action"];
			}
		}
		$mess = ConfService::getMessages();
		
		switch($action)
		{			
			//------------------------------------
			//	BASIC LISTING
			//------------------------------------
			case "ls":
				$rootNodes = array(
					"repositories" => array("LABEL" => $mess["ajxp_conf.3"], "ICON" => "folder_red.png"),
					"users" => array("LABEL" => $mess["ajxp_conf.2"], "ICON" => "yast_kuser.png"),
					"roles" => array("LABEL" => $mess["ajxp_conf.69"], "ICON" => "user_group_new.png"),
					"files" => array("LABEL" => $mess["ajxp_shared.3"], "ICON" => "html.png"),
					"logs" => array("LABEL" => $mess["ajxp_conf.4"], "ICON" => "toggle_log.png"),
					"diagnostic" => array("LABEL" => $mess["ajxp_conf.5"], "ICON" => "susehelpcenter.png")
				);
				$dir = (isset($httpVars["dir"])?$httpVars["dir"]:"");
				$splits = explode("/", $dir);
				if(count($splits)){
					if($splits[0] == "") array_shift($splits);
					if(count($splits)) $strippedDir = strtolower(urldecode($splits[0]));
					else $strippedDir = "";
				}				
				if(array_key_exists($strippedDir, $rootNodes)){
					AJXP_XMLWriter::header();
					if($strippedDir == "users"){
						$this->listUsers();
					}else if($strippedDir == "roles"){
						$this->listRoles();
					}else if($strippedDir == "repositories"){
						$this->listRepositories();
					}else if($strippedDir == "logs"){
						$this->listLogFiles($dir);
					}else if($strippedDir == "diagnostic"){
						$this->printDiagnostic();
					}else if($strippedDir == "files"){
						$this->listSharedFiles();
					}
					AJXP_XMLWriter::close();
					exit(1);
				}else{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.1" attributeName="ajxp_label" sortType="String"/></columns>');
					foreach ($rootNodes as $key => $data){
						$src = '';
						if($key == "logs"){
							$src = 'src="content.php?get_action=ls&amp;dir='.$key.'"';
						}
						print '<tree text="'.$data["LABEL"].'" icon="'.$data["ICON"].'" filename="/'.$key.'" parentname="/" '.$src.' />';
					}
					AJXP_XMLWriter::close();
					exit(1);
				}
			break;
			
			case "stat" :
				
				header("Content-type:application/json");
				print '{"mode":true}';
				exit(1);
				
			break;			
			
			case "create_role":
				$roleId = $httpVars["role_id"];
				if(AuthService::getRole($roleId) !== false){
					throw new Exception($mess["ajxp_conf.65"]);
				}
				AuthService::updateRole(new AjxpRole($roleId));
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.66"], null);
				AJXP_XMLWriter::reloadDataNode("", $_GET["role_id"]);
				AJXP_XMLWriter::close();				
			break;
			
			case "edit_role" : 				
				$roleId = $httpVars["role_id"];
				$role = AuthService::getRole($roleId);
				AJXP_XMLWriter::header("admin_data");
				print(AJXP_XMLWriter::writeRoleRepositoriesData($role));
				AJXP_XMLWriter::close("admin_data");			
			break;
			
			case "update_role_right" :
				if(!isSet($_GET["role_id"]) 
					|| !isSet($_GET["repository_id"]) 
					|| !isSet($_GET["right"]))
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					print("<update_checkboxes user_id=\"".$_GET["role_id"]."\" repository_id=\"".$_GET["repository_id"]."\" read=\"old\" write=\"old\"/>");
					AJXP_XMLWriter::close();
					exit(1);
				}
				$role = AuthService::getRole($_GET["role_id"]);
				$role->setRight($_GET["repository_id"], $_GET["right"]);
				AuthService::updateRole($role);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.64"].$_GET["role_id"], null);
				print("<update_checkboxes user_id=\"".$_GET["role_id"]."\" repository_id=\"".$_GET["repository_id"]."\" read=\"".$role->canRead($_GET["repository_id"])."\" write=\"".$role->canWrite($_GET["repository_id"])."\"/>");
				//AJXP_XMLWriter::reloadRepositoryList();
				AJXP_XMLWriter::close();
				exit(1);
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
				print("<ajxp_roles>");
				foreach (AuthService::getRolesList() as $roleId => $roleObject){
					print("<role id=\"$roleId\"/>");
				}
				print("</ajxp_roles>");
				AJXP_XMLWriter::close("admin_data");
				exit(1) ;
			break;
			
			case "create_user" :
				
				if(!isset($_GET["new_user_login"]) || $_GET["new_user_login"] == "" ||!isset($_GET["new_user_pwd"]) || $_GET["new_user_pwd"] == "")
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					AJXP_XMLWriter::close();
					exit(1);						
				}
				$forbidden = array("guest", "share");
				if(AuthService::userExists($_GET["new_user_login"]) || in_array($_GET["new_user_login"], $forbidden))
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.43"]);
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
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.44"], null);
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
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.45"].$_GET["user_id"], null);
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
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
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
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.46"].$_GET["user_id"], null);
				print("<update_checkboxes user_id=\"".$_GET["user_id"]."\" repository_id=\"".$_GET["repository_id"]."\" read=\"".$user->canRead($_GET["repository_id"])."\" write=\"".$user->canWrite($_GET["repository_id"])."\"/>");
				AJXP_XMLWriter::reloadRepositoryList();
				AJXP_XMLWriter::close();
				return ;
			break;
		
			case "user_add_role" : 
			
				if(!isSet($_GET["user_id"]) || !isSet($_GET["role_id"]) || !AuthService::userExists($_GET["user_id"])){
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					AJXP_XMLWriter::close();
					return ;
				}
				$confStorage = ConfService::getConfStorageImpl();		
				$user = $confStorage->createUserObject($_GET["user_id"]);
				$user->addRole($_GET["role_id"]);
				$user->save();
				$loggedUser = AuthService::getLoggedUser();
				if($loggedUser->getId() == $user->getId()){
					AuthService::updateUser($user);
				}
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.73"].$_GET["user_id"], null);
				AJXP_XMLWriter::close();
				return ;
				
			break;
			
			case "user_delete_role":
				
				if(!isSet($_GET["user_id"]) || !isSet($_GET["role_id"]) || !AuthService::userExists($_GET["user_id"])){
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					AJXP_XMLWriter::close();
					return ;
				}
				$confStorage = ConfService::getConfStorageImpl();		
				$user = $confStorage->createUserObject($_GET["user_id"]);
				$user->removeRole($_GET["role_id"]);
				$user->save();
				$loggedUser = AuthService::getLoggedUser();
				if($loggedUser->getId() == $user->getId()){
					AuthService::updateUser($user);
				}				
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.74"].$_GET["user_id"], null);
				AJXP_XMLWriter::close();
				return ;
				
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
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.47"].$_GET["user_id"], null);
				AJXP_XMLWriter::close();
				exit(1);	
			break;
			
			case "update_user_pwd" : 
				if(!isSet($_GET["user_id"]) || !isSet($_GET["user_pwd"]) || !AuthService::userExists($_GET["user_id"]) || trim($_GET["user_pwd"]) == "")
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					AJXP_XMLWriter::close();
					exit(1);			
				}
				$res = AuthService::updatePassword($_GET["user_id"], $_GET["user_pwd"]);
				AJXP_XMLWriter::header();
				if($res === true)
				{
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.48"].$_GET["user_id"], null);
				}
				else 
				{
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.49"]." : $res");
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
                if ($this->repositoryExists($newRep->getDisplay()))
                {
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.50"]);
					AJXP_XMLWriter::close();
					exit(1);
                }
				$res = ConfService::addRepository($newRep);
				AJXP_XMLWriter::header();
				if($res == -1){
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.51"]);
				}else{
					$confStorage = ConfService::getConfStorageImpl();		
					$loggedUser = AuthService::getLoggedUser();
					$loggedUser->setRight($newRep->getUniqueId(), "rw");
					$loggedUser->save();
					AuthService::updateUser($loggedUser);
					
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.52"], null);
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
						print(" $name=\"".SystemTextEncoding::toUTF8(AJXP_Utils::xmlEntities($option))."\" ");
					}else if(is_array($option)){
						$nested[] = $option;
					}
				}
				if(count($nested)){
					print(">");
					foreach ($nested as $option){
						foreach ($option as $key => $optValue){
							if(is_array($optValue) && count($optValue)){
								print("<param name=\"$key\"><![CDATA[".json_encode($optValue)."]]></param>");
							}else{
								if(is_bool($optValue)){
									$optValue = ($optValue?"true":"false");
								}
								print("<param name=\"$key\" value=\"$optValue\"/>");
							}
						}
					}
					print("</repository>");
				}else{
					print("/>");
				}
				$pServ = AJXP_PluginsService::getInstance();
				$plug = $pServ->getPluginById("access.".$repository->accessType);
				$manifest = $plug->getManifestRawContent("server_settings/param");
				print("<ajxpdriver name=\"".$repository->accessType."\">$manifest</ajxpdriver>");
				print("<metasources>");
				$metas = $pServ->getPluginsByType("meta");
				foreach ($metas as $metaPlug){
					print("<meta id=\"".$metaPlug->getId()."\">");
					$manifest = $metaPlug->getManifestRawContent("server_settings/param");
					print($manifest);
					print("</meta>");
				}
				print("</metasources>");
				AJXP_XMLWriter::close("admin_data");
				exit(1);
			break;
			
			case "edit_repository_label" : 
			case "edit_repository_data" : 
				$repId = $_GET["repository_id"];
				$repo = ConfService::getRepositoryById($repId);
				$res = 0;
				if(isSet($_GET["newLabel"])){
					$newLabel = SystemTextEncoding::fromPostedFileName($_GET["newLabel"]);
                    if ($this->repositoryExists($newLabel))
                    {
		     			AJXP_XMLWriter::header();
			    		AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.50"]);
				    	AJXP_XMLWriter::close();
					    exit(1);
                    }
					$repo->setDisplay($newLabel);                    
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
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.53"]);
				}else{
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.54"], null);					
					AJXP_XMLWriter::reloadDataNode("", (isSet($_GET["newLabel"])?SystemTextEncoding::fromPostedFileName($_GET["newLabel"]):false));
					AJXP_XMLWriter::reloadRepositoryList();
				}
				AJXP_XMLWriter::close();		
				exit(1);
			
			case "add_meta_source" : 
				$repId = $httpVars["repository_id"];
				$repo = ConfService::getRepositoryById($repId);
				$metaSourceType = $httpVars["new_meta_source"];
				$options = array();
				$this->parseParameters($httpVars, $options);
				$repoOptions = $repo->getOption("META_SOURCES");
				if(is_array($repoOptions) && isSet($repoOptions[$metaSourceType])){
					throw new Exception($mess["ajxp_conf.55"]);
				}
				if(!is_array($repoOptions)){
					$repoOptions = array();
				}
				$repoOptions[$metaSourceType] = $options;
				$repo->addOption("META_SOURCES", $repoOptions);
				ConfService::replaceRepository($repId, $repo);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.56"],null);
				AJXP_XMLWriter::close();
			break;
						
			case "delete_meta_source" : 
			
				$repId = $httpVars["repository_id"];
				$repo = ConfService::getRepositoryById($repId);
				$metaSourceId = $httpVars["plugId"];
				$repoOptions = $repo->getOption("META_SOURCES");
				if(is_array($repoOptions) && array_key_exists($metaSourceId, $repoOptions)){
					unset($repoOptions[$metaSourceId]);
					$repo->addOption("META_SOURCES", $repoOptions);
					ConfService::replaceRepository($repId, $repo);
				}
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.57"],null);
				AJXP_XMLWriter::close();

			break;
			
			case "edit_meta_source" : 
				$repId = $httpVars["repository_id"];
				$repo = ConfService::getRepositoryById($repId);
				$metaSourceId = $httpVars["plugId"];
				$options = array();
				$this->parseParameters($httpVars, $options);
				$repoOptions = $repo->getOption("META_SOURCES");
				if(!is_array($repoOptions)){
					$repoOptions = array();
				}
				$repoOptions[$metaSourceId] = $options;
				$repo->addOption("META_SOURCES", $repoOptions);
				ConfService::replaceRepository($repId, $repo);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.58"],null);
				AJXP_XMLWriter::close();
			break;
									
				
			case "delete" :
				if(isSet($httpVars["repository_id"])){
					$repId = $httpVars["repository_id"];
					//if(get_magic_quotes_gpc()) $repLabel = stripslashes($repLabel);
					$res = ConfService::deleteRepository($repId);
					AJXP_XMLWriter::header();
					if($res == -1){
						AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.51"]);
					}else{
						AJXP_XMLWriter::sendMessage($mess["ajxp_conf.59"], null);						
						AJXP_XMLWriter::reloadDataNode();
						AJXP_XMLWriter::reloadRepositoryList();
					}
					AJXP_XMLWriter::close();		
					exit(1);
				}else if(isSet($httpVars["shared_file"])){
					AJXP_XMLWriter::header();
					$element = basename($httpVars["shared_file"]);
					$publicletData = $this->loadPublicletData(PUBLIC_DOWNLOAD_FOLDER."/".$element.".php");
					unlink(PUBLIC_DOWNLOAD_FOLDER."/".$element.".php");
					AJXP_XMLWriter::sendMessage($mess["ajxp_shared.13"], null);
					AJXP_XMLWriter::reloadDataNode();
					AJXP_XMLWriter::close();					
				}else if(isSet($httpVars["role_id"])){
					$roleId = $httpVars["role_id"];
					if(AuthService::getRole($roleId) === false){
						throw new Exception($mess["ajxp_conf.67"]);
					}
					AuthService::deleteRole($roleId);
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.66"], null);
					AJXP_XMLWriter::reloadDataNode();
					AJXP_XMLWriter::close();								
				}else{
					$forbidden = array("guest", "share");
					if(!isset($httpVars["user_id"]) || $httpVars["user_id"]=="" 
						|| in_array($_GET["user_id"], $forbidden)
						|| $loggedUser->getId() == $httpVars["user_id"])
					{
						AJXP_XMLWriter::header();
						AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
						AJXP_XMLWriter::close();
						exit(1);									
					}
					$res = AuthService::deleteUser($httpVars["user_id"]);
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.60"], null);
					AJXP_XMLWriter::reloadDataNode();
					AJXP_XMLWriter::close();
					exit(1);
					
				}
			break;
			
			case "clear_expired" :
				
				$deleted = $this->clearExpiredFiles();
				AJXP_XMLWriter::header();
				if(count($deleted)){
					AJXP_XMLWriter::sendMessage(sprintf($mess["ajxp_shared.23"], count($deleted).""), null);
					AJXP_XMLWriter::reloadDataNode();					
				}else{
					AJXP_XMLWriter::sendMessage($mess["ajxp_shared.24"], null);
				}
				AJXP_XMLWriter::close();
				
			break;			
			
			default:
			break;
		}

		return;
	}
	
	
	function listUsers(){
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist">
			<column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/>
			<column messageId="ajxp_conf.7" attributeName="isAdmin" sortType="String"/>
			<column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String"/>
			</columns>');		
		if(!ENABLE_USERS) return ;
		$users = AuthService::listUsers();
		$mess = ConfService::getMessages();
		$repos = ConfService::getRepositoriesList();
		$loggedUser = AuthService::getLoggedUser();		
        $userArray = array();
		foreach ($users as $userIndex => $userObject){
			$label = $userObject->getId();
			if($userObject->hasParent()){
				$label = $userObject->getParent()."000".$label;
			}
            $userArray[$label] = $userObject;
        }        
        ksort($userArray);
        foreach($userArray as $userObject) {
			$isAdmin = $userObject->isAdmin();
			$userId = AJXP_Utils::xmlEntities($userObject->getId());
			$icon = "user".($userId=="guest"?"_guest":($isAdmin?"_admin":""));
			if($userObject->hasParent()){
				$icon = "user_child";
			}
			$rightsString = "";
			if($isAdmin) {
				$rightsString = $mess["ajxp_conf.63"];
			}else{
				$r = array();
				foreach ($repos as $repoId => $repository){
					if($repository->getAccessType() == "ajxp_shared") continue;
					if($userObject->canWrite($repoId)) $r[] = $repository->getDisplay()." (rw)";
					else if($userObject->canRead($repoId)) $r[] = $repository->getDisplay()." (r)";
				}
				$rightsString = implode(", ", $r);
			}
			AJXP_XMLWriter::renderNode("/users/".$userId, $userId, true, array(
				"isAdmin" => $mess[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")], 
				"icon" => $icon.".png",				
				"rights_summary" => AJXP_Utils::xmlEntities($rightsString, true),				
				"ajxp_mime" => "user".(($userId!="guest"&&$userId!=$loggedUser->getId())?"_editable":"")
			));
		}
	}
	
	function listRoles(){
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist">
			<column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/>			
			<column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String"/>
			</columns>');		
		if(!ENABLE_USERS) return ;
		$roles = AuthService::getRolesList();
		$mess = ConfService::getMessages();
		$repos = ConfService::getRepositoriesList();
        ksort($roles);
        foreach($roles as $roleId => $roleObject) {
			$icon = "user";
			$rightsString = "";
			$r = array();
			foreach ($repos as $repoId => $repository){
				if($repository->getAccessType() == "ajxp_shared") continue;
				if($roleObject->canWrite($repoId)) $r[] = $repository->getDisplay()." (rw)";
				else if($roleObject->canRead($repoId)) $r[] = $repository->getDisplay()." (r)";
			}
			$rightsString = implode(", ", $r);
			AJXP_XMLWriter::renderNode("/roles/".$roleId, $roleId, true, array(
				"icon" => "user_group_new.png",				
				"rights_summary" => AJXP_Utils::xmlEntities($rightsString, true),				
				"ajxp_mime" => "role"
			));
		}
	}
	
    function repositoryExists($name)
    {
		$repos = ConfService::getRepositoriesList();
        foreach ($repos as $obj)
            if ($obj->getDisplay() == $name) return true;

        return false;
    }

	function listRepositories(){
		$repos = ConfService::getRepositoriesList();
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist">
			<column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/>
			<column messageId="ajxp_conf.9" attributeName="accessType" sortType="String"/>
			<column messageId="ajxp_shared.27" attributeName="owner" sortType="String"/>
			</columns>');		
        $repoArray = array();
        $childRepos = array();        
		foreach ($repos as $repoIndex => $repoObject){
			if($repoObject->getAccessType() == "ajxp_conf" || $repoObject->getAccessType() == "ajxp_shared") continue;
			if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;
            $name = AJXP_Utils::xmlEntities(SystemTextEncoding::toUTF8($repoObject->getDisplay()));
			if($repoObject->hasOwner()) {
				$parentId = $repoObject->getParentId();	        	
				if(!isSet($childRepos[$parentId])) $childRepos[$parentId] = array();
				$childRepos[$parentId][] = array("name" => $name, "index" => $repoIndex);
				continue;
			}
            $repoArray[$name] = $repoIndex;
        }
        // Sort the list now by name        
        ksort($repoArray);
        // Append child repositories
        $sortedArray = array();
        foreach ($repoArray as $name => $repoIndex) {
        	$sortedArray[$name] = $repoIndex;
        	if(isSet($childRepos[$repoIndex]) && is_array($childRepos[$repoIndex])){
        		foreach ($childRepos[$repoIndex] as $childData){
        			$sortedArray[$childData["name"]] = $childData["index"];
        		}
        	}
        }
        foreach ($sortedArray as $name => $repoIndex) {
            $repoObject =& $repos[$repoIndex];
            $metaData = array(
            	"repository_id" => $repoIndex,
            	"accessType"	=> $repoObject->getAccessType(),
            	"icon"			=> ($repoObject->hasOwner()?"repo_child.png":"folder_red.png"),
            	"owner"			=> ($repoObject->hasOwner()?$repoObject->getOwner():""),
            	"openicon"		=> "folder_red.png",
            	"parentname"	=> "/repositories",
				"ajxp_mime" 	=> "repository".($repoObject->isWriteable()?"_editable":"")
            );
            AJXP_XMLWriter::renderNode("/repositories/$repoIndex", $name, true, $metaData);
		}
	}
	
	function listLogFiles($dir){	
		$logger = AJXP_Logger::getInstance();
		$parts = explode("/", $dir);
		if(count($parts)>4){
			$config = '<columns switchDisplayMode="list" switchGridMode="grid">
				<column messageId="ajxp_conf.17" attributeName="date" sortType="Date" width="10%"/>
				<column messageId="ajxp_conf.18" attributeName="ip" sortType="String"/>
				<column messageId="ajxp_conf.19" attributeName="level" sortType="String"/>
				<column messageId="ajxp_conf.20" attributeName="user" sortType="String"/>
				<column messageId="ajxp_conf.21" attributeName="action" sortType="String"/>
				<column messageId="ajxp_conf.22" attributeName="params" sortType="String"/>
			</columns>';				
			AJXP_XMLWriter::sendFilesListComponentConfig($config);
			$date = $parts[count($parts)-1];
			$logger->xmlLogs($dir, $date, "tree");
		}else{
			AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.16" attributeName="ajxp_label" sortType="String"/></columns>');
			$logger->xmlListLogFiles("tree", (count($parts)>2?$parts[2]:null), (count($parts)>3?$parts[3]:null));
		}
	}
	
	function printDiagnostic(){
		$outputArray = array();
		$testedParams = array();
		$passed = AJXP_Utils::runTests($outputArray, $testedParams);
		AJXP_Utils::testResultsToFile($outputArray, $testedParams);		
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="list" switchGridMode="fileList"><column messageId="ajxp_conf.23" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_conf.24" attributeName="data" sortType="String"/></columns>');		
		if(is_file(TESTS_RESULT_FILE)){
			include_once(TESTS_RESULT_FILE);			
			foreach ($diagResults as $id => $value){
				$value = AJXP_Utils::xmlEntities($value);
				print "<tree icon=\"susehelpcenter.png\" is_file=\"1\" filename=\"$id\" text=\"$id\" data=\"$value\" ajxp_mime=\"testResult\"/>";
			}
		}		
	}
	
	function listSharedFiles(){
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist">
				<column messageId="ajxp_shared.4" attributeName="ajxp_label" sortType="String" width="20%"/>
				<column messageId="ajxp_shared.27" attributeName="owner" sortType="String" width="20%"/>
				<column messageId="ajxp_shared.17" attributeName="download_url" sortType="String" width="20%"/>
				<column messageId="ajxp_shared.6" attributeName="password" sortType="String" width="5%"/>
				<column messageId="ajxp_shared.7" attributeName="expiration" sortType="String" width="5%"/>
				<column messageId="ajxp_shared.20" attributeName="expired" sortType="String" width="5%"/>
				<column messageId="ajxp_shared.14" attributeName="integrity" sortType="String" width="5%" hidden="true"/>
			</columns>');
		if(!is_dir(PUBLIC_DOWNLOAD_FOLDER)) return ;		
		$files = glob(PUBLIC_DOWNLOAD_FOLDER."/*.php");
		if($files === false) return ;
		$mess = ConfService::getMessages();
		$loggedUser = AuthService::getLoggedUser();
		$userId = $loggedUser->getId();
        if(defined('PUBLIC_DOWNLOAD_URL') && PUBLIC_DOWNLOAD_URL != ""){
        	$downloadBase = rtrim(PUBLIC_DOWNLOAD_URL, "/");
        }else{
	        $http_mode = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
	        $fullUrl = $http_mode . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);    
	        $downloadBase = str_replace("\\", "/", $fullUrl.rtrim(str_replace(INSTALL_PATH, "", PUBLIC_DOWNLOAD_FOLDER), "/"));
        }
		
		foreach ($files as $file){
			$publicletData = $this->loadPublicletData($file);			
			AJXP_XMLWriter::renderNode(str_replace(".php", "", basename($file)), "".SystemTextEncoding::toUTF8($publicletData["REPOSITORY"]->getDisplay()).":/".SystemTextEncoding::toUTF8($publicletData["FILE_PATH"]), true, array(
				"icon"		=> "html.png",
				"password" => ($publicletData["PASSWORD"]!=""?$publicletData["PASSWORD"]:"-"), 
				"expiration" => ($publicletData["EXPIRE_TIME"]!=0?date($mess["date_format"], $publicletData["EXPIRE_TIME"]):"-"), 
				"expired" => ($publicletData["EXPIRE_TIME"]!=0?($publicletData["EXPIRE_TIME"]<time()?$mess["ajxp_shared.21"]:$mess["ajxp_shared.22"]):"-"), 
				"integrity"  => (!$publicletData["SECURITY_MODIFIED"]?$mess["ajxp_shared.15"]:$mess["ajxp_shared.16"]),
				"download_url" => $downloadBase . "/".basename($file),
				"owner" => (isset($publicletData["OWNER_ID"])?$publicletData["OWNER_ID"]:"-"),
				"ajxp_mime" => "shared_file")
			);			
		}
	}
	
	function clearExpiredFiles(){
		$files = glob(PUBLIC_DOWNLOAD_FOLDER."/*.php");
		$loggedUser = AuthService::getLoggedUser();
		$userId = $loggedUser->getId();
		$deleted = array();
		foreach ($files as $file){
			$publicletData = $this->loadPublicletData($file);			
			if(isSet($publicletData["EXPIRATION_TIME"]) && is_numeric($publicletData["EXPIRATION_TIME"]) && $publicletData["EXPIRATION_TIME"] > 0 && $publicletData["EXPIRATION_TIME"] < time()){
				unlink($file);
				$deleted[] = basename($file);
			}
		}
		return $deleted;
	}
	
	protected function loadPublicletData($file){
		$lines = file($file);
		$id = str_replace(".php", "", basename($file));
		$code = $lines[3] . $lines[4] . $lines[5];
		eval($code);
		$dataModified = (md5($inputData) != $id);
		$publicletData = unserialize($inputData);
		$publicletData["SECURITY_MODIFIED"] = $dataModified;		
		return $publicletData;
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
	                    if (trim($value != "") && function_exists('mcrypt_encrypt'))
	                    {
	                        // The initialisation vector is only required to avoid a warning, as ECB ignore IV
	                        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
	                        // We encode as base64 so if we need to store the result in a database, it can be stored in text column
	                        $value = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($userId."\1CDAFx¨op#"), $value, MCRYPT_MODE_ECB, $iv));
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
