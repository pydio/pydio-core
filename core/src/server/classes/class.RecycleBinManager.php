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
		$recycle = ConfService::getRepository()->getOption("RECYCLE_BIN");
		if($recycle == "") return null;
		$cachePath = ConfService::getRepository()->getOption("PATH")."/".$recycle."/".RecycleBinManager::getCacheFileName();
		if(is_file($cachePath))
		{
			$fileLines = file($cachePath);
			$result = unserialize($fileLines[0]);
		}
		return $result;
	}
	
	function saveCache($value){
		$recycle = ConfService::getRepository()->getOption("RECYCLE_BIN");
		if($recycle == "") return ;
		$cachePath = ConfService::getRepository()->getOption("PATH")."/".$recycle."/".RecycleBinManager::getCacheFileName();
		if(!is_dir(ConfService::getRepository()->getOption("PATH")."/".$recycle))
		{
			mkdir(ConfService::getRepository()->getOption("PATH")."/".$recycle);
		}
		$fp = fopen($cachePath, "w");
		fwrite($fp, serialize($value));
		fclose($fp);
	}
	
}
?>