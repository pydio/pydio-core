<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
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
 * @package info.ajaxplorer.core
 * @class UserSelection
 * Abstraction of a user selection passed via http parameters.
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
			$this->files[] = AJXP_Utils::decodeSecureMagic($array[$this->varPrefix]);
			$this->isUnique = true;
			//return ;
		}
		if(isSet($array[$this->varPrefix."_0"]))
		{
			$index = 0;			
			while(isSet($array[$this->varPrefix."_".$index]))
			{
				$this->files[] = AJXP_Utils::decodeSecureMagic($array[$this->varPrefix."_".$index]);
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
			$this->dir = AJXP_Utils::securePath($array[$this->dirPrefix]);
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
	/**
	 * Warning, returns UTF8 encoded path
	 *
	 * @return String
	 */
	function getZipPath($decode = false){
		if($decode) return AJXP_Utils::decodeSecureMagic($this->zipFile);
		else return $this->zipFile;
	}
	
	/**
	 * Warning, returns UTF8 encoded path
	 *
	 * @return String
	 */
	function getZipLocalPath($decode = false){
		if($decode) return AJXP_Utils::decodeSecureMagic($this->localZipPath);
		else return $this->localZipPath;
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
	
	static function detectZip($dirPath){
		if(preg_match("/\.zip\//i", $dirPath) || preg_match("/\.zip$/i", $dirPath)){
			$contExt = strpos(strtolower($dirPath), ".zip");
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
