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
	
	function initName($rep)
	{
		$racine = ConfService::getRootDir();
		$mess = ConfService::getMessages();
		if(!isset($rep) || $rep=="" || $rep == "/")
		{
			$nom_rep=$racine;
		}
		else
		{
			$nom_rep="$racine/$rep";
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
			echo "<error>$mess[100] $rep</error>";
			AJXP_XMLWriter::close();
			exit;
		}
		// the following code will only remove "solo-dots" 
		// this may not work on windowsservers with \ instead of /
		// as directory seperators
		$dirs = explode('/', $nom_rep);
		for ($i = 0; $i < count($dirs); $i++)
		{
			if ($dirs[$i] == '.' or $dirs[$i] == '..')
			{
				$dirs[$i] = '';
			}
		}
		// rebuild safe directory string
		$nom_rep = implode('/', $dirs);
		//Replace double slashes!
		while (eregi('//', $nom_rep)) {
			$nom_rep = str_replace('//', '/', $nom_rep);
		}
		/*
		// Avoid possible hack with ../
		$nom_rep = str_replace('.', '', $nom_rep);
		*/
		return $nom_rep;
	}
	
	
	function listing($nom_rep, $dir_only = false)
	{
		$size_unit = ConfService::getConf("SIZE_UNIT");
		$sens = 0;
		$ordre = "nom";
		$poidstotal=0;
		$handle=opendir($nom_rep);
		while ($fichier = readdir($handle))
		{
			if($fichier!="." && $fichier!=".." && Utils::show_hidden_files($fichier)==1)
			{
				$poidsfic=filesize("$nom_rep/$fichier");
				$poidstotal+=$poidsfic;
				if(is_dir("$nom_rep/$fichier"))
				{					
					if(ConfService::useRecycleBin() && ConfService::getRootDir()."/".ConfService::getRecycleBinDir() == "$nom_rep/$fichier")
					{
						continue;
					}
					if($ordre=="mod") {$liste_rep[$fichier]=filemtime("$nom_rep/$fichier");}
					else {$liste_rep[$fichier]=$fichier;}
				}
				else
				{
					if(!$dir_only)
					{
						if($ordre=="nom") {$liste_fic[$fichier]=Utils::mimetype("$nom_rep/$fichier","image");}
						else if($ordre=="taille") {$liste_fic[$fichier]=$poidsfic;}
						else if($ordre=="mod") {$liste_fic[$fichier]=filemtime("$nom_rep/$fichier");}
						else if($ordre=="type") {$liste_fic[$fichier]=Utils::mimetype("$nom_rep/$fichier","type");}
						else {$liste_fic[$fichier]=Utils::mimetype("$nom_rep/$fichier","image");}
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
	
		$liste = Utils::assemble_tableaux($liste_rep,$liste_fic);
		if ($poidstotal >= 1073741824) {$poidstotal = round($poidstotal / 1073741824 * 100) / 100 . " G".$size_unit;}
		elseif ($poidstotal >= 1048576) {$poidstotal = round($poidstotal / 1048576 * 100) / 100 . " M".$size_unit;}
		elseif ($poidstotal >= 1024) {$poidstotal = round($poidstotal / 1024 * 100) / 100 . " K".$size_unit;}
		else {$poidstotal = $poidstotal . " ".$size_unit;}
	
		return array($liste,$poidstotal);
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
	}
	
	function date_modif($fichier)
	{
		$tmp = filemtime($fichier);
		return date("d/m/Y H:i",$tmp);
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

	
}


?>