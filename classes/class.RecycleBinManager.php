<?php
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
		if(ConfService::getRecycleBinDir() == "") return null;
		$cachePath = ConfService::getRootDir()."/".ConfService::getRecycleBinDir()."/".RecycleBinManager::getCacheFileName();
		if(is_file($cachePath))
		{
			$fileLines = file($cachePath);
			$result = unserialize($fileLines[0]);
		}
		return $result;
	}
	
	function saveCache($value){
		if(ConfService::getRecycleBinDir() == "") return ;
		$cachePath = ConfService::getRootDir()."/".ConfService::getRecycleBinDir()."/".RecycleBinManager::getCacheFileName();
		if(!is_dir(ConfService::getRootDir()."/".ConfService::getRecycleBinDir()))
		{
			mkdir(ConfService::getRootDir()."/".ConfService::getRecycleBinDir());
		}
		$fp = fopen($cachePath, "w");
		fwrite($fp, serialize($value));
		fclose($fp);
	}
	
}
?>