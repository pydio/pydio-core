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

global $RBM_RECYCLE;
global $RBM_RELATIVE_RECYCLE;

class RecycleBinManager
{
	
	function recycleEnabled(){
		global $RBM_RECYCLE;
		return (isSet($RBM_RECYCLE) && $RBM_RECYCLE != null && is_string($RBM_RECYCLE));
	}
	
	/**
	 * Initialize manager
	 *
	 * @param String $recyclePath Full path to the recycle folder, INCLUDED optional wrapper data (ajxp.fs://repoId/path/to/recycle).
	 */
	function init($repositoryWrapperURL, $recyclePath)
	{
		global $RBM_RECYCLE, $RBM_RELATIVE_RECYCLE;
		$RBM_RECYCLE = $repositoryWrapperURL.$recyclePath;
		$RBM_RELATIVE_RECYCLE = $recyclePath;
	}
	
	function getRecyclePath(){
		global $RBM_RECYCLE;
		return $RBM_RECYCLE;
	}
	
	function currentLocationIsRecycle($currentLocation){
		global $RBM_RELATIVE_RECYCLE;
		return ($currentLocation == $RBM_RELATIVE_RECYCLE);
	}
	
	function filterActions($action, $selection, $currentLocation){
		if(!RecycleBinManager::recycleEnabled()) return array();
		global $RBM_RELATIVE_RECYCLE;
		$newArgs = array();

		// FILTER ACTION FOR DELETE
		if($action == "delete" && !RecycleBinManager::currentLocationIsRecycle($currentLocation))
		{
			$newArgs["action"] = "move";
			$newArgs["dest"] = $RBM_RELATIVE_RECYCLE;
			$newArgs["dest_node"] = "AJAXPLORER_RECYCLE_NODE";
		}
		// FILTER ACTION FOR RESTORE
		if($action == "restore" && RecycleBinManager::currentLocationIsRecycle($currentLocation))
		{
			$originalRep = RecycleBinManager::getFileOrigin($selection->getUniqueFile());
			if($originalRep != "")
			{
				$newArgs["action"] = "move";
				$newArgs["dest"] = $originalRep;
			}
		}
		return $newArgs;
		
	}
	
	function getCacheFileName()
	{
		return ".ajxp_recycle_cache.ser";
	}
	
	function fileToRecycle($originalFilePath)
	{
		$cache = RecycleBinManager::loadCache();
		$cache[basename($originalFilePath)] = str_replace("\\", "/", dirname($originalFilePath));
		RecycleBinManager::saveCache($cache);
	}

	function deleteFromRecycle($filePath)
	{
		$cache = RecycleBinManager::loadCache();
		if(array_key_exists(basename($filePath), $cache))
		{
			unset($cache[basename($filePath)]);
		}
		RecycleBinManager::saveCache($cache);		
	}
	
	function getFileOrigin($filePath)
	{
		$cache = RecycleBinManager::loadCache();
		if(is_array($cache) && array_key_exists(basename($filePath), $cache))
		{
			return $cache[basename($filePath)];
		}
		return "";
	}
	
	function loadCache(){
		$result = array();
		if(!RecycleBinManager::recycleEnabled()) return null;
		$cachePath = RecycleBinManager::getRecyclePath()."/".RecycleBinManager::getCacheFileName();
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
	
	function saveCache($value){
		if(!RecycleBinManager::recycleEnabled()) return null;
		$cachePath = RecycleBinManager::getRecyclePath()."/".RecycleBinManager::getCacheFileName();
		$fp = fopen($cachePath, "w");
		if($fp){
			fwrite($fp, serialize($value));
			fflush($fp);
			fclose($fp);
		}
	}
	
}
?>