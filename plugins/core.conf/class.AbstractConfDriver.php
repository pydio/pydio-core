<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.core
 * @class AbstractConfDriver
 * Abstract representation of a conf driver. Must be implemented by the "conf" plugin
 */
abstract class AbstractConfDriver extends AJXP_Plugin {
		
	var $options;
	var $driverType = "conf";
	
	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if(!ConfService::getCoreConf("WEBDAV_ENABLE") && $contribNode->nodeName == "actions"){
			unset($this->actions["webdav_preferences"]);
			$actionXpath=new DOMXPath($contribNode->ownerDocument);
			$publicUrlNodeList = $actionXpath->query('action[@name="webdav_preferences"]', $contribNode);
			$publicUrlNode = $publicUrlNodeList->item(0);
			$contribNode->removeChild($publicUrlNode);			
		}		
		if(!ConfService::getCoreConf("USER_CREATE_REPOSITORY", "conf") && $contribNode->nodeName == "actions"){
			unset($this->actions["user_create_repository"]);
			$actionXpath=new DOMXPath($contribNode->ownerDocument);
			$publicUrlNodeList = $actionXpath->query('action[@name="user_create_repository"]', $contribNode);
			$publicUrlNode = $publicUrlNodeList->item(0);
			$contribNode->removeChild($publicUrlNode);
		}
	}
	
	// NEW FUNCTIONS FOR  LOADING/SAVING PLUGINS CONFIGS
	/**
	 * Returns an array of options=>values merged from various sources (.inc.php, implementation source)
	 * @return Array
	 * @param String $pluginType
	 * @param String $pluginId
	 */
	function loadPluginConfig($pluginType, $pluginName){
		$options = array();
		if(is_file(AJXP_CONF_PATH."/conf.$pluginType.inc")){
			include AJXP_CONF_PATH."/conf.$pluginType.inc";
			if(!empty($DRIVER_CONF)){
				foreach($DRIVER_CONF as $key=>$value){
					$options[$key] = $value;
				}
				unset($DRIVER_CONF);
			}
		}
		if(is_file(AJXP_CONF_PATH."/conf.$pluginType.$pluginName.inc")){
			include AJXP_CONF_PATH."/conf.$pluginType.$pluginName.inc";
			if(!empty($DRIVER_CONF)){
				foreach($DRIVER_CONF as $key=>$value){
					$options[$key] = $value;
				}
				unset($DRIVER_CONF);
			}
		}
		$this->_loadPluginConfig($pluginType.".".$pluginName, $options);
		return $options;
	}

	abstract function _loadPluginConfig($pluginId, &$options);
	
	/**
	 * 
	 * @param String $pluginType
	 * @param String $pluginId
	 * @param String $configHash
	 */
	abstract function savePluginConfig($pluginId, $options);
	
	
	// SAVE / EDIT / CREATE / DELETE REPOSITORY
	/**
	 * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
	 * @return Array
	 */
	abstract function listRepositories();
	/**
	 * Retrieve a Repository given its unique ID.
	 *
	 * @param String $repositoryId
	 * @return Repository
	 */	
	abstract function getRepositoryById($repositoryId);
	/**
	 * Retrieve a Repository given its alias.
	 *
	 * @param String $repositorySlug
	 * @return Repository
	 */	
	abstract function getRepositoryByAlias($repositorySlug);
	/**
	 * Stores a repository, new or not.
	 *
	 * @param Repository $repositoryObject
	 * @param Boolean $update 
	 * @return -1 if failed
	 */	
	abstract function saveRepository($repositoryObject, $update = false);
	/**
	 * Delete a repository, given its unique ID.
	 *
	 * @param String $repositoryId
	 */
	abstract function deleteRepository($repositoryId);
		
	/**
	 * Must return an associative array of roleId => AjxpRole objects.
	 *
	 */
	abstract function listRoles();
	abstract function saveRoles($roles);
	
	/**
	 * Specific queries
	 */
	abstract function countAdminUsers();
	
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
	abstract function instantiateAbstractUserImpl($userId);
	
	abstract function getUserClassFileName();
	
	function getOption($optionName){	
		return (isSet($this->options[$optionName])?$this->options[$optionName]:"");	
	}

    /**
     * @param AbstractAjxpUser $userObject
     * @return array()
     */
    function getExposedPreferences($userObject){
        $stringPrefs = array("display","lang","diapo_autofit","sidebar_splitter_size","vertical_splitter_size","history/last_repository","pending_folder","thumb_size","plugins_preferences","upload_auto_send","upload_auto_close","upload_existing","action_bar_style");
        $jsonPrefs = array("ls_history","columns_size", "columns_visibility", "gui_preferences");
        $prefs = array();
        if( $userObject->getId()=="guest" && ConfService::getCoreConf("SAVE_GUEST_PREFERENCES", "conf") === false){
            return array();
        }
        foreach($stringPrefs as $pref){
            if(strstr($pref, "/")!==false){
                $parts = explode("/", $pref);
                $value = $userObject->getArrayPref($parts[0], $parts[1]);
                $pref = str_replace("/", "_", $pref);
            }else{
                $value = $userObject->getPref($pref);
            }
            $prefs[$pref] = array("value" => $value, "type" => "string" );
        }
        foreach ($jsonPrefs as $pref){
            $prefs[$pref] = array("value" => $userObject->getPref($pref), "type" => "json" );
        }
        return $prefs;
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
                /** @var $repository_id string */
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
				if(isSet($httpVars["bm_action"]) && isset($httpVars["bm_path"]))
				{
					if($httpVars["bm_action"] == "add_bookmark")
					{
						$title = "";
						if(isSet($httpVars["bm_title"])) $title = $httpVars["bm_title"];
						if($title == "" && $httpVars["bm_path"]=="/") $title = ConfService::getCurrentRootDirDisplay();
						$bmUser->addBookMark(SystemTextEncoding::magicDequote($httpVars["bm_path"]), SystemTextEncoding::magicDequote($title));
					}
					else if($httpVars["bm_action"] == "delete_bookmark")
					{
						$bmUser->removeBookmark($httpVars["bm_path"]);
					}
					else if($httpVars["bm_action"] == "rename_bookmark" && isset($httpVars["bm_title"]))
					{
						$bmUser->renameBookmark($httpVars["bm_path"], $httpVars["bm_title"]);
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
				$i = 0;
				while(isSet($httpVars["pref_name_".$i]) && isSet($httpVars["pref_value_".$i]))
				{
					$prefName = AJXP_Utils::sanitize($httpVars["pref_name_".$i], AJXP_SANITIZE_ALPHANUM);
					$prefValue = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote(($httpVars["pref_value_".$i])));
					if($prefName == "password") continue;
					if($prefName != "pending_folder" && $userObject == null){
						$i++;
						continue;
					}
					$userObject->setPref($prefName, $prefValue);
					$userObject->save();
					AuthService::updateUser($userObject);
					//setcookie("AJXP_$prefName", $prefValue);
					$i++;
				}
				header("Content-Type:text/plain");
				print "SUCCESS";
				exit(1);
				
			break;					
					
			//------------------------------------
			// WEBDAV PREFERENCES
			//------------------------------------
			case "webdav_preferences" :
				
				$userObject = AuthService::getLoggedUser();
				$webdavActive = false;
				$passSet = false;
				// Detect http/https and host
				if(ConfService::getCoreConf("WEBDAV_BASEHOST") != ""){
					$baseURL = ConfService::getCoreConf("WEBDAV_BASEHOST");
				}else{
					$baseURL = AJXP_Utils::detectServerURL();
				}
				$webdavBaseUrl = $baseURL.ConfService::getCoreConf("WEBDAV_BASEURI")."/";
				if(isSet($httpVars["activate"]) || isSet($httpVars["webdav_pass"])){
					$davData = $userObject->getPref("AJXP_WEBDAV_DATA");
					if(!empty($httpVars["activate"])){
						$activate = ($httpVars["activate"]=="true" ? true:false);
						if(empty($davData)){
							$davData = array();						
						}
						$davData["ACTIVE"] = $activate;
					}
					if(!empty($httpVars["webdav_pass"])){
						$password = $httpVars["webdav_pass"];
						if (function_exists('mcrypt_encrypt'))
				        {
				        	$user = $userObject->getId();
				        	$secret = (defined("AJXP_SECRET_KEY")? AJXP_SAFE_SECRET_KEY:"\1CDAFx¨op#");
					        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
					        $password = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($user.$secret), $password, MCRYPT_MODE_ECB, $iv));
				        }						
						$davData["PASS"] = $password;
					}
					$userObject->setPref("AJXP_WEBDAV_DATA", $davData);
					$userObject->save();
				}
				$davData = $userObject->getPref("AJXP_WEBDAV_DATA");				
				if(!empty($davData)){
					$webdavActive = (isSet($davData["ACTIVE"]) && $davData["ACTIVE"]===true); 
					$passSet = (isSet($davData["PASS"])); 
				}
				$repoList = ConfService::getRepositoriesList();
				$davRepos = array();
				$loggedUser = AuthService::getLoggedUser();
				foreach($repoList as $repoIndex => $repoObject){
					$accessType = $repoObject->getAccessType();
					if(in_array($accessType, array("fs", "ftp")) && ($loggedUser->canRead($repoIndex) || $loggedUser->canWrite($repoIndex))){
						$davRepos[$repoIndex] = $webdavBaseUrl ."".($repoObject->getSlug()==null?$repoObject->getId():$repoObject->getSlug());
					}
				}
				$prefs = array(
					"webdav_active"  => $webdavActive,
					"password_set"   => $passSet,
					"webdav_base_url"  => $webdavBaseUrl, 
					"webdav_repositories" => $davRepos
				);
				HTMLWriter::charsetHeader("application/json");
				print(json_encode($prefs));
				
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