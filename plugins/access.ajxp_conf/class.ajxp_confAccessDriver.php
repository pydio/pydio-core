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
					"plugins"	   => array("LABEL" => "Plugins Configs", "ICON" => "folder_development.png"),
					"repositories" => array("LABEL" => $mess["ajxp_conf.3"], "ICON" => "folder_red.png"),
					"users" => array("LABEL" => $mess["ajxp_conf.2"], "ICON" => "yast_kuser.png"),
					"roles" => array("LABEL" => $mess["ajxp_conf.69"], "ICON" => "user_group_new.png"),
					"files" => array("LABEL" => $mess["ajxp_shared.3"], "ICON" => "html.png"),
					"logs" => array("LABEL" => $mess["ajxp_conf.4"], "ICON" => "toggle_log.png"),
					"diagnostic" => array("LABEL" => $mess["ajxp_conf.5"], "ICON" => "susehelpcenter.png")
				);
				$dir = AJXP_Utils::decodeSecureMagic((isset($httpVars["dir"])?$httpVars["dir"]:""));
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
					}else if($strippedDir == "plugins"){
						$this->listPlugins($dir);
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
					return;
				}else{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.1" attributeName="ajxp_label" sortType="String"/></columns>');
					foreach ($rootNodes as $key => $data){
						print '<tree text="'.$data["LABEL"].'" icon="'.$data["ICON"].'" filename="/'.$key.'" parentname="/"/>';
					}
					AJXP_XMLWriter::close();
					return;
				}
			break;
			
			case "stat" :
				
				header("Content-type:application/json");
				print '{"mode":true}';
				return;
				
			break;			
			
			case "create_role":
				$roleId = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($httpVars["role_id"]), AJXP_SANITIZE_HTML_STRICT);
				if(!strlen($roleId)){
					throw new Exception($mess[349]);
				}
				if(AuthService::getRole($roleId) !== false){
					throw new Exception($mess["ajxp_conf.65"]);
				}
				AuthService::updateRole(new AjxpRole($roleId));
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.66"], null);
				AJXP_XMLWriter::reloadDataNode("", $httpVars["role_id"]);
				AJXP_XMLWriter::close();				
			break;
			
			case "edit_role" : 				
				$roleId = SystemTextEncoding::magicDequote($httpVars["role_id"]);
				$role = AuthService::getRole($roleId);
				if($role === false) {
					throw new Exception("Cant find role! ");
				}
				AJXP_XMLWriter::header("admin_data");
				print(AJXP_XMLWriter::writeRoleRepositoriesData($role));
				AJXP_XMLWriter::close("admin_data");			
			break;
			
			case "update_role_right" :
				if(!isSet($httpVars["role_id"]) 
					|| !isSet($httpVars["repository_id"]) 
					|| !isSet($httpVars["right"]))
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					print("<update_checkboxes user_id=\"".$httpVars["role_id"]."\" repository_id=\"".$httpVars["repository_id"]."\" read=\"old\" write=\"old\"/>");
					AJXP_XMLWriter::close();
					return ;
				}
				$role = AuthService::getRole($httpVars["role_id"]);
				if($role === false) {
					throw new Exception("Cant find role!");
				}
				$role->setRight($httpVars["repository_id"], $httpVars["right"]);
				AuthService::updateRole($role);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.64"].$httpVars["role_id"], null);
				print("<update_checkboxes user_id=\"".$httpVars["role_id"]."\" repository_id=\"".$httpVars["repository_id"]."\" read=\"".$role->canRead($httpVars["repository_id"])."\" write=\"".$role->canWrite($httpVars["repository_id"])."\"/>");
				//AJXP_XMLWriter::reloadRepositoryList();
				AJXP_XMLWriter::close();
			break;

			case "update_role_actions" : 
			
				if(!isSet($httpVars["role_id"])  
					|| !isSet($httpVars["disabled_actions"]))
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					AJXP_XMLWriter::close();
					return ;
				}
				$role = AuthService::getRole($httpVars["role_id"]);
				if($role === false) {
					throw new Exception("Cant find role!");
				}
				$actions = explode(",", $httpVars["disabled_actions"]);
				// Clear and reload actions
				foreach ($role->getSpecificActionsRights("ajxp.all") as $actName => $actValue){
					$role->setSpecificActionRight("ajxp.all", $actName, true);
				}
				foreach ($actions as $action){
					if(($action = AJXP_Utils::sanitize($action, AJXP_SANITIZE_ALPHANUM)) == "") continue;
					$role->setSpecificActionRight("ajxp.all", $action, false);
				}
				AuthService::updateRole($role);
				AJXP_XMLWriter::header("admin_data");
				print(AJXP_XMLWriter::writeRoleRepositoriesData($role));
				AJXP_XMLWriter::close("admin_data");				
			
			break;		
			
			case "get_custom_params" :
				$confStorage = ConfService::getConfStorageImpl();
				AJXP_XMLWriter::header("admin_data");
				$confDriver = ConfService::getConfStorageImpl();
				$customData = $confDriver->options['CUSTOM_DATA'];
				if(is_array($customData) && count($customData)>0 ){
					print("<custom_data>");
					foreach($customData as $custName=>$custValue){
						print("<param name=\"$custName\" type=\"string\" label=\"$custValue\" description=\"\" value=\"\"/>");
					}
					print("</custom_data>");
				}
				AJXP_XMLWriter::close("admin_data");
				
			break;
			
			case "edit_user" : 
				$confStorage = ConfService::getConfStorageImpl();	
				$userId = $httpVars["user_id"];	
				if(!AuthService::userExists($userId)){
					throw new Exception("Invalid user id!");
				}
				$userObject = $confStorage->createUserObject($userId);		
				//print_r($userObject);
				AJXP_XMLWriter::header("admin_data");
				AJXP_XMLWriter::sendUserData($userObject, true);
				
				// Add CUSTOM USER DATA
				$confDriver = ConfService::getConfStorageImpl();
				$customData = $confDriver->options['CUSTOM_DATA'];
				if(is_array($customData) && count($customData)>0 ){
					$userCustom = $userObject->getPref("CUSTOM_PARAMS");
					print("<custom_data>");
					foreach($customData as $custName=>$custValue){
						$value = isset($userCustom[$custName]) ? $userCustom[$custName] : '';
						print("<param name=\"$custName\" type=\"string\" label=\"$custValue\" description=\"\" value=\"$value\"/>");
					}
					print("</custom_data>");
				}
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
				
			break;
			
			case "create_user" :
				
				if(!isset($httpVars["new_user_login"]) || $httpVars["new_user_login"] == "" ||!isset($httpVars["new_user_pwd"]) || $httpVars["new_user_pwd"] == "")
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					AJXP_XMLWriter::close();
					return;						
				}
				$forbidden = array("guest", "share");
				$new_user_login = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($httpVars["new_user_login"]), AJXP_SANITIZE_ALPHANUM);
				if(AuthService::userExists($new_user_login) || in_array($new_user_login, $forbidden))
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.43"]);
					AJXP_XMLWriter::close();
					return;									
				}
				
				$confStorage = ConfService::getConfStorageImpl();		
				$newUser = $confStorage->createUserObject($new_user_login);
				
				$customData = array();
				$this->parseParameters($httpVars, $customData);
				if(is_array($customData) && count($customData)>0)
					$newUser->setPref("CUSTOM_PARAMS", $customData);
				
				$newUser->save();
				AuthService::createUser($new_user_login, $httpVars["new_user_pwd"]);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.44"], null);
				AJXP_XMLWriter::reloadFileList($new_user_login);
				AJXP_XMLWriter::close();
														
			break;
								
			case "change_admin_right" :
				$userId = $httpVars["user_id"];
				if(!AuthService::userExists($userId)){
					throw new Exception("Invalid user id!");
				}				
				$confStorage = ConfService::getConfStorageImpl();		
				$user = $confStorage->createUserObject($userId);
				$user->setAdmin(($httpVars["right_value"]=="1"?true:false));
				$user->save();
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.45"].$httpVars["user_id"], null);
				AJXP_XMLWriter::reloadFileList(false);
				AJXP_XMLWriter::close();
				
			break;
		
			case "update_user_right" :
				if(!isSet($httpVars["user_id"]) 
					|| !isSet($httpVars["repository_id"]) 
					|| !isSet($httpVars["right"])
					|| !AuthService::userExists($httpVars["user_id"]))
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					print("<update_checkboxes user_id=\"".$httpVars["user_id"]."\" repository_id=\"".$httpVars["repository_id"]."\" read=\"old\" write=\"old\"/>");
					AJXP_XMLWriter::close();
					return;
				}
				$confStorage = ConfService::getConfStorageImpl();		
				$user = $confStorage->createUserObject($httpVars["user_id"]);
				$user->setRight(AJXP_Utils::sanitize($httpVars["repository_id"], AJXP_SANITIZE_ALPHANUM), AJXP_Utils::sanitize($httpVars["right"], AJXP_SANITIZE_ALPHANUM));
				$user->save();
				$loggedUser = AuthService::getLoggedUser();
				if($loggedUser->getId() == $user->getId()){
					AuthService::updateUser($user);
				}
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.46"].$httpVars["user_id"], null);
				print("<update_checkboxes user_id=\"".$httpVars["user_id"]."\" repository_id=\"".$httpVars["repository_id"]."\" read=\"".$user->canRead($httpVars["repository_id"])."\" write=\"".$user->canWrite($httpVars["repository_id"])."\"/>");
				AJXP_XMLWriter::reloadRepositoryList();
				AJXP_XMLWriter::close();
				return ;
			break;
		
			case "user_add_role" : 
			case "user_delete_role":
			
				if(!isSet($httpVars["user_id"]) || !isSet($httpVars["role_id"]) || !AuthService::userExists($httpVars["user_id"]) || !AuthService::getRole($httpVars["role_id"])){
					throw new Exception($mess["ajxp_conf.61"]);
				}
				if($action == "user_add_role"){
					$act = "add";
					$messId = "73";
				}else{
					$act = "remove";
					$messId = "74";
				}
				$this->updateUserRole($httpVars["user_id"], $httpVars["role_id"], $act);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.".$messId].$httpVars["user_id"], null);
				AJXP_XMLWriter::close();
				return ;
				
			break;
			
			case "batch_users_roles" : 
				
				$confStorage = ConfService::getConfStorageImpl();	
				$selection = new UserSelection();
				$selection->initFromHttpVars($httpVars);
				$files = $selection->getFiles();
				$detectedRoles = array();
				
				if(isSet($httpVars["role_id"]) && isset($httpVars["update_role_action"])){
					$update = $httpVars["update_role_action"];
					$roleId = $httpVars["role_id"];
					if(AuthService::getRole($roleId) === false){
						throw new Exception("Invalid role id");
					}
				}
				foreach ($files as $index => $file){
					$userId = basename($file);
					if(isSet($update)){
						$userObject = $this->updateUserRole($userId, $roleId, $update);
					}else{
						$userObject = $confStorage->createUserObject($userId);
					}
					if($userObject->hasParent()){
						unset($files[$index]);
						continue;
					}
					$userRoles = $userObject->getRoles();
					foreach ($userRoles as $roleIndex => $bool){
						if(!isSet($detectedRoles[$roleIndex])) $detectedRoles[$roleIndex] = 0;
						if($bool === true) $detectedRoles[$roleIndex] ++;
					}
				}
				$count = count($files);
				AJXP_XMLWriter::header("admin_data");
				print("<user><ajxp_roles>");
				foreach ($detectedRoles as $roleId => $roleCount){
					if($roleCount < $count) continue;
					print("<role id=\"$roleId\"/>");
				}				
				print("</ajxp_roles></user>");
				print("<ajxp_roles>");
				foreach (AuthService::getRolesList() as $roleId => $roleObject){
					print("<role id=\"$roleId\"/>");
				}
				print("</ajxp_roles>");				
				AJXP_XMLWriter::close("admin_data");
			
			break;
			
			case "save_custom_user_params" : 
				$userId = $httpVars["user_id"];
				if($userId == $loggedUser->getId()){
					$user = $loggedUser;
				}else{
					$confStorage = ConfService::getConfStorageImpl();		
					$user = $confStorage->createUserObject($userId);
				}
				$custom = $user->getPref("CUSTOM_PARAMS");
				if(!is_array($custom)) $custom = array();
				
				$options = $custom;
				$this->parseParameters($httpVars, $options, $userId);
				$custom = $options;
				$user->setPref("CUSTOM_PARAMS", $custom);
				$user->save();
				
				if($loggedUser->getId() == $user->getId()){
					AuthService::updateUser($user);
				}
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.47"].$httpVars["user_id"], null);
				AJXP_XMLWriter::close();
					
			break;
			
			case "save_repository_user_params" : 
				$userId = $httpVars["user_id"];
				if($userId == $loggedUser->getId()){
					$user = $loggedUser;
				}else{
					$confStorage = ConfService::getConfStorageImpl();		
					$user = $confStorage->createUserObject($userId);
				}
				$wallet = $user->getPref("AJXP_WALLET");
				if(!is_array($wallet)) $wallet = array();
				$repoID = $httpVars["repository_id"];
				if(!array_key_exists($repoID, $wallet)){
					$wallet[$repoID] = array();
				}
				$options = $wallet[$repoID];
				$this->parseParameters($httpVars, $options, $userId);
				$wallet[$repoID] = $options;
				$user->setPref("AJXP_WALLET", $wallet);
				$user->save();
				
				if($loggedUser->getId() == $user->getId()){
					AuthService::updateUser($user);
				}
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.47"].$httpVars["user_id"], null);
				AJXP_XMLWriter::close();
					
			break;
			
			case "update_user_pwd" : 
				if(!isSet($httpVars["user_id"]) || !isSet($httpVars["user_pwd"]) || !AuthService::userExists($httpVars["user_id"]) || trim($httpVars["user_pwd"]) == "")
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
					AJXP_XMLWriter::close();
					return;			
				}
				$res = AuthService::updatePassword($httpVars["user_id"], $httpVars["user_pwd"]);
				AJXP_XMLWriter::header();
				if($res === true)
				{
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.48"].$httpVars["user_id"], null);
				}
				else 
				{
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.49"]." : $res");
				}
				AJXP_XMLWriter::close();
										
			break;
	
			case  "get_drivers_definition":
				
				AJXP_XMLWriter::header("drivers");
				print(ConfService::availableDriversToXML("param"));
				AJXP_XMLWriter::close("drivers");
				
				
			break;
			
			case  "get_templates_definition":
				
				AJXP_XMLWriter::header("repository_templates");
				$repositories = ConfService::getRepositoriesList();
				foreach ($repositories as $repo){
					if(!$repo->isTemplate) continue;
					$repoId = $repo->getUniqueId();
					$repoLabel = $repo->getDisplay();
					$repoType = $repo->getAccessType();
					print("<template repository_id=\"$repoId\" repository_label=\"$repoLabel\" repository_type=\"$repoType\">");
					foreach($repo->getOptionsDefined() as $optionName){
						print("<option name=\"$optionName\"/>");
					}
					print("</template>");
				}
				AJXP_XMLWriter::close("repository_templates");
				
				
			break;
			
			case "create_repository" : 
			
				$options = array();
				$repDef = $httpVars;
                $isTemplate = isSet($httpVars["sf_checkboxes_active"]);
                unset($repDef["get_action"]);
                unset($repDef["sf_checkboxes_active"]);
				$this->parseParameters($repDef, $options);
				if(count($options)){
					$repDef["DRIVER_OPTIONS"] = $options;
				}
				if(strstr($repDef["DRIVER"], "ajxp_template_") !== false){
					$templateId = substr($repDef["DRIVER"], 14);
					$templateRepo = ConfService::getRepositoryById($templateId);
					$newRep = $templateRepo->createTemplateChild($repDef["DISPLAY"], $repDef["DRIVER_OPTIONS"]);
				}else{
					$newRep = ConfService::createRepositoryFromArray(0, $repDef);
					if(!$isTemplate && is_file(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$newRep->getAccessType().".php"))
					{
					    chdir(AJXP_TESTS_FOLDER."/plugins");
						include(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$newRep->getAccessType().".php");
						$className = "ajxp_".$newRep->getAccessType();
						$class = new $className();
						$result = $class->doRepositoryTest($newRep);
						if(!$result){
							AJXP_XMLWriter::header();
							AJXP_XMLWriter::sendMessage(null, $class->failedInfo);
							AJXP_XMLWriter::close();
							return;
						}
					}					
				}

                if ($this->repositoryExists($newRep->getDisplay()))
                {
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.50"]);
					AJXP_XMLWriter::close();
					return;
                }
                if($isTemplate){
                    $newRep->isTemplate = true;
                }
				$res = ConfService::addRepository($newRep);
				AJXP_XMLWriter::header();
				if($res == -1){
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.51"]);
				}else{
					$loggedUser = AuthService::getLoggedUser();
					$loggedUser->setRight($newRep->getUniqueId(), "rw");
					$loggedUser->save();
					AuthService::updateUser($loggedUser);
					
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.52"], null);
					AJXP_XMLWriter::reloadFileList($newRep->getDisplay());
					AJXP_XMLWriter::reloadRepositoryList();
				}
				AJXP_XMLWriter::close();
				
				
			
			break;
			
			case "edit_repository" : 
				$repId = $httpVars["repository_id"];
				$repList = ConfService::getRootDirsList();
				//print_r($repList);
				if(!isSet($repList[$repId])){
					throw new Exception("Cannot find repository with id $repId");
				}
				$repository = $repList[$repId];	
				$pServ = AJXP_PluginsService::getInstance();
				$plug = $pServ->getPluginById("access.".$repository->accessType);
				if($plug == null){
					throw new Exception("Cannot find access driver (".$repository->accessType.") for repository!");
				}				
				AJXP_XMLWriter::header("admin_data");		
				$slug = $repository->getSlug();
				if($slug == "" && $repository->isWriteable()){
					$repository->setSlug();
					ConfService::replaceRepository($repId, $repository);
				}
				$nested = array();
				print("<repository index=\"$repId\"");
				foreach ($repository as $name => $option){
					if(strstr($name, " ")>-1) continue;
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
					// Add SLUG
					if(!$repository->isTemplate) print("<param name=\"AJXP_SLUG\" value=\"".$repository->getSlug()."\"/>");
					print("</repository>");
				}else{
					print("/>");
				}
				if($repository->hasParent()){
					$parent = ConfService::getRepositoryById($repository->getParentId());
					if(isSet($parent) && $parent->isTemplate){
						$parentLabel = $parent->getDisplay();
						$parentType = $parent->getAccessType();
						print("<template repository_id=\"".$repository->getParentId()."\" repository_label=\"$parentLabel\" repository_type=\"$parentType\">");
						foreach($parent->getOptionsDefined() as $parentOptionName){
							print("<option name=\"$parentOptionName\"/>");
						}
						print("</template>");						
					}
				}
				$manifest = $plug->getManifestRawContent("server_settings/param");
				// Remove Slug if is template, ugly way
				if($repository->isTemplate){
					$slugString = '<param group="Repository Slug" name="AJXP_SLUG" type="string" label="Alias" description="Alias for replacing the generated unique id of the repository" mandatory="false"/>';
					$manifest = str_replace($slugString, "", $manifest);
				}
				print("<ajxpdriver name=\"".$repository->accessType."\">$manifest</ajxpdriver>");
				print("<metasources>");
				$metas = $pServ->getPluginsByType("meta");
                $metas = array_merge($metas, $pServ->getPluginsByType("index"));
				foreach ($metas as $metaPlug){
					print("<meta id=\"".$metaPlug->getId()."\">");
					$manifest = $metaPlug->getManifestRawContent("server_settings/param");
					print($manifest);
					print("</meta>");
				}
				print("</metasources>");
				AJXP_XMLWriter::close("admin_data");
				return ;
			break;
			
			case "edit_repository_label" : 
			case "edit_repository_data" : 
				$repId = $httpVars["repository_id"];
				$repo = ConfService::getRepositoryById($repId);
				$res = 0;
				if(isSet($httpVars["newLabel"])){
					$newLabel = AJXP_Utils::decodeSecureMagic($httpVars["newLabel"]);
                    if ($this->repositoryExists($newLabel))
                    {
		     			AJXP_XMLWriter::header();
			    		AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.50"]);
				    	AJXP_XMLWriter::close();
					    return;
                    }
					$repo->setDisplay($newLabel);                    
					$res = ConfService::replaceRepository($repId, $repo);
				}else{
					$options = array();
					$this->parseParameters($httpVars, $options);
					if(count($options)){
						foreach ($options as $key=>$value) {
							if($key == "AJXP_SLUG"){
								$repo->setSlug($value);
								continue;
							}
							$repo->addOption($key, $value);
						}
					}
					if(is_file(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$repo->getAccessType().".php")){
					    chdir(AJXP_TESTS_FOLDER."/plugins");
						include(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$repo->getAccessType().".php");
						$className = "ajxp_".$repo->getAccessType();
						$class = new $className();
						$result = $class->doRepositoryTest($repo);
						if(!$result){
							AJXP_XMLWriter::header();
							AJXP_XMLWriter::sendMessage(null, $class->failedInfo);
							AJXP_XMLWriter::close();
							return;
						}
					}
					
					ConfService::replaceRepository($repId, $repo);
				}
				AJXP_XMLWriter::header();
				if($res == -1){
					AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.53"]);
				}else{
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.54"], null);					
					AJXP_XMLWriter::reloadDataNode("", (isSet($httpVars["newLabel"])?AJXP_Utils::decodeSecureMagic($httpVars["newLabel"]):false));
					AJXP_XMLWriter::reloadRepositoryList();
				}
				AJXP_XMLWriter::close();		
				
			break;
			
			case "add_meta_source" : 
				$repId = $httpVars["repository_id"];
				$repo = ConfService::getRepositoryById($repId);
				if(!is_object($repo)){
					throw new Exception("Invalid repository id! $repId");
				}
				$metaSourceType = AJXP_Utils::sanitize($httpVars["new_meta_source"], AJXP_SANITIZE_ALPHANUM);
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
				uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
				$repo->addOption("META_SOURCES", $repoOptions);
				ConfService::replaceRepository($repId, $repo);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.56"],null);
				AJXP_XMLWriter::close();
			break;
						
			case "delete_meta_source" : 
			
				$repId = $httpVars["repository_id"];
				$repo = ConfService::getRepositoryById($repId);
				if(!is_object($repo)){
					throw new Exception("Invalid repository id! $repId");
				}
				$metaSourceId = $httpVars["plugId"];
				$repoOptions = $repo->getOption("META_SOURCES");
				if(is_array($repoOptions) && array_key_exists($metaSourceId, $repoOptions)){
					unset($repoOptions[$metaSourceId]);
					uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
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
				if(!is_object($repo)){
					throw new Exception("Invalid repository id! $repId");
				}				
				$metaSourceId = $httpVars["plugId"];
				$options = array();
				$this->parseParameters($httpVars, $options);
				$repoOptions = $repo->getOption("META_SOURCES");
				if(!is_array($repoOptions)){
					$repoOptions = array();
				}
				$repoOptions[$metaSourceId] = $options;
				uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
				$repo->addOption("META_SOURCES", $repoOptions);
				ConfService::replaceRepository($repId, $repo);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.58"],null);
				AJXP_XMLWriter::close();
			break;
									
				
			case "delete" :
				if(isSet($httpVars["repository_id"])){
					$repId = $httpVars["repository_id"];					
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
					return;
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
						|| in_array($httpVars["user_id"], $forbidden)
						|| $loggedUser->getId() == $httpVars["user_id"])
					{
						AJXP_XMLWriter::header();
						AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
						AJXP_XMLWriter::close();
					}
					$res = AuthService::deleteUser($httpVars["user_id"]);
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage($mess["ajxp_conf.60"], null);
					AJXP_XMLWriter::reloadDataNode();
					AJXP_XMLWriter::close();
					
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
			
			case "get_plugin_manifest" : 

				$ajxpPlugin = AJXP_PluginsService::getInstance()->getPluginById($httpVars["plugin_id"]);
				AJXP_XMLWriter::header("admin_data");
				echo($ajxpPlugin->getManifestRawContent());
				$definitions = $ajxpPlugin->getConfigsDefinitions();
				$values = $ajxpPlugin->getConfigs();
				if(is_array($values) && count($values)){
					echo("<plugin_settings_values>");
					foreach($values as $key => $value){
						if($definitions[$key]["type"] == "array"){
							$value = implode(",", $value);
						}else if($definitions[$key]["type"] == "boolean"){
							$value = ($value === true?"true":"false");
						}
						echo("<param name=\"$key\" value=\"$value\"/>");
					}
					echo("</plugin_settings_values>");				
				}
				AJXP_XMLWriter::close("admin_data");
				
			break;
			
			case "edit_plugin_options":
				
				$options = array();
				$this->parseParameters($httpVars, $options);
				$confStorage = ConfService::getConfStorageImpl();
				$confStorage->savePluginConfig($httpVars["plugin_id"], $options);
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($mess["ajxp_conf.97"], null);
				AJXP_XMLWriter::reloadDataNode();
				AJXP_XMLWriter::close();
				
				
			break;
			
			default:
			break;
		}

		return;
	}
	
	
	function listPlugins($dir){
		$pServ = AJXP_PluginsService::getInstance();
		$plugins = $pServ->getActivePlugins();
		if($dir == "/plugins"){
			// Grab all types
			$types = array();			
			foreach($plugins as $pluginId => $status){
				$type = array_shift(explode(".", $pluginId));
				if($type == "core") continue;
				$types[$type] = $type;
			}
			$meta = array(
				"icon" 		=> "preferences_desktop",	
				"ajxp_mime" => "ajxp_plugin",				
				"is_active"	=> "true",
				"plugin_id" => "core.ajaxplorer"
			);
			AJXP_XMLWriter::renderNode("/plugins/core.ajaxplorer", "AjaXplorer Core", true, $meta);							
			foreach( $types as $t){
				$meta = array(
					"icon" 		=> "folder_development.png",					
					"is_active"	=> $status?"true":"false",
					"plugin_id" => $t
				);
				AJXP_XMLWriter::renderNode("/plugins/".$t, ucfirst($t)." plugins", false, $meta);				
			}
		}else{
			$split = explode("/", $dir);
			if(empty($split[0])) array_shift($split);
			$type = $split[1];
			foreach($plugins as $pluginId => $status){
				list($pType, $pId) = (explode(".", $pluginId));				
				if($pType != $type && !($pType == "core" && $pId == $type)) {
					continue;
				}
				//$plug = $pServ->findPluginById($pluginId);
				//if(!count($plug->getConfigsDefinitions())) continue;
				$meta = array(				
					"icon" 		=> ($pType == "core"?"preferences_desktop.png":"preferences_plugin.png"),
					"ajxp_mime" => "ajxp_plugin",
					"is_active"	=> $status?"true":"false",
					"plugin_id" => $pluginId
				);
				if($pType == "core"){
					$label = "Global options for '$pId' plugins";
				}else{
					$label = $pluginId;
				}
				AJXP_XMLWriter::renderNode("/plugins/".$pluginId, $label, true, $meta);
			}
		}
	}
	
	function listUsers(){
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.users">
			<column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/>
			<column messageId="ajxp_conf.7" attributeName="isAdmin" sortType="String"/>
			<column messageId="ajxp_conf.70" attributeName="ajxp_roles" sortType="String"/>
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
			$userId = $userObject->getId();
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
                    if(!$userObject->canRead($repoId) && !$userObject->canWrite($repoId)) continue;
                    $rs = ($userObject->canRead($repoId) ? "r" : "");
                    $rs .= ($userObject->canWrite($repoId) ? "w" : "");
                    $r[] = $repository->getDisplay()." (".$rs.")";
				}
				$rightsString = implode(", ", $r);
			}
			AJXP_XMLWriter::renderNode("/users/".$userId, $userId, true, array(
				"isAdmin" => $mess[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")], 
				"icon" => $icon.".png",				
				"rights_summary" => $rightsString,
				"ajxp_roles" => implode(", ", array_keys($userObject->getRoles())),
				"ajxp_mime" => "user".(($userId!="guest"&&$userId!=$loggedUser->getId())?"_editable":"")
			));
		}
	}
	
	function listRoles(){
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.roles">
			<column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/>			
			<column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String"/>
			</columns>');		
		if(!ENABLE_USERS) return ;
		$roles = AuthService::getRolesList();
		$mess = ConfService::getMessages();
		$repos = ConfService::getRepositoriesList();
        ksort($roles);
        foreach($roles as $roleId => $roleObject) {
			$r = array();
			foreach ($repos as $repoId => $repository){
				if($repository->getAccessType() == "ajxp_shared") continue;
                if(!$roleObject->canRead($repoId) && !$roleObject->canWrite($repoId)) continue;
                $rs = ($roleObject->canRead($repoId) ? "r" : "");
                $rs .= ($roleObject->canWrite($repoId) ? "w" : "");
                $r[] = $repository->getDisplay()." (".$rs.")";
			}
			$rightsString = implode(", ", $r);
			AJXP_XMLWriter::renderNode("/roles/".$roleId, $roleId, true, array(
				"icon" => "user_group_new.png",				
				"rights_summary" => $rightsString,
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
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.repositories">
			<column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/>
			<column messageId="ajxp_conf.9" attributeName="accessType" sortType="String"/>
			<column messageId="ajxp_shared.27" attributeName="owner" sortType="String"/>
			</columns>');		
        $repoArray = array();
        $childRepos = array();
        $templateRepos = array();        
		foreach ($repos as $repoIndex => $repoObject){
			if($repoObject->getAccessType() == "ajxp_conf" || $repoObject->getAccessType() == "ajxp_shared") continue;
			if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;
            $name = AJXP_Utils::xmlEntities(SystemTextEncoding::toUTF8($repoObject->getDisplay()));
			if($repoObject->hasOwner() || $repoObject->hasParent()) {
				$parentId = $repoObject->getParentId();	        	
				if(!isSet($childRepos[$parentId])) $childRepos[$parentId] = array();
				$childRepos[$parentId][] = array("name" => $name, "index" => $repoIndex);
				continue;
			}
			if($repoObject->isTemplate){
				$templateRepos[$name] = $repoIndex;
			}else{
	            $repoArray[$name] = $repoIndex;
			}
        }
        // Sort the list now by name
        ksort($templateRepos);        
        ksort($repoArray);
        $repoArray = array_merge($templateRepos, $repoArray);
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
            $icon = (($repoObject->hasOwner()||$repoObject->hasParent())?"repo_child.png":"folder_red.png");
            if($repoObject->isTemplate) $icon = "folder_grey.png";
            $metaData = array(
            	"repository_id" => $repoIndex,
            	"accessType"	=> ($repoObject->isTemplate?"Template for ":"").$repoObject->getAccessType(),
            	"icon"			=> $icon,
            	"owner"			=> ($repoObject->hasOwner()?$repoObject->getOwner():""),
            	"openicon"		=> $icon,
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
			$config = '<columns switchDisplayMode="list" switchGridMode="grid" template_name="ajxp_conf.logs">
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
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="list" switchGridMode="fileList" template_name="ajxp_conf.diagnostic"><column messageId="ajxp_conf.23" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_conf.24" attributeName="data" sortType="String"/></columns>');		
		if(is_file(TESTS_RESULT_FILE)){
			include_once(TESTS_RESULT_FILE);			
			foreach ($diagResults as $id => $value){
				$value = AJXP_Utils::xmlEntities($value);
				print "<tree icon=\"susehelpcenter.png\" is_file=\"1\" filename=\"$id\" text=\"$id\" data=\"$value\" ajxp_mime=\"testResult\"/>";
			}
		}		
	}
	
	function listSharedFiles(){
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.shared">
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
	        $downloadBase = str_replace("\\", "/", $fullUrl.rtrim(str_replace(AJXP_INSTALL_PATH, "", PUBLIC_DOWNLOAD_FOLDER), "/"));
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
	
	function metaSourceOrderingFunction($key1, $key2){
		$t1 = array_shift(explode(".", $key1));
		$t2 = array_shift(explode(".", $key2));
		if($t1 == "index") return 1;
		if($t2 == "index") return -1;
		return 0;
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
		
	function updateUserRole($userId, $roleId, $addOrRemove, $updateSubUsers = false){
		$confStorage = ConfService::getConfStorageImpl();		
		$user = $confStorage->createUserObject($userId);
		if($user->hasParent()) return $user;
		if($addOrRemove == "add"){
			$user->addRole($roleId);
		}else{
			$user->removeRole($roleId);
		}
		$user->save();
		$loggedUser = AuthService::getLoggedUser();
		if($loggedUser->getId() == $user->getId()){
			AuthService::updateUser($user);
		}
		return $user;
		
	}
	
	
	function parseParameters(&$repDef, &$options, $userId = null){
		
		foreach ($repDef as $key => $value)
		{
			$value = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($value));
			if(strpos($key, "DRIVER_OPTION_")!== false && strpos($key, "DRIVER_OPTION_")==0 && strpos($key, "ajxptype") === false && strpos($key, "_checkbox") === false){
				if(isSet($repDef[$key."_ajxptype"])){
					$type = $repDef[$key."_ajxptype"];
					if($type == "boolean"){
						$value = ($value == "true"?true:false);
					}else if($type == "integer"){
						$value = intval($value);
					}else if($type == "array"){
						$value = explode(",", $value);
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
                if(isSet($repDef[$key."_checkbox"])){
                    $checked = $repDef[$key."_checkbox"] == "checked";
                    unset($repDef[$key."_checkbox"]);
                    if(!$checked) continue;
                }
				$options[substr($key, strlen("DRIVER_OPTION_"))] = $value;
				unset($repDef[$key]);
			}else{
				if($key == "DISPLAY"){
					$value = SystemTextEncoding::fromUTF8(AJXP_Utils::securePath($value));
				}
				$repDef[$key] = $value;		
			}
		}		
	}
	    
}

?>
