<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2010 Charles du Jeu
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
 * Description : Remote file downloader
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class HttpDownloader extends AJXP_Plugin{
	
	public function switchAction($action, $httpVars, $fileVars){		
		AJXP_Logger::logAction("DL file", $httpVars);
				
		$parts = parse_url($httpVars["file"]);
		
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(false)){
			return false;
		}
		$plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
		$streamData = $plugin->detectStreamWrapper(true);		
		$dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId().$dir."/";
    	
		require_once AJXP_INSTALL_PATH."/server/classes/class.HttpClient.php";
		$mess = ConfService::getMessages();		
		session_write_close();
		
		$client = new HttpClient($parts["host"]);		
		$collectHeaders = array(
			"ajxp-last-redirection" => "",
			"content-disposition"	=> "",
			"content-length"		=> ""
		);
		$client->setHeadersOnly(true, $collectHeaders);
		$client->setMaxRedirects(8);
		$client->setDebug(true);
		$getPath = $parts["path"];
		$client->get($getPath);
		
		AJXP_Logger::debug("COLLECTED HEADERS", $client->collectHeaders);
		$collectHeaders = $client->collectHeaders;
		$basename = basename($getPath);
    	if(!empty($collectHeaders["content-disposition"]) && strstr($collectHeaders["content-disposition"], "filename")!== false){
    		$basename = trim(array_pop(explode("filename=", $collectHeaders["content-disposition"])));
    		$basename = str_replace("\"", "", $basename); // Remove quotes
    	}
    	if(!empty($collectHeaders["content-length"])){
    		$totalSize = intval($collectHeaders["content-length"]);
    		AJXP_Logger::debug("Should download $totalSize bytes!");
    	}
		$qData = false;
		if(!empty($collectHeaders["ajxp-last-redirection"])){
			$newParsed = parse_url($collectHeaders["ajxp-last-redirection"]);
			$client->host = $newParsed["host"];
			$getPath = $newParsed["path"];
			if(isset($newParsed["query"])){
				$qData = parse_url($newParsed["query"]);
			}
		}
		
		$client->redirect_count = 0;
		$client->setHeadersOnly(false);
		$filename = $destStreamURL.$basename;
		$destStream = fopen($filename, "w");
		if($destStream !== false){

			$client->writeContentToStream($destStream);			
			$client->get($getPath, $qData);			
			fclose($destStream);
					
		}		
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::triggerBgAction("reload_node", array(), $mess["httpdownloader.8"]);
		AJXP_XMLWriter::close();
		exit();
		return true;
	}
	
}

?>