<?php

class UserSelection
{
	var $files;
	var $varPrefix = "file";
	var $dirPrefix = "dir";
	var $isUnique = true;
	var $dir;
	
	var $inZip = false;
	var $zipFile;
	var $localZipPath;
	
	function UserSelection()
	{
		$this->files = array();
	}
	
	function initFromHttpVars($passedArray=null)
	{
		if($passedArray != null){
			$this->initFromArray($passedArray);
		}else{
			$this->initFromArray($_GET);
			$this->initFromArray($_POST);
		}
	}
	
	function initFromArray($array)
	{
		if(!is_array($array))
		{
			return ;
		}
		if(isSet($array[$this->varPrefix]) && $array[$this->varPrefix] != "")
		{
			$this->files[] = SystemTextEncoding::fromUTF8($array[$this->varPrefix]);
			$this->isUnique = true;
			//return ;
		}
		if(isSet($array[$this->varPrefix."_0"]))
		{
			$index = 0;			
			while(isSet($array[$this->varPrefix."_".$index]))
			{
				$this->files[] = SystemTextEncoding::fromUTF8($array[$this->varPrefix."_".$index]);
				$index ++;
			}
			$this->isUnique = false;
			if(count($this->files) == 1) 
			{
				$this->isUnique = true;
			}
			//return ;
		}
		if(isSet($array[$this->dirPrefix])){
			$this->dir = $array[$this->dirPrefix];
			if($test = $this->detectZip($this->dir)){
				$this->inZip = true;
				$this->zipFile = $test[0];
				$this->localZipPath = $test[1];
			}
		}
	}
	
	function isUnique()
	{
		return $this->isUnique;
	}
	
	function inZip(){
		return $this->inZip;
	}
	
	function getZipPath(){
		return $this->zipFile;
	}
	
	function getZipLocalPath(){
		return $this->localZipPath;
	}
	
	function getCount()
	{
		return count($this->files);
	}
	
	function getFiles()
	{
		return $this->files;
	}
	
	function getUniqueFile()
	{
		return $this->files[0];
	}
	
	function isEmpty()
	{
		if(count($this->files) == 0)
		{
			return true;
		}
		return false;
	}
	
	function detectZip($dirPath){
		$contExt = strpos(strtolower($dirPath), ".zip");
		if($contExt !== false){
			$zipPath = substr($dirPath, 0, $contExt+4);
			$localPath = substr($dirPath, $contExt+4);
			if($localPath == "") $localPath = "/";
			return array($zipPath, $localPath);
		}
		return false;
	}
	
	function setFiles($files){
		$this->files = $files;
	}
		
}

?>
