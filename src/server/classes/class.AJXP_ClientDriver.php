<?php
/**
 * @package info.ajaxplorer
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
 * Description : Basic implementation of the AbstractDriver, handle low level actions (docs, templates, etc).
 */
class AJXP_ClientDriver extends AbstractDriver 
{
	
	function AJXP_ClientDriver($repository) {
		parent::AbstractDriver("ajxp_actions");
		$this->initXmlActionsFile(CLIENT_RESOURCES_FOLDER."/xml/ajxpclient_actions.xml");
		unset($this->actions["get_driver_actions"]);
		unset($this->actions["get_driver_info_panels"]);
		$this->actions["get_ajxp_actions"] = array();
		$this->actions["get_ajxp_info_panels"] = array();
	}
	
	function applyAction($actionName, $httpVars, $filesVar){
		if($actionName == "get_ajxp_actions"){
			AJXP_XMLWriter::header();
			$this->sendActionsToClient(false, null, null);
			$authDriver = ConfService::getAuthDriverImpl();
			$authDriver->sendActionsToClient(false, null, null);
			$confDriver = ConfService::getConfStorageImpl();
			$confDriver->sendActionsToClient(false, null, null);
			AJXP_XMLWriter::close();
			exit(1);
		}else{
			parent::applyAction($actionName, $httpVars, $filesVar);
		}
	}
	
	function switchAction($action, $httpVars, $fileVars)
	{
		if(!isSet($this->actions[$action])) return;
		$xmlBuffer = "";
		foreach($httpVars as $getName=>$getValue){
			$$getName = Utils::securePath($getValue);
		}
		if(isSet($dir) && $action != "upload") $dir = SystemTextEncoding::fromUTF8($dir);
		$mess = ConfService::getMessages();
		
		switch ($action){			
			//------------------------------------
			//	SWITCH THE ROOT REPOSITORY
			//------------------------------------	
			case "switch_root_dir":
			
				if(!isSet($root_dir_index))
				{
					break;
				}
				$dirList = ConfService::getRootDirsList();
				if(!isSet($dirList[$root_dir_index]))
				{
					$errorMessage = "Trying to switch to an unkown folder!";
					break;
				}
				ConfService::switchRootDir($root_dir_index);
				$logMessage = "Successfully Switched!";
				AJXP_Logger::logAction("Switch Repository", array("rep. id"=>$root_dir_index));
				
			break;	
						
			//------------------------------------
			//	GET AN HTML TEMPLATE
			//------------------------------------
			case "get_template":
			
				header("Content-type:text/html; charset:UTF-8");
				if(isset($template_name) && is_file(CLIENT_RESOURCES_FOLDER."/html/".$template_name))
				{
					if($template_name == "gui_tpl.html"){
						include(CLIENT_RESOURCES_FOLDER."/html/usertemplate_top.html");
					}
					include(CLIENT_RESOURCES_FOLDER."/html/".$template_name);
					if($template_name == "gui_tpl.html"){
						include(CLIENT_RESOURCES_FOLDER."/html/usertemplate_bottom.html");
					}
				}
				exit(0);	
				
			break;
						
			//------------------------------------
			//	GET I18N MESSAGES
			//------------------------------------
			case "get_i18n_messages":
			
				header("Content-type:text/javascript");				
				HTMLWriter::writeI18nMessagesClass(ConfService::getMessages());
				exit(0);	
				
			break;
			
			//------------------------------------
			//	BOOKMARK BAR
			//------------------------------------
			case "get_bookmarks":
				
				$bmUser = null;
				if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
				{
					$bmUser = AuthService::getLoggedUser();
				}
				else if(!AuthService::usersEnabled())
				{
					$confStorage = ConfService::getConfStorageImpl();
					$bmUser = $confStorage->createUserObject("shared");
				}
				if($bmUser == null) exit(1);
				if(isSet($_GET["bm_action"]) && isset($_GET["bm_path"]))
				{
					if($_GET["bm_action"] == "add_bookmark")
					{
						$title = "";
						if(isSet($_GET["title"])) $title = $_GET["title"];
						if($title == "" && $_GET["bm_path"]=="/") $title = ConfService::getCurrentRootDirDisplay();
						$bmUser->addBookMark($_GET["bm_path"], $title);
					}
					else if($_GET["bm_action"] == "delete_bookmark")
					{
						$bmUser->removeBookmark($_GET["bm_path"]);
					}
					else if($_GET["bm_action"] == "rename_bookmark" && isset($_GET["bm_title"]))
					{
						$bmUser->renameBookmark($_GET["bm_path"], $_GET["bm_title"]);
					}
				}
				if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
				{
					$bmUser->save();
					AuthService::updateUser($bmUser);
				}
				else if(!AuthService::usersEnabled())
				{
					$bmUser->save();
				}		
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::writeBookmarks($bmUser->getBookmarks());
				AJXP_XMLWriter::close();
				exit(1);
			
			break;
					
			//------------------------------------
			//	SAVE USER PREFERENCE
			//------------------------------------
			case "save_user_pref":
				
				$userObject = AuthService::getLoggedUser();
				if($userObject == null) exit(1);
				$i = 0;
				while(isSet($_GET["pref_name_".$i]) && isSet($_GET["pref_value_".$i]))
				{
					$prefName = $_GET["pref_name_".$i];
					$prefValue = $_GET["pref_value_".$i];
					if($prefName != "password")
					{
						$userObject->setPref($prefName, $prefValue);
						$userObject->save();
						AuthService::updateUser($userObject);
						setcookie("AJXP_$prefName", $prefValue);
					}
					else
					{
						if(isSet($_GET["crt"]) && AuthService::checkPassword($userObject->getId(), $_GET["crt"], false, $_GET["pass_seed"])){
							AuthService::updatePassword($userObject->getId(), $prefValue);
						}else{
							//$errorMessage = "Wrong password!";
							header("text/plain");
							print "PASS_ERROR";
							exit(1);
						}
					}
					$i++;
				}
				header("text/plain");
				print "SUCCESS";
				exit(1);
				
			break;
			
			//------------------------------------
			//	DISPLAY DOC
			//------------------------------------
			case "display_doc":
			
				header("Content-type:text/html; charset:UTF-8");
				echo HTMLWriter::getDocFile($_GET["doc_file"]);
				exit(1);
				
			break;
			
					
			default;
			break;
		}
		if(isset($logMessage) || isset($errorMessage))
		{
			$xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);			
		}
		
		if(isset($requireAuth))
		{
			$xmlBuffer .= AJXP_XMLWriter::requireAuth(false);
		}
		
		if(isset($reload_current_node) && $reload_current_node == "true")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadCurrentNode(false);
		}
		
		if(isset($reload_dest_node) && $reload_dest_node != "")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadNode($reload_dest_node, false);
		}
		
		if(isset($reload_file_list))
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadFileList($reload_file_list, false);
		}
		
		return $xmlBuffer;		
	}
}

?>
