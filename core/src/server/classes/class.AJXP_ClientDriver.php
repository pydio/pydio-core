<?php

class AJXP_ClientDriver extends AbstractDriver 
{
	
	function AJXP_ClientDriver($repository) {
		parent::AbstractDriver ( "ajxp_actions", CLIENT_RESOURCES_FOLDER."/xml/ajxpclient_actions.xml", $repository );
		unset($this->actions["get_driver_actions"]);
		unset($this->actions["get_driver_info_panels"]);
		$this->actions["get_ajxp_actions"] = array();
		$this->actions["get_ajxp_info_panels"] = array();
	}
	
	function switchAction($action, $httpVars, $fileVars)
	{
		if(!isSet($this->actions[$action])) return;
		$xmlBuffer = "";
		foreach($httpVars as $getName=>$getValue){
			$$getName = Utils::securePath($getValue);
		}
		if(isSet($dir) && $action != "upload") $dir = utf8_decode($dir);
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
				
			break;	
						
			//------------------------------------
			//	GET AN HTML TEMPLATE
			//------------------------------------
			case "get_template":
			
				header("Content-type:text/html");				
				if(isset($template_name) && is_file(CLIENT_RESOURCES_FOLDER."/html/".$template_name))
				{
					if(!isSet($encode) || $encode != "false")
					{
						$mess = array_map("utf8_encode", $mess);
					}
					include(CLIENT_RESOURCES_FOLDER."/html/".$template_name);
				}
				exit(0);	
				
			break;
			
			//------------------------------------
			//	GET I18N MESSAGES
			//------------------------------------
			case "get_i18n_messages":
			
				header("Content-type:text/javascript");				
				HTMLWriter::writeI18nMessagesClass(ConfService::getMessages());
				/*
				if(isset($template_name) && is_file(CLIENT_RESOURCES_FOLDER."/html/".$template_name))
				{
					if(!isSet($encode) || $encode != "false")
					{
						$mess = array_map("utf8_encode", $mess);
					}
					include(CLIENT_RESOURCES_FOLDER."/html/".$template_name);
				}
				*/
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
					$bmUser = new AJXP_User("shared");
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
						AuthService::updatePassword($userObject->getId(), $prefValue);
					}
					$i++;
				}
				$logMessage = "Done($i)";
				
			break;
			
			//------------------------------------
			//	DISPLAY DOC
			//------------------------------------
			case "display_doc":
			
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