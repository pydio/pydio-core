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
class AbstractAjxpUser
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
	
	function isAdmin(){
		return $this->hasAdmin; 
	}
	
	function setAdmin($boolean){
		$this->hasAdmin = $boolean;
	}
	
	function canRead($rootDirId){
		$right = $this->getRight($rootDirId);
		if($right == "rw" || $right == "r") return true;
		return false;
	}
	
	function canWrite($rootDirId){
		$right = $this->getRight($rootDirId);
		if($right == "rw") return true;
		return false;
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
	}
	
	function save(){
	}	

	function deleteUser($userId){
	}
	
	function getTemporaryData($key){
		
	}
	
	function saveTemporaryData($key, $value){
		
	}

    /** Decode a user supplied password before using it */
    function decodeUserPassword($password){
        if (function_exists('mcrypt_decrypt'))
        {
             $users = AuthService::listUsers();
             // The initialisation vector is only required to avoid a warning, as ECB ignore IV
             $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
             // We have encoded as base64 so if we need to store the result in a database, it can be stored in text column
             $password = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($this->getId()."\1CDAFx¨op#"), base64_decode($password), MCRYPT_MODE_ECB, $iv));
        }
        return $password;
    }
}


?>
