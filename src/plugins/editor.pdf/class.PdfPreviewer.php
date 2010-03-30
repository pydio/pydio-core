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
class PdfPreviewer extends AJXP_Plugin {

	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		if(!is_array($this->pluginConf) || !isSet($this->pluginConf["IMAGE_MAGICK_CONVERT"])){
			return false;
		}
    	$destStreamURL = "ajxp.".$repository->getAccessType()."://".$repository->getId();
		    	
		if($action == "pdf_data_proxy"){
			$file = AJXP_Utils::securePath(SystemTextEncoding::fromUTF8($httpVars["file"]));
			$fp = fopen($destStreamURL."/".$file, "r");
			$tmpFileName = tempnam(sys_get_temp_dir(), "img_");
			$tmpFile = fopen($tmpFileName, "w");
			register_shutdown_function("unlink", $tmpFileName);
			while(!feof($fp)) {
				stream_copy_to_stream($fp, $tmpFile, 4096);
			}
			fclose($tmpFile);
			fclose($fp);
			$out = array();
			$return = 0;
			$tmpFileThumb = str_replace(".tmp", ".jpg", $tmpFileName);			
			chdir(sys_get_temp_dir());
			$cmd = $this->pluginConf["IMAGE_MAGICK_CONVERT"]." ".basename($tmpFileName)."[0] ".basename($tmpFileThumb);
			session_write_close(); // Be sure to give the hand back
			exec($cmd, $out, $return);
			if($return){
				throw new AJXP_Exception(implode("\n", $out));
			}
			header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
			header("Content-Length: ".filesize($tmpFileThumb));
			header('Cache-Control: public');
			readfile($tmpFileThumb);
			exit(1);
			
		}
	}
}
?>