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
 * @package info.ajaxplorer.conf
 * @class AbstractAjxpUser
 * @abstract
 * User abstraction, the "conf" driver must provides its own implementation
 */
abstract class AbstractAjxpUser
{
	var $id;
	var $hasAdmin = false;
	var $rights;
	var $roles;
	var $prefs;
	var $bookmarks;
	var $version;
	var $parentUser;
	
	/**
	 * Conf Storage implementation
	 *
	 * @var AbstractConfDriver
	 */
	var $storage;
	
	function AbstractAjxpUser($id, $storage=null){		
		$this->id = $id;
		if($storage == null){
			$storage = ConfService::getConfStorageImpl();
		}
		$this->storage = $storage;		
		$this->load();
	}
	
	function getCookieString(){
		$hash = $this->getPref("cookie_hash");
		if($hash == ""){
			$hash = md5($this->id.":".time());
			$this->setPref("cookie_hash", $hash);
			$this->save();
		}
		return md5($this->id.":".$hash.":ajxp");
	}
	
	function getId(){
		return $this->id;
	}
	
	function storageExists(){
		
	}
	
	function getVersion(){
		if(!isSet($this->version)) return "";
		return $this->version;
	}
	
	function setVersion($v){
		$this->version = $v;
	}
	
	function addRole($roleId){
		if(!isSet($this->rights["ajxp.roles"])) $this->rights["ajxp.roles"] = array();
		$this->rights["ajxp.roles"][$roleId] = true;
	}
	
	function removeRole($roleId){
		if(isSet($this->rights["ajxp.roles"]) && isSet($this->rights["ajxp.roles"][$roleId])){
			unset($this->rights["ajxp.roles"][$roleId]);
		}
	}
	
	function getRoles(){
		if(isSet($this->rights["ajxp.roles"])) return $this->rights["ajxp.roles"];
		else return array();
	}
	
	function isAdmin(){
		return $this->hasAdmin; 
	}
	
	function setAdmin($boolean){
		$this->hasAdmin = $boolean;
	}
	
	function hasParent(){
		return isSet($this->parentUser);
	}
	
	function setParent($user){
		$this->parentUser = $user;
	}
	
	function getParent(){
		return $this->parentUser;
	}
	
	function canRead($rootDirId){
		$right = $this->getRight($rootDirId);
		if($right == "rw" || $right == "r") return true;
		return false;
	}
	
	function canWrite($rootDirId){
		$right = $this->getRight($rootDirId);
		if($right == "rw" || $right == "w") return true;
		return false;
	}
	
	function getSpecificActionsRights($rootDirId){
		$result = array();
		if(isSet($this->rights["ajxp.actions"]) && isSet($this->rights["ajxp.actions"][$rootDirId])){
			$result = $this->rights["ajxp.actions"][$rootDirId];
		}
		// Check in roles if any
		if(isSet($this->roles)){
			foreach ($this->roles as $role){
				$rights = $role->getSpecificActionsRights($rootDirId);
				if(is_array($rights) && count($rights)) $result = array_merge($result, $rights);
			}
		}		
		return $result;
	}
	
	function setSpecificActionRight($rootDirId, $actionName, $allowed){		
		if(!isSet($this->rights["ajxp.actions"])) $this->rights["ajxp.actions"] = array();
		if(!isset($this->rights["ajxp.actions"][$rootDirId])) $this->rights["ajxp.actions"][$rootDirId] = array();
		$this->rights["ajxp.actions"][$rootDirId][$actionName] = $allowed;
	}
	
	/**
	 * Test if user can switch to this repository
	 *
	 * @param integer $repositoryId
	 * @return boolean
	 */
	function canSwitchTo($repositoryId){
		$repositoryObject = ConfService::getRepositoryById($repositoryId);
		if($repositoryObject == null) return false;
		if($repositoryObject->getAccessType() == "ajxp_conf" && !$this->isAdmin()) return false;
        if($repositoryObject->getUniqueUser() && $this->id != $repositoryObject->getUniqueUser()) return false;
		return ($this->canRead($repositoryId) || $this->canWrite($repositoryId)) ;
	}
	
	function getRight($rootDirId){
		if(isSet($this->rights[$rootDirId]) && $this->rights[$rootDirId] != "") {
			if($this->rights[$rootDirId] == "n") return ""; // Force overriding the role
			return $this->rights[$rootDirId];
		}
		// Check in roles if any
		if(isSet($this->roles)){			
			foreach ($this->roles as $role){
				$right = $role->getRight($rootDirId);
				if($right != "") return $right;
			}
		}
		return "";
	}
	
	function setRight($rootDirId, $rightString){
		// If a role already has this right, set user's right to ""
		if(isSet($this->roles)){			
			foreach ($this->roles as $role){
				$right = $role->getRight($rootDirId);
				if($right == $rightString){
					$rightString = "";
					break;
				}
			}
		}
		$this->rights[$rootDirId] = $rightString;
	}
	
	function removeRights($rootDirId){
		if(isSet($this->rights[$rootDirId])) unset($this->rights[$rootDirId]);
	}
	
	function clearRights(){
		$this->rights = array();
	}
		
	function getPref($prefName){
		if(isSet($this->prefs[$prefName])) return $this->prefs[$prefName];
		return "";
	}
	
	function setPref($prefName, $prefValue){
		$this->prefs[$prefName] = $prefValue;
	}
	
	function setArrayPref($prefName, $prefPath, $prefValue){
		if(!isSet($this->prefs[$prefName])) $this->prefs[$prefName] = array();
		$this->prefs[$prefName][$prefPath] = $prefValue;
	}
	
	function getArrayPref($prefName, $prefPath){
		if(!isSet($this->prefs[$prefName]) || !isSet($this->prefs[$prefName][$prefPath])) return "";
		return $this->prefs[$prefName][$prefPath];
	}
		
	function addBookmark($path, $title="", $repId = -1){
		if(!isSet($this->bookmarks)) $this->bookmarks = array();
		if($repId == -1) $repId = ConfService::getCurrentRootDirIndex();
		if($title == "") $title = basename($path);
		if(!isSet($this->bookmarks[$repId])) $this->bookmarks[$repId] = array();
		foreach ($this->bookmarks[$repId] as $v)
		{
			$toCompare = "";
			if(is_string($v)) $toCompare = $v;
			else if(is_array($v)) $toCompare = $v["PATH"];
			if($toCompare == trim($path)) return ; // RETURN IF ALREADY HERE!
		}
		$this->bookmarks[$repId][] = array("PATH"=>trim($path), "TITLE"=>$title);
	}
	
	function removeBookmark($path){
		$repId = ConfService::getCurrentRootDirIndex();
		if(isSet($this->bookmarks) 
			&& isSet($this->bookmarks[$repId])
			&& is_array($this->bookmarks[$repId]))
			{
				foreach ($this->bookmarks[$repId] as $k => $v)
				{
					$toCompare = "";
					if(is_string($v)) $toCompare = $v;
					else if(is_array($v)) $toCompare = $v["PATH"];					
					if($toCompare == trim($path)) unset($this->bookmarks[$repId][$k]);
				}
			} 		
	}
	
	function renameBookmark($path, $title){
		$repId = ConfService::getCurrentRootDirIndex();
		if(isSet($this->bookmarks) 
			&& isSet($this->bookmarks[$repId])
			&& is_array($this->bookmarks[$repId]))
			{
				foreach ($this->bookmarks[$repId] as $k => $v)
				{
					$toCompare = "";
					if(is_string($v)) $toCompare = $v;
					else if(is_array($v)) $toCompare = $v["PATH"];					
					if($toCompare == trim($path)){
						 $this->bookmarks[$repId][$k] = array("PATH"=>trim($path), "TITLE"=>$title);
					}
				}
			} 		
	}
	
	function getBookmarks()
	{
		if(isSet($this->bookmarks) 
			&& isSet($this->bookmarks[ConfService::getCurrentRootDirIndex()]))
			return $this->bookmarks[ConfService::getCurrentRootDirIndex()];
		return array();
	}
	
	abstract function load();
	
	abstract function save();

	/**
	 * Static function for deleting a user.
	 * Also removes associated rights, preferences and bookmarks.
	 * WARNING : MUST ALSO DELETE THE CHILDREN!
	 *
	 * @param String $userId Login to delete.
	 * @param Array $deletedSubUsers an empty array to be filled by the method
	 * @return null or -1 on error.
	 */
	static abstract function deleteUser($userId, &$deletedSubUsers);
	
	abstract function getTemporaryData($key);
	
	abstract function saveTemporaryData($key, $value);

    /** Decode a user supplied password before using it */
    function decodeUserPassword($password){
        if (function_exists('mcrypt_decrypt'))
        {
             // The initialisation vector is only required to avoid a warning, as ECB ignore IV
             $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
             // We have encoded as base64 so if we need to store the result in a database, it can be stored in text column
             $password = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($this->getId()."\1CDAFx¨op#"), base64_decode($password), MCRYPT_MODE_ECB, $iv));
        }
        return $password;
    }
}


?>
