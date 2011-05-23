<?php
/**
 * @package info.ajaxplorer
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
 * Description : Class for handling IMagick formats preview, etc... Rely on the StreamWrappers, ImageMagick and GhostScript
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class IMagickPreviewer extends AJXP_Plugin {

	protected $extractAll = false;
	
	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		if(!is_array($this->pluginConf) || !isSet($this->pluginConf["IMAGE_MAGICK_CONVERT"])){
			return false;
		}
		$streamData = $repository->streamData;		
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		    	
		if($action == "imagick_data_proxy"){
			$this->extractAll = false;
			if(isSet($httpVars["all"])) $this->extractAll = true;		
			$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
			
			if(!filesize($destStreamURL."/".$file)) return ;
			$cache = AJXP_Cache::getItem("imagick_".($this->extractAll?"full":"thumb"), $destStreamURL.$file, array($this, "generateJpegsCallback"));
			$cacheData = $cache->getData();
			
			if($this->extractAll){
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				$prefix = str_replace(".$ext", "", $cache->getId());
				$files = $this->listExtractedJpg($prefix);
				header("Content-Type: application/json");
				print(json_encode($files));
				return;
			}else{
				header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
				header("Content-Length: ".strlen($cacheData));
				header('Cache-Control: public');
				print($cacheData);
				return;
			}			
			
		}else if($action == "get_extracted_page" && isSet($httpVars["file"])){
			$file = AJXP_CACHE_DIR."/".$httpVars["file"];
			if(!is_file($file)) return ;
			header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
			header("Content-Length: ".filesize($file));
			header('Cache-Control: public');
			readfile($file);
			exit(1);			
		}else if($action == "delete_imagick_data" && isSet($httpVars["file"])){
			/*
			$files = $this->listExtractedJpg(AJXP_CACHE_DIR."/".$httpVars["file"]);
			foreach ($files as $file){
				if(is_file(AJXP_CACHE_DIR."/".$file["file"])) unlink(AJXP_CACHE_DIR."/".$file["file"]);
			}
			*/
		}
	}
	
	public function deleteImagickCache($oldFile, $newFile = null, $copy = false){
		// Should remove imagick cache file
		if(!$this->handleMime($oldFile)) return;
		if($newFile == null || $copy == false){
			AJXP_Cache::clearItem("imagick_thumb", $oldFile);			
			$cache = AJXP_Cache::getItem("imagick_full", $oldFile, false);
			$prefix = str_replace(".".pathinfo($cache->getId(), PATHINFO_EXTENSION), "", $cache->getId());
			$files = $this->listExtractedJpg($prefix);				
			foreach ($files as $file){
				if(is_file(AJXP_CACHE_DIR."/".$file["file"])) unlink(AJXP_CACHE_DIR."/".$file["file"]);
			}				
		}		
	}
	
	protected function listExtractedJpg($prefix){
		$files = array();
		$index = 0;
		while(is_file($prefix."-".$index.".jpg")){
			$extract = $prefix."-".$index.".jpg";
			list($width, $height, $type, $attr) = @getimagesize($extract);
			$files[] = array("file" => basename($extract), "width"=>$width, "height"=>$height);
			$index ++;
		}
		if(is_file($prefix.".jpg")){
			$extract = $prefix.".jpg";
			list($width, $height, $type, $attr) = @getimagesize($extract);
			$files[] = array("file" => basename($extract), "width"=>$width, "height"=>$height);
		}
		return $files;
	}
	
	public function generateJpegsCallback($masterFile, $targetFile){
		$extension = pathinfo($masterFile, PATHINFO_EXTENSION);
		//if(in_array(strtolower($extension), array("svg"))) $this->extractAll = true;
		$workingDir = dirname($targetFile);
		$fp = fopen($masterFile, "r");
		$tmpFileName = $workingDir."/ajxp_tmp_".md5(time()).".$extension";
		$tmpFile = fopen($tmpFileName, "w");
		register_shutdown_function("unlink", $tmpFileName);			
		while(!feof($fp)) {
			stream_copy_to_stream($fp, $tmpFile, 4096);
		}
		fclose($tmpFile);
		fclose($fp);
		$out = array();
		$return = 0;
		$tmpFileThumb =  str_replace(".$extension", ".jpg", $targetFile);
		if(!$this->extractAll){
			//register_shutdown_function("unlink", $tmpFileThumb);
		}else{
			@set_time_limit(90);
		}
		chdir($workingDir);
		$pageLimit = ($this->extractAll?"":"[0]");
		$params = ($this->extractAll?"-quality ".$this->pluginConf["IM_VIEWER_QUALITY"]:"-resize 250 -quality ".$this->pluginConf["IM_THUMB_QUALITY"]);
		$cmd = $this->pluginConf["IMAGE_MAGICK_CONVERT"]." ".basename($tmpFileName).$pageLimit." ".$params." ".basename($tmpFileThumb);
		//AJXP_Logger::debug("IMagick Command : $cmd");
		session_write_close(); // Be sure to give the hand back			
		exec($cmd, $out, $return);
		if(is_array($out) && count($out)){
			throw new AJXP_Exception(implode("\n", $out));
		}
		if(!$this->extractAll){
			rename($tmpFileThumb, $targetFile);
		}
		return true;				
	}
	
	protected function handleMime($filename){
		$mimesAtt = explode(",", $this->xPath->query("@mimes")->item(0)->nodeValue);
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		return in_array($ext, $mimesAtt);
	}
	
}
?>