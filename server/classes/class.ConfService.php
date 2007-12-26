<?php

global $G_LANGUE;
global $G_AVAILABLE_LANG;
global $G_MESSAGES;
global $G_ROOT_DIRS_LIST;
global $G_ROOT_DIR;
global $G_USE_HTTPS;
global $G_WM_EMAIL;
global $G_SIZE_UNIT;
global $G_MAX_CHAR;
global $G_SHOW_HIDDEN;
global $G_BOTTOM_PAGE;
global $G_UPLOAD_MAX_NUMBER;
global $G_RECYCLE_BIN;


class ConfService
{
	function init($confFile)
	{
		include_once($confFile);
		// INIT AS GLOBAL
		global $G_LANGUE, $G_AVAILABLE_LANG, $G_MESSAGES,$G_ROOT_DIR,$G_ROOT_DIRS_LIST,$G_USE_HTTPS,$G_WM_EMAIL,$G_SIZE_UNIT,$G_MAX_CHAR,$G_SHOW_HIDDEN,$G_BOTTOM_PAGE, $G_UPLOAD_MAX_NUMBER, $G_RECYCLE_BIN;
		if(!isset($langue) || $langue=="") {$langue=$dft_langue;}
		$G_LANGUE = $langue;
		$G_AVAILABLE_LANG = $available_languages;
		$G_USE_HTTPS = $use_https;
		$G_WM_EMAIL = $webmaster_email;
		$G_SIZE_UNIT = $size_unit;
		$G_MAX_CHAR = $max_caracteres;
		$G_SHOW_HIDDEN = $showhidden;
		$G_BOTTOM_PAGE = $baspage;
		$G_UPLOAD_MAX_NUMBER = $upload_max_number;
		$G_ROOT_DIRS_LIST = $REPOSITORIES;
		
		// INIT ROOT DIR FROM SESSION
		if(isSet($_SESSION['ROOT_DIR']) && array_key_exists($_SESSION['ROOT_DIR'], $G_ROOT_DIRS_LIST))
		{
			$G_ROOT_DIR = $G_ROOT_DIRS_LIST[$_SESSION['ROOT_DIR']]["PATH"];
		}
		else 
		{
			$G_ROOT_DIR = $G_ROOT_DIRS_LIST[0]["PATH"];
			$_SESSION['ROOT_DIR'] = 0;
		}

		// INIT RECYCLE BIN
		if(isSet($recycle_bin) && $recycle_bin != "")
		{
			$G_RECYCLE_BIN = $recycle_bin;
			if(!is_dir($G_ROOT_DIR."/".$recycle_bin))
			{
				@mkdir($G_ROOT_DIR."/".$recycle_bin);
				if(!is_dir($G_ROOT_DIR."/".$recycle_bin))
				{
					unset($G_RECYCLE_BIN);
				}
			}
		}
	}

	function switchRootDir($rootDirIndex)
	{
		$_SESSION['ROOT_DIR'] = $rootDirIndex;
		global $G_ROOT_DIR, $G_ROOT_DIRS_LIST;
		$G_ROOT_DIR = $G_ROOT_DIRS_LIST[$rootDirIndex]["PATH"];
	}
	
	function getRootDirsList()
	{
		global $G_ROOT_DIRS_LIST;
		return $G_ROOT_DIRS_LIST;
	}
	
	function getCurrentRootDirIndex()
	{
		if(isSet($_SESSION['ROOT_DIR']))
		{
			return $_SESSION['ROOT_DIR'];
		}
		return 0;
	}
	
	function getCurrentRootDirDisplay()
	{
		global $G_ROOT_DIRS_LIST;
		return $G_ROOT_DIRS_LIST[ConfService::getCurrentRootDirIndex()]["DISPLAY"];
	}
	
	function useRecycleBin()
	{
		global $G_RECYCLE_BIN;
		if(!isset($G_RECYCLE_BIN)) return false;
		return true;
	}
	
	function getRecycleBinDir()
	{
		global $G_RECYCLE_BIN;
		return $G_RECYCLE_BIN;
	}
	
	function getMessages()
	{
		global $G_MESSAGES, $G_LANGUE;		
		if(!isset($G_MESSAGES))
		{			
			require(INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/i18n/${G_LANGUE}.php");
			$G_MESSAGES = $mess;
		}
		return $G_MESSAGES;
	}

	function getConf($varName)	
	{
		global $G_LANGUE,$G_AVAILABLE_LANG,$G_MESSAGES,$G_ROOT_DIR,$G_USE_HTTPS,$G_WM_EMAIL,$G_SIZE_UNIT,$G_MAX_CHAR,$G_SHOW_HIDDEN,$G_BOTTOM_PAGE, $G_UPLOAD_MAX_NUMBER;
		$globVarName = "G_".$varName;
		return $$globVarName;
	}
	
	function setLanguage($lang)
	{
		global $G_LANGUE, $G_AVAILABLE_LANG;
		if(array_key_exists($lang, $G_AVAILABLE_LANG))
		{
			$G_LANGUE = $lang;
		}
	}
	
	function getLanguage()
	{
		global $G_LANGUE;
		return $G_LANGUE;
	}
	
	function getRootDir()
	{
		global $G_ROOT_DIR;
		return $G_ROOT_DIR;
	}
	
}


?>