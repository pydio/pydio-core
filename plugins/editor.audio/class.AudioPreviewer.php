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
 * Streams MP3 files to the flash client
 */
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
				print("<track><location>".AJXP_SERVER_ACCESS."?secure_token=".AuthService::getSecureToken()."&get_action=audio_proxy&file=".base64_encode($child->getAttribute("filename"))."</location><title>".$label."</title></track>");
			}
			print("</trackList>");
			AJXP_XMLWriter::close("playlist");
		}
	}	
}
?>