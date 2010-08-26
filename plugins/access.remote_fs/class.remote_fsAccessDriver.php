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
defined('AJXP_EXEC') or die( 'Access not allowed');

class remote_fsAccessDriver extends AbstractAccessDriver 
{	
	function switchAction($action, $httpVars, $filesVars){		
		$sessionId = "";
		$crtRep = ConfService::getRepository();
		$httpClient = $this->getRemoteConnexion($sessionId);
		//$httpClient->setDebug(true);
		if($crtRep->getOption("USE_AUTH")){
			$httpVars["ajxp_sessid"] = $sessionId;
		}
		$method = "get";
		if($action == "put_content") $method = "post";
		if($method == "get"){
			if($action == "download" || $action=="image_proxy" || $action=="mp3_proxy"){
				$httpClient->directForwarding = true;
			}
			$result = $httpClient->get($crtRep->getOption("URI"), $httpVars);
		}else{			
			$result = $httpClient->post($crtRep->getOption("URI"), $httpVars);
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
				$result = $httpClient->get($crtRep->getOption("URI"), $httpVars);				
			}else{			
				$result = $httpClient->post($crtRep->getOption("URI"), $httpVars);
			}
		}

		if($result === false && isSet($httpClient->errormsg)){
			throw new Exception(SystemTextEncoding::toUTF8($httpClient->errormsg));
		}
		
		switch ($action){			
			case "image_proxy":
			case "download":
			case "mp3_proxy":
				session_write_close();
				exit();
			break;
			case "get_content":
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
				AJXP_Logger::debug("trigger_remote", $toCopy);
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
				AJXP_Logger::debug("next_to_remote", $nextFile);
				if(intval($response)>=400){
					AJXP_XMLWriter::sendMessage(null, "Error : ".intval($response));
				}else{
					if($nextFile!=''){
						AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".$nextFile." to remote server");
					}else{					
						AJXP_XMLWriter::triggerBgAction("reload_node", array(), "Upload done, reloading client.");
					}
				}
				AJXP_XMLWriter::close();
				exit(1);
			break;
			case "upload":
				
				$rep_source = AJXP_Utils::securePath("/".$httpVars['dir']);
				AJXP_Logger::debug("Upload : rep_source ", array($rep_source));
				$logMessage = "";
				foreach ($filesVars as $boxName => $boxData)
				{
					if(substr($boxName, 0, 9) != "userfile_")     continue;
					AJXP_Logger::debug("Upload : rep_source ", array($rep_source));
					$err = AJXP_Utils::parseFileDataErrors($boxData, $fancyLoader);
					if($err != null)
					{
						$errorCode = $err[0];
						$errorMessage = $err[1];
						break;
					}
					$boxData["destination"] = $rep_source;
					$destCopy = AJXP_XMLWriter::replaceAjxpXmlKeywords($this->repository->getOption("TMP_UPLOAD"));
					AJXP_Logger::debug("Upload : tmp upload folder", array($destCopy));
					if(!is_dir($destCopy)){
						if(! @mkdir($destCopy)){
							AJXP_Logger::debug("Upload error : cannot create temporary folder", array($destCopy));
							$errorCode = 413;
							$errorMessage = "Warning, cannot create folder for temporary copy.";
							break;
						}
					}
					if(!is_writeable($destCopy)){
						AJXP_Logger::debug("Upload error: cannot write into temporary folder");
						$errorCode = 414;
						$errorMessage = "Warning, cannot write into temporary folder.";
						break;
					}
					AJXP_Logger::debug("Upload : tmp upload folder", array($destCopy));
					if(isSet($boxData["input_upload"])){
						try{
							$destName .= tempnam($destCopy, "");
							AJXP_Logger::debug("Begining reading INPUT stream");
							$input = fopen("php://input", "r");
							$output = fopen($destName, "w");
							$sizeRead = 0;
							while($sizeRead < intval($boxData["size"])){
								$chunk = fread($input, 4096);
								$sizeRead += strlen($chunk);
								fwrite($output, $chunk, strlen($chunk));
							}
							fclose($input);
							fclose($output);
							$boxData["tmp_name"] = $destName;
							$this->storeFileToCopy($boxData);
							AJXP_Logger::debug("End reading INPUT stream");
						}catch (Exception $e){
							$errorCode=411;
							$errorMessage = $e->getMessage();
							break;
						}
					}else{										
						$destName = $destCopy."/".basename($boxData["tmp_name"]);
						if ($destName == $boxData["tmp_name"]) $destName .= "1";
						if(move_uploaded_file($boxData["tmp_name"], $destName)){
							$boxData["tmp_name"] = $destName;
							$this->storeFileToCopy($boxData);
						}else{
							$mess = ConfService::getMessages();
							$errorCode = 411;
							$errorMessage="$mess[33] ".$boxData["name"];
							break;
						}
					}
				}
				if(isSet($errorMessage)){
					AJXP_Logger::debug("Return error $errorCode $errorMessage");
					return array("ERROR" => array("CODE" => $errorCode, "MESSAGE" => $errorMessage));
				}else{
					AJXP_Logger::debug("Return success");
					return array("SUCCESS" => true);
				}

				session_write_close();				
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
		$httpClient->timeout = 10;
		//$httpClient->setDebug(true);
		if(!$crtRep->getOption("USE_AUTH")){
			return $httpClient;
		}
		$uri = "";
		if($crtRep->getOption("AUTH_URI") != ""){
			$httpClient->setAuthorization($crtRep->getOption("AUTH_USER"), $crtRep->getOption("AUTH_PASS"));			
			$uri = $crtRep->getOption("AUTH_URI");
		}
		if(!isSet($_SESSION["AJXP_REMOTE_SESSION"]) || $refreshSessId){		
			if($uri == ""){
				// Retrieve a seed!
				$httpClient->get($crtRep->getOption("URI")."?get_action=get_seed");
				$seed = $httpClient->getContent();
				$user = $crtRep->getOption("AUTH_USER");
				$pass = $crtRep->getOption("AUTH_PASS");
				$pass = md5(md5($pass).$seed);
				$uri = $crtRep->getOption("URI")."?get_action=login&userid=".$user."&password=".$pass."&login_seed=$seed";
			}
			$httpClient->setHeadersOnly(true);
			$httpClient->get($uri);
			$httpClient->setHeadersOnly(false);
			$cookies = $httpClient->getCookies();		
			if(isSet($cookies["AjaXplorer"])){
				$_SESSION["AJXP_REMOTE_SESSION"] = $cookies["AjaXplorer"];
				$remoteSessionId = $cookies["AjaXplorer"];
			}
		}else{
			$remoteSessionId = $_SESSION["AJXP_REMOTE_SESSION"];
			$httpClient->setCookies(array("AjaXplorer"=>$remoteSessionId));
		}
		return $httpClient;
	}
	
	function storeFileToCopy($fileData){
		$user = AuthService::getLoggedUser();
		$files = $user->getTemporaryData("tmp_upload");
		$files[] = $fileData;
		AJXP_Logger::debug("Storing data", $fileData);
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
