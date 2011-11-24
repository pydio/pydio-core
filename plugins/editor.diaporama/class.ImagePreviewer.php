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
 * Generate an image thumbnail and send the thumb/full version to the browser
 */
class ImagePreviewer extends AJXP_Plugin {

	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		if(!isSet($this->pluginConf)){
			$this->pluginConf = array("GENERATE_THUMBNAIL"=>false);
		}
		
		
		$streamData = $repository->streamData;
		$this->streamData = $streamData;
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		    	
		if($action == "preview_data_proxy"){
			$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
			
			if(isSet($httpVars["get_thumb"]) && $this->pluginConf["GENERATE_THUMBNAIL"]){
				$cacheItem = AJXP_Cache::getItem("diaporama_200", $destStreamURL.$file, array($this, "generateThumbnail"));
				$data = $cacheItem->getData();
				$cId = $cacheItem->getId();
				
				header("Content-Type: ".AJXP_Utils::getImageMimeType(basename($cId))."; name=\"".basename($cId)."\"");
				header("Content-Length: ".strlen($data));
				header('Cache-Control: public');
                header("Pragma:");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
				print($data);

			}else{
	 			$filesize = filesize($destStreamURL.$file);
	 			
	 			$fp = fopen($destStreamURL.$file, "r");
				header("Content-Type: ".AJXP_Utils::getImageMimeType(basename($file))."; name=\"".basename($file)."\"");
				header("Content-Length: ".$filesize);
				header('Cache-Control: public');
                header("Pragma:");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");

				$class = $streamData["classname"];
				$stream = fopen("php://output", "a");
				call_user_func(array($streamData["classname"], "copyFileInStream"), $destStreamURL.$file, $stream);
				fflush($stream);
				fclose($stream);
				//exit(1);
			}
		}
	}
	
	/**
	 * 
	 * @param AJXP_Node $oldFile
	 * @param AJXP_Node $newFile
	 * @param Boolean $copy
	 */
	public function removeThumbnail($oldFile, $newFile = null, $copy = false){
		if($oldFile == null) return ;
		if(!$this->handleMime($oldFile->getUrl())) return;
		if($newFile == null || $copy == false){
			AJXP_Logger::debug("Should find cache item ".$oldFile->getUrl());
			AJXP_Cache::clearItem("diaporama_200", $oldFile->getUrl());			
		}
	}
	
	public function generateThumbnail400($masterFile, $targetFile){
		return $this->generateThumbnail($masterFile, $targetFile, 380);
	}
	
	public function generateThumbnail($masterFile, $targetFile, $size = 200){
		require_once(AJXP_INSTALL_PATH."/plugins/editor.diaporama/PThumb.lib.php");
		$pThumb = new PThumb($this->pluginConf["THUMBNAIL_QUALITY"]);
		if(!$pThumb->isError()){
			$pThumb->remote_wrapper = $this->streamData["classname"];
			$sizes = $pThumb->fit_thumbnail($masterFile, $size, -1, 1, true);		
			$pThumb->print_thumbnail($masterFile,$sizes[0],$sizes[1],false, false, $targetFile);
			if($pThumb->isError()){
				print_r($pThumb->error_array);
				AJXP_Logger::logAction("error", $pThumb->error_array);
				return false;
			}			
		}else{
			print_r($pThumb->error_array);
			AJXP_Logger::logAction("error", $pThumb->error_array);			
			return false;		
		}		
	}
	
	//public function extractImageMetadata($currentNode, &$metadata, $wrapperClassName, &$realFile){
	/**
	 * Enrich node metadata
	 * @param AJXP_Node $ajxpNode
	 */
	public function extractImageMetadata(&$ajxpNode){
		$currentPath = $ajxpNode->getUrl();
		$wrapperClassName = $ajxpNode->wrapperClassName;
		$isImage = AJXP_Utils::is_image($currentPath);
		$ajxpNode->is_image = $isImage;
		if(!$isImage) return;
		$setRemote = false;
		$remoteWrappers = $this->pluginConf["META_EXTRACTION_REMOTEWRAPPERS"];
        if(is_string($remoteWrappers)){
            $remoteWrappers = explode(",",$remoteWrappers);
        }
		$remoteThreshold = $this->pluginConf["META_EXTRACTION_THRESHOLD"];		
		if(in_array($wrapperClassName, $remoteWrappers)){
			if($remoteThreshold != 0 && isSet($ajxpNode->bytesize)){
				$setRemote = ($ajxpNode->bytesize > $remoteThreshold);
			}else{
				$setRemote = true;
			}
		}
		if($isImage)
		{
			if($setRemote){
				$ajxpNode->image_type = "N/A";
				$ajxpNode->image_width = "N/A";
				$ajxpNode->image_height = "N/A";
				$ajxpNode->readable_dimension = "";
			}else{
				$realFile = $ajxpNode->getRealFile();
				list($width, $height, $type, $attr) = @getimagesize($realFile);
				$ajxpNode->image_type = image_type_to_mime_type($type);
				$ajxpNode->image_width = $width;
				$ajxpNode->image_height = $height;
				$ajxpNode->readable_dimension = $width."px X ".$height."px";
			}
		}
		//AJXP_Logger::debug("CURRENT NODE IN EXTRACT IMAGE METADATA ", $ajxpNode);
	}
	
	protected function handleMime($filename){
		$mimesAtt = explode(",", $this->xPath->query("@mimes")->item(0)->nodeValue);
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		return in_array($ext, $mimesAtt);
	}	
	
}
?>