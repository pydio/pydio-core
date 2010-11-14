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
 * Description : Class for handling image_proxy, etc... Will rely on the StreamWrappers.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

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
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		    	
		if($action == "preview_data_proxy"){
			$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
			
			if(isSet($httpVars["get_thumb"]) && $this->pluginConf["GENERATE_THUMBNAIL"]){
				require_once(INSTALL_PATH."/plugins/editor.diaporama/PThumb.lib.php");
				$pThumb = new PThumb($this->pluginConf["THUMBNAIL_QUALITY"]);
				if(!$pThumb->isError()){
					$pThumb->remote_wrapper = $streamData["classname"];
					$pThumb->use_cache = $this->pluginConf["USE_THUMBNAIL_CACHE"];
					$pThumb->cache_dir = $this->pluginConf["THUMBNAIL_CACHE_DIR"];	
					$pThumb->fit_thumbnail($destStreamURL.$file, 200);
					
					if($pThumb->isError()){
						print_r($pThumb->error_array);
						AJXP_Logger::logAction("error", $pThumb->error_array);
					}
					//exit(0);
				}else{
					print_r($pThumb->error_array);
					AJXP_Logger::logAction("error", $pThumb->error_array);					
				}
			}else{
	 			$filesize = filesize($destStreamURL.$file);
	 			
	 			$fp = fopen($destStreamURL.$file, "r");
				header("Content-Type: ".AJXP_Utils::getImageMimeType(basename($file))."; name=\"".basename($file)."\"");
				header("Content-Length: ".$filesize);
				header('Cache-Control: public');
				
				$class = $streamData["classname"];
				$stream = fopen("php://output", "a");
				call_user_func(array($streamData["classname"], "copyFileInStream"), $destStreamURL.$file, $stream);
				fflush($stream);
				fclose($stream);
				//exit(1);
			}
		}
	}
	
	public function extractImageMetadata($currentNode, &$metadata, $wrapperClassName, &$realFile){
		$isImage = AJXP_Utils::is_image($currentNode);
		$metadata["is_image"] = $isImage;
		$setRemote = false;
		$remoteWrappers = $this->pluginConf["META_EXTRACTION_REMOTEWRAPPERS"];
		$remoteThreshold = $this->pluginConf["META_EXTRACTION_THRESHOLD"];		
		if(in_array($wrapperClassName, $remoteWrappers)){
			if($remoteThreshold != 0 && isSet($metadata["bytesize"])){
				$setRemote = ($metadata["bytesize"] > $remoteThreshold);
			}else{
				$setRemote = true;
			}
		}
		if($isImage)
		{
			if($setRemote){
				$metadata["image_type"] = "N/A";
				$metadata["image_width"] = "N/A";
				$metadata["image_height"] = "N/A";
				$metadata["readable_dimension"] = "";
			}else{
				if(!isSet($realFile)){
					$realFile = call_user_func(array($wrapperClassName, "getRealFSReference"), $currentNode);
				}
				list($width, $height, $type, $attr) = @getimagesize($realFile);
				$metadata["image_type"] = image_type_to_mime_type($type);
				$metadata["image_width"] = $width;
				$metadata["image_height"] = $height;
				$metadata["readable_dimension"] = $width."px X ".$height."px";
			}
		}
	}
	
}
?>