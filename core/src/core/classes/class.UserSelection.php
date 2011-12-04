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
 * @package info.ajaxplorer.core
 */
/**
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
	/**
     * Construction selector
     */
	function UserSelection()
	{
		$this->files = array();
	}
	/**
     * Init the selection from the query vars
     * @param array $passedArray
     * @return void
     */
	function initFromHttpVars($passedArray=null)
	{
		if($passedArray != null){
			$this->initFromArray($passedArray);
		}else{
			$this->initFromArray($_GET);
			$this->initFromArray($_POST);
		}
	}
	/**
     * Init from a simple array
     * @param $array
     * @return
     */
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
	/**
     * Does the selection have one or more items
     * @return bool
     */
	function isUnique()
	{
		return $this->isUnique;
	}
	/**
     * Are we currently inside a zip?
     * @return bool
     */
	function inZip(){
		return $this->inZip;
	}
	/**
	 * Returns UTF8 encoded path
	 * @param bool $decode
	 * @return String
	 */
	function getZipPath($decode = false){
		if($decode) return AJXP_Utils::decodeSecureMagic($this->zipFile);
		else return $this->zipFile;
	}
	
	/**
	 * Returns UTF8 encoded path
	 * @param bool $decode
	 * @return String
	 */
	function getZipLocalPath($decode = false){
		if($decode) return AJXP_Utils::decodeSecureMagic($this->localZipPath);
		else return $this->localZipPath;
	}
	/**
     * Number of selected items
     * @return int
     */
	function getCount()
	{
		return count($this->files);
	}
	/**
     * List of items selected
     * @return string[]
     */
	function getFiles()
	{
		return $this->files;
	}
	/**
     * First item of the list
     * @return string
     */
	function getUniqueFile()
	{
		return $this->files[0];
	}
	/**
     * Is this selection empty?
     * @return bool
     */
	function isEmpty()
	{
		if(count($this->files) == 0)
		{
			return true;
		}
		return false;
	}
	/**
     * Detect if there is .zip somewhere in the path
     * @static
     * @param string $dirPath
     * @return array|bool
     */
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
	/**
     * Sets the selected items
     * @param array $files
     * @return void
     */
	function setFiles($files){
		$this->files = $files;
	}
		
}

?>
