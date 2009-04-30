<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Cyril Russo
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
 * Description : Access a remote server via SSH protocol.
 */
require_once("class.SSHOperations.php");

class sshDriver extends AbstractAccessDriver 
{
	/** Should be in the form bob@distantServer.com or distantServer
	* @var Repository
	*/
	var $repository;
	/** The SSH operation object
	* @var SSHOperation
	*/
	var $SSHOperation;

    /** The remote path (used to make absolute path)
     * @var cwd
     */
    var $serverCwd;
    
    /** The remote charset (if known)
     * @var charset
     */
    var $charset;
    
	
	function  sshDriver($driverName, $filePath, $repository){	
        $repositoryPath = $repository->getOption("PATH");
        $accountLimit = strpos($repositoryPath, "@");
        if ($accountLimit !== false) 
        {
            $account = substr($repositoryPath, 0, $accountLimit);
            $repositoryPath = substr($repositoryPath, $accountLimit+1);
            $repository->setOption("PATH", $repositoryPath);            
        }
        // Set the password from a per user specific config
        $account = $this->getUserName($repository); 
        $password = $this->getPassword($repository); 
        $this->SSHOperation = new SSHOperations($repositoryPath, $account, $password);
		parent::AbstractAccessDriver($driverName, $filePath, $repository);
	}

    function getUserName($repository){
        $logUser = AuthService::getLoggedUser(); 
        $wallet = $logUser->getPref("AJXP_WALLET");
        return is_array($wallet) ? $wallet[$repository->getUniqueId()]["remote_username"] : "";
    }

    function getPassword($repository){
        $logUser = AuthService::getLoggedUser(); 
        $wallet = $logUser->getPref("AJXP_WALLET");
        return is_array($wallet) ? $logUser->decodeUserPassword($wallet[$repository->getUniqueId()]["remote_password"]) : "";
    }
	
	function initRepository(){
		$path = $this->repository->getOption("PATH");
		// We cache this in the session object so it's only done once
		global $_SESSION;
		if (!isset($_SESSION["cwd"]) || !strlen($_SESSION["cwd"]))
		{
 		    $param = $this->SSHOperation->checkConnection();
		    if (count($param)==0)
		    {
		        return new AJXP_Exception("Cannot connect to remote server. Please check repository configuration and install.txt!");
		    }
		    $_SESSION["cwd"] = trim($param[0]);
		    $fullCharset = explode('.', trim($param[1]));
		    $_SESSION["charset"] = array_pop($fullCharset);
		}
		// If it's set, then cache the result to avoid multiple connection on the remote server 
		$this->serverCwd = rtrim(trim($_SESSION["cwd"]), '/').'/';
		$this->charset = trim($_SESSION["charset"]);
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
			case "download";
				AJXP_Logger::logAction("Download", array("files"=>$selection));
				
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
					$this->sendFile($this->SSHOperation->getRemoteContent($this->makeName($selection->getFiles())), "force-download", "archive.zip", false);
				}else{
					$this->sendFile($this->SSHOperation->getRemoteContent($this->makeName($selection->getUniqueFile())), "force-download", $selection->getUniqueFile());
				}
				exit(0);
			break;
		
			case "image_proxy":
			    $this->sendFile($this->SSHOperation->getRemoteContent($this->makeName($file)), "image", $file);
				exit(0);
			break;
			
			case "mp3_proxy":
				$this->sendFile($this->SSHOperation->getRemoteContent($this->makeName($file)), "mp3", $file);
				exit(0);
			break;
			
			//------------------------------------
			//	ONLINE EDIT
			//------------------------------------
			case "edit";	
				if(isset($save) && $save==1)
				{
					AJXP_Logger::logAction("Online Edition", array("file"=>SystemTextEncoding::fromUTF8($file)));
					$code=stripslashes($code);
					$code=str_replace("&lt;","<",$code);
					$this->SSHOperation->setRemoteContent($this->makeName($file), $code);
					echo $mess[115];
				}
				else 
				{
					$this->sendFile($this->SSHOperation->getRemoteContent($this->makeName($file)), "plain", $file);
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
				
				$result = "";
				if ($action == "move")
				    $result = $this->SSHOperation->moveFile($this->makeName($selection->getFiles()), $this->makeName($dest));
				else
				    $result = $this->SSHOperation->copyFile($this->makeName($selection->getFiles()), $this->makeName($dest));
				
				{
				    $mess = ConfService::getMessages();
				    if(strlen($result))
				    {
					    $errorMessage = $mess[114];
				    }
   				    else 
				    {
					    foreach($selection->getFiles() as $files)
                            $logMessage .= $mess[34]." ".SystemTextEncoding::toUTF8(basename($file))." ".$mess[$action=="move" ? 74:73]." ".SystemTextEncoding::toUTF8($dest)."\n";
					    AJXP_Logger::logAction(($action=="move"?"Move":"Copy"), array("files"=>$selection, "destination"=>$dest));
				     }
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
				$result = $this->SSHOperation->deleteFile($this->makeName($selection->getFiles()));
				if(strlen($result))
				{
				    $mess = ConfService::getMessages();
                    $errorMessage = $mess[120];				 
				}   
				else
				{   
				    $mess = ConfService::getMessages();
				    foreach($selection->getFiles() as $file)
				        $logMessages[]="$mess[34] ".SystemTextEncoding::toUTF8($file)." $mess[44].";
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
			
				$filename_new = $dir."/".$filename_new;
				$error = $this->SSHOperation->moveFile($this->makeName($file), $this->makeName($filename_new));
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
				$dirname=Utils::processFileName($dirname);
				$error = $this->SSHOperation->createRemoteDirectory($this->makeName($dir."/".$dirname));
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
				$filename=Utils::processFileName($filename);	
				$error = $this->SSHOperation->setRemoteContent($this->makeName($dir."/".$filename), "");
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
			//	UPLOAD
			//------------------------------------	
			case "upload":
                if($dir!=""){$rep_source="/$dir";}
                else $rep_source = "";
                $destination = $rep_source;
                $logMessage = "";
                $fancyLoader = false;
                foreach ($fileVars as $boxName => $boxData)
                {
                    if($boxName != "Filedata" && substr($boxName, 0, 9) != "userfile_") continue;
                    if($boxName == "Filedata") $fancyLoader = true;
                    $err = Utils::parseFileDataErrors($boxData, $fancyLoader);
                    if($err != null)
                    {
                        $errorMessage = $err;
                        break;
                    }
                    $userfile_name = $boxData["name"];
                    $userfile_name=Utils::processFileName($userfile_name);
                    if (!$this->SSHOperation->uploadFile($boxData["tmp_name"], $this->makeName($destination."/".$userfile_name)))
                    {
                        $errorMessage=($fancyLoader?"411 ":"")."$mess[33] ".$userfile_name;
                        break;
                    }
                    $logMessage.="$mess[34] ".SystemTextEncoding::toUTF8($userfile_name)." $mess[35] $dir";
                    AJXP_Logger::logAction("Upload File", array("file"=>$dir."/".$userfile_name));
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


				$nom_rep = $dir;
				AJXP_Exception::errorToXml($nom_rep);
				$result = $this->SSHOperation->listFilesIn($nom_rep);
				AJXP_XMLWriter::header();
                foreach ($result as $file)
                {
                    $attributes = "";
                    $fileName = SystemTextEncoding::toUTF8($file["name"]);
                    $icon = Utils::mimetype($fileName, "image", $file["isDir"]==1);
                    if ($searchMode)
                    {
                        if($file["isDir"] == 0) { $attributes = "is_file=\"true\" icon=\"".SystemTextEncoding::toUTF8($icon)."\""; }
                    } else if ($fileListMode)
                    {
                        $atts = array();
                        $atts[] = "is_file=\"".(1 - $file["isDir"])."\"";
                        $atts[] = "is_image=\"".Utils::is_image($fileName)."\"";
                        $atts[] = "mimestring=\"".Utils::mimetype($fileName, "type", $file["isDir"]==1)."\"";
                        $atts[] = "ajxp_modiftime=\"".$this->dateModif($file["time"])."\"";
                        $atts[] = "filesize=\"".Utils::roundSize($file["size"])."\"";
                        $atts[] = "bytesize=\"".$file["size"]."\"";
                        $atts[] = "filename=\"".str_replace("&", "&amp;", $dir."/".$fileName)."\"";
                        $atts[] = "icon=\"".($file["isDir"]==1 ? "folder.png" : SystemTextEncoding::toUTF8($icon))."\"";
                        
                        $attributes = join(" ", $atts);
                    } else if ($file["isDir"]==1)
                    {
						$link = SERVER_ACCESS."?dir=".$dir."/".$fileName;
						$link = urlencode($link);
						$folderBaseName = str_replace("&", "&amp;", $fileName);
						$folderFullName = "$dir/".$folderBaseName;
						$parentFolderName = $dir;
						if(!$completeMode){
							$icon = CLIENT_RESOURCES_FOLDER."/images/foldericon.png";
							$openicon = CLIENT_RESOURCES_FOLDER."/images/openfoldericon.png";
							if(eregi("\.zip$",$file["name"])){
								$icon = $openicon = CLIENT_RESOURCES_FOLDER."/images/crystal/actions/16/accessories-archiver.png";
							}
							$attributes = "icon=\"$icon\"  openicon=\"$openicon\" filename=\"".$folderFullName."\" src=\"$link\"";
						}
					}
					if (strlen($attributes) > 0)
					{
					    print("<tree text=\"".str_replace("&", "&amp;", SystemTextEncoding::toUTF8($this->SSHOperation->unescapeFileName($file["name"])))."\" $attributes>");
					    print("</tree>");
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

    function makeName($param)
    {
        if (is_array($param))
        {
           $retArray = array();
           foreach($param as $item)
           {
               $retArray[] = $this->serverCwd.SystemTextEncoding::fromUTF8(trim($item, './'));
           }
           return $retArray;
        }  
        else
        {
           $param = SystemTextEncoding::fromUTF8(trim($param, './'));
           return $this->serverCwd.$param;
        }
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
	
	function sendFile($filePath, $headerType="plain", $fileName, $gzip=true)
	{
		$size = strlen($filePath);
		if($headerType == "plain")
		{
			header("Content-type:text/plain");
		}
		else if($headerType == "image")
		{
			header("Content-Type: ".Utils::getImageMimeType(basename($fileName))."; name=\"".basename($fileName)."\"");
			header("Content-Length: ".$size);
			header('Cache-Control: public');			
		}
		else if($headerType == "mp3")
		{
			header("Content-Type: audio/mp3; name=\"".basename($fileName)."\"");
			header("Content-Length: ".$size);
		}
		else 
		{
			header("Content-Type: application/force-download; name=\"".$fileName."\"");
			header("Content-Transfer-Encoding: binary");
			if($gzip) header("Content-Encoding: gzip");
			header("Content-Length: ".$size);
			header("Content-Disposition: attachment; filename=\"".$fileName."\"");
			header("Expires: 0");
			header("Cache-Control: no-cache, must-revalidate");
			header("Pragma: no-cache");
			// For SSL websites there is a bug with IE see article KB 323308
			// therefore we must reset the Cache-Control and Pragma Header
			if (ConfService::getConf("USE_HTTPS")==1 && preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']))
			{
				header("Cache-Control:");
				header("Pragma:");
			}
			if($gzip){
					print(gzencode($filePath, 9));
				return ;
			}
		}
    	print($filePath);
	}
	
	function dateModif($time)
	{
		$tmp = mktime(substr($time, 11, 2), substr($time, 14, 2), 0, substr($time, 5, 2), substr($time, 8, 2), substr($time, 0, 4));
		return $tmp;//date("d/m/Y H:i",$tmp);
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
		mkdir($this->getPath()."/$crtDir/$newDirName",0775);
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
			$dirRes = $this->dircopy($realSrcFile, $destFile, $errors, $succFiles);
			if(count($errors))
			{
				$error[] = $mess[114];
				return ;
			}			
		}
		else 
		{
			$res = copy($realSrcFile,$destFile);
			if($res != 1)
			{
				$error[] = $mess[114];
				return ;
			}
		}
		
		if($move)
		{
			// Now delete original
			$this->deldir($realSrcFile); // both file and dir
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
	 * @return zipfile
	 */ 
    function makeZip ($src, $dest, $basedir)
    {
    	set_time_limit(60);
    	require_once(SERVER_RESOURCES_FOLDER."/pclzip.lib.php");
    	$filePaths = array();
    	$totalSize = 0;
    	foreach ($src as $item){
    		$filePaths[] = $this->getPath().$item;
    	}
    	$archive = new PclZip($dest);
    	$vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $this->getPath().$basedir, PCLZIP_OPT_NO_COMPRESSION);
    	if($vList == 0) return false;
    }
    
    
    /**
     * @param $selection UserSelection
     */
    function convertSelectionToTmpFiles($tmpDir, $selection){
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
    
}

?>
