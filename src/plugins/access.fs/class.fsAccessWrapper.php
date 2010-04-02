<?php

require_once(INSTALL_PATH."/server/classes/interface.AjxpWrapper.php");

class fsAccessWrapper implements AjxpWrapper {
		
	/**
	 * FileHandle resource
	 *
	 * @var resource
	 */
    private $fp;
	/**
	 * DirHandle resource
	 *
	 * @var resource
	 */
    private $dH;
    
    /**
     * If dH is not used but an array containing the listing 
     * instead. dH == -1 in that case.
     *
     * @var array()
     */
    private static $currentListing;
    private static $currentListingKeys;
    private static $currentListingIndex;
    private static $currentFileKey;
	private static $crtZip;
	private $realPath;

    /**
     * Initialize the stream from the given path. 
     *
     * @param string $path
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $streamType, $storeOpenContext = false, $skipZip = false){
    	$url = parse_url($path);
    	$repoId = $url["host"];
    	$repoObject = ConfService::getRepositoryById($repoId);
    	if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);
    	$split = UserSelection::detectZip($url["path"]);
    	$insideZip = false;
    	if($split && $streamType == "file" && $split[1] != "/") $insideZip = true;
    	if($split && $streamType == "dir") $insideZip = true;
    	if($skipZip) $insideZip = false;
    	//var_dump($path);
    	//var_dump($skipZip);
    	// Inside a zip : copy the file to a tmp file and return a reference to it    	    
		if($insideZip){    	
			$zipPath = $split[0];
			$localPath = $split[1];
			require_once(INSTALL_PATH."/server/classes/pclzip.lib.php");
			//print($streamType.$path);
		   	if($streamType == "file"){	
		   		if(self::$crtZip == null ||  !is_array(self::$currentListingKeys)){
		   			$tmpDir = sys_get_temp_dir() . "/" . md5(time()-rand());
		   			mkdir($tmpDir);
		   			$tmpFileName = $tmpDir."/".basename($localPath);
		   			register_shutdown_function(array("fsAccessWrapper", "removeTmpFile"), $tmpDir, $tmpFileName);
					$crtZip = new PclZip(AJXP_Utils::securePath($repoObject->getOption("PATH").$zipPath));
					$crtZip->extract(PCLZIP_OPT_BY_NAME, substr($localPath, 1), PCLZIP_OPT_REMOVE_ALL_PATH, PCLZIP_OPT_PATH, $tmpDir);
					if($storeOpenContext) self::$crtZip = $crtZip;
					return $tmpFileName;
		   		}else{
		   			$key = basename($localPath);
		   			if(array_key_exists($key, self::$currentListing)){
		   				self::$currentFileKey = $key;
		   				return -1;
		   			}else{
			   			throw new AJXP_Exception("Cannot find key");
		   			}
		   		}
		   	}else{
				$crtZip = new PclZip(AJXP_Utils::securePath($repoObject->getOption("PATH").$zipPath));
				$liste = $crtZip->listContent();				
				if($storeOpenContext) self::$crtZip = $crtZip;
				$folders = array(); $files = array();
				if($localPath[strlen($localPath)-1] != "/") $localPath.="/";
				foreach ($liste as $item){
					$stored = $item["stored_filename"];			
					if($stored[0] != "/") $stored = "/".$stored;						
					$pathPos = strpos($stored, $localPath);
					if($pathPos !== false){
						$afterPath = substr($stored, $pathPos+strlen($localPath));
						if($afterPath != "" && (strpos($afterPath, "/")=== false || strpos($afterPath, "/") == strlen($afterPath)-1)){
							$statValue = array();
							$statValue[2] = $statValue["mode"] = ($item["folder"]?"00040000":"0100000");
							$statValue[7] = $statValue["size"] = $item["size"];
							$statValue[8] = $statValue["atime"] = $item["mtime"];
							$statValue[9] = $statValue["mtime"] = $item["mtime"];
							$statValue[10] = $statValue["ctime"] = $item["mtime"];
							if(strpos($afterPath, "/") == strlen($afterPath)-1){
								$afterPath = substr($afterPath, 0, strlen($afterPath)-1);
							}
							//$statValue["filename"] = $zipPath.$localPath.$afterPath;
							if($item["folder"]){
								$folders[$afterPath] = $statValue;
							}else{
								$files[$afterPath] = $statValue;
							}
						}						
					}
				}
				self::$currentListing = array_merge($folders, $files);
				self::$currentListingKeys = array_keys(self::$currentListing);
				self::$currentListingIndex = 0;
				return -1;
		   	}
		}else{
			return $repoObject->getOption("PATH").$url["path"];
		}    	
    }
    
    public static function removeTmpFile($tmpDir, $tmpFile){
    	if(is_file($tmpFile)) unlink($tmpFile);
    	if(is_dir($tmpDir)) rmdir($tmpDir);
    }
    
    protected static function closeWrapper(){
    	if(self::$crtZip != null) {
    		self::$crtZip = null;
    		self::$currentListing  = null;
    		self::$currentListingKeys = null;
    		self::$currentListingIndex = null;
    		self::$currentFileKey = null;
    	}
    }
    
    public static function getRealFSReference($path){
    	$contextOpened =false;
    	if(self::$crtZip != null){
    		$contextOpened = true;
    		$crtZip = self::$crtZip;
    		self::$crtZip = null;
    	}
    	$realPath = self::initPath($path, "file");
    	if(!$contextOpened) {
    		self::closeWrapper();
    	}else{
    		self::$crtZip = $crtZip;
    	}
    	return $realPath;
    }
    
    public static function copyFileInStream($path, $stream){
    	$fp = fopen(self::getRealFSReference($path), "rb");
    	while (!feof($fp)) {
 			$data = fread($fp, 4096);
 			fwrite($stream, $data, strlen($data));
    	}
    	fclose($fp);
    }    
    
    public static function changeMode($path, $chmodValue){
    	$realPath = self::initPath($path, "file");
    	chmod($realPath, $chmodValue);
    }
    
    /**
     * Opens the strem
     *
     * @param String $path Maybe in the form "ajxp.fs://repositoryId/pathToFile" 
     * @param String $mode
     * @param unknown_type $options
     * @param unknown_type $opened_path
     * @return unknown
     */
    public function stream_open($path, $mode, $options, &$context)
    {
    	try{
	    	$this->realPath = AJXP_Utils::securePath(self::initPath($path, "file"));
    	}catch (Exception $e){
    		AJXP_Logger::logAction("error", array("message" => "Error while opening stream $path"));
    		return false;
    	}
    	if($this->realPath == -1){
    		$this->fp = -1;
    		return true;
    	}else{
	        $this->fp = fopen($this->realPath, $mode, $options);
	        return ($this->fp !== false);
    	}		
    }
    
    public function stream_seek($offset , $whence = SEEK_SET){
    	fseek($this->fp, $offset, SEEK_SET);
    }
    
    public function stream_tell(){
    	return ftell($this->fp);
    }
    
    public function stream_stat(){
        $PROBE_REAL_SIZE = ConfService::getConf("PROBE_REAL_SIZE");    	
    	if(is_resource($this->fp)){
    		$statValue = fstat($this->fp);
    		if($statValue[2] > 0 && $PROBE_REAL_SIZE && !ini_get("safe_mode")){
	    		$statValue[7] = $statValue["size"] = floatval(trim($this->getTrueSizeOnFileSystem($this->realPath)));
    		}
	    	return $statValue;    		
    	}
    	if(is_resource($this->dH)){
    		return fstat($this->dH);    		
    	}
    	if($this->fp == -1){
    		return self::$currentListing[self::$currentFileKey];
    	}
    	return null;
    }
    
    public function url_stat($path, $flags){    
    	// File and zip case	
    	if($fp = @fopen($path, "r")){    		
	    	$stat = fstat($fp);
    		fclose($fp);
    		return $stat;
    	}
    	// Folder case
    	$real = self::initPath($path, "dir", false, true);
    	if($real!=-1 && $fp = @opendir($real)){
    		closedir($fp);
    		$stat = stat($real);
    		return $stat;
    	}
    	// Zip Folder case
    	$search = basename($path);
    	$real = self::initPath(dirname($path), "dir");
    	if($real == -1){
    		if(array_key_exists($search, self::$currentListing)){	    		
    			return self::$currentListing[$search];
    		}
    	}    	
    	// Non existing file
   		return null;
    }
    
    public function rename($from, $to){
    	return rename($this->initPath($from, "file", false, true), $this->initPath($to, "file", false, true));
    }
    
    public function stream_read($count){
    	return fread($this->fp, $count);
    }

    public function stream_write($data){
    	fwrite($this->fp, $data, strlen($data));
        return strlen($data);
    }

    public function stream_eof(){
    	return feof($this->fp);
    }
   
    public function stream_close(){
    	if(isSet($this->fp) && $this->fp!=-1 && $this->fp!==false){
    		fclose($this->fp);
    	}
    }
    
    public function stream_flush(){
    	if(isSet($this->fp) && $this->fp!=-1 && $this->fp!==false){
	    	fflush($this->fp);
    	}
    }
    
    public function unlink($path){
    	$this->realPath = self::initPath($path, "file", false, true);
    	return unlink($this->realPath);
    }
    
    public function rmdir($path, $options){
    	$this->realPath = self::initPath($path, "file", false, true);
    	return rmdir($this->realPath);
    }
    
    public function mkdir($path, $mode, $options){
    	return mkdir(self::initPath($path, "file"), $mode);
    }
    
    /**
     * Readdir functions
     *
     * @param string $path
     * @param int $options
     */
	public function dir_opendir ($path , $options ){
		$this->realPath = self::initPath($path, "dir", true);	
		if(is_string($this->realPath)){			
			$this->dH = @opendir($this->realPath);
		}else if($this->realPath == -1){
			$this->dH = -1;
		}
		return $this->dH !== false;
	}
	public function dir_closedir  (){
		self::closeWrapper();
		if($this->dH == -1){			
			return true;
		}else{
			return closedir($this->dH);
		}
	}
	public function dir_readdir (){
		if($this->dH == -1){			
			if(isSet(self::$currentListingKeys[self::$currentListingIndex])){
				self::$currentListingIndex ++;
				return self::$currentListingKeys[self::$currentListingIndex-1];
			}else{
				return false;
			}
		}else{
			return readdir($this->dH);
		}
	}
	public function dir_rewinddir ()    {
		if($this->dH == -1){
			self::$currentListingIndex = 0;
		}else{
			return rewinddir($this->dH);
		}
	}
	
	protected function getTrueSizeOnFileSystem($file) {
		if (!(strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')){
			$cmd = "stat -L -c%s \"".$file."\"";
			$val = trim(`$cmd`);
			if (strlen($val) == 0 || floatval($val) == 0)
			{
				// No stat on system
				$cmd = "ls -1s --block-size=1 \"".$file."\"";
				$val = trim(`$cmd`);
			}
			if (strlen($val) == 0 || floatval($val) == 0)
			{
				// No block-size on system (probably busybox), try long output
				$cmd = "ls -l \"".$file."\"";

				$arr = explode("/[\s]+/", `$cmd`);
				$val = trim($arr[4]);
			}
			if (strlen($val) == 0 || floatval($val) == 0){
				// Still not working, get a value at least, not 0...
				$val = sprintf("%u", filesize($file));
			}
			return floatval($val);
		}else if (class_exists("COM")){
			$fsobj = new COM("Scripting.FileSystemObject");
			$f = $fsobj->GetFile($file);
			return floatval($f->Size);
		}
		else if (is_file($file)){
			return exec('FOR %A IN ("'.$file.'") DO @ECHO %~zA');
		}
		else return sprintf("%u", filesize($file));
	}
	
}
?>