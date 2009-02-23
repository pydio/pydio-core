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
class RecycleBinManager
{
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
		if(array_key_exists(basename($filePath), $cache))
		{
			return $cache[basename($filePath)];
		}
		return "";
	}
	
	function loadCache(){
		$result = array();
		$repository = ConfService::getRepository();
		$recycle = $repository->getOption("RECYCLE_BIN");
		if($recycle == "") return null;
		$cachePath = $repository->getOption("PATH")."/".$recycle."/".RecycleBinManager::getCacheFileName();
		if(is_file($cachePath))
		{
			$fileLines = file($cachePath);
			$result = unserialize($fileLines[0]);
		}
		return $result;
	}
	
	function saveCache($value){
		$repository = ConfService::getRepository();
		$recycle = $repository->getOption("RECYCLE_BIN");
		if($recycle == "") return ;
		$cachePath = $repository->getOption("PATH")."/".$recycle."/".RecycleBinManager::getCacheFileName();
		if(!is_dir($repository->getOption("PATH")."/".$recycle))
		{
			mkdir($repository->getOption("PATH")."/".$recycle);
		}
		$fp = fopen($cachePath, "w");
		fwrite($fp, serialize($value));
		fclose($fp);
	}
	
}
?>