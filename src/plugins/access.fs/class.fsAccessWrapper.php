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

		if($split = UserSelection::detectZip(SystemTextEncoding::fromUTF8($url["path"]))){
			// split[0] : path to zip , split[1] : inside path 
			require_once("server/classes/pclzip.lib.php");
			$zip = new PclZip($repoObject->getOption("PATH").$split[0]);
			$data = $zip->extract(PCLZIP_OPT_BY_NAME, substr($split[1], 1), PCLZIP_OPT_EXTRACT_AS_STRING);
			$this->fp = tmpfile();
			fwrite($this->fp, $data[0]["content"]);
			rewind($this->fp);
			return true;
		}else{    	
	    	$filePath = $repoObject->getOption("PATH")."/".$url["path"];
	    	try{
		        $this->fp = @fopen($filePath, $mode, $options);
	            return ($this->fp !== false);
	    	}catch (Exception $e){
	    		return false;
	    	}
		}
    }
    
    function stream_seek($offset, $option){
    	if(!isSet($this->fp)) return ;
    	fseek($this->fp, $offset, SEEK_SET);
    }
    
    function stream_tell(){
    	if(!isSet($this->fp)) return false;    	
    	return ftell($this->fp);
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