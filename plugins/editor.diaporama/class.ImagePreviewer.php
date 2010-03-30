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
 * Description : Class for handling image_proxy, mp3 proxy, etc... Will rely on the StreamWrappers.
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
		
    	$destStreamURL = "ajxp.".$repository->getAccessType()."://".$repository->getId();
		    	
		if($action == "preview_data_proxy"){
			$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
			$fp = fopen($destStreamURL.$file, "r");
			
			if($this->pluginConf["GENERATE_THUMBNAIL"]){
				$tmpFileName = tempnam(sys_get_temp_dir(), "img_");
				$tmpFile = fopen($tmpFileName, "w");
				register_shutdown_function("unlink", $tmpFileName);
				while (!feof($fp)) {
					fwrite($tmpFile, fread($fp, 4096));
				}
				fclose($tmpFile);
				fclose($fp);
				require_once("server/classes/PThumb.lib.php");
				$pThumb = new PThumb($this->pluginConf["THUMBNAIL_QUALITY"]);
				if(!$pThumb->isError()){							
					$pThumb->use_cache = $this->pluginConf["USE_THUMBNAIL_CACHE"];
					$pThumb->cache_dir = $this->pluginConf["THUMBNAIL_CACHE_DIR"];	
					$pThumb->fit_thumbnail($tmpFileName, 200);
					if($pThumb->isError()){
						print_r($pThumb->error_array);
					}
					exit(0);
				}
			}else{
				$filesize = 0;
				while(!feof($fp)){
					$filesize += strlen(fread($fp, 4096));
				}			
	 			// fseek is not working, don't know why...
	 			fclose($fp);
	 			$fp = fopen($destStreamURL."/".$file, "r");
	 			// end
				header("Content-Type: ".AJXP_Utils::getImageMimeType(basename($file))."; name=\"".basename($file)."\"");
				header("Content-Length: ".$filesize);
				header('Cache-Control: public');
				while (!feof($fp)) {
					print fread($fp, 4096);
				}
	 			fclose($fp);
				exit(1);
			}
		}
	}
	
}
?>