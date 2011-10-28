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
 *
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * AJXP_Plugin to access a remote server using the File Transfer Protocol
 */
class ftpAccessDriver extends fsAccessDriver {
	
	public function loadManifest(){
		parent::loadManifest();
		// BACKWARD COMPATIBILITY!
		$res = $this->xPath->query('//param[@name="USER"] | //param[@name="PASS"] | //user_param[@name="USER"] | //user_param[@name="PASS"]');
		foreach($res as $node){
			if($node->getAttribute("name") == "USER"){
				$node->setAttribute("name", "FTP_USER");
			}else if($node->getAttribute("name") == "PASS"){
				$node->setAttribute("name", "FTP_PASS");
			}
		}
		$this->reloadXPath();
	}
	
	/**
	 * Parse 
	 * @param DOMNode $contribNode
	 */
	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if($contribNode->nodeName != "actions") return ;
		$this->disableArchiveBrowsingContributions($contribNode);
        $this->redirectActionsToMethod($contribNode, array("upload", "next_to_remote", "trigger_remote_copy"), "uploadActions");
	}	
	
	function initRepository(){
		if(is_array($this->pluginConf)){
			$this->driverConf = $this->pluginConf;
		}else{
			$this->driverConf = array();
		}
		$create = $this->repository->getOption("CREATE");
		$wrapperData = $this->detectStreamWrapper(true);
		$this->wrapperClassName = $wrapperData["classname"];
		$this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
		$recycle = $this->repository->getOption("RECYCLE_BIN");
		if($recycle != ""){
			RecycleBinManager::init($this->urlBase, "/".$recycle);
		}
	}

	function uploadActions($action, $httpVars, $filesVars){
		switch ($action){
			case "trigger_remote_copy":
				if(!$this->hasFilesToCopy()) break;
				$toCopy = $this->getFileNameToCopy();
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".$toCopy." to ftp server");
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
				AJXP_Logger::debug("Base64 : ", array("from"=>$fData["destination"], "to"=>base64_decode($fData['destination'])));
				$destPath = $this->urlBase.base64_decode($fData['destination'])."/".$fData['name'];
				//$destPath = AJXP_Utils::decodeSecureMagic($destPath);
				// DO NOT "SANITIZE", THE URL IS ALREADY IN THE FORM ajxp.ftp://repoId/filename
				$destPath = SystemTextEncoding::fromPostedFileName($destPath);
				AJXP_Logger::debug("Copying file to server", array("from"=>$fData["tmp_name"], "to"=>$destPath, "name"=>$fData["name"]));
				try {
					$fp = fopen($destPath, "w");
					$fSource = fopen($fData["tmp_name"], "r");
					while(!feof($fSource)){
						fwrite($fp, fread($fSource, 4096));						
					}
					fclose($fSource);
					AJXP_Logger::debug("Closing target : begin ftp copy");
					// Make sur the script does not time out!
					@set_time_limit(240); 
					fclose($fp);
					AJXP_Logger::debug("FTP Upload : end of ftp copy");
					@unlink($fData["tmp_name"]);
				}catch (Exception $e){
					AJXP_Logger::debug("Error during ftp copy", array($e->getMessage(), $e->getTrace()));
				}
				AJXP_Logger::debug("FTP Upload : shoud trigger next or reload nextFile=$nextFile");
				AJXP_XMLWriter::header();
				if($nextFile!=''){
					AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".SystemTextEncoding::toUTF8($nextFile)." to remote server");
				}else{
					AJXP_XMLWriter::triggerBgAction("reload_node", array(), "Upload done, reloading client.");
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
					$boxData["destination"] = base64_encode($rep_source);
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
					if(!$this->isWriteable($destCopy)){
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
				
			break;
			default:
			break;
		}		
		session_write_close();
		exit;

	}

	public function isWriteable($path, $type="dir"){
		
		$parts = parse_url($path);
		$dir = $parts["path"];
		if($type == "dir" && ($dir == "" || $dir == "/" || $dir == "\\")){ // ROOT, WE ARE NOT SURE TO BE ABLE TO READ THE PARENT
			return true;
		}else{
			return is_writable($path);
		}
		
	}
	
	function deldir($location)
	{
		if(is_dir($location))
		{
			$dirsToRecurse = array();
			$all=opendir($location);
			while ($file=readdir($all))
			{
				if (is_dir("$location/$file") && $file !=".." && $file!=".")
				{
					$dirsToRecurse[] = "$location/$file";
				}
				elseif (!is_dir("$location/$file"))
				{
					if(file_exists("$location/$file")){						
						unlink("$location/$file"); 
					}
					unset($file);
				}
			}
			closedir($all);
			foreach ($dirsToRecurse as $recurse){
				$this->deldir($recurse);
			}
			rmdir($location);
		}
		else
		{
			if(file_exists("$location")) {
				$test = @unlink("$location");
				if(!$test) throw new Exception("Cannot delete file ".$location);
			}
		}
		if(basename(dirname($location)) == $this->repository->getOption("RECYCLE_BIN"))
		{
			// DELETING FROM RECYCLE
			RecycleBinManager::deleteFromRecycle($location);
		}
	}
	
	
    function storeFileToCopy($fileData){
            $user = AuthService::getLoggedUser();
            $files = $user->getTemporaryData("tmp_upload");
            AJXP_Logger::debug("Saving user temporary data", array($fileData));
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