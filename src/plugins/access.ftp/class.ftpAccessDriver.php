<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * 
 * Description : The most used and standard plugin : FileSystem access
 */
class ftpAccessDriver extends  AbstractAccessDriver
{
	/**
	* @var Repository
	*/
	var $connect;
        /** The user to connect to */
        var $user;
        /** The password to use */
	var $password;
	var $path;

        function  ftpAccessDriver($driverName, $filePath, $repository, $optOptions = NULL){
        	$this->user = $optOptions ? $optOptions["user"] : $this->getUserName($repository);
        	$this->password = $optOptions ? $optOptions["password"] : $this->getPassword($repository);
		parent::AbstractAccessDriver($driverName, INSTALL_PATH."/plugins/access.fs/fsActions.xml", $repository);
		unset($this->actions["upload"]);
		// DISABLE NON-IMPLEMENTED FUNCTIONS FOR THE MOMENT
		unset($this->actions["copy"]);
		unset($this->actions["move"]);
		unset($this->actions["chmod"]);
		$this->initXmlActionsFile(INSTALL_PATH."/plugins/access.remote_fs/additionalActions.xml");
		$this->xmlFilePath = INSTALL_PATH."/plugins/access.fs/fsActions.xml";
	}


        function initRepository(){
            $this->connect = $this->createFTPLink();
            // Try to detect the charset encoding
            global $_SESSION;
            if (!isset($_SESSION["ftpCharset"]) || !strlen($_SESSION["ftpCharset"]))
            {
                $features = $this->getServerFeatures();
                $_SESSION["charset"] = $features["charset"];
                $_SESSION["ftpCharset"] = $features["charset"];
            }
        }

        function getUserName($repository){
            $logUser = AuthService::getLoggedUser();
            $wallet = $logUser->getPref("AJXP_WALLET");
            return is_array($wallet) ? $wallet[$repository->getUniqueId()]["FTP_USER"] : "";
        }

        function getPassword($repository){
            $logUser = AuthService::getLoggedUser();
            $wallet = $logUser->getPref("AJXP_WALLET");
            return is_array($wallet) ? $logUser->decodeUserPassword($wallet[$repository->getUniqueId()]["FTP_PASS"]) : "";
        }

        /** This method retrieves the FTP server features as described in RFC2389
            A decent FTP server support MLST command to list file using UTF-8 encoding
            @return an array of features (see code) */ 
        function getServerFeatures(){
            $features = @ftp_raw($this->connect, "FEAT");
            // Check the answer code
            if (!$this->checkCode($features)) return array("list"=>"LIST", "charset"=>$this->repository->getOption("CHARSET"));
            $retArray = array("list"=>"LIST", "charset"=>$this->repository->getOption("CHARSET"));
            // Ok, find out the encoding used
            foreach($features as $feature)
            {
                if (strstr($feature, "UTF8") !== FALSE)
                {   // See http://wiki.filezilla-project.org/Character_Set for an explaination
                    @ftp_raw($this->connect, "OPTS UTF-8 ON");
                    $retArray['charset'] = "UTF-8"; 
                    return $retArray;
                }
            }
            // In the future version, we should also use MLST as it standardize the listing format
            return $retArray;
        }

        function checkCode($array)
        {   // Good output is 2xx value
            if ($array[0] && $array[0][0] != "2") return FALSE;
            return TRUE;
        }

        function createFTPLink(){
        	$link = FALSE;
       		//Connects to the FTP.          
	        $host = $this->repository->getOption("FTP_HOST");
	        $this->path = $this->repository->getOption("PATH");
	        $link = @ftp_connect($host);
	        if(!$link) {
	            $ajxpExp = new AJXP_Exception("Cannot connect to FTP server!");
	            AJXP_Exception::errorToXml($ajxpExp);
	               
	 	    }
            register_shutdown_function('ftp_close', $link);
            @ftp_set_option($link, FTP_TIMEOUT_SEC, 10);
		    if(!@ftp_login($link,$this->user,$this->password)){
	            $ajxpExp = new AJXP_Exception("Cannot login to FTP server!");
	            AJXP_Exception::errorToXml($ajxpExp);
	        }
            if ($this->repository->getOption("FTP_DIRECT") != "TRUE")
            {
                @ftp_pasv($link, true);
                global $_SESSION;
                $_SESSION["ftpPasv"]="true";
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
		
		switch($action)
		{			
			//------------------------------------
			//	DOWNLOAD, IMAGE & MP3 PROXYS
			//------------------------------------
			case "download":
			case "image_proxy":
			case "mp3_proxy":
				AJXP_Logger::logAction("Download", array("files"=>$selection));
               			$this->sendRemoteFile($selection->files[0], $action == "download");
				exit(0);		
			break;
		
			//------------------------------------
			//	ONLINE EDIT
			//------------------------------------
			case "edit";	
			$file_name = basename($file);
			$this->ftp_get_contents($file);
			if(isset($save) && $save==1 && isSet($code))
			{
					// Reload "code" variable directly from POST array, do not "securePath"...
					$code = $_POST["code"];
					AJXP_Logger::logAction("Online Edition", array("file"=>SystemTextEncoding::fromUTF8($file_name)));
					$code=stripslashes($code);
					$code=str_replace("&lt;","<",$code);
					$fp=fopen("files/".SystemTextEncoding::fromUTF8("$file_name"),"w");
					fputs ($fp,$code);
					fclose($fp);
					echo $mess[115];
					ftp_put($this->connect,$this->secureFtpPath($this->getPath().$file),"files/".SystemTextEncoding::fromUTF8($file_name), FTP_BINARY);
					$this->ftpRemoveFileTmp("files/".SystemTextEncoding::fromUTF8("$file_name"));
				 $reload_current_node = true;

				}
				else 
				{
					$this->readFile("files/".SystemTextEncoding::fromUTF8($file_name), "plain");
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
			$this->copyOrMove($dest, $selection->getFiles(), $error, $success, ($action=="move"?true:false));	
			$errorMessage = "function not implemented";
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
				$errorMessage = $this->delete($selection->getFiles(), $logMessages,$dir);
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
			
				$errorMessage = "function not implemented";
	                        $reload_current_node = true;
        	                if(isSet($dest_node)) $reload_dest_node = $dest_node;
                	        $reload_file_list = true;

			break;
			
			//------------------------------------
			//	UPLOAD
			//------------------------------------	
			case "upload":

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
				$this->fileListData = $result[0];
				$reps = $result[0];
				AJXP_XMLWriter::header();
                if (!is_array($reps))
                {
       				AJXP_XMLWriter::close();
    				exit(1);
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
						$currentFile = $nom_rep."/".$repName['name'];			
						$atts = array();
						$atts[] = "is_file=\"".($repName['isDir']?"0":"1")."\"";
						$atts[] = "is_image=\"".Utils::is_image($currentFile)."\"";
						$atts[] = "file_group=\"".$repName['group']."\"";
						$atts[] = "file_owner=\"".$repName['owner']."\"";
						$atts[] = "file_perms=\"".$repName['chmod1']."\"";
						if(Utils::is_image($currentFile))
						{
							list($width, $height, $type, $attr) = $this->getimagesize($currentFile);
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
				@ftp_put($this->connect,$this->secureFtpPath($this->path.base64_decode($fData['destination'])."/".$fData['name']),$fData['tmp_name'], FTP_BINARY);
                                unlink($fData["tmp_name"]);
                               AJXP_XMLWriter::header();
                                        if($nextFile!=''){
                                                AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".$nextFile." to remote server");
					}else{
						AJXP_XMLWriter::sendMessage("Done", null);
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
                                        if($boxName != "Filedata" && substr($boxName, 0, 9) != "userfile_")     continue;
                                        if($boxName == "Filedata") $fancyLoader = true;
                                        $err = Utils::parseFileDataErrors($boxData, $fancyLoader);
                                        if($err != null)
                                        {
                                                $errorMessage = $err;
                                                break;
                                        }
                                        $boxData["destination"] = $rep_source;
                                        $destCopy = INSTALL_PATH."/tmp";
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

	
	function getPath(){
		return $this->repository->getOption("PATH");
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

		$v_in  = htmlspecialchars($v_in);
		$v_out = str_replace(array("//","///","\\"),"/",$v_in);
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

	function ftpRemoveFileTmp($file)
	{
		@unlink ($file);

	}
		
	function listing($nom_rep, $dir_only = false)
	{
		$mess = ConfService::getMessages();
		$size_unit = $mess["byte_unit_symbol"];
		$sens = 0;
		$ordre = "nom";
		$poidstotal=0;
		$contents = @ftp_rawlist($this->connect, $nom_rep);
        if (!is_array($contents)) 
        {
            // We might have timed out, so let's go passive if not done yet
            global $_SESSION;
            if ($_SESSION["ftpPasv"] == "true")
                return array();
            @ftp_pasv($this->connect, TRUE);
            $_SESSION["ftpPasv"]="true";
    		$contents = @ftp_rawlist($this->connect, $nom_rep);
            if (!is_array($contents))
                return array();
        }
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
	          		$info['timeOrYear']  = $vinfo[7];
	          		$info['name']  = $vinfo[8];
    			 }
        	 $file  = trim($info['name']);
			 $filetaille= trim($info['size']);
			 if(strstr($info["timeOrYear"], ":")){
			 	$info["time"] = $info["timeOrYear"];
			 	$info["year"] = date("Y");
			 }else{
			 	$info["time"] = '09:00';
			 	$info["year"] = $info["timeOrYear"];
			 }
        	 $filedate  = trim($info['day'])." ".trim($info['month'])." ".trim($info['year'])." ".trim($info['time']);
        	 $filedate  = strtotime($filedate);        	 
        	 
        	 $fileperms = trim($info['chmod']);
			 $info['chmod1'] = $this->convertingChmod(trim($info['chmod']));
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
	
	function mkDir($crtDir, $newDirName)
	{
		$mess = ConfService::getMessages();
		if($newDirName=="")
		{
			return "$mess[37]";
		}
		if(@ftp_mkdir($this->connect,$this->getPath()."/$crtDir/$newDirName")===false)
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
		$fp=fopen("files/".$newFileName,"x+");
		if($fp)
		{
			if(eregi("\.html$",$newFileName)||eregi("\.htm$",$newFileName))
			{
				fputs($fp,"<html>\n<head>\n<title>New Document - Created By AjaXplorer</title>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n");
			}
			fclose($fp);
			@ftp_put($this->connect,$this->getPath()."/$crtDir/$newFileName","files/".$newFileName, FTP_BINARY);
			@unlink(utf8_decode("files/".$newFileName));
			return null;
		}
		else
		{
			return "$mess[102] $crtDir/$newFileName (".$fp.")";
		}		
	}

	function copyOrMove($destDir, $selectedFiles, &$error, &$success, $move = false)
        {
                $mess = ConfService::getMessages();

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

	
	
	function delete($selectedFiles, &$logMessages,$dir="")
	{
		$mess = ConfService::getMessages();
		$result = $this->listing($this->secureFtpPath($this->getPath().$dir));
		foreach ($selectedFiles as $selectedFile)
        {
 			$data ="";
			$selectedFile =  basename($selectedFile);
			if($selectedFile == "" || $selectedFile == DIRECTORY_SEPARATOR)
			{
			   	return $mess[120];
			}

			if (array_key_exists($selectedFile,$result[0]))
			{
				$data = $result[0][$selectedFile];

				$this->deldir($data['name'],$dir);
				if ($data['isDir'])
				{
					$logMessages[]="$mess[38] ".SystemTextEncoding::toUTF8($selectedFile)." $mess[44].";
				}
				else
				{
					$logMessages[]="$mess[34] ".SystemTextEncoding::toUTF8($selectedFile)." $mess[44].";
				}							
			}
			else
			{
				$logMessages[]=$mess[100]." ".SystemTextEncoding::toUTF8($selectedFile);
                                continue;
			}
		}		
		return null;
	}


	function deldir($dir,$currentDir) 
	{
		if (($contents = ftp_rawlist($this->connect,$this->secureFtpPath($this->getPath().$currentDir."/".$dir)))!==FALSE) 
		{
			foreach($contents as $file) 
			{
           			if (preg_match("/^[.]{2}$|^[.]{1}$/", $file)==0) 
				{
             		$info = array();
             		$vinfo = preg_split("/[\s]+/", $file, 9);
					if ($vinfo[0] !== "total") 
					{
       					$fileperms = $vinfo[0];
       					$filename  = $vinfo[8];
     				}
     				if (strpos($fileperms,"d")!==FALSE)
					{
						$this->deldir($dir."/".$filename,$currentDir);
					}
					else
					{
						if (strpos($filename, $this->getPath())!== false)
						{
							@ftp_delete($this->connect,$this->secureFtpPath($filename));
						}
						else
						{
             				@ftp_delete($this->connect,$this->secureFtpPath($this->getPath().$currentDir."/".$dir."/".$filename));
						}
					}
				}			
			}
         		@ftp_rmdir($this->connect,$this->secureFtpPath($this->getPath().$currentDir."/".$dir."/"));
		} 
	}
	
	// Distant loading
	function ftp_get_contents($file)
    	{
		if (is_array($file))
	  	{
			$name_file= basename($this->secureFtpPath($file->files[0]));
	  		ftp_get($this->connect,"files/".$name_file,$this->secureFtpPath($this->getPath().$file->files[0]), FTP_BINARY);
	  	}
	  	else
		{
			$name_file= basename($this->secureFtpPath($file));
			ftp_get($this->connect,"files/".$name_file,$this->secureFtpPath($this->getPath().$file), FTP_BINARY);
		}
  	}

    // Instantaneous ftp loading and transferring
    function sendRemoteFile($file, $forceDownload)
    {
        if (is_array($file)) $file = $file[0];
        $localName = basename($file);
        // Need to send the headers too
		header("Content-type:text/plain");			
		if(preg_match("/\.(jpg|jpeg|png|bmp|mng|gif)$/i", $file) !== FALSE)
		{
			header("Content-Type: ".Utils::getImageMimeType($localName)."; name=\"".$localName."\"");
			header('Cache-Control: public');			
		}
		else if(substr($file, -4) ==  ".mp3")
		{
			header("Content-Type: audio/mp3; name=\"".$localName."\"");
		}
		if ($forceDownload)
		{
			if(preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']) || preg_match('/ WebKit /',$_SERVER['HTTP_USER_AGENT'])){
				$localName = str_replace("+", " ", urlencode(SystemTextEncoding::toUTF8($localName)));
			}			
			header("Content-Type: application/force-download; name=\"".$localName."\"");
			header("Content-Transfer-Encoding: binary");
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
		}

        $handle = fopen('php://output', 'a');
        ftp_fget($this->connect, $handle, $this->secureFtpPath($this->getPath().$file), FTP_BINARY, 0);
        fclose($handle);
    }

 
	function getimagesize($image){
		 $name_file= basename($this->secureFtpPath($image));
		 @ftp_get($this->connect,"files/".$name_file,$image, FTP_BINARY);
		 $result = @getimagesize("files/".$name_file);
		 return $result;
	}

	function convertingChmod($permissions)
	{
		$mode = 0;

		if ($permissions[1] == 'r') $mode += 0400;
		if ($permissions[2] == 'w') $mode += 0200;
		if ($permissions[3] == 'x') $mode += 0100;
	 	else if ($permissions[3] == 's') $mode += 04100;
	 	else if ($permissions[3] == 'S') $mode += 04000;
	
	 	if ($permissions[4] == 'r') $mode += 040;
	 	if ($permissions[5] == 'w') $mode += 020;
	 	if ($permissions[6] == 'x') $mode += 010;
	 	else if ($permissions[6] == 's') $mode += 02010;
	 	else if ($permissions[6] == 'S') $mode += 02000;
	
	 	if ($permissions[7] == 'r') $mode += 04;
	 	if ($permissions[8] == 'w') $mode += 02;
	 	if ($permissions[9] == 'x') $mode += 01;
	 	else if ($permissions[9] == 't') $mode += 01001;
	 	else if ($permissions[9] == 'T') $mode += 01000;	
		$mode = (string)("0".$mode);	
		return  $mode;
	}	

    /** The publiclet URL making */
    function makePubliclet($filePath, $password, $expire)
    {
        $data = array("DRIVER"=>"ftp", "OPTIONS"=>array('account'=>$this->getUserName($this->repository), 'password'=>$this->getPassword($this->repository)), "FILE_PATH"=>$filePath, "ACTION"=>"download", "EXPIRE_TIME"=>$expire ? (time() + $expire * 86400) : 0, "PASSWORD"=>$password);
        return $this->writePubliclet($data);
    }

   

}

?>
