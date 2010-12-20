<?php
/**
 * @package info.ajaxplorer.plugins
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
 * Description : The most used and standard plugin : FileSystem access
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(INSTALL_PATH."/plugins/access.fs/class.fsAccessWrapper.php");

class webdavAccessWrapper extends fsAccessWrapper {		

    /**
     * Initialize the stream from the given path. 
     * Concretely, transform ajxp.webdav:// into webdav://
     *
     * @param string $path
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $streamType, $storeOpenContext = false, $skipZip = false){    	
    	$url = parse_url($path);
    	$repoId = $url["host"];
    	$repoObject = ConfService::getRepositoryById($repoId);
    	if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);
		$path = $url["path"];
		$host = $repoObject->getOption("HOST");
		$host = str_replace(array("http", "https"), array("webdav", "webdavs"), $host);
		// MAKE SURE THERE ARE NO // OR PROBLEMS LIKE THAT...
		$basePath = $repoObject->getOption("PATH");		
		if($basePath[strlen($basePath)-1] == "/"){
			$basePath = substr($basePath, 0, -1);			
		}
		if($basePath[0] != "/"){
			$basePath = "/$basePath";
		}
		$path = AJXP_Utils::securePath($path);
		if($path[0] == "/"){
			$path = substr($path, 1);
		}
		// SHOULD RETURN webdav://host_server/uri/to/webdav/folder
		return $host.$basePath."/".$path;
    }    
    
    /**
     * Opens the stream
     * Diff with parent class : do not "securePath", as it removes double slash
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
	    	$this->realPath = $this->initPath($path, "file");
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
    
    /**
     * Stats the given path. 
     * Fix PEAR by adding S_ISREG mask when file case.
     *
     * @param unknown_type $path
     * @param unknown_type $flags
     * @return unknown
     */
    public function url_stat($path, $flags){    
    	// File and zip case	
    	if($fp = @fopen($path, "r")){    		
	    	$stat = fstat($fp);
    		fclose($fp);
    		if($stat["mode"] == 0666){
    			$stat[2] = $stat["mode"] |= 0100000; // S_ISREG
    		}
    		return $stat;
    	}    	    	

    	// Non existing file
   		return null;
    }
    
    /**
     * Opens a handle to the dir
     * Fix PEAR by being sure it ends up with "/", to avoid 
     * adding the current dir to the children list.
     *
     * @param unknown_type $path
     * @param unknown_type $options
     * @return unknown
     */
	public function dir_opendir ($path , $options ){
		$this->realPath = $this->initPath($path, "dir", true);	
		if($this->realPath[strlen($this->realPath)-1] != "/"){
			$this->realPath.="/";
		}
		if(is_string($this->realPath)){			
			$this->dH = @opendir($this->realPath);
		}else if($this->realPath == -1){
			$this->dH = -1;
		}
		return $this->dH !== false;
	}

	
	// DUPBLICATE STATIC FUNCTIONS TO BE SURE 
	// NOT TO MESS WITH self:: CALLS
	
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
}
?>