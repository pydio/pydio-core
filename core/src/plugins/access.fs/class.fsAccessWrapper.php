<?php

require_once(INSTALL_PATH."/server/classes/interface.AjxpWrapper.php");

class fsAccessWrapper implements AjxpWrapper {
		
    var $fp;

    /**
     * Opens the strem
     *
     * @param String $path Maybe in the form "ajxp.fs://repositoryId/pathToFile" 
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
    	if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);
    	$filePath = $repoObject->getOption("PATH")."/".$url["path"];
    	try{
	        $this->fp = @fopen($filePath, $mode, $options);
            return ($this->fp !== false);
    	}catch (Exception $e){
    		return false;
    	}
    }

    function stream_read($count)
    {
    	if(!isSet($this->fp)) return ;
    	return fread($this->fp, $count);
    }

    function stream_write($data)
    {
    	if(!isSet($this->fp)) return ;
    	fwrite($this->fp, $data, strlen($data));
        return strlen($data);
    }

    function stream_eof()
    {
    	if(!isSet($this->fp)) return ;
    	return feof($this->fp);
    }
   
    function stream_close()
    {
    	if(!isSet($this->fp)) return ;
    	fclose($this->fp);
    }
    
    function stream_flush(){
    	
    }
    
}
?>