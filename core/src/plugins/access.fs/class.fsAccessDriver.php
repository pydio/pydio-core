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
class fsAccessDriver extends AbstractAccessDriver 
{
	/**
	* @var Repository
	*/
	var $repository;
	
	function  fsAccessDriver($driverName, $filePath, $repository, $optOptions = NULL){
		parent::AbstractAccessDriver($driverName, $filePath, $repository);
	}
	
	function initRepository(){
		$create = $this->repository->getOption("CREATE");
		$path = $this->repository->getOption("PATH");
		$recycle = $this->repository->getOption("RECYCLE_BIN");
		if($create == true){
			if(!is_dir($path)) @mkdir($path);
			if(!is_dir($path)){
				return new AJXP_Exception("Cannot create root path for repository. Please check repository configuration or that your folder is writeable!");
			}
			if($recycle!= "" && !is_dir($path."/".$recycle)){
				@mkdir($path."/".$recycle);
				if(!is_dir($path."/".$recycle)){
					return new AJXP_Exception("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
				}
			}
		}else{
			if(!is_dir($path)){
				return new AJXP_Exception("Cannot find base path for your repository! Please check the configuration!");
			}
		}
	}
	
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		$xmlBuffer = "";
		foreach($httpVars as $getName=>$getValue){
			$$getName = Utils::securePath(SystemTextEncoding::magicDequote($getValue));
		}
		$selection = new UserSelection();

		$selection->initFromHttpVars($httpVars);
		if(isSet($dir) && $action != "upload") { $safeDir = $dir; $dir = SystemTextEncoding::fromUTF8($dir); }
		if(isSet($dest)) $dest = SystemTextEncoding::fromUTF8($dest);
		$mess = ConfService::getMessages();
		$recycleBinOption = $this->repository->getOption("RECYCLE_BIN");
		// FILTER ACTION FOR DELETE
		if($recycleBinOption!="" && $action == "delete" && $dir != "/".$recycleBinOption)
		{
			$action = "move";
			$dest = "/".$recycleBinOption;
			$dest_node = "AJAXPLORER_RECYCLE_NODE";
		}
		// FILTER ACTION FOR RESTORE
		if($recycleBinOption!="" &&  $action == "restore" && $dir == "/".$recycleBinOption)
		{
			$originalRep = RecycleBinManager::getFileOrigin($selection->getUniqueFile());
			if($originalRep != "")
			{
				$action = "move";
				$dest = $originalRep;
			}
		}
		// FILTER DIR PAGINATION ANCHOR
		if(isSet($dir) && strstr($dir, "#")!==false){
			$parts = split("#", $dir);
			$dir = $parts[0];
			$page = $parts[1];
		}		
		
		switch($action)
		{			
			//------------------------------------
			//	DOWNLOAD, IMAGE & MP3 PROXYS
			//------------------------------------
			case "download":
				AJXP_Logger::logAction("Download", array("files"=>$selection));				
				if($selection->inZip){
					$tmpDir = dirname($selection->getZipPath())."/.tmpExtractDownload";
					@mkdir($this->getPath()."/".$tmpDir);
					$this->convertSelectionToTmpFiles($tmpDir, $selection);
				}
				$zip = false;
				if($selection->isUnique()){
					if(is_dir($this->getPath()."/".$selection->getUniqueFile())) {
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
					if(!$zipFile) AJXP_Exception::errorToXml("Error while compressing");
					$localName = (basename($dir)==""?"Files":basename($dir)).".zip";
					$this->readFile($file, "force-download", $localName, false, false);
					register_shutdown_function("unlink", $file);
				}else{
					$this->readFile($this->getPath()."/".$selection->getUniqueFile(), "force-download");
				}
				if(isSet($tmpDir)){
					//$this->deldir($this->getPath()."/".$tmpDir);
					$delDir = $this->getPath()."/".$tmpDir;
					register_shutdown_function(array($this, "deldir"), $delDir);
				}
				exit(0);
			break;
		
			case "image_proxy":
				if($split = UserSelection::detectZip(SystemTextEncoding::fromUTF8($file))){
					require_once("server/classes/pclzip.lib.php");
					$zip = new PclZip($this->getPath().$split[0]);
					$data = $zip->extract(PCLZIP_OPT_BY_NAME, substr($split[1], 1), PCLZIP_OPT_EXTRACT_AS_STRING);
					header("Content-Type: ".Utils::getImageMimeType(basename($split[1]))."; name=\"".basename($split[1])."\"");
					header("Content-Length: ".strlen($data[0]["content"]));
					header('Cache-Control: public');
					print($data[0]["content"]);
				}else{
					
					if(isSet($get_thumb) && $get_thumb == "true" && $this->driverConf["GENERATE_THUMBNAIL"]){
						require_once("server/classes/PThumb.lib.php");
						$pThumb = new PThumb($this->driverConf["THUMBNAIL_QUALITY"]);						
						if(!$pThumb->isError()){							
							$pThumb->use_cache = $this->driverConf["USE_THUMBNAIL_CACHE"];
							$pThumb->cache_dir = INSTALL_PATH."/".$this->driverConf["THUMBNAIL_CACHE_DIR"];	
							$pThumb->fit_thumbnail($this->getPath()."/".SystemTextEncoding::fromUTF8($file), 200);
							if($pThumb->isError()){
								print_r($pThumb->error_array);
							}
							exit(0);
						}
					}
					
					$this->readFile($this->getPath()."/".SystemTextEncoding::fromUTF8($file), "image");
				}
				exit(0);
			break;
			
			case "mp3_proxy":
				if($split = UserSelection::detectZip(SystemTextEncoding::fromUTF8($file))){
					require_once("server/classes/pclzip.lib.php");
					$zip = new PclZip($this->getPath().$split[0]);
					$data = $zip->extract(PCLZIP_OPT_BY_NAME, substr($split[1], 1), PCLZIP_OPT_EXTRACT_AS_STRING);					
					header("Content-Type: audio/mp3; name=\"".basename($split[1])."\"");
					header("Content-Length: ".strlen($data[0]["content"]));
					print($data[0]["content"]);
				}else{
					$this->readFile($this->getPath()."/".SystemTextEncoding::fromUTF8($file), "mp3");
				}
				exit(0);
			break;
			
			//------------------------------------
			//	ONLINE EDIT
			//------------------------------------
			case "edit";	
				if(isset($save) && $save==1 && isSet($code))
				{
					// Reload "code" variable directly from POST array, do not "securePath"...
					$code = $_POST["code"];
					AJXP_Logger::logAction("Online Edition", array("file"=>SystemTextEncoding::fromUTF8($file)));
					$code=stripslashes($code);
					$code=str_replace("&lt;","<",$code);
					$fp=fopen($this->getPath().SystemTextEncoding::fromUTF8("/$file"),"w");
					fputs ($fp,$code);
					fclose($fp);
					echo $mess[115];
				}
				else 
				{
					$this->readFile($this->getPath()."/".SystemTextEncoding::fromUTF8($file), "plain");
				}
				exit(0);
			break;
		
			//------------------------------------
			//	COPY / MOVE
			//------------------------------------
			case "copy";
			case "move";
				
				if($selection->isEmpty())
				{
					$errorMessage = $mess[113];
					break;
				}
				if($selection->inZip()){
					$tmpDir = dirname($selection->getZipPath())."/.tmpExtractDownload";
					@mkdir($this->getPath()."/".$tmpDir);					
					$this->convertSelectionToTmpFiles($tmpDir, $selection);
					if(is_dir($tmpDir))	$this->deldir($this->getPath()."/".$tmpDir);					
				}
				$success = $error = array();
				
				$this->copyOrMove($dest, $selection->getFiles(), $error, $success, ($action=="move"?true:false));
				
				if(count($error)){
					$errorMessage = join("\n", $error);
				}
				else {
					$logMessage = join("\n", $success);
					AJXP_Logger::logAction(($action=="move"?"Move":"Copy"), array("files"=>$selection, "destination"=>$dest));
				}
				$reload_current_node = true;
				if(isSet($dest_node)) $reload_dest_node = $dest_node;
				$reload_file_list = true;
				
			break;
			
			//------------------------------------
			//	SUPPRIMER / DELETE
			//------------------------------------
			case "delete";
			
				if($selection->isEmpty())
				{
					$errorMessage = $mess[113];
					break;
				}
				$logMessages = array();
				$errorMessage = $this->delete($selection->getFiles(), $logMessages);
				if(count($logMessages))
				{
					$logMessage = join("\n", $logMessages);
				}
				AJXP_Logger::logAction("Delete", array("files"=>$selection));
				$reload_current_node = true;
				$reload_file_list = true;
				
			break;
		
			//------------------------------------
			//	RENOMMER / RENAME
			//------------------------------------
			case "rename";
			
				$file = SystemTextEncoding::fromUTF8($file);
				$filename_new = SystemTextEncoding::fromUTF8($filename_new);
				$error = $this->rename($file, $filename_new);
				if($error != null) {
					$errorMessage  = $error;
					break;
				}
				$logMessage= SystemTextEncoding::toUTF8($file)." $mess[41] ".SystemTextEncoding::toUTF8($filename_new);
				$reload_current_node = true;
				$reload_file_list = basename($filename_new);
				AJXP_Logger::logAction("Rename", array("original"=>$file, "new"=>$filename_new));
				
			break;
		
			//------------------------------------
			//	CREER UN REPERTOIRE / CREATE DIR
			//------------------------------------
			case "mkdir";
			        
				$messtmp="";
				$dirname=Utils::processFileName(SystemTextEncoding::fromUTF8($dirname));
				$error = $this->mkDir($dir, $dirname);
				if(isSet($error)){
					$errorMessage = $error; break;
				}
				$reload_file_list = $dirname;
				$messtmp.="$mess[38] ".SystemTextEncoding::toUTF8($dirname)." $mess[39] ";
				if($dir=="") {$messtmp.="/";} else {$messtmp.= SystemTextEncoding::toUTF8($dir);}
				$logMessage = $messtmp;
				$reload_current_node = true;
				AJXP_Logger::logAction("Create Dir", array("dir"=>$dir."/".$dirname));
				
			break;
		
			//------------------------------------
			//	CREER UN FICHIER / CREATE FILE
			//------------------------------------
			case "mkfile";
			
				$messtmp="";
				$filename=Utils::processFileName(SystemTextEncoding::fromUTF8($filename));	
				$error = $this->createEmptyFile($dir, $filename);
				if(isSet($error)){
					$errorMessage = $error; break;
				}
				$messtmp.="$mess[34] ".SystemTextEncoding::toUTF8($filename)." $mess[39] ";
				if($dir=="") {$messtmp.="/";} else {$messtmp.=SystemTextEncoding::toUTF8($dir);}
				$logMessage = $messtmp;
				$reload_file_list = $filename;
				AJXP_Logger::logAction("Create File", array("file"=>$dir."/".$filename));
		
			break;
			
			//------------------------------------
			//	CHANGE FILE PERMISSION
			//------------------------------------
			case "chmod";
			
				$messtmp="";
				$files = $selection->getFiles();
				$changedFiles = array();
				foreach ($files as $fileName){
					$error = $this->chmod($this->getPath().$fileName, $chmod_value, ($recursive=="on"), ($recursive=="on"?$recur_apply_to:"both"), $changedFiles);
				}
				if(isSet($error)){
					$errorMessage = $error; break;
				}
				//$messtmp.="$mess[34] ".SystemTextEncoding::toUTF8($filename)." $mess[39] ";
				$logMessage="Successfully changed permission to ".$chmod_value." for ".count($changedFiles)." files or folders";
				$reload_file_list = $dir;
				AJXP_Logger::logAction("Chmod", array("dir"=>$dir, "filesCount"=>count($changedFiles)));
		
			break;
			
			//------------------------------------
			//	UPLOAD
			//------------------------------------	
			case "upload":

				$fancyLoader = false;
				if(isSet($fileVars["Filedata"])){
					$fancyLoader = true;
					if($dir!="") $dir = "/".base64_decode($dir);
				}
				if($dir!=""){$rep_source="/$dir";}
				else $rep_source = "";
				$destination=SystemTextEncoding::fromUTF8($this->getPath().$rep_source);
				if(!$this->isWriteable($destination))
				{
					$errorMessage = "$mess[38] ".SystemTextEncoding::toUTF8($dir)." $mess[99].";
					if($fancyLoader){
						header('HTTP/1.0 412 '.$errorMessage);
						die('Error 412 '.$errorMessage);
					}else{
						print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext('".str_replace("'", "\'", $errorMessage)."');");		
						break;
					}
				}	
				$logMessage = "";
				foreach ($fileVars as $boxName => $boxData)
				{
					if($boxName != "Filedata" && substr($boxName, 0, 9) != "userfile_")	continue;
					if($boxName == "Filedata") $fancyLoader = true;
					$err = Utils::parseFileDataErrors($boxData, $fancyLoader);
					if($err != null)
					{
						$errorMessage = $err;
						break;
					}
					$userfile_name = $boxData["name"];
					if($fancyLoader) $userfile_name = SystemTextEncoding::fromUTF8($userfile_name);
					$userfile_name=Utils::processFileName($userfile_name);
					if(isSet($auto_rename)){
						$userfile_name = fsDriver::autoRenameForDest($destination, $userfile_name);
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
				$file = SystemTextEncoding::fromUTF8($file);
                $url = $this->makePubliclet($file, $password, $expiration);
                header("Content-type:text/plain");
                echo $url;
                exit(1);                
            break;
			
			//------------------------------------
			//	XML LISTING
			//------------------------------------
			case "ls":
			
				if(!isSet($dir) || $dir == "/") $dir = "";
				$searchMode = $fileListMode = $completeMode = false;
				if(isSet($mode)){
					if($mode == "search") $searchMode = true;
					else if($mode == "file_list") $fileListMode = true;
					else if($mode == "complete") $completeMode = true;
				}				
				if(isSet($skipZip) && $skipZip == "true"){
					$skipZip = true;
				}else{
					$skipZip = false;
				}
				if($test = UserSelection::detectZip($dir)){
					$liste = array();
					$zip = $this->zipListing($test[0], $test[1], $liste);
					AJXP_XMLWriter::header();
					$tmpDir = $this->getPath().dirname($test[0]).".tmpZipExtract";					
					foreach ($liste as $zipEntry){
						$atts = array();
						if(!$fileListMode && !$zipEntry["folder"]) continue;
						$atts[] = "is_file=\"".($zipEntry["folder"]?"false":"true")."\"";
						$atts[] = "text=\"".Utils::xmlEntities( basename(SystemTextEncoding::toUTF8($zipEntry["stored_filename"])))."\"";
						$atts[] = "filename=\"".Utils::xmlEntities( SystemTextEncoding::toUTF8($zipEntry["filename"]))."\"";
						if($fileListMode){
							$atts[] = "filesize=\"".Utils::roundSize($zipEntry["size"])."\"";
							$atts[] = "bytesize=\"".$zipEntry["size"]."\"";
							$atts[] = "ajxp_modiftime=\"".$zipEntry["mtime"]."\"";
							$atts[] = "mimestring=\"".Utils::mimetype($zipEntry["stored_filename"], "mime", $zipEntry["folder"])."\"";
							$atts[] = "icon=\"".Utils::mimetype($zipEntry["stored_filename"], "image", $zipEntry["folder"])."\"";
							$is_image = Utils::is_image(basename($zipEntry["stored_filename"]));
							$atts[] = "is_image=\"".$is_image."\"";
							if($is_image){
								if(!is_dir($tmpDir)) mkdir($tmpDir);
								$currentFile = $tmpDir."/".basename($zipEntry["stored_filename"]);								
								$data = $zip->extract(PCLZIP_OPT_BY_NAME, $zipEntry["stored_filename"], PCLZIP_OPT_REMOVE_ALL_PATH, PCLZIP_OPT_PATH, $tmpDir);
								list($width, $height, $type, $attr) = @getimagesize($currentFile);
								$atts[] = "image_type=\"".image_type_to_mime_type($type)."\"";
								$atts[] = "image_width=\"$width\"";
								$atts[] = "image_height=\"$height\"";
								unlink($currentFile);
							}
						}else{							
							$atts[] = "icon=\"client/images/foldericon.png\"";
							$atts[] = "openicon=\"client/images/foldericon.png\"";
							$atts[] = "src=\"content.php?dir=".urlencode(SystemTextEncoding::toUTF8($zipEntry["filename"]))."\"";
						}						
						print("<tree ".join(" ", $atts)."/>");
						if(is_dir($tmpDir)){
							rmdir($tmpDir);
						}
					}
					AJXP_XMLWriter::close();
					exit(0);
				}
				$nom_rep = $this->initName($dir);
				AJXP_Exception::errorToXml($nom_rep);
				
				if($fileListMode){
					$countFiles = $this->countFiles($nom_rep);
					$threshold = $this->repository->getOption("PAGINATION_THRESHOLD");
					if(!isSet($threshold) || intval($threshold) == 0) $threshold = 500;					
					if($countFiles > $threshold){
						$limitPerPage = $this->repository->getOption("PAGINATION_NUMBER");
						if(!isset($limitPerPage) || intval($limitPerPage) == 0) $limitPerPage = 200;
						$offset = 0;
						$crtPage = 1;
						if(isSet($page)){
							$offset = (intval($page)-1)*$limitPerPage; 
							$crtPage = $page;
						}
						$totalPages = floor($countFiles / $limitPerPage) + 1;
						$reps = $this->listing($nom_rep, false, $offset, $limitPerPage);						
					}else{
						$reps = $this->listing($nom_rep, $searchMode);
					}
				}else{
					$reps = $this->listing($nom_rep, !$searchMode);
				}
				//$reps = $result[0];
				AJXP_XMLWriter::header();
				if(isSet($totalPages) && isSet($crtPage)){
					print '<columns switchDisplayMode="list" switchGridMode="filelist"/>';
					print '<pagination count="'.$countFiles.'" total="'.$totalPages.'" current="'.$crtPage.'"/>';
				}
				foreach ($reps as $repIndex => $repName)
				{
					if((eregi("\.zip$",$repName) && $skipZip)) continue;
					$attributes = "";
					if($searchMode)
					{
						if(is_file($nom_rep."/".$repIndex)) {$attributes = "is_file=\"true\" icon=\"$repName\""; $repName = $repIndex;}
					}
					else if($fileListMode)
					{
						$currentFile = $nom_rep."/".$repIndex;			
						$atts = array();
						$atts[] = "is_file=\"".(is_file($currentFile)?"1":"0")."\"";
						$atts[] = "is_image=\"".Utils::is_image($currentFile)."\"";
						$atts[] = "file_group=\"".filegroup($currentFile)."\"";
						$atts[] = "file_owner=\"".fileowner($currentFile)."\"";
						$atts[] = "file_perms=\"".substr(decoct( fileperms($currentFile) ), (is_file($currentFile)?2:1))."\"";
						if(Utils::is_image($currentFile))
						{
							list($width, $height, $type, $attr) = @getimagesize($currentFile);
							$atts[] = "image_type=\"".image_type_to_mime_type($type)."\"";
							$atts[] = "image_width=\"$width\"";
							$atts[] = "image_height=\"$height\"";
						}
						$atts[] = "mimestring=\"".Utils::mimetype($currentFile, "type", is_dir($currentFile))."\"";
						$datemodif = $this->date_modif($currentFile);
						$atts[] = "ajxp_modiftime=\"".($datemodif ? $datemodif : "0")."\"";
						$bytesize = @filesize($currentFile) or 0;
						if($bytesize < 0) $bytesize = sprintf("%u", $bytesize);
						$atts[] = "filesize=\"".Utils::roundSize($bytesize)."\"";
						$atts[] = "bytesize=\"".$bytesize."\"";
						$atts[] = "filename=\"".Utils::xmlEntities( SystemTextEncoding::toUTF8($dir."/".$repIndex))."\"";
						$atts[] = "icon=\"".(is_file($currentFile)?SystemTextEncoding::toUTF8($repName):(is_dir($currentFile) ? "folder.png" : "mime-empty.png"))."\"";
						
						$attributes = join(" ", $atts);
						$repName = $repIndex;
					}
					else 
					{
						$folderBaseName = Utils::xmlEntities( $repName);
						$link = SystemTextEncoding::toUTF8(SERVER_ACCESS."?dir=".$dir."/".$folderBaseName);
						$link = urlencode($link);						
						$folderFullName = Utils::xmlEntities( $dir)."/".$folderBaseName;
						$parentFolderName = $dir;
						if(!$completeMode){
							$icon = CLIENT_RESOURCES_FOLDER."/images/foldericon.png";
							$openicon = CLIENT_RESOURCES_FOLDER."/images/openfoldericon.png";
							if(eregi("\.zip$",$repName)){
								$icon = $openicon = CLIENT_RESOURCES_FOLDER."/images/crystal/actions/16/accessories-archiver.png";
							}
							$attributes = "icon=\"$icon\"  openicon=\"$openicon\" filename=\"".SystemTextEncoding::toUTF8($folderFullName)."\" src=\"$link\"";
						}
					}
					print("<tree text=\"".Utils::xmlEntities( SystemTextEncoding::toUTF8($repName))."\" $attributes>");
					print("</tree>");
				}
				// ADD RECYCLE BIN TO THE LIST
				if($nom_rep == $this->repository->getOption("PATH") && $recycleBinOption!="" && !$completeMode && !$skipZip)
				{
					if($fileListMode)
					{
						print("<tree text=\"".Utils::xmlEntities($mess[122])."\" filesize=\"-\" is_file=\"0\" is_recycle=\"1\" mimestring=\"Trashcan\" ajxp_modiftime=\"".$this->date_modif($this->repository->getOption("PATH")."/".$recycleBinOption)."\" filename=\"/".$recycleBinOption."\" icon=\"trashcan.png\"></tree>");
					}
					else 
					{						
						print("<tree text=\"$mess[122]\" is_recycle=\"true\" icon=\"".CLIENT_RESOURCES_FOLDER."/images/crystal/mimes/16/trashcan.png\"  openIcon=\"".CLIENT_RESOURCES_FOLDER."/images/crystal/mimes/16/trashcan.png\" filename=\"/".$recycleBinOption."\"/>");
					}
				}
				AJXP_XMLWriter::close();
				exit(1);
				
			break;		
		}

		if(isset($logMessage) || isset($errorMessage))
		{
			$xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);			
		}
		
		if(isset($requireAuth))
		{
			$xmlBuffer .= AJXP_XMLWriter::requireAuth(false);
		}
		
		if(isset($reload_current_node) && $reload_current_node == "true")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadCurrentNode(false);
		}
		
		if(isset($reload_dest_node) && $reload_dest_node != "")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadNode($reload_dest_node, false);
		}
		
		if(isset($reload_file_list))
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadFileList($reload_file_list, false);
		}
		
		return $xmlBuffer;
	}
	
	function getPath(){
		return $this->repository->getOption("PATH");
	}
	
	function zipListing($zipPath, $localPath, &$filteredList){
		require_once("server/classes/pclzip.lib.php");
		$crtZip = new PclZip($this->getPath()."/".$zipPath);
		$liste = $crtZip->listContent();
		$files = array();
		if($localPath[strlen($localPath)-1] != "/") $localPath.="/";
		foreach ($liste as $item){
			$stored = $item["stored_filename"];			
			if($stored[0] != "/") $stored = "/".$stored;						
			$pathPos = strpos($stored, $localPath);
			if($pathPos !== false){
				$afterPath = substr($stored, $pathPos+strlen($localPath));
				if($afterPath != "" && strpos($afterPath, "/")=== false || strpos($afterPath, "/") == strlen($afterPath)-1){
					$item["filename"] = $zipPath.$localPath.$afterPath;
					if($item["folder"]){
						$filteredList[] = $item;
					}else{
						$files[] = $item;
					}
				}
				
			}
		}
		$filteredList = array_merge($filteredList, $files);
		return $crtZip;		
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
	
	function initName($dir)
	{
		$racine = $this->getPath();		
		$mess = ConfService::getMessages();
		if(!isset($dir) || $dir=="" || $dir == "/")
		{
			$nom_rep=$racine;
		}
		else
		{
			$nom_rep="$racine/$dir";
		}
		if(!file_exists($racine))
		{
			return new AJXP_Exception(72);
		}
		if(!is_dir($nom_rep))
		{
			return new AJXP_Exception(100);
		}
		return $nom_rep;
	}

    function getTrueSize($file) {
        if (!(strtoupper(substr(PHP_OS, 0, 3)) == 'WIN'))
            return trim(`stat -Lc%s $file`);
        else if (extension_loaded("COM")){
            $fsobj = new COM("Scripting.FileSystemObject");
            $f = $fsobj->GetFile($file);
            return floatval($file->Size);
        }
        else if (is_file($file)){
            return exec('FOR %A IN ("'.$file.'") DO @ECHO %~zA');
        }
        else return '0';
    } 	

	function readFile($filePathOrData, $headerType="plain", $localName="", $data=false, $gzip=GZIP_DOWNLOAD)
	{		
		session_write_close();
		$size = ($data ? strlen($filePathOrData) : floatval(trim($this->getTrueSize($filePathOrData))));
        $isFile = !$data && !$gzip;
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
			header("Content-Type: ".Utils::getImageMimeType(basename($filePathOrData))."; name=\"".$localName."\"");
			header("Content-Length: ".$size);
			header('Cache-Control: public');			
		}
		else if($headerType == "mp3")
		{
			header("Content-Type: audio/mp3; name=\"".$localName."\"");
			header("Content-Length: ".$size);
		}
		else 
		{
			if(preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']) || preg_match('/ WebKit /',$_SERVER['HTTP_USER_AGENT'])){
				$localName = str_replace("+", " ", urlencode(SystemTextEncoding::toUTF8($localName)));
			}			

            if ($isFile) header("Accept-Ranges: bytes");
            // Check if we have a range header (we are resuming a transfer)
            if ( isset($_SERVER['HTTP_RANGE']) &&  $isFile ) 
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
                if ($isFile) header("Content-Range: bytes 0-" . $size - 1 . "/" . $size . ";");
  			    header("Content-Disposition: attachment; filename=\"".$localName."\"");
			    header("Expires: 0");
			    header("Cache-Control: no-cache, must-revalidate");
			    header("Pragma: no-cache");
			    if (preg_match('/ MSIE 6/',$_SERVER['HTTP_USER_AGENT'])){
			    	header("Cache-Control: max_age=0");
				    header("Pragma: public");
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
			readfile($filePathOrData);
		}
	}
	
	
	function countFiles($dirName){
		$handle=opendir($dirName);
		$count = 0;
		while ($file = readdir($handle))
		{
			if($file != "." && $file !=".." && !(Utils::isHidden($file) && !$this->driverConf["SHOW_HIDDEN_FILES"])){
				$count++;
			}			
		}
		closedir($handle);
		return $count;
	}
	
	function listing($nom_rep, $dir_only = false, $offset=0, $limit=0)
	{
		$mess = ConfService::getMessages();
		$size_unit = $mess["byte_unit_symbol"];
		$orderDir = 0;
		$orderBy = "filename";
		$handle=opendir($nom_rep);
		$recycle = $this->repository->getOption("RECYCLE_BIN");
		$cursor = 0;
		while ($file = readdir($handle))
		{
			if($file!="." && $file!=".." && !(Utils::isHidden($file) && !$this->driverConf["SHOW_HIDDEN_FILES"]))
			{
				if($offset > 0 && $cursor < $offset){
					$cursor ++;
					continue;
				}
				if($limit > 0 && ($cursor - $offset) >= $limit) {
					break;
				}
				$cursor ++;
				if($recycle != "" 
					&& $nom_rep == $this->repository->getOption("PATH")."/".$recycle 
					&& $file == RecycleBinManager::getCacheFileName()){
					continue;
				}
				$poidsfic=@filesize("$nom_rep/$file") or 0;
				if(is_dir("$nom_rep/$file"))
				{	
					if($this->filterFolder($file)) continue;				
					if($recycle != "" && $this->getPath()."/".$recycle == "$nom_rep/$file")
					{
						continue;
					}
					if($orderBy=="mod") {$liste_rep[$file]=filemtime("$nom_rep/$file");}
					else {$liste_rep[$file]=$file;}
				}
				else
				{
					if($this->filterFile($file)) continue;
					if(!$dir_only)
					{
						if($orderBy=="filename") {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","image", is_dir("$nom_rep/$file"));}
						else if($orderBy=="filesize") {$liste_fic[$file]=$poidsfic;}
						else if($orderBy=="mod") {$liste_fic[$file]=filemtime("$nom_rep/$file");}
						else if($orderBy=="filetype") {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","type",is_dir("$nom_rep/$file"));}
						else {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","image", is_dir("$nom_rep/$file"));}
					}
					else if(eregi("\.zip$",$file) && ConfService::zipEnabled()){
						if(!isSet($liste_zip)) $liste_zip = array();
						$liste_zip[$file] = $file;
					}
				}
			}
		}
		closedir($handle);
	
		if(isset($liste_fic) && is_array($liste_fic))
		{
			if($orderBy=="filename") {if($orderDir==0){ksort($liste_fic);}else{krsort($liste_fic);}}
			else if($orderBy=="mod") {if($orderDir==0){arsort($liste_fic);}else{asort($liste_fic);}}
			else if($orderBy=="filesize"||$orderBy=="filetype") {if($orderDir==0){asort($liste_fic);}else{arsort($liste_fic);}}
			else {if($orderDir==0){ksort($liste_fic);}else{krsort($liste_fic);}}

			if($orderBy != "filename"){
				foreach ($liste_fic as $index=>$value){
					$liste_fic[$index] = Utils::mimetype($index, "image", false);
				}
			}
		}
		else
		{
			$liste_fic = array();
		}
		if(isset($liste_rep) && is_array($liste_rep))
		{
			if($orderBy=="mod") {if($orderDir==0){arsort($liste_rep);}else{asort($liste_rep);}}
			else {if($orderDir==0){ksort($liste_rep);}else{krsort($liste_rep);}}
			if($orderBy != "filename"){
				foreach ($liste_rep as $index=>$value){
					$liste_rep[$index] = $index;
				}
			}
		}
		else ($liste_rep = array());

		$liste = Utils::mergeArrays($liste_rep,$liste_fic);
		if(isSet($liste_zip)){
			$liste = Utils::mergeArrays($liste,$liste_zip);
		}
	
		return $liste;
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
			chmod($filePath, octdec(ltrim($chmodValue, "0")));
		}		
	}
	
	function copyOrMove($destDir, $selectedFiles, &$error, &$success, $move = false)
	{
		$mess = ConfService::getMessages();
		if(!is_writable($this->getPath()."/".$destDir))
		{
			$error[] = $mess[38]." ".$destDir." ".$mess[99];
			return ;
		}
				
		foreach ($selectedFiles as $selectedFile)
		{
			if($move && !is_writable(dirname($this->getPath()."/".$selectedFile)))
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
		$filename_new=Utils::processFileName($filename_new);
		$old=$this->getPath()."/$filePath";
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
		$split = split("\.", $fileName);
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
		if(file_exists($this->getPath()."/$crtDir/$newDirName"))
		{
			return "$mess[40]"; 
		}
		if(!is_writable($this->getPath()."/$crtDir"))
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
		mkdir($this->getPath()."/$crtDir/$newDirName", $dirMode);
		return null;		
	}
	
	function createEmptyFile($crtDir, $newFileName)
	{
		$mess = ConfService::getMessages();
		if($newFileName=="")
		{
			return "$mess[37]";
		}
		if(file_exists($this->getPath()."/$crtDir/$newFileName"))
		{
			return "$mess[71]";
		}
		if(!is_writable($this->getPath()."/$crtDir"))
		{
			return "$mess[38] $crtDir $mess[99]";
		}
		
		$fp=fopen($this->getPath()."/$crtDir/$newFileName","w");
		if($fp)
		{
			if(eregi("\.html$",$newFileName)||eregi("\.htm$",$newFileName))
			{
				fputs($fp,"<html>\n<head>\n<title>New Document - Created By AjaXplorer</title>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n");
			}
			fclose($fp);
			$this->changeMode($this->getPath()."/$crtDir/$newFileName");
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
			$fileToDelete=$this->getPath().$selectedFile;
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
		$destFile = $this->repository->getOption("PATH").$destDir."/".basename($srcFile);
		$realSrcFile = $this->repository->getOption("PATH")."/$srcFile";
		$recycle = $this->repository->getOption("RECYCLE_BIN");		
		if(!file_exists($realSrcFile))
		{
			$error[] = $mess[100].$srcFile;
			return ;
		}
		if($realSrcFile==$destFile)
		{
			$error[] = $mess[101];
			return ;
		}
		if(is_dir($realSrcFile))
		{
			$errors = array();
			$succFiles = array();
			if($move){
				if(is_file($destFile)) unlink($destFile);
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
				if(is_file($destFile)) unlink($destFile);
				$res = rename($realSrcFile, $destFile);
			}else{
				$res = copy($realSrcFile,$destFile);
			}
			if($res != 1)
			{
				$error[] = $mess[114];
				return ;
			}
		}
		
		if($move)
		{
			// Now delete original
			// $this->deldir($realSrcFile); // both file and dir
			$messagePart = $mess[74]." ".SystemTextEncoding::toUTF8($destDir);
			if($destDir == "/".$recycle)
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
			if($destDir == "/".$this->repository->getOption("RECYCLE_BIN"))
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
		if(!is_dir($dstdir)) mkdir($dstdir);
		if($curdir = opendir($srcdir)) 
		{
			while($file = readdir($curdir)) 
			{
				if($file != '.' && $file != '..') 
				{
					$srcfile = $srcdir . DIRECTORY_SEPARATOR . $file;
					$dstfile = $dstdir . DIRECTORY_SEPARATOR . $file;
					if(is_file($srcfile)) 
					{
						if(is_file($dstfile)) $ow = filemtime($srcfile) - filemtime($dstfile); else $ow = 1;
						if($ow > 0) 
						{
							if($verbose) echo "Copying '$srcfile' to '$dstfile'...";
							if(copy($srcfile, $dstfile)) 
							{
								touch($dstfile, filemtime($srcfile)); $num++;
								if($verbose) echo "OK\n";
								$success[] = $srcfile;
							}
							else 
							{
								$errors[] = $srcfile;
							}
						}
					}
					else if(is_dir($srcfile)) 
					{
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
		if(is_file($path) && ($nodeType=="both" || $nodeType=="file")){
			chmod($path, $chmodValue);
			$changedFiles[] = $path;
		}else if(is_dir($path)){
			if($nodeType=="both" || $nodeType=="dir"){
				chmod($path, $chmodValue);				
				$changedFiles[] = $path;
			}
			if($recursive){
				$handler = opendir($path);
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
    	$totalSize = 0;
    	foreach ($src as $item){
    		$filePaths[] = array(PCLZIP_ATT_FILE_NAME => $this->getPath().$item, 
    							 PCLZIP_ATT_FILE_NEW_SHORT_NAME => basename($item));
    	}
    	$archive = new PclZip($dest);
    	$vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $this->getPath().$basedir, PCLZIP_OPT_NO_COMPRESSION);
    	if($vList == 0) return false;
    }
    
    
    /**
     * @param $selection UserSelection
     */
    function convertSelectionToTmpFiles($tmpDir, &$selection){
    	$zipPath = $selection->getZipPath();
    	$localDir = $selection->getZipLocalPath();
    	$files = $selection->getFiles();
    	foreach ($files as $key => $item){// Remove path
    		$item = substr($item, strlen($zipPath));
    		if($item[0] == "/") $item = substr($item, 1);
    		$files[$key] = $item;
    	}
    	require_once("server/classes/pclzip.lib.php");
    	$zip = new PclZip($this->getPath().$zipPath);
    	$err = $zip->extract(PCLZIP_OPT_BY_NAME, $files, 
    				  PCLZIP_OPT_PATH, $this->getPath()."/".$tmpDir);
    	foreach ($files as $key => $item){// Remove path
    		$files[$key] = $tmpDir."/".$item;
    	}
    	$selection->setFiles($files);
    }
    
    /** The publiclet URL making */
    function makePubliclet($filePath, $password, $expire)
    {
        $data = array("DRIVER"=>"fs", "OPTIONS"=>NULL, "FILE_PATH"=>$filePath, "ACTION"=>"download", "EXPIRE_TIME"=>$expire ? (time() + $expire * 86400) : 0, "PASSWORD"=>$password);
        return $this->writePubliclet($data);
     }
    
}

?>
