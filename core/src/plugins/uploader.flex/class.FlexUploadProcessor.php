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
 * Legacy Flash plugin for upload
 */
class FlexUploadProcessor extends AJXP_Plugin {

	private static $active = false;
	
	public function preProcess($action, &$httpVars, &$fileVars){
		$repository = ConfService::getRepository();
		$skipDecoding = false;
		if($repository->detectStreamWrapper(false)){
			$plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
			$streamData = $plugin->detectStreamWrapper(true);		
	    	if($streamData["protocol"] == "ajxp.ftp" || $streamData["protocol"]=="ajxp.remotefs"){
	    		AJXP_Logger::debug("Skip decoding");
	    		$skipDecoding = true;
	    	}
		}
		
		if(isSet($fileVars["Filedata"])){
			self::$active = true;
			AJXP_Logger::debug("Dir before base64", $httpVars);
			$httpVars["dir"] = base64_decode(urldecode($httpVars["dir"]));
			if(!$skipDecoding) {
				$fileVars["Filedata"]["name"] = SystemTextEncoding::fromUTF8($fileVars["Filedata"]["name"]);
			}
			$fileVars["userfile_0"] = $fileVars["Filedata"];
			unset($fileVars["Filedata"]);
			AJXP_Logger::debug("Setting FlexProc active");
		}
	}	
	
	public function postProcess($action, $httpVars, $postProcessData){
		if(!self::$active){
			return false;
		}
		AJXP_Logger::debug("FlexProc is active=".self::$active, $postProcessData);
		$result = $postProcessData["processor_result"];
		if(isSet($result["SUCCESS"]) && $result["SUCCESS"] === true){
			header('HTTP/1.0 200 OK');
			//die("200 OK");
		}else if(isSet($result["ERROR"]) && is_array($result["ERROR"])){
			$code = $result["ERROR"]["CODE"];
			$message = $result["ERROR"]["MESSAGE"];
			
			//header("HTTP/1.0 $code $message");
			die("Error $code $message");
		}
	}	
}
?>