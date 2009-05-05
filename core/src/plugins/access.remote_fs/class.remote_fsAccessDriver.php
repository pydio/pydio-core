<?php
/**
 * @package info.ajaxplorer.plugins
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
 * Description : This driver will access another installation of AjaXplorer on a remote machine, thus acting as a proxy.
 */
class remote_fsAccessDriver extends AbstractAccessDriver 
{
	
	function remote_fsAccessDriver($driverName, $filePath, $repository, $optOptions = NULL){
		parent::AbstractAccessDriver($driverName, INSTALL_PATH."/plugins/access.fs/fsActions.xml", $repository);
		unset($this->actions["upload"]);
		// ADD additional actions
		/*
		$this->xmlFilePath = INSTALL_PATH."/plugins/access.remote_fs/additionalActions.xml";
		$this->parseXMLActions();
		*/
		$this->initXmlActionsFile(INSTALL_PATH."/plugins/access.remote_fs/additionalActions.xml");
		$this->xmlFilePath = INSTALL_PATH."/plugins/access.fs/fsActions.xml";
	}
	
	function switchAction($action, $httpVars, $filesVars){		
		$sessionId = "";
		$crtRep = ConfService::getRepository();
		$httpClient = $this->getRemoteConnexion($sessionId);
		$httpVars["ajxp_sessid"] = $sessionId;
		$method = "get";
		if($action == "edit" && isSet($httpVars["save"])) $method = "post";
		if($method == "get"){
			if($action == "download" || $action=="image_proxy" || $action=="mp3_proxy"){
				$httpClient->directForwarding = true;
			}
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
				if($action == "download"){
					$httpClient->directForwarding = true;
				}
				$httpClient->get($crtRep->getOption("URI"), $httpVars);				
			}else{			
				$httpClient->post($crtRep->getOption("URI"), $httpVars);
			}
		}

		switch ($action){			
			case "image_proxy":
			case "download":
			case "mp3_proxy":
				session_write_close();
				exit();
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
					"dir"=>base64_encode($fData["destination"]));
					
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
				
				$fancyLoader = false;
				if(isSet($fileVars["Filedata"])){
					$fancyLoader = true;
					if($httpVars['dir']!="") $httpVars['dir'] = "/".base64_decode($httpVars['dir']);
				}				
				if(isSet($httpVars['dir']) && $httpVars['dir']!=""){$rep_source=$httpVars['dir'];}
				else $rep_source = "/";
				$logMessage = "";
				//$fancyLoader = false;				
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
					if(!is_dir($destCopy)){
						if(! @mkdir($destCopy)){
							$errorMessage = "Warning, cannot create folder for temporary copy.";
							break;
						}						
					}
					if(!is_writeable($destCopy)){
						$errorMessage = "Warning, cannot write into temporary folder.";
						break;
					}
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
			$httpClient->setCookies(array("PHPSESSID"=>$remoteSessionId));
		}
		return $httpClient;
	}
	
	function storeFileToCopy($fileData){
		$user = AuthService::getLoggedUser();
		$files = $user->getTemporaryData("tmp_upload");
		$files[] = $fileData;
		$user->saveTemporaryData("tmp_upload", $files);
	}
	
	function getFileNameToCopy(){
		$user = AuthService::getLoggedUser();
		$files = $user->getTemporaryData("tmp_upload");
		return $files[0]["name"];
	}
	
	function getNextFileToCopy(){
		if(!$this->hasFilesToCopy()) return "";
		$user = AuthService::getLoggedUser();
		$files = $user->getTemporaryData("tmp_upload");
		$fData = $files[0];
		array_shift($files);		
		$user->saveTemporaryData("tmp_upload", $files);
		return $fData;
	}
	
	function hasFilesToCopy(){
		$user = AuthService::getLoggedUser();
		$files = $user->getTemporaryData("tmp_upload");
		return (count($files)?true:false);	
	}
	
}

?>
