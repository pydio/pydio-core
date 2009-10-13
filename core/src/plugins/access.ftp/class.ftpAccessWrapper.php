<?php

require_once(INSTALL_PATH."/server/classes/interface.AjxpWrapper.php");
require_once(INSTALL_PATH."/plugins/access.ftp/class.ftpAccessDriver.php");

class ftpAccessWrapper extends ftpAccessDriver implements AjxpWrapper {
		
    var $connectId;
    var $path;
    var $cacheWHandler;
    var $cacheRHandler;

    function ftpAccessWrapper(){
    	// Warning, do not call parent constructor
    }
    
    function createFTPLink(){
    	return parent::createFTPLink(false);
    }
    
    /**
     * Opens the strem
     *
     * @param String $path Maybe in the form "ajxp.ftp://repositoryId/pathToFile" 
     * @param String $mode
     * @param unknown_type $options
     * @param unknown_type $opened_path
     * @return unknown
     */
    function stream_open($path, $mode, $options, &$opened_path)
    {
    	$url = parse_url($path);
    	$repoId = $url["host"];
    	$repoObject = ConfService::getRepositoryById($repoId);
    	if(!isSet($repoObject)) return false;
    	
    	$this->repository = $repoObject;    	
    	$this->user = $this->getUserName($repoObject);
    	$this->password = $this->getPassword($repoObject);
    	
    	$res = $this->initRepository();
    	$this->path = $this->secureFtpPath($this->path."/".$url["path"]);
    	
    	if($mode == "r"){    		
    		if ($contents = @ftp_rawlist($this->connect,$this->path)!==FALSE){
	    		$this->cacheRHandler = tmpfile();
		        @ftp_fget($this->connect, $this->cacheRHandler, $this->path, FTP_BINARY, 0);
	    		rewind($this->cacheRHandler);
    		}
    	}
    	
    	return true;    	
    }

    function stream_read($count)
    {
    	if(!isSet($this->connect)) return ;
    	if(!isSet($this->cacheRHandler)){
    		return false;
    	}
        return fread($this->cacheRHandler, $count);
    }

    function stream_write($data)
    {
    	if(!isSet($this->connect)) return ;
    	if(!isSet($this->cacheWHandler)){
    		$this->cacheWHandler = tmpfile();
    	}
    	fwrite($this->cacheWHandler, $data, strlen($data));    	
    	return strlen($data);
    }
    
    function stream_flush(){
    	if(!isSet($this->connect) || !isset($this->cacheWHandler)) return ;
    	rewind($this->cacheWHandler);
    	ftp_fput($this->connect, $this->path, $this->cacheWHandler, FTP_BINARY, 0);
    }
    
    function stream_eof()
    {
    	if(!isSet($this->connect)) return true;
    	if(!isSet($this->cacheRHandler)) return true;    	
    	return feof($this->cacheRHandler);
    }
    
    function stream_close()
    {
    	ftp_close($this->connect);
    	if(isSet($this->cacheWHandler)){
    		fclose($this->cacheWHandler);
    	}
    	if(isSet($this->cacheRHandler)){
    		fclose($this->cacheRHandler);
    	}
    }
    
}
?>