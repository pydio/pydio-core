<?php

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
		$client = new HttpClient($parts["host"]);
		
		session_write_close();
    	
		$filename = $destStreamURL.basename($parts["path"]);
		$destStream = fopen($filename, "w");
		if($destStream !== false){

			$client->writeContentToStream($destStream);			
			$client->get($parts["path"]);
			
			fclose($destStream);
		
		}
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::triggerBgAction("reload_node", array(), "File downloaded done, reloading client!");
		AJXP_XMLWriter::close();
		exit();
		return true;
	}
	
	
}

?>