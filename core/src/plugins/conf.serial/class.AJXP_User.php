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
 * @package info.ajaxplorer.plugins
 * Implementation of the AbstractUser for serial 
 */
class AJXP_User extends AbstractAjxpUser 
{
	var $id;
	var $hasAdmin = false;
	var $rights;
	var $prefs;
	var $bookmarks;
	var $version;
	
	/**
	 * Conf Storage implementation
	 *
	 * @var AbstractConfDriver
	 */
	var $storage;
	
	function AJXP_User($id, $storage=null){
		parent::AbstractAjxpUser($id, $storage);
	}
			
	function storageExists(){		
		return is_dir( AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/".$this->getId() );
	}
				
	
	function load(){
		$serialDir = $this->storage->getOption("USERS_DIRPATH");
		$this->rights = AJXP_Utils::loadSerialFile($serialDir."/".$this->getId()."/rights.ser");
		$this->prefs = AJXP_Utils::loadSerialFile($serialDir."/".$this->getId()."/prefs.ser");
		$this->bookmarks = AJXP_Utils::loadSerialFile($serialDir."/".$this->getId()."/bookmarks.ser");
		if(isSet($this->rights["ajxp.admin"]) && $this->rights["ajxp.admin"] === true){
			$this->setAdmin(true);
		}
		if(isSet($this->rights["ajxp.parent_user"])){
			$this->setParent($this->rights["ajxp.parent_user"]);
		}
		// Load roles
		if(isSet($this->rights["ajxp.roles"])){
			//$allRoles = $this->storage->listRoles();
			$allRoles = AuthService::getRolesList(); // Maintained as instance variable
			foreach (array_keys($this->rights["ajxp.roles"]) as $roleId){
				if(isSet($allRoles[$roleId])){
					$this->roles[$roleId] = $allRoles[$roleId];
				}else{
					unset($this->rights["ajxp.roles"][$roleId]);
				}
			}
		}
	}
	
	function save(){
		$serialDir = $this->storage->getOption("USERS_DIRPATH");
		if($this->isAdmin() === true){
			$this->rights["ajxp.admin"] = true;
		}else{
			$this->rights["ajxp.admin"] = false;
		}
		if($this->hasParent()){
			$this->rights["ajxp.parent_user"] = $this->parentUser;
		}
		AJXP_Utils::saveSerialFile($serialDir."/".$this->getId()."/rights.ser", $this->rights);
		AJXP_Utils::saveSerialFile($serialDir."/".$this->getId()."/prefs.ser", $this->prefs);
		AJXP_Utils::saveSerialFile($serialDir."/".$this->getId()."/bookmarks.ser", $this->bookmarks);		
	}	
	
	function getTemporaryData($key){
		return AJXP_Utils::loadSerialFile($this->storage->getOption("USERS_DIRPATH")."/".$this->getId()."/".$key.".ser");
	}
	
	function saveTemporaryData($key, $value){
		return AJXP_Utils::saveSerialFile($this->storage->getOption("USERS_DIRPATH")."/".$this->getId()."/".$key.".ser", $value);
	}
	
	/**
	 * Static function for deleting a user
	 * 
	 * @param String $userId
	 * @param Array $deletedSubUsers
	 */
	static function deleteUser($userId, &$deletedSubUsers){
		$storage = ConfService::getConfStorageImpl();
		$serialDir = AJXP_VarsFilter::filter($storage->getOption("USERS_DIRPATH"));
		$files = glob($serialDir."/".$userId."/*.ser");
		if(is_array($files) && count($files)){
			foreach ($files as $file){
				unlink($file);
			}
		}
		if(is_dir($serialDir."/".$userId)) rmdir($serialDir."/".$userId);
		
		$authDriver = ConfService::getAuthDriverImpl();
		$confDriver = ConfService::getConfStorageImpl();		
		$users = $authDriver->listUsers();
		foreach (array_keys($users) as $id){
			$object = $confDriver->createUserObject($id);
			if($object->hasParent() && $object->getParent() == $userId){
				AJXP_User::deleteUser($id, $deletedSubUsers);
				$deletedSubUsers[] = $id;
			}
		}
		
	}

}


?>
