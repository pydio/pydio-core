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
 * Description : Abstraction of a user selection passed via http parameters.
 */
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
			$this->files[] = SystemTextEncoding::fromPostedFileName($array[$this->varPrefix]);
			$this->isUnique = true;
			//return ;
		}
		if(isSet($array[$this->varPrefix."_0"]))
		{
			$index = 0;			
			while(isSet($array[$this->varPrefix."_".$index]))
			{
				$this->files[] = SystemTextEncoding::fromPostedFileName($array[$this->varPrefix."_".$index]);
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
