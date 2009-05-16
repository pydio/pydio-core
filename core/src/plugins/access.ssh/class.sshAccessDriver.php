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

class sshAccessDriver extends AbstractAccessDriver 
{
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
    
	
	function  sshAccessDriver($driverName, $filePath, $repository, $optOption = NULL){	
        $repositoryPath = $repository->getOption("PATH");
        $accountLimit = strpos($repositoryPath, "@");
        if ($accountLimit !== false) 
        {
            $account = substr($repositoryPath, 0, $accountLimit);
            $repositoryPath = substr($repositoryPath, $accountLimit+1);
            $repository->setOption("PATH", $repositoryPath);            
        }
        // Set the password from a per user specific config
        $account = $optOption ? $optOption["account"] : $this->getUserName($repository); 
        $password = $optOption ? $optOption["password"] : $this->getPassword($repository);
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
					$this->downFile($this->makeName($selection->getFiles()), "force-download", "archive.zip");
				}else{
					$this->downFile($this->makeName($selection->getUniqueFile()), "force-download", $selection->getUniqueFile());
				}
				exit(0);
			break;
		
			case "image_proxy":
			    $this->downFile($this->makeName($file), "image", $file);
				exit(0);
			break;
			
			case "mp3_proxy":
				$this->downFile($this->makeName($file), "mp3", $file);
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
            //  CHANGE FILE PERMISSION
            //------------------------------------
            case "chmod";

                $messtmp="";
                $changedFiles = array();
                $value = "0".decoct(octdec(ltrim($chmod_value, "0"))); // On error, the command will fail
  		        $result = $this->SSHOperation->chmodFile($this->makeName($selection->getFiles()), $chmod_value);
				{
				    $mess = ConfService::getMessages();
				    if(strlen($result))
				    {
					    $errorMessage = $mess[114];
				    }
   				    else 
				    {
                        $logMessage="Successfully changed permission to ".$chmod_value." for ".count($selection->getFiles())." files or folders";
                        AJXP_Logger::logAction("Chmod", array("dir"=>$dir, "filesCount"=>count($selection->getFiles())));
                        $reload_file_list = $dir;
				     }
				}

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
				
				$fancyLoader = false;
				if(isSet($fileVars["Filedata"])){
					$fancyLoader = true;
					if($dir!="") $dir = "/".base64_decode($dir);
				}
				
                if($dir!=""){$rep_source="/$dir";}
                else $rep_source = "";
                $destination = $rep_source;
                $logMessage = "";
                //$fancyLoader = false;
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
	
    /** The publiclet URL making */
    function makePubliclet($filePath, $password, $expire)
    {
        $data = array("DRIVER"=>"ssh", "OPTIONS"=>array('account'=>$this->getUserName($this->repository), 'password'=>$this->getPassword($this->repository)), "FILE_PATH"=>$filePath, "ACTION"=>"download", "EXPIRE_TIME"=>$expire ? (time() + $expire * 86400) : 0, "PASSWORD"=>$password);
        return $this->writePubliclet($data);
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

	function downFile($fileArray, $headerType="plain", $fileName)
	{
		if($headerType == "plain")
		{
			header("Content-type:text/plain");
		}
		else if($headerType == "image")
		{
			header("Content-Type: ".Utils::getImageMimeType(basename($fileName))."; name=\"".basename($fileName)."\"");
			header('Cache-Control: public');			
		}
		else if($headerType == "mp3")
		{
			header("Content-Type: audio/mp3; name=\"".basename($fileName)."\"");
		}
		else 
		{
			header("Content-Type: application/force-download; name=\"".$fileName."\"");
			header("Content-Transfer-Encoding: binary");
			if($gzip) header("Content-Encoding: gzip");
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
		}
        $this->SSHOperation->downloadRemoteFile($fileArray);
	}
	
	function dateModif($time)
	{
		$tmp = mktime(substr($time, 11, 2), substr($time, 14, 2), 0, substr($time, 5, 2), substr($time, 8, 2), substr($time, 0, 4));
		return $tmp;//date("d/m/Y H:i",$tmp);
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
