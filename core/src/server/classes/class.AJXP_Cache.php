<?php

class AJXP_Cache {
	
	private static $instance;

	protected $cacheDir;
	protected $cacheId;
	protected $masterFile;
	
	public static function getItem($pluginId, $filepath){
		return new AJXP_Cache($pluginId,$filepath);
	}
	
	public function AJXP_Cache($pluginId, $filepath){
		$this->cacheDir = AJXP_INSTALL_PATH."/server/tmp";
		$this->masterFile = $filepath;
		$this->cacheId = $this->buildCacheId($pluginId, $filepath);
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