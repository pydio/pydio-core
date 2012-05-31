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
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * Encapsulation of the Jumploader Java applet (must be downloaded separately).
 */
class JumploaderProcessor extends AJXP_Plugin {

	/**
	 * Handle UTF8 Decoding
	 *
	 * @var unknown_type
	 */
	private static $skipDecoding = false;
	
	public function preProcess($action, &$httpVars, &$fileVars){
        if(isSet($httpVars["simple_uploader"]) || isSet($httpVars["xhr_uploader"])) return;
		$repository = ConfService::getRepository();
		if($repository->detectStreamWrapper(false)){
			$plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
			$streamData = $plugin->detectStreamWrapper(true);		
	    	if($streamData["protocol"] == "ajxp.ftp" || $streamData["protocol"]=="ajxp.remotefs"){
	    		AJXP_Logger::debug("Skip decoding");
	    		self::$skipDecoding = true;
	    	}
		}		
		AJXP_Logger::debug("Jumploader HttpVars", $httpVars);
		AJXP_Logger::debug("Jumploader FileVars", $fileVars);
		
		$httpVars["dir"] = base64_decode(str_replace(" ","+",$httpVars["dir"])); 		
		if(isSet($httpVars["partitionCount"]) && intval($httpVars["partitionCount"]) > 1){
			AJXP_LOGGER::debug("Partitioned upload");
			$index = $httpVars["partitionIndex"];
			$realName = $fileVars["userfile_0"]["name"];
			//$realName = $httpVars["relativePath"];
			$fileId = $httpVars["fileId"];
			$clientId = $httpVars["ajxp_sessid"];
			$fileVars["userfile_0"]["name"] = "$clientId.$fileId.$index";
			if(intval($index) == intval($httpVars["partitionCount"])-1){
				$httpVars["partitionRealName"] = $realName;
			}
		}else if(isSet($httpVars["partitionCount"]) && $httpVars["partitionCount"] == 1){
			$httpVars["checkRelativePath"] = true;
		}		
		
	}	
	
	public function postProcess($action, $httpVars, $postProcessData){
        if(isSet($httpVars["simple_uploader"]) || isSet($httpVars["xhr_uploader"])) return;
		if(self::$skipDecoding){
			
		}
		if(!isSet($httpVars["partitionRealName"]) && !isSet($httpVars["checkRelativePath"])) {
			return ;
		}
		
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(false)){
			return false;
		}
		$plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
		$streamData = $plugin->detectStreamWrapper(true);
		$dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);		
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId().$dir."/";    	
		
		if(isSet($httpVars["partitionRealName"])){
			
			$count = intval($httpVars["partitionCount"]);
			$index = intval($httpVars["partitionIndex"]);
			$fileId = $httpVars["fileId"];
			$clientId = $httpVars["ajxp_sessid"];
			AJXP_Logger::debug("Should now rebuild file!", $httpVars);
			
			$newDest = fopen($destStreamURL.$httpVars["partitionRealName"], "w");
			AJXP_LOGGER::debug("PartitionRealName", $destStreamURL.$httpVars["partitionRealName"]);
			for ($i = 0; $i < $count ; $i++){
				$part = fopen($destStreamURL."$clientId.$fileId.$i", "r");
				while(!feof($part)){
					fwrite($newDest, fread($part, 4096));
				}
				fclose($part);
				unlink($destStreamURL."$clientId.$fileId.$i");
			}
			fclose($newDest);
		}
		if (isSet($httpVars["checkRelativePath"])) {
		    AJXP_LOGGER::debug("Now dispatching relativePath dest:", $httpVars["relativePath"]);
		    $subs = explode("/", $httpVars["relativePath"]);
		    $userfile_name = array_pop($subs);
		    $subpath = "";
		    $curDir = "";
		    
		    // remove trailing slash from current dir if we've got subdirs
		    if (count($subs) > 0) {
		    	if(substr($curDir, -1) == "/"){
					$curDir = substr($curDir, 0, -1);
		    	} 
		    	$folderForbidden = false;
		    	// Create the folder tree as necessary
				foreach ($subs as $key => $spath) {
				    $messtmp="";
				    $dirname=AJXP_Utils::decodeSecureMagic($spath, AJXP_SANITIZE_HTML_STRICT);
				    $dirname = substr($dirname, 0, ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
				    //$this->filterUserSelectionToHidden(array($dirname));
				    if(AJXP_Utils::isHidden($dirname)){
				    	$folderForbidden = true;
				    	break;
				    }
				
				    if(file_exists($destStreamURL."$curDir/$dirname")) {
					// if the folder exists, traverse
				        AJXP_Logger::debug("$curDir/$dirname existing, traversing for $userfile_name out of", $httpVars["relativePath"]);
						$curDir .= "/".$dirname;
						continue;
				    }
				
				    AJXP_Logger::debug($destStreamURL.$curDir);
			        $dirMode = 0775;
					$chmodValue = $repository->getOption("CHMOD_VALUE");
					if(isSet($chmodValue) && $chmodValue != "")
					{
						$dirMode = octdec(ltrim($chmodValue, "0"));
						if ($dirMode & 0400) $dirMode |= 0100; // User is allowed to read, allow to list the directory
						if ($dirMode & 0040) $dirMode |= 0010; // Group is allowed to read, allow to list the directory
						if ($dirMode & 0004) $dirMode |= 0001; // Other are allowed to read, allow to list the directory
					}
					$old = umask(0);				    
				    mkdir($destStreamURL.$curDir."/".$dirname, $dirMode);
					umask($old);				    
				    $curDir .= "/".$dirname;				
				}
				// Now move the final file to the right folder
				// Currently the file is at the base of the current
				$relPath = AJXP_Utils::decodeSecureMagic($httpVars["relativePath"]); 
				$current = $destStreamURL.basename($relPath);
				$target = $destStreamURL.$relPath;
				if(!$folderForbidden){
					$err = copy($current, $target);
					if($err!== false){
						unlink($current);
					}
				}else{
					// Remove the file, as it should not have been uploaded!
					unlink($current);
				}
		    }
		}		
		
	}	
}
?>