<?php

class FS_Storage
{
	
	/**
	* @var String
	*/
	var $rootDir;
	
	function  FS_Storage($rootDir)
	{
		$this->rootDir = $rootDir;
	}
	
	function initName($dir)
	{
		$racine = ConfService::getRootDir();
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
			AJXP_XMLWriter::header();
			echo "<error>$mess[72] $racine</error>";
			AJXP_XMLWriter::close();
			exit;
		}
		if(!is_dir($nom_rep))
		{
			AJXP_XMLWriter::header();
			echo "<error>$mess[100] $dir</error>";
			AJXP_XMLWriter::close();
			exit;
		}
		return $nom_rep;
	}
	
	function readFile($filePath, $headerType="plain")
	{
		if($headerType == "plain")
		{
			header("Content-type:text/plain");			
		}
		else if($headerType == "image")
		{
			$size=filesize($filePath);
			header("Content-Type: ".Utils::getImageMimeType(basename($filePath))."; name=\"".basename($filePath)."\"");
			header("Content-Length: ".$size);
			header('Cache-Control: public');			
		}
		else if($headerType == "mp3")
		{
			$size=filesize($filePath);
			header("Content-Type: audio/mp3; name=\"".basename($filePath)."\"");
			header("Content-Length: ".$size);
		}
		else 
		{
			$size=filesize($filePath);
			header("Content-Type: application/force-download; name=\"".basename($filePath)."\"");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".$size);
			header("Content-Disposition: attachment; filename=\"".basename($filePath)."\"");
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
		readfile($filePath);
	}
	
	
	function listing($nom_rep, $dir_only = false)
	{
		$size_unit = ConfService::getConf("SIZE_UNIT");
		$sens = 0;
		$ordre = "nom";
		$poidstotal=0;
		$handle=opendir($nom_rep);
		while ($file = readdir($handle))
		{
			if($file!="." && $file!=".." && Utils::showHiddenFiles($file)==1)
			{
				if(ConfService::getRecycleBinDir() != "" 
					&& $nom_rep == ConfService::getRootDir()."/".ConfService::getRecycleBinDir() 
					&& $file == RecycleBinManager::getCacheFileName()){
					continue;
				}
				$poidsfic=filesize("$nom_rep/$file");
				$poidstotal+=$poidsfic;
				if(is_dir("$nom_rep/$file"))
				{					
					if(ConfService::useRecycleBin() && ConfService::getRootDir()."/".ConfService::getRecycleBinDir() == "$nom_rep/$file")
					{
						continue;
					}
					if($ordre=="mod") {$liste_rep[$file]=filemtime("$nom_rep/$file");}
					else {$liste_rep[$file]=$file;}
				}
				else
				{
					if(!$dir_only)
					{
						if($ordre=="nom") {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","image");}
						else if($ordre=="taille") {$liste_fic[$file]=$poidsfic;}
						else if($ordre=="mod") {$liste_fic[$file]=filemtime("$nom_rep/$file");}
						else if($ordre=="type") {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","type");}
						else {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","image");}
					}
				}
			}
		}
		closedir($handle);
	
		if(isset($liste_fic) && is_array($liste_fic))
		{
			if($ordre=="nom") {if($sens==0){ksort($liste_fic);}else{krsort($liste_fic);}}
			else if($ordre=="mod") {if($sens==0){arsort($liste_fic);}else{asort($liste_fic);}}
			else if($ordre=="taille"||$ordre=="type") {if($sens==0){asort($liste_fic);}else{arsort($liste_fic);}}
			else {if($sens==0){ksort($liste_fic);}else{krsort($liste_fic);}}
		}
		else
		{
			$liste_fic = array();
		}
		if(isset($liste_rep) && is_array($liste_rep))
		{
			if($ordre=="mod") {if($sens==0){arsort($liste_rep);}else{asort($liste_rep);}}
			else {if($sens==0){ksort($liste_rep);}else{krsort($liste_rep);}}
		}
		else ($liste_rep = array());
	
		$liste = Utils::mergeArrays($liste_rep,$liste_fic);
		if ($poidstotal >= 1073741824) {$poidstotal = round($poidstotal / 1073741824 * 100) / 100 . " G".$size_unit;}
		elseif ($poidstotal >= 1048576) {$poidstotal = round($poidstotal / 1048576 * 100) / 100 . " M".$size_unit;}
		elseif ($poidstotal >= 1024) {$poidstotal = round($poidstotal / 1024 * 100) / 100 . " K".$size_unit;}
		else {$poidstotal = $poidstotal . " ".$size_unit;}
	
		return array($liste,$poidstotal);
	}
	
	function date_modif($file)
	{
		$tmp = filemtime($file);
		return date("d/m/Y H:i",$tmp);
	}
	
	function copyOrMove($destDir, $selectedFiles, &$error, &$success, $move = false)
	{
		$mess = ConfService::getMessages();
		if(!is_writable(ConfService::getRootDir()."/".$destDir))
		{
			$errorMessage = $mess[38]." ".$destDir." ".$mess[99];
			break;
		}
				
		foreach ($selectedFiles as $selectedFile)
		{
			if($move && !is_writable(dirname(ConfService::getRootDir()."/".$selectedFile)))
			{
				$error[] = "\n".$mess[38]." ".dirname($selectedFile)." ".$mess[99];
				continue;
			}
			FS_Storage::copyOrMoveFile($destDir, $selectedFile, $error, $success, $move);
		}
	}
	
	
	function rename($filePath, $filename_new)
	{
		$nom_fic=basename($filePath);
		$mess = ConfService::getMessages();
		$filename_new=Utils::processFileName(utf8_decode($filename_new));
		$old=ConfService::getRootDir()."/$filePath";
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
	}
	
	function mkDir($crtDir, $newDirName)
	{
		$mess = ConfService::getMessages();
		if($newDirName=="")
		{
			return "$mess[37]";
		}
		if(file_exists(ConfService::getRootDir()."/$crtDir/$newDirName"))
		{
			return $errorMessage="$mess[40]"; 
		}
		if(!is_writable(ConfService::getRootDir()."/$crtDir"))
		{
			return $mess[38]." $dir ".$mess[99];
		}
		mkdir(ConfService::getRootDir()."/$crtDir/$newDirName",0775);		
	}
	
	function createEmptyFile($crtDir, $newFileName)
	{
		$mess = ConfService::getMessages();
		if($newFileName=="")
		{
			return "$mess[37]";
		}
		if(file_exists(ConfService::getRootDir()."/$crtDir/$newFileName"))
		{
			return "$mess[71]";
		}
		if(!is_writable(ConfService::getRootDir()."/$crtDir"))
		{
			return "$mess[38] $crtDir $mess[99]";
		}
		
		$fp=fopen(ConfService::getRootDir()."/$crtDir/$newFileName","w");
		if($fp)
		{
			if(eregi("\.html$",$newFileName)||eregi("\.htm$",$newFileName))
			{
				fputs($fp,"<html>\n<head>\n<title>New Document - Created By AjaXplorer</title>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n");
			}
			fclose($fp);
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
			$fileToDelete=ConfService::getRootDir().$selectedFile;
			if(!file_exists($fileToDelete))
			{
				$logMessages[]=$mess[100]." $selectedFile";
				continue;
			}		
			FS_Storage::deldir($fileToDelete);
			if(is_dir($fileToDelete))
			{
				$logMessages[]="$mess[38] $selectedFile $mess[44].";
			}
			else 
			{
				$logMessages[]="$mess[34] $selectedFile $mess[44].";
			}
		}
	}
	
	
	
	function copyOrMoveFile($destDir, $srcFile, &$error, &$success, $move = false)
	{
		$mess = ConfService::getMessages();		
		$destFile = ConfService::getRootDir().$destDir."/".basename($srcFile);
		$realSrcFile = ConfService::getRootDir()."/$srcFile";		
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
			$dirRes = FS_Storage::dircopy($realSrcFile, $destFile, $errors, $succFiles);
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
			FS_Storage::deldir($realSrcFile); // both file and dir
			$messagePart = $mess[74]." $destDir";
			if($destDir == "/".ConfService::getRecycleBinDir())
			{
				RecycleBinManager::fileToRecycle($srcFile);
				$messagePart = $mess[123]." ".$mess[122];
			}
			if(isset($dirRes))
			{
				$success[] = $mess[117]." ".basename($srcFile)." ".$messagePart." ($dirRes ".$mess[116].") ";
			}
			else 
			{
				$success[] = $mess[34]." ".basename($srcFile)." ".$messagePart;
			}
		}
		else
		{			
			if($destDir == "/".ConfService::getRecycleBinDir())
			{
				RecycleBinManager::fileToRecycle($srcFile);
			}
			if(isSet($dirRes))
			{
				$success[] = $mess[117]." ".basename($srcFile)." ".$mess[73]." $destDir (".$dirRes." ".$mess[116].")";	
			}
			else 
			{
				$success[] = $mess[34]." ".basename($srcFile)." ".$mess[73]." $destDir";
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
						$num += FS_Storage::dircopy($srcfile, $dstfile, $errors, $success, $verbose);
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
					FS_Storage::deldir("$location/$file");
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
		if(basename(dirname($location)) == ConfService::getRecycleBinDir())
		{
			// DELETING FROM RECYCLE
			RecycleBinManager::deleteFromRecycle($location);
		}
	}

	
}


?>