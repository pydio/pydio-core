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
 * Encapsulates calls to Image Magick to extract JPG previews of PDF, PSD, TIFF, etc.
 */
class IMagickPreviewer extends AJXP_Plugin {

	protected $extractAll = false;
	protected $onTheFly = false;
	
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
			
			if(false && $this->extractAll){ // extract all on first view
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				$prefix = str_replace(".$ext", "", $cache->getId());
				$files = $this->listExtractedJpg($prefix);
				header("Content-Type: application/json");
				print(json_encode($files));
				return;
			}else if($this->extractAll){ // on the fly extract mode
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				$prefix = str_replace(".$ext", "", $cache->getId());
				$files = $this->listPreviewFiles($destStreamURL.$file, $prefix);
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
			$file = AJXP_CACHE_DIR."/imagick_full/".$httpVars["file"];
			if(!is_file($file)){
				$this->onTheFly = true;
				$this->generateJpegsCallback($httpVars["src_file"], $file);
			}
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
	
	/**
	 * 
	 * @param AJXP_Node $oldNode
	 * @param AJXP_Node $newNode
	 * @param Boolean $copy
	 */
	public function deleteImagickCache($oldNode, $newNode = null, $copy = false){
		if($oldNode == null) return;
		$oldFile = $oldNode->getUrl();
		// Should remove imagick cache file
		if(!$this->handleMime($oldFile)) return;		
		if($newNode == null || $copy == false){
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
	
	protected function listPreviewFiles($file, $prefix){
		$files = array();
		$index = 0;
		$count = $this->countPages($file);
		while($index < $count){
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
		$repository = ConfService::getRepository();
		$streamData = $repository->streamData;
		$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		$path = $repository->getOption("PATH");
		$masterFile = $path . str_replace($destStreamURL, "", $masterFile);
		$masterFile = str_replace("/", "\\", $masterFile);
		$extension = pathinfo($masterFile, PATHINFO_EXTENSION);
		$workingDir = dirname($targetFile);
		$out = array();
		$return = 0;
		$tmpFileThumb =  str_replace(".$extension", ".jpg", $targetFile);
		$tmpFileThumb =  str_replace("/", "\\", $tmpFileThumb);
		if(!$this->extractAll){
			//register_shutdown_function("unlink", $tmpFileThumb);
		}else{
			@set_time_limit(90);
		}
		chdir($workingDir);
		if($this->onTheFly){
			//extract page number
			$pageNumber = strrchr($targetFile, "-");
			$pageNumber = str_replace(array(".jpg","-"), "", $pageNumber);
			$pageLimit = "[".$pageNumber."]";
			$this->extractAll = true;
		}else{
			//$pageLimit = ($this->extractAll?"":"[0]");
			$pageLimit = "[0]";
			if($this->extractAll) $tmpFileThumb = str_replace(".jpg", "-0.jpg", $tmpFileThumb);
		}
		$params = ($this->extractAll?"-quality ".$this->pluginConf["IM_VIEWER_QUALITY"]:"-resize 250 -quality ".$this->pluginConf["IM_THUMB_QUALITY"]);
		$cmd = $this->pluginConf["IMAGE_MAGICK_CONVERT"]." ".($masterFile).$pageLimit." ".$params." ".($tmpFileThumb);
		AJXP_Logger::logAction("IMagick Command : $cmd");
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
	
	protected function countPages($file) 
	{
		if(!file_exists($file))return null;
		if (!$fp = @fopen($file,"r"))return null;
		$max=0;
		while(!feof($fp)) {
			$line = fgets($fp, 255);
			if (preg_match('/\/Count [0-9]+/', $line, $matches)){
							preg_match('/[0-9]+/',$matches[0], $matches2);
							if ($max<$matches2[0]) $max=$matches2[0];
			}
		}
		fclose($fp);
		return (int)$max;
	}

	
}
?>