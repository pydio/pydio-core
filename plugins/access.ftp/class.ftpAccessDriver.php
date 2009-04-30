<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * 
 * Description : The most used and standard plugin : FileSystem access
 */
class ftpAccessDriver extends AbstractAccessDriver
{
	/**
	* @var Repository
	*/
	var $connect;

	function  ftpAccessDriver($driverName, $filePath, $repository){
		parent::AbstractAccessDriver($driverName, INSTALL_PATH."/plugins/access.fs/fsActions.xml", $repository);
	}
	
	function initRepository(){
		$this->connect = $this->createFTPLink();
	}

	function createFTPLink(){ 
        $link = FALSE;  
        //Connects to the FTP.          
        $repo = ConfService::getRepository();
        $user = $repo->getOption("FTP_USER");
        $pass = $repo->getOption("FTP_PASS");
        $host = $repo->getOption("FTP_HOST");
        $this->path = $repo->getOption("PATH");
        $link = @ftp_connect($host);
        if(!$link) {
                $ajxpExp = new AJXP_Exception("Cannot connect to FTP server!");
                AJXP_Exception::errorToXml($ajxpExp);
        }
        if(!@ftp_login($link,$user,$pass)){
                $ajxpExp = new AJXP_Exception("Cannot login to FTP server!");
                AJXP_Exception::errorToXml($ajxpExp);
        }
        return $link;
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
		
		switch($action)
		{			
			//------------------------------------
			//	DOWNLOAD, IMAGE & MP3 PROXYS
			//------------------------------------
			case "download":
				AJXP_Logger::logAction("Download", array("files"=>$selection));
				$this->ftp_get_contents($this->connect,'',$this->getPath().$selection->getUniqueFile(),'');	
				$reload_current_node = true;
                $reload_file_list = true;
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
				$logMessage="Successfully changed permission for ".count($changedFiles)." files or folders";
				$reload_file_list = $dir;
				AJXP_Logger::logAction("Chmod", array("dir"=>$dir, "filesCount"=>count($changedFiles)));
		
			break;
			
			//------------------------------------
			//	UPLOAD
			//------------------------------------	
			case "upload":

				if($dir!=""){$rep_source="/$dir";}
				else $rep_source = "";
				$destination=SystemTextEncoding::fromUTF8($this->getPath().$rep_source);
				if(!$this->isWriteable($destination))
				{
					$errorMessage = "$mess[38] ".SystemTextEncoding::toUTF8($dir)." $mess[99].";
					break;
				}	
				$logMessage = "";
				$fancyLoader = false;
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
						$atts[] = "text=\"".str_replace("&", "&amp;", basename(SystemTextEncoding::toUTF8($zipEntry["stored_filename"])))."\"";
						$atts[] = "filename=\"".str_replace("&", "&amp;", SystemTextEncoding::toUTF8($zipEntry["filename"]))."\"";
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
				$result = $this->listing($nom_rep, !($searchMode || $fileListMode));
#				print_r($result);
				$reps = $result[0];
				AJXP_XMLWriter::header();
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
						$currentFile = $nom_rep.$repName['name'];			
						$atts = array();
						$atts[] = "is_file=\"".(is_file($currentFile)?"1":"0")."\"";
						$atts[] = "is_image=\"".Utils::is_image($currentFile)."\"";
						$atts[] = "file_group=\"".$repName['group']."\"";
						$atts[] = "file_owner=\"".$repName['owner']."\"";
						$atts[] = "file_perms=\"".$repName['chmod']."\"";
						if(Utils::is_image($currentFile))
						{
							list($width, $height, $type, $attr) = @getimagesize($currentFile);
							$atts[] = "image_type=\"".image_type_to_mime_type($type)."\"";
							$atts[] = "image_width=\"$width\"";
							$atts[] = "image_height=\"$height\"";
						}
						$atts[] = "mimestring=\"".$repName['type']."\"";
						$datemodif = $repName['modifTime'];
						$atts[] = "ajxp_modiftime=\"".($datemodif ? $datemodif : "0")."\"";
						$bytesize = $repName['size'] or 0;
						if($bytesize < 0) $bytesize = sprintf("%u", $bytesize);
						$atts[] = "filesize=\"".Utils::roundSize($bytesize)."\"";
						$atts[] = "bytesize=\"".$bytesize."\"";
						$atts[] = "filename=\"".str_replace("&", "&amp;", SystemTextEncoding::toUTF8($dir."/".$repIndex))."\"";
						$atts[] = "icon=\"".$repName['icon']."\"";
						$attributes = join(" ", $atts);
						$repName = $repIndex;
					}
					else 
					{
						//Menu treeview repertoire
						$folderBaseName = str_replace("&", "&amp;", $repName['name']);
						$link = SystemTextEncoding::toUTF8(SERVER_ACCESS."?dir=".$dir."/".$folderBaseName);
						$link = urlencode($link);						
						$folderFullName = str_replace("&", "&amp;", $dir)."/".$folderBaseName;
						$parentFolderName = $dir;
						$repName = $repIndex;
						if(!$completeMode){
							$icon = CLIENT_RESOURCES_FOLDER."/images/foldericon.png";
							$openicon = CLIENT_RESOURCES_FOLDER."/images/openfoldericon.png";
							if(eregi("\.zip$",$repName)){
								$icon = $openicon = CLIENT_RESOURCES_FOLDER."/images/crystal/actions/16/accessories-archiver.png";
							}
							$attributes = "icon=\"$icon\"  openicon=\"$openicon\" filename=\"".SystemTextEncoding::toUTF8($folderFullName)."\" src=\"$link\"";
						}
					}
					print("<tree text=\"".str_replace("&", "&amp;", SystemTextEncoding::toUTF8($repName))."\" $attributes>");
					print("</tree>");
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
			$nom_rep=$this->secureFtpPath($racine."/".$dir);
		}
		return $nom_rep;
	}

	function secureFtpPath($v_in) {
	        $v_in  = utf8_encode(htmlspecialchars($v_in));
		$v_out = str_replace(array("%2F","%2F%2F","//","///","\\"),"",$v_in);
		return $v_out;
	}


	function readFile($filePathOrData, $headerType="plain", $localName="", $data=false, $gzip=GZIP_DOWNLOAD)
	{		
		$size = ($data ? strlen($filePathOrData) : filesize($filePathOrData));
		if(!$data && $size < 0){
			// fix files above 2Gb 
			$size = sprintf("%u", $size);
		}
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
			header("Content-Type: application/force-download; name=\"".$localName."\"");
			header("Content-Transfer-Encoding: binary");
			if($gzip){
				header("Content-Encoding: gzip");
				// If gzip, recompute data size!
				$gzippedData = ($data?gzencode($filePathOrData,9):gzencode(file_get_contents($filePathOrData), 9));
				$size = strlen($gzippedData);
			}
			header("Content-Length: ".$size);
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
		if($data){
			print($filePathOrData);
		}else{
			readfile($filePathOrData);
		}
	}
	
	
	function listing($nom_rep, $dir_only = false)
	{
		$mess = ConfService::getMessages();
		$size_unit = $mess["byte_unit_symbol"];
		$sens = 0;
		$ordre = "nom";
		$poidstotal=0;
		$contents = ftp_rawlist($this->connect, $nom_rep);
		foreach($contents as $entry)
	       	{
                	$info = array();                              
			$vinfo = preg_split("/[\s]+/", $entry, 9);                            
        		if ($vinfo[0] !== "total")
		       	{
			        $info['chmod'] = $vinfo[0];                                         
				$info['num']   = $vinfo[1];
	          		$info['owner'] = $vinfo[2];
	          		$info['group'] = $vinfo[3];
	          		$info['size']  = $vinfo[4];
	          		$info['month'] = $vinfo[5];
	          		$info['day']   = $vinfo[6];
	          		$info['time']  = $vinfo[7];
	          		$info['name']  = $vinfo[8];
    			 }
        		 $file  = trim($info['name']);
			 $filetaille= trim($info['size']);
        		 $filedate  = trim($info['day'])." ".trim($info['month'])." ".trim($info['time']);
       			 $fileperms = trim($info['chmod']);
			 $isDir =false;
			 $info['modifTime']=$filedate;
			 $info['isDir']=false;
			 //gestion des Simbolic Link pour la navigation	
			 if (strpos($fileperms,"d")!==FALSE || strpos($fileperms,"l")!==FALSE)
			 {
				 if(strpos($fileperms,"l")!==FALSE)
				 {
        				$test=explode(" ->", $file);
					$file=$test[0];
					$info['name']=$file;
			 	 }
				 $isDir=true;
				 $info['isDir']=true;
			}
		                                                        
			if($file!="." && $file!=".." )
			{

				$poidstotal+=$filetaille;
				//fixme : utile pour la navigation de gauche
				if($isDir)
				{	
					$liste_rep[$file]=$info;
					$liste_rep[$file]['icon']=Utils::mimetype("$nom_rep/$file","image", $isDir);
					$liste_rep[$file]['type']=Utils::mimetype("$nom_rep/$file","type", $isDir);
				}
				else
				{
					if(!$dir_only)
					{
						$liste_fic[$file]=$info;
						$liste_fic[$file]['icon']=Utils::mimetype("$nom_rep/$file","image", $isDir);
						$liste_fic[$file]['type']=Utils::mimetype("$nom_rep/$file","type", $isDir); 
					}
					else if(eregi("\.zip$",$file) && ConfService::zipEnabled()){
						if(!isSet($liste_zip)) $liste_zip = array();
						$liste_zip[$file] = $file;
					}
				}
			}
		}
		if(isset($liste_fic) && is_array($liste_fic))
		{
			if($ordre=="nom") {if($sens==0){ksort($liste_fic);}else{krsort($liste_fic);}}
			else if($ordre=="mod") {if($sens==0){arsort($liste_fic);}else{asort($liste_fic);}}
			else if($ordre=="taille"||$ordre=="type") {if($sens==0){asort($liste_fic);}else{arsort($liste_fic);}}
			else {if($sens==0){ksort($liste_fic);}else{krsort($liste_fic);}}

			if($ordre != "nom"){
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
			if($ordre=="mod") {if($sens==0){arsort($liste_rep);}else{asort($liste_rep);}}
			else {if($sens==0){ksort($liste_rep);}else{krsort($liste_rep);}
			}
			if($ordre != "nom"){
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
		if ($poidstotal >= 1073741824) {$poidstotal = round($poidstotal / 1073741824 * 100) / 100 . " G".$size_unit;}
		elseif ($poidstotal >= 1048576) {$poidstotal = round($poidstotal / 1048576 * 100) / 100 . " M".$size_unit;}
		elseif ($poidstotal >= 1024) {$poidstotal = round($poidstotal / 1024 * 100) / 100 . " K".$size_unit;}
		else {$poidstotal = $poidstotal . " ".$size_unit;}
	
		return array($liste,$poidstotal);
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
		$pathFile= dirname($filePath);		
		$mess = ConfService::getMessages();
		$filename_new=Utils::processFileName($filename_new);
                $filename_new = $this->secureFtpPath($this->getPath()."/".$pathFile."///".$filename_new);
		$nom_fic = $this->secureFtpPath($this->getPath()."/".$pathFile."///".$nom_fic);		
		ftp_rename($this->connect,$nom_fic, $filename_new);		
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
		if(@ftp_mkdir($this->connect,utf8_decode($this->getPath()."/$crtDir/$newDirName"))===false)
		{
			return $mess[38]." $crtDir ".$mess[99];
		}
		return null;		
	}
	
	function createEmptyFile($crtDir, $newFileName)
	{
		$mess = ConfService::getMessages();
		if($newFileName=="")
		{
			return "$mess[37]";
		}
		// Local creation => ftp upload => local deletion
		$fp=fopen("files/".$newFileName,"x+");
		if($fp)
		{
			if(eregi("\.html$",$newFileName)||eregi("\.htm$",$newFileName))
			{
				fputs($fp,"<html>\n<head>\n<title>New Document - Created By AjaXplorer</title>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n");
			}
			fclose($fp);
			@ftp_put($this->connect,utf8_decode($this->getPath()."/$crtDir/$newFileName"),utf8_decode("files/".$newFileName), FTP_BINARY);
			@unlink(utf8_decode("files/".$newFileName));
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
		#	$fileToDelete=$this->getPath().$selectedFile;
		#	if(!file_exists($fileToDelete))
		#	{
		#		$logMessages[]=$mess[100]." ".SystemTextEncoding::toUTF8($selectedFile);
		#		continue;
		#	}		
		#	$this->ftpRecursiveDelete($fileToDelete);
		#	if(is_dir($fileToDelete))
		#	{
		#		$logMessages[]="$mess[38] ".SystemTextEncoding::toUTF8($selectedFile)." $mess[44].";
		#	}
		#	else 
		#	{
		#		$logMessages[]="$mess[34] ".SystemTextEncoding::toUTF8($selectedFile)." $mess[44].";
		#	}
		}
		return null;
	}
	
	function getRawlistFile($dir)
	{	
	$ftp_rawlist = ftp_rawlist($this->connect, $dir);
  		foreach ($ftp_rawlist as $v) 
		{
    			$info = array();
    			$vinfo = preg_split("/[\s]+/", $v, 9);
    			if ($vinfo[0] !== "total") 
			{
      				$info['chmod'] = $vinfo[0];
      				$info['num'] = $vinfo[1];
      				$info['owner'] = $vinfo[2];
      				$info['group'] = $vinfo[3];
      				$info['size'] = $vinfo[4];
      				$info['month'] = $vinfo[5];
      				$info['day'] = $vinfo[6];
      				$info['time'] = $vinfo[7];
      				$info['name'] = $vinfo[8];
      				$rawlist[$info['name']] = $info;
    			}
  		}
	return $rawlist;
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
	
	// Recursive ftp delete
	function ftpRecursiveDelete($dir)
	{
        if (($contents = ftp_rawlist($this->connect, $dir))!==FALSE) {
         foreach($contents as $file) {

           if (preg_match("/^[.]{2}$|^[.]{1}$/", $file)==0) {

             $info = array();
             $vinfo = preg_split("/[\s]+/", $file, 9);
             if ($vinfo[0] !== "total") {
               $fileperms     = $vinfo[0];
               $filename      = $vinfo[8];
             }
             if (strpos($fileperms,"d")!==FALSE) $this->ftpRecursiveDelete("$dir/$filename");
             else @ftp_delete($this->connect,"$dir/$filename");

           }
         }
         @ftp_rmdir($this->connect,"$dir/");
      } else return false;
      return true;
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
					if(file_exists("$location/$file")){rmdir("$location/$file"); }
					unset($file);
				}
				elseif (!is_dir("$location/$file"))
				{
					if(file_exists("$location/$file")){unlink("$location/$file"); }
					unset($file);
				}
			}
			closedir($all);
			rmdir($location);
		}
		else
		{
			if(file_exists("$location")) {unlink("$location");}
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
    
    // Distant loading
    function ftp_get_contents($ftp_stream, $remote_file, $mode, $resume_pos=null)
    {
    	$name_file= basename($this->secureFtpPath($remote_file));
    	$pathRoot= dirname(isset($_SERVER["PATH_TRANSLATED"])?$_SERVER["PATH_TRANSLATED"]:$_SERVER["SCRIPT_FILENAME"]);
    	ftp_get($ftp_stream, $this->secureFtpPath($pathRoot."/files/".$name_file), $remote_file, FTP_BINARY);
    	@unlink ($this->secureFtpPath($pathRoot."/files/".$name_file));
    }


}

?>
