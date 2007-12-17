<?php

class AJXP_User
{
	var $id;
	var $name;
	var $rights;
	var $prefs;
	var $bookmarks;
	
	function AJXP_User($id){
		$this->id = $id;
		$this->load();
	}
	
	function getId(){
		return $this->id;
	}
	
	function getRight($rootDirId){
		if(isSet($this->rights[$rootDirId])) return $this->rights[$rootDirId];
		return "";
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
		
	function addBookmark($path, $repId = -1){
		if(!isSet($this->bookmarks)) $this->bookmarks = array();
		if($repId == -1) $repId = ConfService::getCurrentRootDirIndex();
		if(!isSet($this->bookmarks[$repId])) $this->bookmarks[$repId] = array();
		foreach ($this->bookmarks[$repId] as $v)
		{
			if($v == trim($path)) return ; // RETURN IF ALREADY HERE!
		}
		$this->bookmarks[$repId][] = trim($path);
	}
	
	function removeBookmark($path){
		if(isSet($this->bookmarks) 
			&& isSet($this->bookmarks[ConfService::getCurrentRootDirIndex()])
			&& is_array($this->bookmarks[ConfService::getCurrentRootDirIndex()]))
			{
				foreach ($this->bookmarks[ConfService::getCurrentRootDirIndex()] as $k => $v)
				{
					if($v == trim($path)) unset($this->bookmarks[ConfService::getCurrentRootDirIndex()][$k]);
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
		$this->rights = $this->loadUserFile("rights");
		$this->prefs = $this->loadUserFile("prefs");
		$this->bookmarks = $this->loadUserFile("bookmarks");
	}
	
	function save(){
		$this->saveUserFile("rights", $this->rights);
		$this->saveUserFile("prefs", $this->prefs);
		$this->saveUserFile("bookmarks", $this->bookmarks);
	}
	
	function loadUserFile($file){
		$result = array();
		if(is_file(USERS_DIR."/".$this->id."/".$file.".ser"))
		{
			$fileLines = file(USERS_DIR."/".$this->id."/".$file.".ser");
			$result = unserialize($fileLines[0]);
		}
		return $result;
	}
	
	function saveUserFile($file, $value){
		if(!is_dir(USERS_DIR."/".$this->id)) mkdir(USERS_DIR."/".$this->id);
		$fp = fopen(USERS_DIR."/".$this->id."/".$file.".ser", "w");
		fwrite($fp, serialize($value));
		fclose($fp);
	}
		
}


?>