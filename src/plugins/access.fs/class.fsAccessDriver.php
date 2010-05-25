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
		if($create == true){
			if(!is_dir($path)) @mkdir($path);
			if(!is_dir($path)){
				throw new AJXP_Exception("Cannot create root path for repository. Please check repository configuration or that your folder is writeable!");
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
		$recycle = $this->repository->getOption("RECYCLE_BIN");
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
		$mess = ConfService::getMessages();
		
		$newArgs = RecycleBinManager::filterActions($action, $selection, $dir);
		if(isSet($newArgs["action"])) $action = $newArgs["action"];
		if(isSet($newArgs["dest"])) $httpVars["dest"] = SystemTextEncoding::toUTF8($newArgs["dest"]);//Re-encode!
 		// FILTER DIR PAGINATION ANCHOR
		$page = null;
		if(isSet($dir) && strstr($dir, "#")!==false){
			$parts = explode("#", $dir);
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
						$dir .= "/".basename($selection->getUniqueFile());
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
					$localName = (basename($dir)==""?"Files":basename($dir)).".zip";
					$this->readFile($file, "force-download", $localName, false, false, true);
				}else{
					$this->readFile($this->urlBase.$selection->getUniqueFile(), "force-download");
				}
				exit(0);
			break;
		
			case "compress" : 					
					// Make a temp zip and send it as download					
					if(isSet($httpVars["archive_name"])){
						$localName = AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]);
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
			
			//------------------------------------
			//	ONLINE EDIT
			//------------------------------------
			case "get_content":
					
				$this->readFile($this->urlBase.AJXP_Utils::decodeSecureMagic($httpVars["file"]), "plain");
				exit(0);
			break;
			
			case "put_content":	
				if(!isset($httpVars["content"])) break;
				// Reload "code" variable directly from POST array, do not "securePath"...
				$code = $httpVars["content"];
				$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
				AJXP_Logger::logAction("Online Edition", array("file"=>$file));
				$code=stripslashes($code);
				$code=str_replace("&lt;","<",$code);
				$fileName = $this->urlBase.$file;
				if(!is_file($fileName) || !is_writable($fileName)){
					header("Content-Type:text/plain");
					print((!is_writable($fileName)?"1001":"1002"));
					exit(1);
				}
				$fp=fopen($fileName,"w");
				fputs ($fp,$code);
				fclose($fp);
				header("Content-Type:text/plain");
				print($mess[115]);
				exit(0);
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
				$this->copyOrMove($dest, $selection->getFiles(), $error, $success, ($action=="move"?true:false));
				
				if(count($error)){
					throw new AJXP_Exception(join("\n", $error));
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
				if($errorMessage) throw new AJXP_Exception($errorMessage);
				AJXP_Logger::logAction("Delete", array("files"=>$selection));
				$reloadContextNode = true;
				
			break;
		
			//------------------------------------
			//	RENOMMER / RENAME
			//------------------------------------
			case "rename";
			
				$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
				$filename_new = AJXP_Utils::decodeSecureMagic($httpVars["filename_new"]);
				$error = $this->rename($file, $filename_new);
				if($error != null) {
					throw new AJXP_Exception($error);
				}
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

				$fancyLoader = false;
				AJXP_Logger::debug("Upload Filedata", $fileVars["Filedata"]);
				if(isSet($fileVars["Filedata"])){
					$fancyLoader = true;
					if($dir!="") $dir = "/".base64_decode($dir);
				}
				if($dir!=""){$rep_source="/$dir";}
				else $rep_source = "";
				$destination=$this->urlBase.SystemTextEncoding::fromPostedFileName($rep_source);
				AJXP_Logger::debug("Upload inside", array("destination"=>$destination));
				if(!$this->isWriteable($destination))
				{
					$errorMessage = "$mess[38] ".SystemTextEncoding::toUTF8($dir)." $mess[99].";
					if($fancyLoader || isset($httpVars["ajxp_sessid"])){
						AJXP_Logger::debug("Upload error 412", array("destination"=>$destination));
						header('HTTP/1.0 412 '.$errorMessage);
						die('Error 412 '.$errorMessage);
					}else{
						AJXP_Logger::debug("Upload error 412", array("destination"=>$destination));
						print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext('".str_replace("'", "\'", $errorMessage)."');");		
						break;
					}
				}	
				foreach ($fileVars as $boxName => $boxData)
				{
					if($boxName != "Filedata" && substr($boxName, 0, 9) != "userfile_")	continue;
					if($boxName == "Filedata") $fancyLoader = true;
					$err = AJXP_Utils::parseFileDataErrors($boxData, $fancyLoader);
					if($err != null)
					{
						$errorMessage = $err;
						break;
					}
					$userfile_name = $boxData["name"];
					if($fancyLoader) $userfile_name = SystemTextEncoding::fromUTF8($userfile_name);
					$userfile_name=AJXP_Utils::processFileName($userfile_name);
					if(isSet($httpVars["auto_rename"])){
						$userfile_name = self::autoRenameForDest($destination, $userfile_name);
					}
					if (!move_uploaded_file($boxData["tmp_name"], "$destination/".$userfile_name))
					{
						$errorMessage=($fancyLoader?"411 ":"")."$mess[33] ".$userfile_name;
						break;
					}
					$this->changeMode($destination."/".$userfile_name);
					$logMessage.="$mess[34] ".SystemTextEncoding::toUTF8($userfile_name)." $mess[35] $dir";
					AJXP_Logger::logAction("Upload File", array("file"=>SystemTextEncoding::fromUTF8($dir)."/".$userfile_name));
				}
				if($fancyLoader)
				{
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
				exit;
				
			break;
            
            //------------------------------------
            // Public URL
            //------------------------------------
            case "public_url":
				$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $url = $this->makePubliclet($file, $httpVars["password"], $httpVars["expiration"]);
                header("Content-type:text/plain");
                echo $url;
                exit(1);                
            break;
			
			//------------------------------------
			//	XML LISTING
			//------------------------------------
			case "ls":
			
				if(!isSet($dir) || $dir == "/") $dir = "";
				$lsOptions = $this->parseLsOptions((isSet($httpVars["options"])?$httpVars["options"]:"a"));
								
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
				if(RecycleBinManager::recycleEnabled() && RecycleBinManager::currentLocationIsRecycle($dir)){
					$metaData["ajxp_mime"] = "ajxp_recycle";
				}
								
				AJXP_XMLWriter::renderHeaderNode(
					AJXP_Utils::xmlEntities($dir, true), 
					AJXP_Utils::xmlEntities(basename($dir), true), 
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
				if(!$handle) throw new AJXP_Exception("Cannot open dir ".$path);
				$fullList = array("d" => array(), "z" => array(), "f" => array());
				while(strlen($nodeName = readdir($handle)) > 0){
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
						$metaData["is_image"] = AJXP_Utils::is_image($currentFile);
						$metaData["file_group"] = @filegroup($currentFile) || "unknown";
						$metaData["file_owner"] = @fileowner($currentFile) || "unknown";
						$fPerms = @fileperms($currentFile);
						if($fPerms !== false){
							$fPerms = substr(decoct( $fPerms ), ($isLeaf?2:1));
						}else{
							$fPerms = '0000';
						}
						$metaData["file_perms"] = $fPerms;
						if(AJXP_Utils::is_image($currentFile))
						{
							$realFile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $currentFile);
							list($width, $height, $type, $attr) = @getimagesize($realFile);
							$metaData["image_type"] = image_type_to_mime_type($type);
							$metaData["image_width"] = $width;
							$metaData["image_height"] = $height;
						}
						$metaData["mimestring"] = AJXP_Utils::mimetype($currentFile, "type", !$isLeaf);
						$datemodif = $this->date_modif($currentFile);
						$metaData["ajxp_modiftime"] = ($datemodif ? $datemodif : "0");
						$metaData["bytesize"] = 0;
						if($isLeaf){
							$metaData["bytesize"] = filesize($currentFile);							
						}
						$metaData["filesize"] = AJXP_Utils::roundSize($metaData["bytesize"]);
						if(AJXP_Utils::isBrowsableArchive($nodeName)){
							$metaData["ajxp_mime"] = "ajxp_browsable_archive";
						}
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
				foreach ($fullList as $key => $list){
					uksort($list, 'strnatcasecmp');
					$fullList[$key] = $list;
				}
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
						
						AJXP_XMLWriter::renderNode(
							$recycleBinOption,
							AJXP_Utils::xmlEntities(AJXP_Utils::xmlEntities($mess[122])),
							false, 
							array("ajxp_modiftime" 	=> $this->date_modif($this->urlBase.$recycleBinOption),
								  "mimestring" 		=> "Trashcan",
								  "icon"			=> "$recycleIcon", 
								  "filesize"		=> "-",
								  "ajxp_mime"		=> "ajxp_recycle")
						);
					}
				}
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

        set_exception_handler(download_exception_handler);
        set_error_handler(download_exception_handler);
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
				if($nonEmptyCheckOnly) return 1;
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
	
	function copyOrMove($destDir, $selectedFiles, &$error, &$success, $move = false)
	{
		$mess = ConfService::getMessages();
		if(!is_writable($this->urlBase.$destDir))
		{
			$error[] = $mess[38]." ".$destDir." ".$mess[99];
			return ;
		}
				
		foreach ($selectedFiles as $selectedFile)
		{
			if($move && !is_writable(dirname($this->urlBase.$selectedFile)))
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
		if(!is_writable($old))
		{
			return $mess[34]." ".$nom_fic." ".$mess[99];
		}
		$new=dirname($old)."/".$filename_new;
		if($filename_new=="")
		{
			return "$mess[37]";
		}
		if(file_exists($new))
		{
			return "$filename_new $mess[43]"; 
		}
		if(!file_exists($old))
		{
			return $mess[100]." $nom_fic";
		}
		rename($old,$new);
		return null;		
	}
	
	function autoRenameForDest($destination, $fileName){
		if(!is_file($destination."/".$fileName)) return $fileName;
		$i = 1;
		$ext = "";
		$name = "";
		$split = explode("\.", $fileName);
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
		if(!is_writable($this->urlBase."$crtDir"))
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
		mkdir($this->urlBase."$crtDir/$newDirName", $dirMode);
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
		if(!is_writable($this->urlBase."$crtDir"))
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
			}else{
				try{
					copy($realSrcFile, $destFile);
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
	
	function isWriteable($dir)
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
    	$safeMode =  (@ini_get("safe_mode") == 'On' || @ini_get("safe_mode") === 1) ? TRUE : FALSE;
    	if(!$safeMode){
	    	set_time_limit(60);
    	}
    	require_once(SERVER_RESOURCES_FOLDER."/pclzip.lib.php");
    	$filePaths = array();
    	foreach ($src as $item){
    		$realFile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase."/".$item);    		
    		$basedir = trim(dirname($realFile));
    		$filePaths[] = array(PCLZIP_ATT_FILE_NAME => $realFile, 
    							 PCLZIP_ATT_FILE_NEW_SHORT_NAME => basename($item));
    	}   		
    	$archive = new PclZip($dest);
    	$vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $basedir, PCLZIP_OPT_NO_COMPRESSION);
    	return $vList;
    }
    
    
    
    /** The publiclet URL making */
    function makePubliclet($filePath, $password, $expire)
    {
        $data = array("DRIVER"=>$this->repository->getAccessType(), "OPTIONS"=>NULL, "FILE_PATH"=>$filePath, "ACTION"=>"download", "EXPIRE_TIME"=>$expire ? (time() + $expire * 86400) : 0, "PASSWORD"=>$password);
        return $this->writePubliclet($data);
     }
    
}

?>