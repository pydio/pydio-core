<?php

class UserSelection
{
	var $files;
	var $varPrefix = "file";
	var $isUnique = true;
	
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
			$this->files[] = utf8_decode($array[$this->varPrefix]);
			$this->isUnique = true;
			return ;
		}
		if(isSet($array[$this->varPrefix."_0"]))
		{
			$index = 0;			
			while(isSet($array[$this->varPrefix."_".$index]))
			{
				$this->files[] = utf8_decode($array[$this->varPrefix."_".$index]);
				$index ++;
			}
			$this->isUnique = false;
			if(count($this->files) == 1) 
			{
				$this->isUnique = true;
			}
			return ;
		}
	}
	
	function isUnique()
	{
		return $this->isUnique;
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
}

?>