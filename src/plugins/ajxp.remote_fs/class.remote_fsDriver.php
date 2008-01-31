<?php

class remote_fsDriver extends AbstractDriver 
{
	
	function remote_fsDriver($driverName, $filePath, $repository){
		parent::AbstractDriver($driverName, INSTALL_PATH."/plugins/ajxp.fs/fsActions.xml", $repository);
		unset($this->actions["upload"]);
		// ADD additional actions
		$this->xmlFilePath = INSTALL_PATH."/plugins/ajxp.remote_fs/additionalActions.xml";
		$this->parseXMLActions();
	}
	
	function switchAction($action, $httpVars, $filesVars){		
		$sessionId = "";
		$crtRep = ConfService::getRepository();
		$httpClient = $this->getRemoteConnexion($sessionId);
		$httpVars["ajxp_sessid"] = $sessionId;
		$method = "get";
		if($action == "edit" && isSet($httpVars["save"])) $method = "post";
		if($method == "get"){
			$httpClient->get($crtRep->getOption("URI"), $httpVars);
		}else{			
			$httpClient->post($crtRep->getOption("URI"), $httpVars);
		}
		// check if session is expired
		if(strpos($httpClient->getHeader("content-type"), "text/xml") !== false && strpos($httpClient->getContent(), "require_auth") != false){
			$httpClient = $this->getRemoteConnexion($sessionId, true);
			$httpVars["ajxp_sessid"] = $sessionId;
			$method = "get";
			if($method == "get"){
				$httpClient->get($crtRep->getOption("URI"), $httpVars);
			}else{			
				$httpClient->post($crtRep->getOption("URI"), $httpVars);
			}
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
		session_write_close();
		exit();
	}
	
	function uploadActions($action, $httpVars, $filesVars){
		switch ($action){
			case "trigger_remote_copy":
				if(!$this->hasFilesToCopy()) break;
				$toCopy = $this->getFileNameToCopy();
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".$toCopy." to remote server");
				AJXP_XMLWriter::close();
				exit(1);
			break;
			case "next_to_remote":			
				if(!$this->hasFilesToCopy()) break;
				$fData = $this->getNextFileToCopy();				
				$nextFile = '';
				if($this->hasFilesToCopy()){
					$nextFile = $this->getFileNameToCopy();
				}
				$crtRep = ConfService::getRepository();
				session_write_close();
				
				$sessionId = "";
				$httpClient = $this->getRemoteConnexion($sessionId);
				//$httpClient->setDebug(true);
				$postData = array(
					"get_action"=>"upload", 
					"dir"=>$fData["destination"]);
					
				$httpClient->postFile($crtRep->getOption("URI")."?ajxp_sessid=$sessionId", $postData, "Filedata", $fData);
				if(strpos($httpClient->getHeader("content-type"), "text/xml") !== false && strpos($httpClient->getContent(), "require_auth") != false){
					$httpClient = $this->getRemoteConnexion($sessionId, true);
					$postData["ajxp_sessid"] = $sessionId;
					$httpClient->postFile($crtRep->getOption("URI"), $postData, "Filedata", $fData);
				}
				unlink($fData["tmp_name"]);
				$response = $httpClient->getContent();				
				AJXP_XMLWriter::header();
				if(intval($response)>=400){
					AJXP_XMLWriter::sendMessage(null, "Error : ".intval($response));
				}else{
					if($nextFile!=''){
						AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".$nextFile." to remote server");
					}else{					
						AJXP_XMLWriter::sendMessage("Done", null);
					}
				}
				AJXP_XMLWriter::close();
				exit(1);
			break;
			case "upload":
				if(isSet($httpVars['dir']) && $httpVars['dir']!=""){$rep_source=$httpVars['dir'];}
				else $rep_source = "/";
				$destination=$this->repository->getPath().$rep_source;
				$logMessage = "";
				$fancyLoader = false;				
				foreach ($filesVars as $boxName => $boxData)
				{					
					if($boxName != "Filedata" && substr($boxName, 0, 9) != "userfile_")	continue;
					if($boxName == "Filedata") $fancyLoader = true;
					$err = Utils::parseFileDataErrors($boxData, $fancyLoader);
					if($err != null)
					{
						$errorMessage = $err;
						break;
					}
					$boxData["destination"] = $rep_source;
					$destCopy = INSTALL_PATH."/".$this->repository->getOption("TMP_UPLOAD");
					$destName = $destCopy."/".basename($boxData["tmp_name"]);
					if(move_uploaded_file($boxData["tmp_name"], $destName)){
						$boxData["tmp_name"] = $destName;
						$this->storeFileToCopy($boxData);
					}else{
						$mess = ConfService::getMessages();
						$errorMessage=($fancyLoader?"411 ":"")."$mess[33] ".$boxData["name"];
					}
				}
				if($fancyLoader)
				{
					session_write_close();
					if(isSet($errorMessage)){
						header('HTTP/1.0 '.$errorMessage);
						die('Error '.$errorMessage);
					}else{
						header('HTTP/1.0 200 OK');
						die("200 OK");
					}
				}
				else
				{
					print("<html><script language=\"javascript\">\n");
					if(isSet($errorMessage)){
						print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext('".str_replace("'", "\'", $errorMessage)."');");		
					}else{		
						print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext();");
					}
					print("</script></html>");
				}
				session_write_close();
				exit;
				
			break;
			default:
			break;			
		}
		
	}
	
	/**
	* @return HttpClient
	*/
	function getRemoteConnexion(&$remoteSessionId, $refreshSessId=false){
		require_once(INSTALL_PATH."/server/classes/class.HttpClient.php");
		$crtRep = ConfService::getRepository();
		$httpClient = new HttpClient($crtRep->getOption("HOST"));
		$httpClient->cookie_host = $crtRep->getOption("HOST");
		$httpClient->timeout = 50;
		//$httpClient->setDebug(true);
		if($crtRep->getOption("AUTH_URI") != ""){
			$httpClient->setAuthorization($crtRep->getOption("AUTH_NAME"), $crtRep->getOption("AUTH_PASS"));
		}
		if(!isSet($_SESSION["AJXP_REMOTE_SESSION"]) || $refreshSessId){			
			$httpClient->setHeadersOnly(true);
			$httpClient->get($crtRep->getOption("AUTH_URI"));
			$httpClient->setHeadersOnly(false);
			$cookies = $httpClient->getCookies();		
			if(isSet($cookies["PHPSESSID"])){
				$_SESSION["AJXP_REMOTE_SESSION"] = $cookies["PHPSESSID"];
				$remoteSessionId = $cookies["PHPSESSID"];
			}
		}else{
			$remoteSessionId = $_SESSION["AJXP_REMOTE_SESSION"];
		}
		return $httpClient;
	}
	
	function storeFileToCopy($fileData){
		$user = AuthService::getLoggedUser();
		$files = $user->loadUserFile("tmp_upload");
		$files[] = $fileData;
		$user->saveUserFile("tmp_upload", $files);
	}
	
	function getFileNameToCopy(){
		$user = AuthService::getLoggedUser();
		$files = $user->loadUserFile("tmp_upload");
		return $files[0]["name"];
	}
	
	function getNextFileToCopy(){
		if(!$this->hasFilesToCopy()) return "";
		$user = AuthService::getLoggedUser();
		$files = $user->loadUserFile("tmp_upload");
		$fData = $files[0];
		array_shift($files);		
		$user->saveUserFile("tmp_upload", $files);
		return $fData;
	}
	
	function hasFilesToCopy(){
		$user = AuthService::getLoggedUser();
		$files = $user->loadUserFile("tmp_upload");
		return (count($files)?true:false);	
	}
	
}

?>