<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 *
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * AJXP_Plugin to access an ftp server over SSH
 */
class sftpAccessDriver extends fsAccessDriver
{
	/**
	* @var Repository
	*/
	public $repository;
	public $driverConf;
	protected $wrapperClassName;
	protected $urlBase;
		
	function initRepository(){
		if(is_array($this->pluginConf)){
			$this->driverConf = $this->pluginConf;
		}else{
			$this->driverConf = array();
		}

		if(!function_exists('ssh2_connect')){
			throw new Exception("You must have the php ssh2 extension active!");
		}
        ConfService::setConf("PROBE_REAL_SIZE", false);
		$path = $this->repository->getOption("PATH");
		$recycle = $this->repository->getOption("RECYCLE_BIN");
		$wrapperData = $this->detectStreamWrapper(true);
		$this->wrapperClassName = $wrapperData["classname"];
		$this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
		if(!file_exists($this->urlBase)){
			throw new AJXP_Exception("Cannot find base path ($path) for your repository! Please check the configuration!");
		}
		if($recycle != ""){
			RecycleBinManager::init($this->urlBase, "/".$recycle);
		}
	}
	
	/**
	 * Parse 
	 * @param DOMNode $contribNode
	 */
	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if($contribNode->nodeName != "actions") return ;
		$this->disableArchiveBrowsingContributions($contribNode);
	}	
	
	
	/**
	 * We have to override the standard copyOrMoveFile, as feof() does
	 * not seem to work with ssh2.ftp stream... 
	 * Maybe something to search hear http://www.mail-archive.com/php-general@lists.php.net/msg169992.html?
	 *
	 * @param string $destDir
	 * @param string $srcFile
	 * @param array $error
	 * @param array $success
	 * @param boolean $move
	 */
	function copyOrMoveFile($destDir, $srcFile, &$error, &$success, $move = false)
	{
		$mess = ConfService::getMessages();		
		$destFile = $this->urlBase.$destDir."/".basename($srcFile);
		$realSrcFile = $this->urlBase.$srcFile;
		if(!file_exists($realSrcFile))
		{
			$error[] = $mess[100].$srcFile;
			return ;
		}		
		if(dirname($realSrcFile)==dirname($destFile))
		{
			if($move){
				$error[] = $mess[101];
				return ;
			}else{
				$base = basename($srcFile);
				$i = 1;
				if(is_file($realSrcFile)){
					$dotPos = strrpos($base, ".");
					if($dotPos>-1){
						$radic = substr($base, 0, $dotPos);
						$ext = substr($base, $dotPos);
					}
				}
				// auto rename file
				$i = 1;
				$newName = $base;
				while (file_exists($this->urlBase.$destDir."/".$newName)) {
					$suffix = "-$i";
					if(isSet($radic)) $newName = $radic . $suffix . $ext;
					else $newName = $base.$suffix;
					$i++;
				}
				$destFile = $this->urlBase.$destDir."/".$newName;
			}
		}
		if(!is_file($realSrcFile))
		{			
			$errors = array();
			$succFiles = array();
			if($move){				
				if(file_exists($destFile)) $this->deldir($destFile);
				$res = rename($realSrcFile, $destFile);
			}else{				
				$dirRes = $this->dircopy($realSrcFile, $destFile, $errors, $succFiles);
			}			
			if(count($errors) || (isSet($res) && $res!==true))
			{
				$error[] = $mess[114];
				return ;
			}			
		}
		else 
		{			
			if($move){
                AJXP_Controller::applyHook("node.before_change", array(new AJXP_Node($realSrcFile)));
				if(file_exists($destFile)) unlink($destFile);				
				rename($realSrcFile, $destFile);
				AJXP_Controller::applyHook("node.change", array(new AJXP_Node($realSrcFile), new AJXP_Node($destFile), false));
			}else{
				try{
					// BEGIN OVERRIDING
					list($connection, $remote_base_path) = sftpAccessWrapper::getSshConnection($realSrcFile);
					$remoteSrc = $remote_base_path.$srcFile;
					$remoteDest = $remote_base_path.$destDir;
					AJXP_Logger::debug("SSH2 CP", array("cmd" => 'cp '.$remoteSrc.' '.$remoteDest));
					ssh2_exec($connection, 'cp '.$remoteSrc.' '.$remoteDest);
					AJXP_Controller::applyHook("node.change", array(new AJXP_Node($realSrcFile), new AJXP_Node($destFile), true));
					// END OVERRIDING
				}catch (Exception $e){
					$error[] = $e->getMessage();
					return ;					
				}
			}
		}
		
		if($move)
		{
			// Now delete original
			// $this->deldir($realSrcFile); // both file and dir
			$messagePart = $mess[74]." ".SystemTextEncoding::toUTF8($destDir);
			if(RecycleBinManager::recycleEnabled() && $destDir == RecycleBinManager::getRelativeRecycle())
			{
				RecycleBinManager::fileToRecycle($srcFile);
				$messagePart = $mess[123]." ".$mess[122];
			}
			if(isset($dirRes))
			{
				$success[] = $mess[117]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$messagePart." (".SystemTextEncoding::toUTF8($dirRes)." ".$mess[116].") ";
			}
			else 
			{
				$success[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$messagePart;
			}
		}
		else
		{			
			if(RecycleBinManager::recycleEnabled() && $destDir == "/".$this->repository->getOption("RECYCLE_BIN"))
			{
				RecycleBinManager::fileToRecycle($srcFile);
			}
			if(isSet($dirRes))
			{
				$success[] = $mess[117]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$mess[73]." ".SystemTextEncoding::toUTF8($destDir)." (".SystemTextEncoding::toUTF8($dirRes)." ".$mess[116].")";	
			}
			else 
			{
				$success[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$mess[73]." ".SystemTextEncoding::toUTF8($destDir);
			}
		}
		
	}	

    function filesystemFileSize($filePath){
        $bytesize = filesize($filePath);
        if($bytesize < 0){
            $bytesize = sprintf("%u", $bytesize);
        }
        return $bytesize;
    }

	/**
     * @param $src
     * @param $dest
     * @param $basedir
	 * @return zipfile
	 */ 
    function makeZip ($src, $dest, $basedir)
    {
    	@set_time_limit(60);
    	require_once(AJXP_BIN_FOLDER."/pclzip.lib.php");
    	$filePaths = array();
    	
    	$uniqid = uniqid();
    	$uniqfolder = '/tmp/ajaxplorer-zip-'.$uniqid;
    	mkdir($uniqfolder);
    	
    	foreach ($src as $item){
    		$basedir = trim(dirname($item));
    		$basename = basename($item);
    		$uniqpath = $uniqfolder.'/'.$basename;
    		$this->full_copy($this->urlBase.$item, $uniqpath);
    		$filePaths[] = array(PCLZIP_ATT_FILE_NAME => $uniqpath, 
    							 PCLZIP_ATT_FILE_NEW_SHORT_NAME => $basename);    				
    	}
    	AJXP_Logger::debug("Pathes", $filePaths);
    	AJXP_Logger::debug("Basedir", array($basedir));
    	$archive = new PclZip($dest);
    	$vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $uniqfolder, PCLZIP_OPT_NO_COMPRESSION);
    	$this->recursiveRmdir($uniqfolder);
    	if(!$vList){
    		throw new Exception("Zip creation error : ($dest) ".$archive->errorInfo(true));
    	}
    	return $vList;
    }

    function full_copy( $source, $destination ) {
    	if ( is_dir( $source ) ) {
    		@mkdir( $destination );
    		$directory = dir( $source );
    		while ( FALSE !== ( $readdirectory = $directory->read() ) ) {
    			if ( $readdirectory == '.' || $readdirectory == '..' ) {
    				continue;
    			}
    			$PathDir = $source . '/' . $readdirectory;
    			if ( is_dir( $PathDir ) ) {
    				$this->full_copy( $PathDir, $destination . '/' . $readdirectory );
    				continue;
    			}
    			copy( $PathDir, $destination . '/' . $readdirectory );
    		}

    		$directory->close();
    	}else {
    		copy( $source, $destination );
    	}
    }

    function recursiveRmdir($path)
    {
    	if (is_dir($path))
    	{
    		$path = rtrim($path, '/');
    		$subdir = dir($path);
    		while (($file = $subdir->read()) !== false)
    		{
    			if ($file != '.' && $file != '..')
    			{
    			(!is_link("$path/$file") && is_dir("$path/$file")) ? $this->recursiveRmdir("$path/$file") : unlink("$path/$file");
    			}
    		}
    		$subdir->close();
    		rmdir($path);
    		return true;
    	}
    	return false;
    }
	
}	

?>