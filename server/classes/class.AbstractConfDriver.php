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
 * Description : Abstract representation of Ajaxplorer Data Access
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class AbstractConfDriver extends AJXP_Plugin {
		
	var $options;
	var $driverType = "conf";
	
	// SAVE / EDIT / CREATE / DELETE REPOSITORY
	/**
	 * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
	 * @return Array
	 */
	function listRepositories(){
		
	}
	/**
	 * Retrieve a Repository given its unique ID.
	 *
	 * @param String $repositoryId
	 * @return Repository
	 */	
	function getRepositoryById($repositoryId){
		
	}
	/**
	 * Stores a repository, new or not.
	 *
	 * @param Repository $repositoryObject
	 * @param Boolean $update 
	 * @return -1 if failed
	 */	
	function saveRepository($repositoryObject, $update = false){
		
	}
	/**
	 * Delete a repository, given its unique ID.
	 *
	 * @param String $repositoryId
	 */
	function deleteRepository($repositoryId){
		
	}
	
	// SAVE / EDIT / CREATE / DELETE USER OBJECT (except password)
	/**
	 * Retrieve the list of available users
	 * @return Array
	 */
	function listUsers(){
		
	}
	
	
	/**
	 * Instantiate a new AJXP_User
	 *
	 * @param String $userId
	 * @return AbstractAjxpUser
	 */
	function createUserObject($userId){
		$abstractUser = $this->instantiateAbstractUserImpl($userId);
		if(!$abstractUser->storageExists()){			
			AuthService::updateDefaultRights($abstractUser);
		}
		return $abstractUser;
	}
	
	/**
	 * Instantiate the right class
	 *
	 * @param AbstractAjxpUser $userId
	 */
	function instantiateAbstractUserImpl($userId){
		
	}
	
	function getUserClassFileName(){		
	}
	
	function getOption($optionName){	
		return (isSet($this->options[$optionName])?$this->options[$optionName]:"");	
	}
	
		
	function switchAction($action, $httpVars, $fileVars)
	{
		if(!isSet($this->actions[$action])) return;
		$xmlBuffer = "";
		foreach($httpVars as $getName=>$getValue){
			$$getName = AJXP_Utils::securePath($getValue);
		}
		if(isSet($dir) && $action != "upload") $dir = SystemTextEncoding::fromUTF8($dir);
		$mess = ConfService::getMessages();
		
		switch ($action){			
			//------------------------------------
			//	SWITCH THE ROOT REPOSITORY
			//------------------------------------	
			case "switch_repository":
			
				if(!isSet($repository_id))
				{
					break;
				}
				$dirList = ConfService::getRootDirsList();
				if(!isSet($dirList[$repository_id]))
				{
					$errorMessage = "Trying to switch to an unkown repository!";
					break;
				}
				ConfService::switchRootDir($repository_id);
				// Load try to init the driver now, to trigger an exception
				// if it's not loading right.
				ConfService::loadRepositoryDriver();
				if(AuthService::usersEnabled() && AuthService::getLoggedUser()!=null){
					$user = AuthService::getLoggedUser();
					$activeRepId = ConfService::getCurrentRootDirIndex();
					$user->setArrayPref("history", "last_repository", $activeRepId);
					$user->save();
				}
				//$logMessage = "Successfully Switched!";
				AJXP_Logger::logAction("Switch Repository", array("rep. id"=>$repository_id));
				
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
					$prefValue = stripslashes($_GET["pref_value_".$i]);
					if($prefName != "password")
					{
						if($prefName == "ls_history"){
							$prefValue = str_replace("'", "\\\'", $prefValue);
						}
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
							header("Content-Type:text/plain");
							print "PASS_ERROR";
							exit(1);
						}
					}
					$i++;
				}
				header("Content-Type:text/plain");
				print "SUCCESS";
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
				
		return $xmlBuffer;		
	}
	

}
?>