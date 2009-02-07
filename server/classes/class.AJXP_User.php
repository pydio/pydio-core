<?php

class AJXP_User
{
	var $id;
	var $name;
	var $rights;
	var $prefs;
	var $bookmarks;
	var $version;
	
	function AJXP_User($id){
		$this->id = $id;
		$this->load();
	}
	
	function getId(){
		return $this->id;
	}
	
	function getVersion(){
		if(!isSet($this->version)) return "";
		return $this->version;
	}
	
	function setVersion($v){
		$this->version = $v;
	}
	
	function isAdmin(){
		return (isSet($this->rights["ajxp.admin"])?$this->rights["ajxp.admin"]:false);
	}
	
	function setAdmin($boolean){
		$this->rights["ajxp.admin"] = $boolean;
		AuthService::setUserAdmin($this->id, $boolean);
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