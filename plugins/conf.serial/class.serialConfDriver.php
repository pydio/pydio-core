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
 * Description : Serialized Files implementation of AbstractConfDriver
 */
class serialConfDriver {
		
	var $repoSerialFile;
	var $usersSerialDir;
	
	function init($options){
		$this->repoSerialFile = $options["REPOSITORIES_FILEPATH"];
		$this->usersSerialDir = $options["USERS_DIRPATH"];
	}
	
	// SAVE / EDIT / CREATE / DELETE REPOSITORY
	function listRepositories(){
		return Utils::loadSerialFile($this->repoSerialFile);
		
	}
	/**
	 * Unique ID of the repositor
	 *
	 * @param String $repositoryId
	 * @return Repository
	 */
	function getRepositoryById($repositoryId){
		$repositories = Utils::loadSerialFile($this->repoSerialFile);
		if(isSet($repositories[$repositoryId])){
			return $repositories[$repositoryId];		
		}
		return null;
	}
	/**
	 * Store a newly created repository 
	 *
	 * @param Repository $repositoryObject
	 * @param Boolean $update 
	 * @return -1 if failed
	 */
	function saveRepository($repositoryObject, $update = false){
		$repositories = Utils::loadSerialFile($this->repoSerialFile);
		if(!$update){
			$repositoryObject->writeable = true;
			$repositories[$repositoryObject->getUniqueId()] = $repositoryObject;
		}else{
			foreach ($repositories as $index => $repo){
				if($repo->getUniqueId() == $repositoryObject->getUniqueId()){
					$repositories[$index] = $repositoryObject;
					break;
				}
			}
		}
		$res = Utils::saveSerialFile($this->repoSerialFile, $repositories);
		if($res == -1){
			return $res;
		}		
	}
	/**
	 * Delete a repository, given its unique ID.
	 *
	 * @param String $repositoryId
	 */	
	function deleteRepository($repositoryId){
		$repositories = Utils::loadSerialFile($this->repoSerialFile);
		$newList = array();
		foreach ($repositories as $repo){
			if($repo->getUniqueId() != $repositoryId){
				$newList[$repo->getUniqueId()] = $repo;
			}
		}
		Utils::saveSerialFile($this->repoSerialFile, $newList);
	}
	
	// SAVE / EDIT / CREATE / DELETE USER OBJECT (except password)
	/**
	 * Retrieve the list of available users
	 * @return Array
	 */
	function listUsers(){
		
	}
	/**
	 * Load a user by its ID
	 *
	 * @param AJXP_User $userObject The object to initialize (must contain at least its own ID).
	 * @return AJXP_User
	 */
	function loadUser(&$userObject){
		$userObject->rights = Utils::loadSerialFile($this->usersSerialDir."/".$userObject->getId()."/rights.ser");
		$userObject->prefs = Utils::loadSerialFile($this->usersSerialDir."/".$userObject->getId()."/prefs.ser");
		$userObject->bookmarks = Utils::loadSerialFile($this->usersSerialDir."/".$userObject->getId()."/bookmarks.ser");
	}
	/**
	 * Add or update a given User object
	 *
	 * @param AJXP_User $userObject
	 */
	function saveUser($userObject){
		Utils::saveSerialFile($this->usersSerialDir."/".$userObject->getId()."/rights.ser", $userObject->rights);
		Utils::saveSerialFile($this->usersSerialDir."/".$userObject->getId()."/prefs.ser", $userObject->prefs);
		Utils::saveSerialFile($this->usersSerialDir."/".$userObject->getId()."/bookmarks.ser", $userObject->bookmarks);
	}
	/**
	 * Delete a user by it's ID.
	 *
	 * @param String $userId
	 */
	function deleteUser($userId){
		if(is_file($this->usersSerialDir."/".$userId."/rights.ser")){
			unlink($this->usersSerialDir."/".$userId."/rights.ser");
		}
		if(is_file($this->usersSerialDir."/".$userId."/prefs.ser")){
			unlink($this->usersSerialDir."/".$userId."/prefs.ser");
		}
		if(is_file($this->usersSerialDir."/".$userId."/bookmarks.ser")){
			unlink($this->usersSerialDir."/".$userId."/bookmarks.ser");
		}
		rmdir($this->usersSerialDir."/".$userId);
	}
	

}
?>