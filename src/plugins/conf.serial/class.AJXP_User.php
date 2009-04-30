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
	
	function getRight($rootDirId){
		if(isSet($this->rights[$rootDirId])) return $this->rights[$rootDirId];
		return "";
	}
	
	function setRight($rootDirId, $rightString){
		$this->rights[$rootDirId] = $rightString;
	}
	
	function removeRights($rootDirId){
		if(isSet($this->rights[$rootDirId])) unset($this->rights[$rootDirId]);
	}
		
	function getPref($prefName){
		if(isSet($this->prefs[$prefName])) return $this->prefs[$prefName];
		return "";
	}
	
	function setPref($prefName, $prefValue){
		$this->prefs[$prefName] = $prefValue;
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
	
	function load(){
		$serialDir = $this->storage->getOption("USERS_DIRPATH");
		$this->rights = Utils::loadSerialFile($serialDir."/".$this->getId()."/rights.ser");
		$this->prefs = Utils::loadSerialFile($serialDir."/".$this->getId()."/prefs.ser");
		$this->bookmarks = Utils::loadSerialFile($serialDir."/".$this->getId()."/bookmarks.ser");
		if(isSet($this->rights["ajxp.admin"]) && $this->rights["ajxp.admin"] === true){
			$this->setAdmin(true);
		}
	}
	
	function save(){
		$serialDir = $this->storage->getOption("USERS_DIRPATH");
		if($this->isAdmin() === true){
			$this->rights["ajxp.admin"] = true;
		}else{
			$this->rights["ajxp.admin"] = false;
		}
		Utils::saveSerialFile($serialDir."/".$this->getId()."/rights.ser", $this->rights);
		Utils::saveSerialFile($serialDir."/".$this->getId()."/prefs.ser", $this->prefs);
		Utils::saveSerialFile($serialDir."/".$this->getId()."/bookmarks.ser", $this->bookmarks);		
	}	
	
	function getTemporaryData($key){
		return Utils::loadSerialFile($this->storage->getOption("USERS_DIRPATH")."/".$this->getId()."/".$key.".ser");
	}
	
	function saveTemporaryData($key, $value){
		return Utils::saveSerialFile($this->storage->getOption("USERS_DIRPATH")."/".$this->getId()."/".$key.".ser", $value);
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
