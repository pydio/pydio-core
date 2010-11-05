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
 * Description : User abstraction
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(INSTALL_PATH."/server/classes/class.AbstractAjxpUser.php");

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
		return is_dir(str_replace("AJXP_INSTALL_PATH", INSTALL_PATH, $this->storage->getOption("USERS_DIRPATH")."/".$this->getId()));
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
	 */
	function deleteUser($userId){
		$storage = ConfService::getConfStorageImpl();
		$serialDir = str_replace("AJXP_INSTALL_PATH", INSTALL_PATH, $storage->getOption("USERS_DIRPATH"));
		if(is_file($serialDir."/".$userId."/rights.ser")){
			unlink($serialDir."/".$userId."/rights.ser");
		}
		if(is_file($serialDir."/".$userId."/prefs.ser")){
			unlink($serialDir."/".$userId."/prefs.ser");
		}
		if(is_file($serialDir."/".$userId."/bookmarks.ser")){
			unlink($serialDir."/".$userId."/bookmarks.ser");
		}
		if(is_dir($serialDir."/".$userId)) rmdir($serialDir."/".$userId);
		
	}

}


?>
