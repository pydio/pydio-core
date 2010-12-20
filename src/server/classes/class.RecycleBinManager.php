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
 * Description : A manager for the various recycle bin actions.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class RecycleBinManager
{
	private static $rbmRecycle;
	private static $rbmRelativeRecycle;    
	
	public static function recycleEnabled(){
		return (isSet(self::$rbmRecycle) && self::$rbmRecycle != null && is_string(self::$rbmRecycle));
	}
	
	/**
	 * Initialize manager
	 *
	 * @param String $recyclePath Full path to the recycle folder, INCLUDED optional wrapper data (ajxp.fs://repoId/path/to/recycle).
	 */
	public static function init($repositoryWrapperURL, $recyclePath)
	{
		self::$rbmRecycle = $repositoryWrapperURL.$recyclePath;
		self::$rbmRelativeRecycle = $recyclePath;
	}
	
	public static function getRecyclePath(){
		return self::$rbmRecycle ;
	}
	
	public static function getRelativeRecycle(){
		return self::$rbmRelativeRecycle;
	}
	
	public static function currentLocationIsRecycle($currentLocation){
		return ($currentLocation == self::$rbmRelativeRecycle);
	}
	
	public static function filterActions($action, $selection, $currentLocation, $httpVars = array()){
		if(!self::recycleEnabled()) return array();
		$newArgs = array();

		// FILTER ACTION FOR DELETE
		if($action == "delete" && !self::currentLocationIsRecycle($currentLocation) && !isSet($httpVars["force_deletion"]))
		{
			$newArgs["action"] = "move";
			$newArgs["dest"] = self::$rbmRelativeRecycle;
		}
		// FILTER ACTION FOR RESTORE
		if($action == "restore" && self::currentLocationIsRecycle($currentLocation))
		{
			$originalRep = self::getFileOrigin($selection->getUniqueFile());
			if($originalRep != "")
			{
				$newArgs["action"] = "move";
				$newArgs["dest"] = $originalRep; // CHECK UTF8 HANDLING HERE
			}
		}
		return $newArgs;
		
	}
	
	public static function getCacheFileName()
	{
		return ".ajxp_recycle_cache.ser";
	}
	
	public static function fileToRecycle($originalFilePath)
	{
		$cache = self::loadCache();
		$cache[basename($originalFilePath)] = str_replace("\\", "/", dirname($originalFilePath));
		self::saveCache($cache);
	}

	public static function deleteFromRecycle($filePath)
	{
		$cache = self::loadCache();
		if(array_key_exists(basename($filePath), $cache))
		{
			unset($cache[basename($filePath)]);
		}
		self::saveCache($cache);		
	}
	
	public static function getFileOrigin($filePath)
	{
		$cache = self::loadCache();
		if(is_array($cache) && array_key_exists(basename($filePath), $cache))
		{
			return $cache[basename($filePath)];
		}
		return "";
	}
	
	public static function loadCache(){
		$result = array();
		if(!self::recycleEnabled()) return null;
		$cachePath = self::getRecyclePath()."/".self::getCacheFileName();
		$fp = @fopen($cachePath, "r");
		if($fp){
			$s = "";
			while(!feof($fp)){
				$s .= fread($fp, 4096);
			}
			fclose($fp);
			$result = unserialize($s);
		}		
		return $result;
	}
	
	public static function saveCache($value){
		if(!self::recycleEnabled()) return null;
		$cachePath = self::getRecyclePath()."/".self::getCacheFileName();
		$fp = fopen($cachePath, "w");
		if($fp){
			fwrite($fp, serialize($value));
			fflush($fp);
			fclose($fp);
		}
	}
	
}
?>