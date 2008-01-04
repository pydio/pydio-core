<?php

class remote_fsDriver extends AbstractDriver 
{
	
	function remote_fsDriver($driverName, $filePath, $repository){
		parent::AbstractDriver($driverName, INSTALL_PATH."/plugins/ajxp.fs/fsActions.xml", $repository);
		unset($this->actions["upload"]);
	}
	
	function switchAction($action, $httpVars, $filesVars){
		require_once(INSTALL_PATH."/server/classes/class.HttpClient.php");
		$crtRep = ConfService::getRepository();
		$httpClient = new HttpClient($crtRep->getOption("HOST"));
		$httpClient->cookie_host = $crtRep->getOption("HOST");
		$httpClient->timeout = 50;
		//$httpClient->setDebug(true);
		if($crtRep->getOption("AUTH_URI") != ""){
			$httpClient->setAuthorization($crtRep->getOption("AUTH_NAME"), $crtRep->getOption("AUTH_PASS"));
			$httpClient->setHeadersOnly(true);
			$httpClient->get($crtRep->getOption("AUTH_URI"));
			$httpClient->setHeadersOnly(false);
			$cookies = $httpClient->getCookies();		
			if(isSet($cookies["PHPSESSID"])) $httpVars["ajxp_sessid"] = $cookies["PHPSESSID"];
		}
		$method = "get";
		if($action == "edit" && isSet($httpVars["save"])) $method = "post";
		if($method == "get"){
			$httpClient->get($crtRep->getOption("URI"), $httpVars);
		}else{
			$httpClient->post($crtRep->getOption("URI"), $httpVars);
		}

		switch ($action){
			case "image_proxy":
				$size=strlen($httpClient->content);
				header("Content-Type: ".Utils::getImageMimeType(basename($httpVars["file"]))."; name=\"".basename($httpVars["file"])."\"");
				header("Content-Length: ".$size);
				header('Cache-Control: public');							
			break;
			case "download":
				$size=strlen($httpClient->content);
				$filePath = $httpVars["file"];
				header("Content-Type: application/force-download; name=\"".basename($filePath)."\"");
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: ".$size);
				header("Content-Disposition: attachment; filename=\"".basename($filePath)."\"");
				header("Expires: 0");
				header("Cache-Control: no-cache, must-revalidate");
				header("Pragma: no-cache");
				// For SSL websites, bug with IE see article KB 323308
				if (ConfService::getConf("USE_HTTPS")==1 && preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])){
					header("Cache-Control:");
					header("Pragma:");
				}
			break;
			case "mp3_proxy":
				$size=strlen($httpClient->content);
				header("Content-Type: audio/mp3; name=\"".basename($httpVars["file"])."\"");
				header("Content-Length: ".$size);
			break;
			case "edit":
				header("Content-type:text/plain");
			break;
			default:
				header("Content-type: text/xml");
			break;
		}
		print $httpClient->getContent();
		exit();
	}
	
}

?>