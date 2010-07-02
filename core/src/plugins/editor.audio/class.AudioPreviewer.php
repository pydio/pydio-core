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
defined('AJXP_EXEC') or die( 'Access not allowed');

class AudioPreviewer extends AJXP_Plugin {

	public function preProcessAction($action, &$httpVars, &$fileVars){
		if($action != "ls" || !isset($httpVars["playlist"])){
			return ;
		}
		$httpVars["dir"] = base64_decode($httpVars["dir"]);
	}	
	
	public function switchAction($action, $httpVars, $postProcessData){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(false)){
			return false;
		}
		$plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
		$streamData = $plugin->detectStreamWrapper(true);		
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId()."/";
		    	
		if($action == "audio_proxy"){			
			$file = AJXP_Utils::decodeSecureMagic(base64_decode($httpVars["file"]));
			$localName = basename($file);
			header("Content-Type: audio/mp3; name=\"".$localName."\"");
			header("Content-Length: ".filesize($destStreamURL.$file));
			
			$stream = fopen("php://output", "a");
			call_user_func(array($streamData["classname"], "copyFileInStream"), $destStreamURL.$file, $stream);
			fflush($stream);
			fclose($stream);
			exit(1);
			
		}else if($action == "ls"){
			if(!isSet($httpVars["playlist"])){
				// This should not happen anyway, because of the applyCondition.				
				AJXP_Controller::passProcessDataThrough($postProcessData);
				return ;
			}
			// We transform the XML into XSPF
			$xmlString = $postProcessData["ob_output"];
			$xmlDoc = DOMDocument::loadXML($xmlString);
			$xElement = $xmlDoc->documentElement;
			header("Content-Type:application/xspf+xml;charset=UTF-8");
			print('<?xml version="1.0" encoding="UTF-8"?>');
			print('<playlist version="1" xmlns="http://xspf.org/ns/0/">');
			print("<trackList>");
			foreach ($xElement->childNodes as $child){
				$isFile = ($child->getAttribute("is_file") == "true");
				$label = $child->getAttribute("text");
				$ext = strtolower(end(explode(".", $label)));
				if(!$isFile || $ext != "mp3") continue;
				print("<track><location>content.php?get_action=audio_proxy&file=".base64_encode($child->getAttribute("filename"))."</location><title>".$label."</title></track>");
			}
			print("</trackList>");
			AJXP_XMLWriter::close("playlist");
		}
	}	
}
?>