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
 * Manager for various recycle bin actions
 */
class RecycleBinManager
{
	private static $rbmRecycle;
	private static $rbmRelativeRecycle;    
	
	public static function recycleEnabled(){
		return (isSet(self::$rbmRecycle) && self::$rbmRecycle != null && is_string(self::$rbmRecycle));
	}
	
    /**
     * Initialize manager
     * @static
     * @param $repositoryWrapperURL
     * @param $recyclePath
     * @return void
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