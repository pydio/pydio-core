<?php

class SerialMetaManager extends AJXP_Plugin {
	
	private static $currentMetaName;
	private static $metaCache;
		
	public function extractMeta($currentFile, &$metadata, $wrapperClassName, &$realFile){
		$base = basename($currentFile);
		$this->loadMetaFileData($currentFile);		
		if(is_array(self::$metaCache) && array_key_exists($base, self::$metaCache)){
			$metadata = array_merge($metadata, self::$metaCache[$base]);
		}else{
			//$this->addMeta($currentFile, array("testKey1"=>"value1"));
		}
	}
	
	public function updateMetaLocation($oldFile, $newFile = null, $copy = false){
		$this->loadMetaFileData($oldFile);
		$oldKey = basename($oldFile);
		if(!array_key_exists($oldKey, self::$metaCache)){
			return;
		}
		$oldData = self::$metaCache[$oldKey];
		// If it's a move or a delete, delete old data
		if(!$copy){
			unset(self::$metaCache[$oldKey]);
			$this->saveMetaFileData($oldFile);
		}
		// If copy or move, copy data.
		if($newFile != null){
			$this->addMeta($newFile, $oldData);
		}
	}
	
	public function addMeta($currentFile, $dataArray){
		$this->loadMetaFileData($currentFile);
		self::$metaCache[basename($currentFile)] = $dataArray;
		$this->saveMetaFileData($currentFile);
	}
	
	protected function loadMetaFileData($currentFile){
		$metaFile = dirname($currentFile)."/.ajxp_meta";
		if(self::$currentMetaName == $metaFile && is_array(self::$metaCache)){
			return;
		}
		if(is_file($metaFile) && is_readable($metaFile)){
			$rawData = file_get_contents($metaFile);
			self::$metaCache = unserialize($rawData);
		}else{
			self::$metaCache = array();
		}
	}
	
	protected function saveMetaFileData($currentFile){
		$metaFile = dirname($currentFile)."/.ajxp_meta";
		if((is_file($metaFile) && is_writable($metaFile)) || is_writable(dirname($metaFile))){
			$fp = fopen($metaFile, "w");
			fwrite($fp, serialize(self::$metaCache), strlen(serialize(self::$metaCache)));
			fclose($fp);
		}
	}
	
}

?>