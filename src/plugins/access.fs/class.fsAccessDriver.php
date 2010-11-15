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
 * Description : The most used and standard plugin : FileSystem access
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

// This is used to catch exception while downloading
function download_exception_handler($exception){}

class fsAccessDriver extends AbstractAccessDriver 
{
	/**
	* @var Repository
	*/
	public $repository;
	public $driverConf;
	protected $wrapperClassName;
	protected $urlBase;
		
	function initRepository(){
		if(is_array($this->pluginConf)){
			$this->driverConf = $this->pluginConf;
		}else{
			$this->driverConf = array();
		}
		$create = $this->repository->getOption("CREATE");
		$path = $this->repository->getOption("PATH");
		$recycle = $this->repository->getOption("RECYCLE_BIN");
		if($create == true){
			if(!is_dir($path)) @mkdir($path);
			if(!is_dir($path)){
				throw new AJXP_Exception("Cannot create root path for repository (".$this->repository->getDisplay()."). Please check repository configuration or that your folder is writeable!");
			}
			if($recycle!= "" && !is_dir($path."/".$recycle)){
				@mkdir($path."/".$recycle);
				if(!is_dir($path."/".$recycle)){
					throw new AJXP_Exception("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
				}
			}
		}else{
			if(!is_dir($path)){
				throw new AJXP_Exception("Cannot find base path for your repository! Please check the configuration!");
			}
		}
		$wrapperData = $this->detectStreamWrapper(true);
		$this->wrapperClassName = $wrapperData["classname"];
		$this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
		if($recycle != ""){
			RecycleBinManager::init($this->urlBase, "/".$recycle);
		}
	}
	
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		parent::accessPreprocess($action, $httpVars, $fileVars);
		$selection = new UserSelection();
		$dir = $httpVars["dir"] OR "";
		$dir = AJXP_Utils::securePath($dir);
		if($action != "upload"){
			$dir = SystemTextEncoding::fromPostedFileName($dir);
		}
		$selection->initFromHttpVars($httpVars);
		if(!$selection->isEmpty()){
			$this->filterUserSelectionToHidden($selection->getFiles());			
		}
		$mess = ConfService::getMessages();
		
		$newArgs = RecycleBinManager::filterActions($action, $selection, $dir, $httpVars);
		if(isSet($newArgs["action"])) $action = $newArgs["action"];
		if(isSet($newArgs["dest"])) $httpVars["dest"] = SystemTextEncoding::toUTF8($newArgs["dest"]);//Re-encode!
 		// FILTER DIR PAGINATION ANCHOR
		$page = null;
		if(isSet($dir) && strstr($dir, "%23")!==false){
			$parts = explode("%23", $dir);
			$dir = $parts[0];
			$page = $parts[1];
		}
		
		$pendingSelection = "";
		$logMessage = null;
		$reloadContextNode = false;
		
		switch($action)
		{			
			//------------------------------------
			//	DOWNLOAD
			//------------------------------------
			case "download":
				AJXP_Logger::logAction("Download", array("files"=>$selection));
				@set_error_handler(array("HTMLWriter", "javascriptErrorHandler"), E_ALL & ~ E_NOTICE);
				@register_shutdown_function("restore_error_handler");
				$zip = false;
				if($selection->isUnique()){
					if(is_dir($this->urlBase.$selection->getUniqueFile())) {
						$zip = true;
						$base = basename($selection->getUniqueFile());
						$dir .= "/".dirname($selection->getUniqueFile());
					}else{
						if(!file_exists($this->urlBase.$selection->getUniqueFile())){
							throw new Exception("Cannot find file!");
						}
					}
				}else{
					$zip = true;
				}
				if($zip){
					// Make a temp zip and send it as download
					$loggedUser = AuthService::getLoggedUser();
					$file = USERS_DIR."/".($loggedUser?$loggedUser->getId():"shared")."/".time()."tmpDownload.zip";
					$zipFile = $this->makeZip($selection->getFiles(), $file, $dir);
					if(!$zipFile) throw new AJXP_Exception("Error while compressing");
					register_shutdown_function("unlink", $file);
					$localName = ($base==""?"Files":$base).".zip";
					$this->readFile($file, "force-download", $localName, false, false, true);
				}else{
					$this->readFile($this->urlBase.$selection->getUniqueFile(), "force-download");
				}
				exit(0);
			break;
		
			case "compress" : 					
					// Make a temp zip and send it as download					
					$loggedUser = AuthService::getLoggedUser();
					if(isSet($httpVars["archive_name"])){						
						$localName = AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]);
						$this->filterUserSelectionToHidden(array($httpVars["archive_name"]));
					}else{
						$localName = (basename($dir)==""?"Files":basename($dir)).".zip";
					}
					$file = USERS_DIR."/".($loggedUser?$loggedUser->getId():"shared")."/".time()."tmpCompression.zip";
					$zipFile = $this->makeZip($selection->getFiles(), $file, $dir);
					if(!$zipFile) throw new AJXP_Exception("Error while compressing file $localName");
					register_shutdown_function("unlink", $file);					
					copy($file, $this->urlBase.$dir."/".str_replace(".zip", ".tmp", $localName));
					@rename($this->urlBase.$dir."/".str_replace(".zip", ".tmp", $localName), $this->urlBase.$dir."/".$localName);
					$reloadContextNode = true;
					$pendingSelection = $localName;					
			break;
			
			case "stat" :
				
				clearstatcache();
				$stat = @stat($this->urlBase.$selection->getUniqueFile());
				header("Content-type:application/json");
				if(!$stat){
					print '{}';
				}else{
					print json_encode($stat);
				}
				exit(1);
				
			break;
			
			
			//------------------------------------
			//	ONLINE EDIT
			//------------------------------------
			case "get_content":
					
				$this->readFile($this->urlBase.$selection->getUniqueFile(), "plain");
				exit(0);
			break;
			
			case "put_content":	
				if(!isset($httpVars["content"])) break;
				// Reload "code" variable directly from POST array, do not "securePath"...
				$code = $httpVars["content"];
				$file = $selection->getUniqueFile($httpVars["file"]);
				AJXP_Logger::logAction("Online Edition", array("file"=>$file));
				if(isSet($httpVars["encode"]) && $httpVars["encode"] == "base64"){
				    $code = base64_decode($code);
				}else{
					$code=stripslashes($code);
					$code=str_replace("&lt;","<",$code);
				}
				$fileName = $this->urlBase.$file;
				if(!is_file($fileName) || !$this->isWriteable($fileName, "file")){
					header("Content-Type:text/plain");
					print((!$this->isWriteable($fileName, "file")?"1001":"1002"));
					exit(1);
				}
				$fp=fopen($fileName,"w");
				fputs ($fp,$code);
				fclose($fp);
				header("Content-Type:text/plain");
				print($mess[115]);
				//exit(0);
			break;
		
			//------------------------------------
			//	COPY / MOVE
			//------------------------------------
			case "copy";
			case "move";
				
				if($selection->isEmpty())
				{
					throw new AJXP_Exception("", 113);
				}
				$success = $error = array();
				$dest = AJXP_Utils::decodeSecureMagic($httpVars["dest"]);
				$this->filterUserSelectionToHidden(array($httpVars["dest"]));
				if($selection->inZip()){
					// Set action to copy anycase (cannot move from the zip).
					$action = "copy";
					$this->extractArchive($dest, $selection, $error, $success);
				}else{
					$this->copyOrMove($dest, $selection->getFiles(), $error, $success, ($action=="move"?true:false));
				}
				
				if(count($error)){					
					throw new AJXP_Exception(SystemTextEncoding::toUTF8(join("\n", $error)));
				}else {
					$logMessage = join("\n", $success);
					AJXP_Logger::logAction(($action=="move"?"Move":"Copy"), array("files"=>$selection, "destination"=>$dest));
				}
				$reloadContextNode = true;
				$reloadDataNode = $dest;
				
			break;
			
			//------------------------------------
			//	SUPPRIMER / DELETE
			//------------------------------------
			case "delete";
			
				if($selection->isEmpty())
				{
					throw new AJXP_Exception("", 113);
				}
				$logMessages = array();
				$errorMessage = $this->delete($selection->getFiles(), $logMessages);
				if(count($logMessages))
				{
					$logMessage = join("\n", $logMessages);
				}
				if($errorMessage) throw new AJXP_Exception(SystemTextEncoding::toUTF8($errorMessage));
				AJXP_Logger::logAction("Delete", array("files"=>$selection));
				$reloadContextNode = true;
				
			break;
		
			//------------------------------------
			//	RENOMMER / RENAME
			//------------------------------------
			case "rename";
			
				$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
				$filename_new = AJXP_Utils::decodeSecureMagic($httpVars["filename_new"]);
				$this->filterUserSelectionToHidden(array($filename_new));
				$this->rename($file, $filename_new);
				$logMessage= SystemTextEncoding::toUTF8($file)." $mess[41] ".SystemTextEncoding::toUTF8($filename_new);
				$reloadContextNode = true;
				$pendingSelection = $filename_new;
				AJXP_Logger::logAction("Rename", array("original"=>$file, "new"=>$filename_new));
				
			break;
		
			//------------------------------------
			//	CREER UN REPERTOIRE / CREATE DIR
			//------------------------------------
			case "mkdir";
			        
				$messtmp="";
				$dirname=AJXP_Utils::processFileName(SystemTextEncoding::fromUTF8($httpVars["dirname"]));
				$this->filterUserSelectionToHidden(array($dirname));
				$error = $this->mkDir($dir, $dirname);
				if(isSet($error)){
					throw new AJXP_Exception($error);
				}
				$messtmp.="$mess[38] ".SystemTextEncoding::toUTF8($dirname)." $mess[39] ";
				if($dir=="") {$messtmp.="/";} else {$messtmp.= SystemTextEncoding::toUTF8($dir);}
				$logMessage = $messtmp;
				$pendingSelection = $dirname;
				$reloadContextNode = true;
				AJXP_Logger::logAction("Create Dir", array("dir"=>$dir."/".$dirname));
				
			break;
		
			//------------------------------------
			//	CREER UN FICHIER / CREATE FILE
			//------------------------------------
			case "mkfile";
			
				$messtmp="";
				$filename=AJXP_Utils::processFileName(SystemTextEncoding::fromUTF8($httpVars["filename"]));	
				$this->filterUserSelectionToHidden(array($filename));
				$error = $this->createEmptyFile($dir, $filename);
				if(isSet($error)){
					throw new AJXP_Exception($error);
				}
				$messtmp.="$mess[34] ".SystemTextEncoding::toUTF8($filename)." $mess[39] ";
				if($dir=="") {$messtmp.="/";} else {$messtmp.=SystemTextEncoding::toUTF8($dir);}
				$logMessage = $messtmp;
				$reloadContextNode = true;
				$pendingSelection = $dir."/".$filename;
				AJXP_Logger::logAction("Create File", array("file"=>$dir."/".$filename));
		
			break;
			
			//------------------------------------
			//	CHANGE FILE PERMISSION
			//------------------------------------
			case "chmod";
			
				$messtmp="";
				$files = $selection->getFiles();
				$changedFiles = array();
				$chmod_value = $httpVars["chmod_value"];
				$recursive = $httpVars["recursive"];
				$recur_apply_to = $httpVars["recur_apply_to"];
				foreach ($files as $fileName){
					$error = $this->chmod($fileName, $chmod_value, ($recursive=="on"), ($recursive=="on"?$recur_apply_to:"both"), $changedFiles);
				}
				if(isSet($error)){
					throw new AJXP_Exception($error);
				}
				//$messtmp.="$mess[34] ".SystemTextEncoding::toUTF8($filename)." $mess[39] ";
				$logMessage="Successfully changed permission to ".$chmod_value." for ".count($changedFiles)." files or folders";
				$reloadContextNode = true;
				AJXP_Logger::logAction("Chmod", array("dir"=>$dir, "filesCount"=>count($changedFiles)));
		
			break;
			
			//------------------------------------
			//	UPLOAD
			//------------------------------------	
			case "upload":

				AJXP_Logger::debug("Upload Files Data", $fileVars);
				$destination=$this->urlBase.SystemTextEncoding::fromPostedFileName($dir);
				AJXP_Logger::debug("Upload inside", array("destination"=>$destination));
				if(!$this->isWriteable($destination))
				{
					$errorCode = 412;
					$errorMessage = "$mess[38] ".SystemTextEncoding::toUTF8($dir)." $mess[99].";
					AJXP_Logger::debug("Upload error 412", array("destination"=>$destination));
					return array("ERROR" => array("CODE" => $errorCode, "MESSAGE" => $errorMessage));
				}	
				foreach ($fileVars as $boxName => $boxData)
				{
					if(substr($boxName, 0, 9) != "userfile_") continue;
					$err = AJXP_Utils::parseFileDataErrors($boxData);
					if($err != null)
					{
						$errorCode = $err[0];
						$errorMessage = $err[1];
						break;
					}
					$userfile_name = $boxData["name"];
					try{
						$this->filterUserSelectionToHidden(array($userfile_name));					
					}catch (Exception $e){
						return array("ERROR" => array("CODE" => 411, "MESSAGE" => "Forbidden"));
					}
					$userfile_name=AJXP_Utils::processFileName($userfile_name);
					if(isSet($httpVars["auto_rename"])){
						$userfile_name = self::autoRenameForDest($destination, $userfile_name);
					}
					if(isSet($boxData["input_upload"])){
						try{
							AJXP_Logger::debug("Begining reading INPUT stream");
							$input = fopen("php://input", "r");
							$output = fopen("$destination/".$userfile_name, "w");
							$sizeRead = 0;
							while($sizeRead < intval($boxData["size"])){
								$chunk = fread($input, 4096);
								$sizeRead += strlen($chunk);
								fwrite($output, $chunk, strlen($chunk));
							}
							fclose($input);
							fclose($output);
							AJXP_Logger::debug("End reading INPUT stream");
						}catch (Exception $e){
							$errorCode=411;
							$errorMessage = $e->getMessage();
							break;
						}
					}else{
						if (!move_uploaded_file($boxData["tmp_name"], "$destination/".$userfile_name))
						{
							$errorCode=411;
							$errorMessage="$mess[33] ".$userfile_name;
							break;
						}
					}
					$this->changeMode($destination."/".$userfile_name);
					$logMessage.="$mess[34] ".SystemTextEncoding::toUTF8($userfile_name)." $mess[35] $dir";
					AJXP_Logger::logAction("Upload File", array("file"=>SystemTextEncoding::fromUTF8($dir)."/".$userfile_name));
				}
				
				if(isSet($errorMessage)){
					AJXP_Logger::debug("Return error $errorCode $errorMessage");
					return array("ERROR" => array("CODE" => $errorCode, "MESSAGE" => $errorMessage));
				}else{
					AJXP_Logger::debug("Return success");
					return array("SUCCESS" => true);
				}
				return ;
				
			break;
            
            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "public_url":
            	$subAction = (isSet($httpVars["sub_action"])?$httpVars["sub_action"]:"");
            	if($subAction == "delegate_repo"){
					header("Content-type:text/plain");				
					$result = $this->createSharedRepository($httpVars);				
					print($result);        		
            	}else if($subAction == "list_shared_users"){
            		header("Content-type:text/html");
            		$loggedUser = AuthService::getLoggedUser();
            		$allUsers = AuthService::listUsers();
            		$crtValue = $httpVars["value"];
            		$users = "";
            		foreach ($allUsers as $userId => $userObject){            			
            			if($crtValue != "" && (strstr($userId, $crtValue) === false || strstr($userId, $crtValue) != 0)) continue;
            			if($userObject->hasParent() && $userObject->getParent() == $loggedUser->getId()){
            				$users .= "<li>".$userId."</li>";
            			}
            		}
            		if(strlen($users)) {
            			print("<ul>".$users."</ul>");
            		}
            	}else{
					$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
	                $url = $this->makePubliclet($file, $httpVars["password"], $httpVars["expiration"]);
	                header("Content-type:text/plain");
	                echo $url;
            	}
            break;
						
			//------------------------------------
			//	XML LISTING
			//------------------------------------
			case "ls":
			
				if(!isSet($dir) || $dir == "/") $dir = "";
				$lsOptions = $this->parseLsOptions((isSet($httpVars["options"])?$httpVars["options"]:"a"));
								
				$startTime = microtime();
				
				$dir = AJXP_Utils::securePath(SystemTextEncoding::magicDequote($dir));
				$path = $this->urlBase.($dir!= ""?"/".$dir:"");	
				$threshold = $this->repository->getOption("PAGINATION_THRESHOLD");
				if(!isSet($threshold) || intval($threshold) == 0) $threshold = 500;
				$limitPerPage = $this->repository->getOption("PAGINATION_NUMBER");
				if(!isset($limitPerPage) || intval($limitPerPage) == 0) $limitPerPage = 200;
				
				$countFiles = $this->countFiles($path, !$lsOptions["f"]);
				if($countFiles > $threshold){
					$offset = 0;
					$crtPage = 1;
					if(isSet($page)){
						$offset = (intval($page)-1)*$limitPerPage; 
						$crtPage = $page;
					}
					$totalPages = floor($countFiles / $limitPerPage) + 1;
				}else{
					$offset = $limitPerPage = 0;
				}					
												
				$metaData = array();
				$crtLabel = AJXP_Utils::xmlEntities(basename($dir), true);
				if(RecycleBinManager::recycleEnabled()){
					if(RecycleBinManager::currentLocationIsRecycle($dir)){
						$metaData["ajxp_mime"] = "ajxp_recycle";
						$crtLabel = AJXP_Utils::xmlEntities($mess[122]);
					}else if($dir == ""){
						$metaData["repo_has_recycle"] = "true";
					}
				}
				if(AJXP_Utils::isBrowsableArchive($dir)){
					$metaData["ajxp_mime"] = "ajxp_browsable_archive";
				}
				AJXP_XMLWriter::renderHeaderNode(
					AJXP_Utils::xmlEntities($dir, true), 
					$crtLabel, 
					false, 
					$metaData);
				if(isSet($totalPages) && isSet($crtPage)){
					AJXP_XMLWriter::renderPaginationData($countFiles, $crtPage, $totalPages);
					if(!$lsOptions["f"]){
						AJXP_XMLWriter::close();
						exit(1);
					}
				}
				
				$cursor = 0;
				$handle = opendir($path);
				if(!$handle) {
					throw new AJXP_Exception("Cannot open dir ".$path);
				}
				closedir($handle);				
				$fullList = array("d" => array(), "z" => array(), "f" => array());				
				$nodes = scandir($path);
				//while(strlen($nodeName = readdir($handle)) > 0){
				foreach ($nodes as $nodeName){
					if($nodeName == "." || $nodeName == "..") continue;
					$isLeaf = (is_file($path."/".$nodeName) || AJXP_Utils::isBrowsableArchive($nodeName));
					if(!$this->filterNodeName($path, $nodeName, $isLeaf, $lsOptions)){
						continue;
					}
					if(RecycleBinManager::recycleEnabled() && $dir == "" && "/".$nodeName == RecycleBinManager::getRecyclePath()){
						continue;
					}					
					$nodeType = "d";
					if($isLeaf){
						if(AJXP_Utils::isBrowsableArchive($nodeName)) {
							if($lsOptions["f"] && $lsOptions["z"]){
								// See archives as files
								$nodeType = "f";
							}else{
								$nodeType = "z";
							}
						}
						else $nodeType = "f";
					}			
					if($offset > 0 && $cursor < $offset){
						$cursor ++;
						continue;
					}
					if($limitPerPage > 0 && ($cursor - $offset) >= $limitPerPage) {				
						break;
					}
					
					$metaData = array();
					$currentFile = $path."/".$nodeName;	
					$metaData["is_file"] = ($isLeaf?"1":"0");
					$metaData["filename"] = AJXP_Utils::xmlEntities(SystemTextEncoding::toUTF8($dir."/".$nodeName));
					$metaData["icon"] = AJXP_Utils::mimetype($nodeName, "image", !$isLeaf);
					if($metaData["icon"] == "folder.png"){
						$metaData["openicon"] = "folder_open.png";
					}
					if(!is_file($currentFile) || AJXP_Utils::isBrowsableArchive($nodeName)){
						$link = SystemTextEncoding::toUTF8(SERVER_ACCESS."?get_action=ls&options=dz&dir=".$dir."/".$nodeName);
						$link = urlencode($link);						
						$metaData["src"] = $link;
					}
					if($lsOptions["l"]){
						$metaData["file_group"] = @filegroup($currentFile) || "unknown";
						$metaData["file_owner"] = @fileowner($currentFile) || "unknown";
						$fPerms = @fileperms($currentFile);
						if($fPerms !== false){
							$fPerms = substr(decoct( $fPerms ), ($isLeaf?2:1));
						}else{
							$fPerms = '0000';
						}
						$metaData["file_perms"] = $fPerms;
						$metaData["mimestring"] = AJXP_Utils::mimetype($currentFile, "type", !$isLeaf);
						$datemodif = $this->date_modif($currentFile);
						$metaData["ajxp_modiftime"] = ($datemodif ? $datemodif : "0");
						$metaData["bytesize"] = 0;
						if($isLeaf){
							$metaData["bytesize"] = filesize($currentFile);							
							if($metaData["bytesize"] < 0){
								$metaData["bytesize"] = sprintf("%u", $metaData["bytesize"]);
							}
						}
						$metaData["filesize"] = AJXP_Utils::roundSize($metaData["bytesize"]);
						if(AJXP_Utils::isBrowsableArchive($nodeName)){
							$metaData["ajxp_mime"] = "ajxp_browsable_archive";
						}
						$realFile = null; // A reference to the real file.
						AJXP_Controller::applyHook("ls.metadata", array($currentFile, &$metaData, $this->wrapperClassName, &$realFile));						
					}
									
					$attributes = "";
					foreach ($metaData as $key => $value){
						$attributes .= "$key=\"$value\" ";
					}
					
					$renderNodeData = array(
						AJXP_Utils::xmlEntities($dir."/".$nodeName,true), 
						AJXP_Utils::xmlEntities($nodeName, true), 
						$isLeaf, 
						$metaData
					);
					$fullList[$nodeType][$nodeName] = $renderNodeData;
					$cursor ++;
				}				
				/*
				closedir($handle);
				foreach ($fullList as $key => $list){
					uksort($list, 'strnatcasecmp');
					$fullList[$key] = $list;
				}
				*/
				$allNodes = array_merge($fullList["d"], $fullList["z"], $fullList["f"]);				
				array_map(array("AJXP_XMLWriter", "renderNodeArray"), $fullList["d"]);
				array_map(array("AJXP_XMLWriter", "renderNodeArray"), $fullList["z"]);
				array_map(array("AJXP_XMLWriter", "renderNodeArray"), $fullList["f"]);
				
				// ADD RECYCLE BIN TO THE LIST
				if($dir == "" && RecycleBinManager::recycleEnabled())
				{
					$recycleBinOption = RecycleBinManager::getRelativeRecycle();										
					if(file_exists($this->urlBase.$recycleBinOption)){
						$recycleIcon = ($this->countFiles($this->urlBase.$recycleBinOption, false, true)>0?"trashcan_full.png":"trashcan.png");
						$recycleMetaData = array("ajxp_modiftime" 	=> $this->date_modif($this->urlBase.$recycleBinOption),
						  "mimestring" 		=> AJXP_Utils::xmlEntities($mess[122]),
						  "icon"			=> "$recycleIcon", 
						  "filesize"		=> "-",
						  "ajxp_mime"		=> "ajxp_recycle");
						$nullFile = null;
						AJXP_Controller::applyHook("ls.metadata", array($this->urlBase.$recycleBinOption, &$recycleMetaData, $this->wrapperClassName, &$nullFile));
						AJXP_XMLWriter::renderNode(
							$recycleBinOption,
							AJXP_Utils::xmlEntities($mess[122]),
							false, 
							$recycleMetaData
						);
					}
				}
				
				AJXP_Logger::debug("LS Time : ".intval((microtime()-$startTime)*1000)."ms");
				
				AJXP_XMLWriter::close();
				return ;
				
			break;		
		}

		
		$xmlBuffer = "";
		if(isset($logMessage) || isset($errorMessage))
		{
			$xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);			
		}				
		if($reloadContextNode){
			if(!isSet($pendingSelection)) $pendingSelection = "";
			$xmlBuffer .= AJXP_XMLWriter::reloadDataNode("", $pendingSelection, false);
		}
		if(isSet($reloadDataNode)){
			$xmlBuffer .= AJXP_XMLWriter::reloadDataNode($reloadDataNode, "", false);
		}
					
		return $xmlBuffer;
	}
			
	function parseLsOptions($optionString){
		// LS OPTIONS : dz , a, d, z, all of these with or without l
		// d : directories
		// z : archives
		// f : files
		// => a : all, alias to dzf
		// l : list metadata
		$allowed = array("a", "d", "z", "f", "l");
		$lsOptions = array();
		foreach ($allowed as $key){
			if(strchr($optionString, $key)!==false){
				$lsOptions[$key] = true;
			}else{
				$lsOptions[$key] = false;
			}
		}
		if($lsOptions["a"]){
			$lsOptions["d"] = $lsOptions["z"] = $lsOptions["f"] = true;
		}
		return $lsOptions;
	}
	
	/**
	 * Test if userSelection is containing a hidden file, which should not be the case!
	 *
	 * @param UserSelection $userSelection
	 */
	function filterUserSelectionToHidden($files){
		foreach ($files as $file){
			$file = basename($file);
			if(AJXP_Utils::isHidden($file) && !$this->driverConf["SHOW_HIDDEN_FILES"]){
				throw new Exception("Forbidden");
			}
			if($this->filterFile($file) || $this->filterFolder($file)){
				throw new Exception("Forbidden");
			}
		}
	}
	
	function filterNodeName($nodePath, $nodeName, $isLeaf, $lsOptions){
		if(AJXP_Utils::isHidden($nodeName) && !$this->driverConf["SHOW_HIDDEN_FILES"]){
			return false;
		}
		$nodeType = "d";
		if($isLeaf){
			if(AJXP_Utils::isBrowsableArchive($nodeName)) $nodeType = "z";
			else $nodeType = "f";
		}		
		if(!$lsOptions[$nodeType]) return false;
		if($nodeType == "d"){			
			if(RecycleBinManager::recycleEnabled() 
				&& $nodePath."/".$nodeName == RecycleBinManager::getRecyclePath()){
					return false;
				}
			return !$this->filterFolder($nodeName);
		}else{
			if($nodeName == "." || $nodeName == "..") return false;
			if(RecycleBinManager::recycleEnabled() 
				&& $nodePath == RecycleBinManager::getRecyclePath() 
				&& $nodeName == RecycleBinManager::getCacheFileName()){
				return false;
			}
			return !$this->filterFile($nodeName);
		}
	}
	
	function filterFile($fileName){
		$pathParts = pathinfo($fileName);
		if(array_key_exists("HIDE_FILENAMES", $this->driverConf) && is_array($this->driverConf["HIDE_FILENAMES"])){
			foreach ($this->driverConf["HIDE_FILENAMES"] as $search){
				if(strcasecmp($search, $pathParts["basename"]) == 0) return true;
			}
		}
		if(array_key_exists("HIDE_EXTENSIONS", $this->driverConf) && is_array($this->driverConf["HIDE_EXTENSIONS"])){
			foreach ($this->driverConf["HIDE_EXTENSIONS"] as $search){
				if(strcasecmp($search, $pathParts["extension"]) == 0) return true;
			}
		}
		return false;
	}
	
	function filterFolder($folderName){
		if(array_key_exists("HIDE_FOLDERS", $this->driverConf) && is_array($this->driverConf["HIDE_FOLDERS"])){
			foreach ($this->driverConf["HIDE_FOLDERS"] as $search){
				if(strcasecmp($search, $folderName) == 0) return true;
			}
		}
		return false;		
	}
	
	function readFile($filePathOrData, $headerType="plain", $localName="", $data=false, $gzip=GZIP_DOWNLOAD, $realfileSystem=false)
	{
		session_write_close();

        set_exception_handler('download_exception_handler');
        set_error_handler('download_exception_handler');
        // required for IE, otherwise Content-disposition is ignored
        if(ini_get('zlib.output_compression')) { ini_set('zlib.output_compression', 'Off'); }

		$isFile = !$data && !$gzip; 		
		$size = ($data ? strlen($filePathOrData) : filesize($filePathOrData));
		
		if($gzip && ($size > GZIP_LIMIT || !function_exists("gzencode") || @strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') === FALSE)){
			$gzip = false; // disable gzip
		}
		
		$localName = ($localName=="" ? basename($filePathOrData) : $localName);
		if($headerType == "plain")
		{
			header("Content-type:text/plain");
		}
		else if($headerType == "image")
		{
			header("Content-Type: ".AJXP_Utils::getImageMimeType(basename($filePathOrData))."; name=\"".$localName."\"");
			header("Content-Length: ".$size);
			header('Cache-Control: public');
		}
		else
		{
			if(preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']) || preg_match('/ WebKit /',$_SERVER['HTTP_USER_AGENT'])){
				$localName = str_replace("+", " ", urlencode(SystemTextEncoding::toUTF8($localName)));
			}

			if ($isFile) header("Accept-Ranges: bytes");
			// Check if we have a range header (we are resuming a transfer)
			if ( isset($_SERVER['HTTP_RANGE']) && $isFile && $size != 0 )
			{
				// multiple ranges, which can become pretty complex, so ignore it for now
				$ranges = explode('=', $_SERVER['HTTP_RANGE']);
				$offsets = explode('-', $ranges[1]);
				$offset = floatval($offsets[0]);

				$length = floatval($offsets[1]) - $offset;
				if (!$length) $length = $size - $offset;
				if ($length + $offset > $size || $length < 0) $length = $size - $offset;
				header('HTTP/1.1 206 Partial Content');

				header('Content-Range: bytes ' . $offset . '-' . ($offset + $length - 1) . '/' . $size);
				header("Content-Length: ". $length);
				$file = fopen($filePathOrData, 'rb');
				fseek($file, 0);
				$relOffset = $offset;
				while ($relOffset > 2.0E9)
				{
					// seek to the requested offset, this is 0 if it's not a partial content request
					fseek($file, 2000000000, SEEK_CUR);
					$relOffset -= 2000000000;
					// This works because we never overcome the PHP 32 bit limit
				}
				fseek($file, $relOffset, SEEK_CUR);

                while(ob_get_level()) ob_end_flush();
				$readSize = 0.0;
				while (!feof($file) && $readSize < $length && connection_status() == 0)
				{
					echo fread($file, 2048);
					$readSize += 2048.0;
					flush();
				}
				fclose($file);
				return;
			} else
			{
				header("Content-Type: application/force-download; name=\"".$localName."\"");
				header("Content-Transfer-Encoding: binary");
				if($gzip){
					header("Content-Encoding: gzip");
					// If gzip, recompute data size!
					$gzippedData = ($data?gzencode($filePathOrData,9):gzencode(file_get_contents($filePathOrData), 9));
					$size = strlen($gzippedData);
				}
				header("Content-Length: ".$size);
				if ($isFile && ($size != 0)) header("Content-Range: bytes 0-" . ($size - 1) . "/" . $size . ";");
				header("Content-Disposition: attachment; filename=\"".$localName."\"");
				header("Expires: 0");
				header("Cache-Control: no-cache, must-revalidate");
				header("Pragma: no-cache");
				if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])){
					header("Cache-Control: max_age=0");
					header("Pragma: public");
				}

                // IE8 is dumb
				if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']))
                {
                    header("Pragma: public");
                    header("Expires: 0");
                    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                    header("Cache-Control: private",false);
//                    header("Content-Type: application/octet-stream");
                }

				// For SSL websites there is a bug with IE see article KB 323308
				// therefore we must reset the Cache-Control and Pragma Header
				if (ConfService::getConf("USE_HTTPS")==1 && preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']))
				{
					header("Cache-Control:");
					header("Pragma:");
				}
				if($gzip){
					print $gzippedData;
					return;
				}
			}
		}

		if($data){
			print($filePathOrData);
		}else{
			$stream = fopen("php://output", "a");
			if($realfileSystem){
				AJXP_Logger::debug("realFS!", array("file"=>$filePathOrData));
		    	$fp = fopen($filePathOrData, "rb");
		    	while (!feof($fp)) {
		 			$data = fread($fp, 4096);
		 			fwrite($stream, $data, strlen($data));
		    	}
		    	fclose($fp);
			}else{
				call_user_func(array($this->wrapperClassName, "copyFileInStream"), $filePathOrData, $stream);
			}
			fflush($stream);
			fclose($stream);
		}
	}

	function countFiles($dirName, $foldersOnly = false, $nonEmptyCheckOnly = false){
		$handle=opendir($dirName);
		$count = 0;
		while (strlen($file = readdir($handle)) > 0)
		{
			if($file != "." && $file !=".." 
				&& !(AJXP_Utils::isHidden($file) && !$this->driverConf["SHOW_HIDDEN_FILES"])
				&& !($foldersOnly && is_file($dirName."/".$file)) ){
				$count++;
				if($nonEmptyCheckOnly) break;
			}			
		}
		closedir($handle);
		return $count;
	}
			
	function date_modif($file)
	{
		$tmp = @filemtime($file) or 0;
		return $tmp;// date("d,m L Y H:i:s",$tmp);
	}
	
	function changeMode($filePath)
	{
		$chmodValue = $this->repository->getOption("CHMOD_VALUE");
		if(isSet($chmodValue) && $chmodValue != "")
		{
			$chmodValue = octdec(ltrim($chmodValue, "0"));
			call_user_func(array($this->wrapperClassName, "changeMode"), $filePath, $chmodValue);
		}		
	}
	
	/**
	 * Extract an archive directly inside the dest directory.
	 *
	 * @param string $destDir
	 * @param UserSelection $selection
	 * @param array $error
	 * @param array $success
	 */
	function extractArchive($destDir, $selection, &$error, &$success){
		require_once("server/classes/pclzip.lib.php");
		$zipPath = $selection->getZipPath(true);
		$zipLocalPath = $selection->getZipLocalPath(true);
		if(strlen($zipLocalPath)>1 && $zipLocalPath[0] == "/") $zipLocalPath = substr($zipLocalPath, 1)."/";
		$files = $selection->getFiles();

		$realZipFile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase.$zipPath);
		$archive = new PclZip($realZipFile);
		$content = $archive->listContent();		
		foreach ($files as $key => $item){// Remove path
			$item = substr($item, strlen($zipPath));
			if($item[0] == "/") $item = substr($item, 1);			
			foreach ($content as $zipItem){
				if($zipItem["stored_filename"] == $item || $zipItem["stored_filename"] == $item."/"){
					$files[$key] = $zipItem["stored_filename"];
					break;
				}else{
					unset($files[$key]);
				}
			}
		}
		AJXP_Logger::debug("Archive", $files);
		$realDestination = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase.$destDir);
		AJXP_Logger::debug("Extract", array($realDestination, $realZipFile, $files, $zipLocalPath));
		$result = $archive->extract(PCLZIP_OPT_BY_NAME, $files, 
									PCLZIP_OPT_PATH, $realDestination, 
									PCLZIP_OPT_REMOVE_PATH, $zipLocalPath);
		if($result <= 0){
			$error[] = $archive->errorInfo(true);
		}else{
			$mess = ConfService::getMessages();
			$success[] = sprintf($mess[368], basename($zipPath), $destDir);
		}
	}
	
	function copyOrMove($destDir, $selectedFiles, &$error, &$success, $move = false)
	{
		AJXP_Logger::debug("CopyMove", array("dest"=>$destDir));
		$mess = ConfService::getMessages();
		if(!$this->isWriteable($this->urlBase.$destDir))
		{
			$error[] = $mess[38]." ".$destDir." ".$mess[99];
			return ;
		}
				
		foreach ($selectedFiles as $selectedFile)
		{
			if($move && !$this->isWriteable(dirname($this->urlBase.$selectedFile)))
			{
				$error[] = "\n".$mess[38]." ".dirname($selectedFile)." ".$mess[99];
				continue;
			}
			$this->copyOrMoveFile($destDir, $selectedFile, $error, $success, $move);
		}
	}
	
	function renameAction($actionName, $httpVars)
	{
		$filePath = SystemTextEncoding::fromUTF8($httpVars["file"]);
		$newFilename = SystemTextEncoding::fromUTF8($httpVars["filename_new"]);
		return $this->rename($filePath, $newFilename);
	}
	
	function rename($filePath, $filename_new)
	{
		$nom_fic=basename($filePath);
		$mess = ConfService::getMessages();
		$filename_new=AJXP_Utils::processFileName($filename_new);
		$old=$this->urlBase."/$filePath";
		if(!$this->isWriteable($old))
		{
			throw new AJXP_Exception($mess[34]." ".$nom_fic." ".$mess[99]);
		}
		$new=dirname($old)."/".$filename_new;
		if($filename_new=="")
		{
			throw new AJXP_Exception("$mess[37]");
		}
		if(file_exists($new))
		{
			throw new AJXP_Exception("$filename_new $mess[43]"); 
		}
		if(!file_exists($old))
		{
			throw new AJXP_Exception($mess[100]." $nom_fic");
		}
		AJXP_Controller::applyHook("move.metadata", array($old, $new, false));
		rename($old,$new);
	}
	
	function autoRenameForDest($destination, $fileName){
		if(!is_file($destination."/".$fileName)) return $fileName;
		$i = 1;
		$ext = "";
		$name = "";
		$split = explode(".", $fileName);
		if(count($split) > 1){
			$ext = ".".$split[count($split)-1];
			array_pop($split);
			$name = join("\.", $split);
		}else{
			$name = $fileName;
		}
		while (is_file($destination."/".$name."-$i".$ext)) {
			$i++; // increment i until finding a non existing file.
		}
		return $name."-$i".$ext;
	}
	
	function mkDir($crtDir, $newDirName)
	{
		$mess = ConfService::getMessages();
		if($newDirName=="")
		{
			return "$mess[37]";
		}
		if(file_exists($this->urlBase."$crtDir/$newDirName"))
		{
			return "$mess[40]"; 
		}
		if(!$this->isWriteable($this->urlBase."$crtDir"))
		{
			return $mess[38]." $crtDir ".$mess[99];
		}

        $dirMode = 0775;
		$chmodValue = $this->repository->getOption("CHMOD_VALUE");
		if(isSet($chmodValue) && $chmodValue != "")
		{
			$dirMode = octdec(ltrim($chmodValue, "0"));
			if ($dirMode & 0400) $dirMode |= 0100; // User is allowed to read, allow to list the directory
			if ($dirMode & 0040) $dirMode |= 0010; // Group is allowed to read, allow to list the directory
			if ($dirMode & 0004) $dirMode |= 0001; // Other are allowed to read, allow to list the directory
		}
		$old = umask(0);
		mkdir($this->urlBase."$crtDir/$newDirName", $dirMode);
		umask($old);
		return null;		
	}
	
	function createEmptyFile($crtDir, $newFileName)
	{
		$mess = ConfService::getMessages();
		if($newFileName=="")
		{
			return "$mess[37]";
		}
		if(file_exists($this->urlBase."$crtDir/$newFileName"))
		{
			return "$mess[71]";
		}
		if(!$this->isWriteable($this->urlBase."$crtDir"))
		{
			return "$mess[38] $crtDir $mess[99]";
		}
		
		$fp=fopen($this->urlBase."$crtDir/$newFileName","w");
		if($fp)
		{
			if(preg_match("/\.html$/",$newFileName)||preg_match("/\.htm$/",$newFileName))
			{
				fputs($fp,"<html>\n<head>\n<title>New Document - Created By AjaXplorer</title>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n");
			}
			fclose($fp);
			$this->changeMode($this->urlBase."/$crtDir/$newFileName");
			return null;
		}
		else
		{
			return "$mess[102] $crtDir/$newFileName (".$fp.")";
		}		
	}
	
	
	function delete($selectedFiles, &$logMessages)
	{
		$mess = ConfService::getMessages();
		foreach ($selectedFiles as $selectedFile)
		{	
			if($selectedFile == "" || $selectedFile == DIRECTORY_SEPARATOR)
			{
				return $mess[120];
			}
			$fileToDelete=$this->urlBase.$selectedFile;
			if(!file_exists($fileToDelete))
			{
				$logMessages[]=$mess[100]." ".SystemTextEncoding::toUTF8($selectedFile);
				continue;
			}		
			$this->deldir($fileToDelete);
			if(is_dir($fileToDelete))
			{
				$logMessages[]="$mess[38] ".SystemTextEncoding::toUTF8($selectedFile)." $mess[44].";
			}
			else 
			{
				$logMessages[]="$mess[34] ".SystemTextEncoding::toUTF8($selectedFile)." $mess[44].";
			}
			AJXP_Controller::applyHook("move.metadata", array($fileToDelete));
		}
		return null;
	}
	
	
	
	function copyOrMoveFile($destDir, $srcFile, &$error, &$success, $move = false)
	{
		$mess = ConfService::getMessages();		
		$destFile = $this->urlBase.$destDir."/".basename($srcFile);
		$realSrcFile = $this->urlBase.$srcFile;
		if(!file_exists($realSrcFile))
		{
			$error[] = $mess[100].$srcFile;
			return ;
		}		
		if(dirname($realSrcFile)==dirname($destFile))
		{
			if($move){
				$error[] = $mess[101];
				return ;
			}else{
				$base = basename($srcFile);
				$i = 1;
				if(is_file($realSrcFile)){
					$dotPos = strrpos($base, ".");
					if($dotPos>-1){
						$radic = substr($base, 0, $dotPos);
						$ext = substr($base, $dotPos);
					}
				}
				// auto rename file
				$i = 1;
				$newName = $base;
				while (file_exists($this->urlBase.$destDir."/".$newName)) {
					$suffix = "-$i";
					if(isSet($radic)) $newName = $radic . $suffix . $ext;
					else $newName = $base.$suffix;
					$i++;
				}
				$destFile = $this->urlBase.$destDir."/".$newName;
			}
		}
		if(!is_file($realSrcFile))
		{			
			$errors = array();
			$succFiles = array();
			if($move){				
				if(file_exists($destFile)) $this->deldir($destFile);
				$res = rename($realSrcFile, $destFile);
			}else{				
				$dirRes = $this->dircopy($realSrcFile, $destFile, $errors, $succFiles);
			}			
			if(count($errors) || (isSet($res) && $res!==true))
			{
				$error[] = $mess[114];
				return ;
			}			
		}
		else 
		{			
			if($move){	
				if(file_exists($destFile)) unlink($destFile);				
				$res = rename($realSrcFile, $destFile);
				AJXP_Controller::applyHook("move.metadata", array($realSrcFile, $destFile, false));
			}else{
				try{
					$src = fopen($realSrcFile, "r");
					$dest = fopen($destFile, "w");
					while (!feof($src)) {
						stream_copy_to_stream($src, $dest, 4096);
					}					
					fclose($src);
					fclose($dest);
					AJXP_Controller::applyHook("move.metadata", array($realSrcFile, $destFile, true));
				}catch (Exception $e){
					$error[] = $e->getMessage();
					return ;					
				}
			}
		}
		
		if($move)
		{
			// Now delete original
			// $this->deldir($realSrcFile); // both file and dir
			$messagePart = $mess[74]." ".SystemTextEncoding::toUTF8($destDir);
			if(RecycleBinManager::recycleEnabled() && $destDir == RecycleBinManager::getRelativeRecycle())
			{
				RecycleBinManager::fileToRecycle($srcFile);
				$messagePart = $mess[123]." ".$mess[122];
			}
			if(isset($dirRes))
			{
				$success[] = $mess[117]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$messagePart." (".SystemTextEncoding::toUTF8($dirRes)." ".$mess[116].") ";
			}
			else 
			{
				$success[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$messagePart;
			}
		}
		else
		{			
			if(RecycleBinManager::recycleEnabled() && $destDir == "/".$this->repository->getOption("RECYCLE_BIN"))
			{
				RecycleBinManager::fileToRecycle($srcFile);
			}
			if(isSet($dirRes))
			{
				$success[] = $mess[117]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$mess[73]." ".SystemTextEncoding::toUTF8($destDir)." (".SystemTextEncoding::toUTF8($dirRes)." ".$mess[116].")";	
			}
			else 
			{
				$success[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$mess[73]." ".SystemTextEncoding::toUTF8($destDir);
			}
		}
		
	}

	// A function to copy files from one directory to another one, including subdirectories and
	// nonexisting or newer files. Function returns number of files copied.
	// This function is PHP implementation of Windows xcopy  A:\dir1\* B:\dir2 /D /E /F /H /R /Y
	// Syntaxis: [$number =] dircopy($sourcedirectory, $destinationdirectory [, $verbose]);
	// Example: $num = dircopy('A:\dir1', 'B:\dir2', 1);

	function dircopy($srcdir, $dstdir, &$errors, &$success, $verbose = false) 
	{
		$num = 0;
		//$verbose = true;
		if(!is_dir($dstdir)) mkdir($dstdir);
		if($curdir = opendir($srcdir)) 
		{
			while($file = readdir($curdir)) 
			{
				if($file != '.' && $file != '..') 
				{
					$srcfile = $srcdir . "/" . $file;
					$dstfile = $dstdir . "/" . $file;
					if(is_file($srcfile)) 
					{
						if(is_file($dstfile)) $ow = filemtime($srcfile) - filemtime($dstfile); else $ow = 1;
						if($ow > 0) 
						{
							try { 
								$tmpPath = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $srcfile);
								if($verbose) echo "Copying '$tmpPath' to '$dstfile'...";
								copy($tmpPath, $dstfile);
								$success[] = $srcfile;
								$num ++;
							}catch (Exception $e){
								$errors[] = $srcfile;
							}
						}
					}
					else
					{
						if($verbose) echo "Dircopy $srcfile";
						$num += $this->dircopy($srcfile, $dstfile, $errors, $success, $verbose);
					}
				}
			}
			closedir($curdir);
		}
		return $num;
	}
	
	function simpleCopy($origFile, $destFile)
	{
		return copy($origFile, $destFile);
	}
	
	public function isWriteable($dir, $type="dir")
	{
		return is_writable($dir);
	}
	
	function deldir($location)
	{
		if(is_dir($location))
		{
			$all=opendir($location);
			while ($file=readdir($all))
			{
				if (is_dir("$location/$file") && $file !=".." && $file!=".")
				{
					$this->deldir("$location/$file");
					if(file_exists("$location/$file")){
						rmdir("$location/$file"); 
					}
					unset($file);
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
	
	/**
	 * Change file permissions 
	 *
	 * @param String $path
	 * @param String $chmodValue
	 * @param Boolean $recursive
	 * @param String $nodeType "both", "file", "dir"
	 */
	function chmod($path, $chmodValue, $recursive=false, $nodeType="both", &$changedFiles)
	{
	    $chmodValue = octdec(ltrim($chmodValue, "0"));
		if(is_file($this->urlBase.$path) && ($nodeType=="both" || $nodeType=="file")){
			call_user_func(array($this->wrapperClassName, "changeMode"), $this->urlBase.$path, $chmodValue);
			$changedFiles[] = $path;
		}else{
			if($nodeType=="both" || $nodeType=="dir"){
				call_user_func(array($this->wrapperClassName, "changeMode"), $this->urlBase.$path, $chmodValue);				
				$changedFiles[] = $path;
			}
			if($recursive){
				$handler = opendir($this->urlBase.$path);
				while ($child=readdir($handler)) {
					if($child == "." || $child == "..") continue;
					$this->chmod($path."/".$child, $chmodValue, $recursive, $nodeType, $changedFiles);
				}
				closedir($handler);
			}
		}
	}
	
	/**
	 * @return zipfile
	 */ 
    function makeZip ($src, $dest, $basedir)
    {
    	@set_time_limit(60);
    	require_once(SERVER_RESOURCES_FOLDER."/pclzip.lib.php");
    	$filePaths = array();
    	foreach ($src as $item){
    		$realFile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase."/".$item);    		
    		$realFile = AJXP_Utils::securePath($realFile);
    		$basedir = trim(dirname($realFile));
    		$filePaths[] = array(PCLZIP_ATT_FILE_NAME => $realFile, 
    							 PCLZIP_ATT_FILE_NEW_SHORT_NAME => basename($item));    				
    	}
    	AJXP_Logger::debug("Pathes", $filePaths);
    	AJXP_Logger::debug("Basedir", array($basedir));
    	$archive = new PclZip($dest);
    	$vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $basedir, PCLZIP_OPT_NO_COMPRESSION);
    	if(!$vList){
    		throw new Exception("Zip creation error : ($dest) ".$archive->errorInfo(true));
    	}
    	return $vList;
    }
    
    
    
    /** The publiclet URL making */
    function makePubliclet($filePath, $password, $expire)
    {
    	$data = array("DRIVER"=>$this->repository->getAccessType(), "OPTIONS"=>NULL, "FILE_PATH"=>$filePath, "ACTION"=>"download", "EXPIRE_TIME"=>$expire ? (time() + $expire * 86400) : 0, "PASSWORD"=>$password);
    	return $this->writePubliclet($data);
    }

    function makeSharedRepositoryOptions($httpVars){
		$newOptions = array(
			"PATH" => $this->repository->getOption("PATH").AJXP_Utils::decodeSecureMagic($httpVars["file"]), 
			"CREATE" => false, 
			"RECYCLE_BIN" => "", 
			"DEFAULT_RIGHTS" => "");
    	return $newOptions;			
    }

    
}

?>