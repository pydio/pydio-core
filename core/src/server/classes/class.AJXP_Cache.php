<?php

class AJXP_Cache {
	
	private static $instance;

	protected $cacheDir;
	protected $cacheId;
	protected $masterFile;
	protected $dataCallback;
	
	/**
	 * Create an AJXP_Cache instance
	 * @param string $pluginId
	 * @param string $filepath
	 * @param Function $dataCallback
	 * @return AJXP_Cache
	 */
	public static function getItem($pluginId, $filepath, $dataCallback){
		return new AJXP_Cache($pluginId,$filepath, $dataCallback);
	}
	
	public static function clearItem($pluginId, $filepath){
		$inst = new AJXP_Cache($pluginId,$filepath, false);
		AJXP_Logger::debug("SHOULD REMOVE ".$inst->getId());
		if(file_exists($inst->getId())){
			@unlink($inst->getId());
		}
	}
	
	public function AJXP_Cache($pluginId, $filepath, $dataCallback){
		$this->cacheDir = AJXP_CACHE_DIR;
		$this->masterFile = $filepath;
		$this->dataCallback = $dataCallback;
		$this->cacheId = $this->buildCacheId($pluginId, $filepath);
	}
	
	public function getData(){
		if(!$this->hasCachedVersion()){
			AJXP_Logger::debug("caching data");
			$result = call_user_func($this->dataCallback, $this->masterFile, $this->cacheId);
			if($result !== false){
				$this->touch();
			}
		}else{
			AJXP_Logger::debug("getting from cache");
		}
		return file_get_contents($this->cacheId);
	}
	
	public function writeable(){
		return is_dir($this->cacheDir) && is_writeable($this->cacheDir);
	}
	
	public function getId(){
		return $this->cacheId;
	}
	
	public function hasCachedVersion(){
		$modifTime = filemtime($this->masterFile);
		if(file_exists($this->cacheId) && filemtime($this->cacheId) >= $modifTime){
			return true;
		}
		return false;
	}
	
	public function touch(){
		touch($this->cacheId, filemtime($this->masterFile));
	}
	
	
	protected function buildCacheId($pluginId, $filePath){
		$info = pathinfo($filePath);
		return $this->cacheDir ."/".$pluginId."_".md5($filePath).".".$info["extension"];
	}
	
	
}

?>