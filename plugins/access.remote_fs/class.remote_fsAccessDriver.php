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
 * AJXP_Plugin to access a remote server that implements AjaXplorer API
 */
class remote_fsAccessDriver extends AbstractAccessDriver 
{
	private $plugCapabilities = array();
	
	function init($repository, $options = null){
		$repoCapabilities = $repository->getOption("API_CAPABILITIES");
		if($repoCapabilities != ""){
			$this->plugCapabilities = explode(",", $repoCapabilities);
			// Register one preprocessor per capability. 		
			foreach ($this->plugCapabilities as $capability){
				$xml = '<action name="'.$capability.'"><pre_processing><serverCallback methodName="switchAction"/></pre_processing></action>';
                $tmpDoc = new DOMDocument();
				$tmpDoc->loadXML($xml);
				$newNode = $this->manifestDoc->importNode($tmpDoc->documentElement, true);
				$this->xPath->query("registry_contributions/actions")->item(0)->appendChild($newNode);
			}
		}
		parent::init($repository, $options);
	}

    function redirectActionsToMethod(&$contribNode, $arrayActions, $targetMethod){
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        foreach($arrayActions as $index => $value){
            $arrayActions[$index] = 'action[@name="'.$value.'"]/processing/serverCallback';
        }
        $procList = $actionXpath->query(implode(" | ", $arrayActions), $contribNode);
        foreach($procList as $node){
            $node->setAttribute("methodName", $targetMethod);
        }
    }

    /**
     * Parse
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode){
        parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->redirectActionsToMethod($contribNode, array("upload", "next_to_remote", "trigger_remote_copy"), "uploadActions");
    }


	function switchAction($action, $httpVars, $filesVars){		
		$secureToken = "";
		$crtRep = ConfService::getRepository();
		$httpClient = $this->getRemoteConnexion($secureToken);
		//$httpClient->setDebug(true);
		$method = "get";
		if($action == "put_content") $method = "post";
		$httpVars["secure_token"] = $secureToken;
		if($method == "get"){
			if($action == "download"){
				$httpClient->directForwarding = true;
			}
			$result = $httpClient->get($crtRep->getOption("URI"), $httpVars);
		}else{			
			$result = $httpClient->post($crtRep->getOption("URI"), $httpVars);
		}
		// check if session is expired
		if(strpos($httpClient->getHeader("content-type"), "text/xml") !== false && strpos($httpClient->getContent(), "require_auth") != false){
			$httpClient = $this->getRemoteConnexion($secureToken, true);
			$httpVars["secure_token"] = $secureToken;
			$method = "get";
			if($method == "get"){
				if($action == "download"){
					$httpClient->directForwarding = true;
				}
				$result = $httpClient->get($crtRep->getOption("URI"), $httpVars);				
				$result = $httpClient->get($crtRep->getOption("URI"), $httpVars);				
			}else{			
				$result = $httpClient->post($crtRep->getOption("URI"), $httpVars);
			}
		}

		if($result === false && isSet($httpClient->errormsg)){
			throw new Exception(SystemTextEncoding::toUTF8($httpClient->errormsg));
		}
		
		switch ($action){			
			case "download":
				session_write_close();
				exit();
			break;
			case "get_content":
				header("Content-type:text/plain");
			break;	
			case "stat": 
				header("Content-type:application/json");
			break;		
			default:
				$contentType = $httpClient->getHeader("content-type");
				if(!isSet($contentType) || strlen($contentType) == 0){
					$contentType = "text/xml";
				}
				header("Content-type: ".$contentType);
			break;
		}
		print $httpClient->getContent();
		session_write_close();
		exit();
	}
	
	function resetConnexionRepository($action, $httpVars, $params){
		if($action == "switch_repository"){
			if(isSet($_SESSION["AJXP_REMOTE_SESSION"])){
				unset($_SESSION["AJXP_REMOTE_SESSION"]);
			}
		}
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
				
				$secureToken = "";
				$httpClient = $this->getRemoteConnexion($secureToken);
				//$httpClient->setDebug(true);
				$postData = array(
					"get_action"=>"upload", 
					"dir"=>base64_encode($fData["destination"]),
					"secure_token" => $secureToken
				);
					
				$httpClient->postFile($crtRep->getOption("URI")."?", $postData, "Filedata", $fData);
				if(strpos($httpClient->getHeader("content-type"), "text/xml") !== false && strpos($httpClient->getContent(), "require_auth") != false){
					$httpClient = $this->getRemoteConnexion($secureToken, true);
					$postData["secure_token"] = $secureToken;
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
						AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".SystemTextEncoding::toUTF8($nextFile)." to remote server");
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
					$err = AJXP_Utils::parseFileDataErrors($boxData);
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
							$destName = tempnam($destCopy, "");
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
	function getRemoteConnexion(&$remoteSecureToken, $refreshSessId=false, $repository = null){
		require_once(AJXP_BIN_FOLDER."/class.HttpClient.php");
		if($repository != null){
			$crtRep = $repository;
		}else{
			$crtRep = ConfService::getRepository();
		}
		$httpClient = new HttpClient($crtRep->getOption("HOST"));
		$httpClient->cookie_host = $crtRep->getOption("HOST");
		$httpClient->timeout = 10;
		if(isSet($_SESSION["AJXP_REMOTE_SESSION"]) && is_array($_SESSION["AJXP_REMOTE_SESSION"])){
			$httpClient->setCookies($_SESSION["AJXP_REMOTE_SESSION"]);
		}
		
		//$httpClient->setDebug(true);
		if(!isSet($_SESSION["AJXP_REMOTE_SECURE_TOKEN"])){
			$httpClient->get($crtRep->getOption("URI")."?get_action=get_secure_token");
			$remoteSecureToken = $httpClient->getContent();
			$_SESSION["AJXP_REMOTE_SECURE_TOKEN"] = $remoteSecureToken;
		}else{
			$remoteSecureToken = $_SESSION["AJXP_REMOTE_SECURE_TOKEN"];
		}
		
		if(!$crtRep->getOption("USE_AUTH")){
			return $httpClient;
		}
		$uri = "";
		if($crtRep->getOption("AUTH_URI") != ""){
			$httpClient->setAuthorization($crtRep->getOption("AUTH_USER"), $crtRep->getOption("AUTH_PASS"));			
			$uri = $crtRep->getOption("AUTH_URI")."?secure_token=$remoteSecureToken";
		}
		if(!isSet($_SESSION["AJXP_REMOTE_SESSION"]) || !is_array($_SESSION["AJXP_REMOTE_SESSION"]) || $refreshSessId){		
			if($uri == ""){
				AJXP_Logger::debug("Remote_fs : relog necessary");
				// Retrieve a seed!
				$httpClient->get($crtRep->getOption("URI")."?get_action=get_seed&secure_token=$remoteSecureToken");
				$seed = $httpClient->getContent();
				$cookies = $httpClient->getCookies();
				if(isSet($cookies["AjaXplorer"])){
					$_SESSION["AJXP_REMOTE_SESSION"] = $cookies;
				}
				$user = $crtRep->getOption("AUTH_USER");
				$pass = $crtRep->getOption("AUTH_PASS");
				$pass = md5(md5($pass).$seed);
				$uri = $crtRep->getOption("URI")."?get_action=login&userid=".$user."&password=".$pass."&login_seed=$seed&secure_token=$remoteSecureToken";
				$httpClient->get($uri);
				$content = $httpClient->getContent();
				$matches = array();
				if(preg_match_all('#.*?secure_token="(.*?)".*?#s', $content, $matches)){
					$remoteSecureToken = $matches[1][0];
					$_SESSION["AJXP_REMOTE_SECURE_TOKEN"] = $remoteSecureToken;
				}
				$httpClient->setHeadersOnly(false);				
			}else{				
				$httpClient->setHeadersOnly(true);
				$httpClient->get($uri);
				$httpClient->setHeadersOnly(false);				
			}
			$cookies = $httpClient->getCookies();
			$_SESSION["AJXP_REMOTE_SESSION"] = $httpClient->getCookies();
		}else{
			$httpClient->setCookies($_SESSION["AJXP_REMOTE_SESSION"]);
		}		
		return $httpClient;
	}
	
	public static function isWriteable($path, $type="dir"){
		return is_writable($path);
	}
	
	function storeFileToCopy($fileData){
		$user = AuthService::getLoggedUser();
		$files = $user->getTemporaryData("tmp_upload");
		$files[] = $fileData;
		AJXP_Logger::debug("Storing data", $fileData);
		$user->saveTemporaryData("tmp_upload", $files);
        if(strpos($_SERVER["HTTP_USER_AGENT"], "ajaxplorer-ios-client") !== false
            || strpos($_SERVER["HTTP_USER_AGENT"], "Apache-HttpClient") !== false){
            AJXP_Logger::logAction("Up from ".$_SERVER["HTTP_USER_AGENT"] ." - direct triger of next to remote");
            $this->uploadActions("next_to_remote", array(), array());
        }
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
