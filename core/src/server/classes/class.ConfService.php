<?php
/**
 * @package info.ajaxplorer
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
 * Description : Main configurations parsing.
 */
global $G_DEFAULT_REPOSITORIES;

global $G_LANGUE;
global $G_AVAILABLE_LANG;
global $G_MESSAGES;
global $G_REPOSITORIES;
global $G_REPOSITORY;
global $G_USE_HTTPS;
global $G_WM_EMAIL;
global $G_MAX_CHAR;
global $G_UPLOAD_MAX_NUMBER;
global $G_UPLOAD_MAX_FILE;
global $G_UPLOAD_MAX_TOTAL;

global $G_ACCESS_DRIVER;
global $G_CONF_DRIVER;
global $G_AUTH_DRIVER;
global $G_AUTH_DRIVER_DEF;

class ConfService
{
	function init($confFile)
	{
		include_once($confFile);
		// INIT AS GLOBAL
		global $G_LANGUE, $G_AVAILABLE_LANG, $G_REPOSITORIES, $G_REPOSITORY, $G_USE_HTTPS,$G_WM_EMAIL,$G_MAX_CHAR, $G_UPLOAD_MAX_NUMBER, $G_UPLOAD_MAX_FILE, $G_UPLOAD_MAX_TOTAL, $G_DEFAULT_REPOSITORIES, $G_AUTH_DRIVER_DEF;
		if(!isset($langue) || $langue=="") {$langue=$default_language;}
		$G_LANGUE = $langue;
		if(isSet($available_languages)){
			$G_AVAILABLE_LANG = $available_languages;
		}else{
			$G_AVAILABLE_LANG = ConfService::listAvailableLanguages();
		}
		$G_USE_HTTPS = $use_https;
		$G_WM_EMAIL = $webmaster_email;
		$G_MAX_CHAR = $max_caracteres;
		$G_UPLOAD_MAX_NUMBER = $upload_max_number;
		$G_UPLOAD_MAX_FILE = Utils::convertBytes($upload_max_size_per_file);
		$G_UPLOAD_MAX_TOTAL = Utils::convertBytes($upload_max_size_total);
		$G_DEFAULT_REPOSITORIES = $REPOSITORIES;
		$G_AUTH_DRIVER_DEF = $AUTH_DRIVER;
		ConfService::initConfStorageImpl($CONF_STORAGE["NAME"], $CONF_STORAGE["OPTIONS"]);
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);
		ConfService::switchRootDir();
	}
	
	function initConfStorageImpl($name, $options){
		global $G_CONF_STORAGE_DRIVER;
		$filePath = INSTALL_PATH."/plugins/conf.".$name."/class.".$name."ConfDriver.php";
		if(!is_file($filePath)){
			die("Warning, cannot find driver for conf storage! ($name, $filePath)");
		}
		require_once($filePath);
		$className = $name."ConfDriver";
		$G_CONF_STORAGE_DRIVER = new $className($name);
		$G_CONF_STORAGE_DRIVER->init($options);
	}
	
	/**
	 * Returns the current conf storage driver
	 * @return AbstractConfDriver
	 */
	function getConfStorageImpl(){
		global $G_CONF_STORAGE_DRIVER;
		return $G_CONF_STORAGE_DRIVER;
	}

	function initAuthDriverImpl(){		
		global $G_AUTH_DRIVER_DEF, $G_AUTH_DRIVER;
		$name = $G_AUTH_DRIVER_DEF["NAME"];
		$options = $G_AUTH_DRIVER_DEF["OPTIONS"];
		$filePath = INSTALL_PATH."/plugins/auth.".$name."/class.".$name."AuthDriver.php";
		if(!is_file($filePath)){
			die("Warning, cannot find driver for Authentication method! ($name, $filePath)");
		}
		require_once($filePath);
		$className = $name."AuthDriver";
		$G_AUTH_DRIVER = new $className($name);
		$G_AUTH_DRIVER->init($options);
	}
	
	/**
	 * Returns the current Aithentication driver
	 * @return AbstractAuthDriver
	 */
	function getAuthDriverImpl(){
		global $G_AUTH_DRIVER;
		if($G_AUTH_DRIVER == null){			
			ConfService::initAuthDriverImpl();
		}
		return $G_AUTH_DRIVER;
	}

	function switchRootDir($rootDirIndex=-1)
	{
		global $G_REPOSITORY, $G_REPOSITORIES, $G_ACCESS_DRIVER;
		if($rootDirIndex == -1){
			if(isSet($_SESSION['REPO_ID']) && array_key_exists($_SESSION['REPO_ID'], $G_REPOSITORIES))
			{			
				$G_REPOSITORY = $G_REPOSITORIES[$_SESSION['REPO_ID']];
			}
			else 
			{
				$keys = array_keys($G_REPOSITORIES);
				$G_REPOSITORY = $G_REPOSITORIES[$keys[0]];
				$_SESSION['REPO_ID'] = $keys[0];
			}
		}
		else 
		{
			$G_REPOSITORY = $G_REPOSITORIES[$rootDirIndex];			
			$_SESSION['REPO_ID'] = $rootDirIndex;
			if(isSet($G_ACCESS_DRIVER)) unset($G_ACCESS_DRIVER);
		}
		
	}
	
	function getRepositoriesList()
	{
		global $G_REPOSITORIES;
		return $G_REPOSITORIES;
	}
	
	/**
	 * Deprecated, use getRepositoriesList instead.
	 *
	 * @return Array
	 */
	function getRootDirsList()
	{
		global $G_REPOSITORIES;
		return $G_REPOSITORIES;
	}
	
	function getCurrentRootDirIndex()
	{
		global $G_REPOSITORIES;
		if(isSet($_SESSION['REPO_ID']) &&  isSet($G_REPOSITORIES[$_SESSION['REPO_ID']]))
		{
			return $_SESSION['REPO_ID'];
		}
		$keys = array_keys($G_REPOSITORIES);
		return $keys[0];
	}
	
	function getCurrentRootDirDisplay()
	{
		global $G_REPOSITORY;
		return $G_REPOSITORY->getDisplay();
	}
	
	/**
	 * @param array $repositories
	 * @return array
	 */
	function initRepositoriesList($defaultRepositories)
	{
		// APPEND CONF FILE REPOSITORIES
		$objList = array();
		foreach($defaultRepositories as $index=>$repository)
		{
			$repo = ConfService::createRepositoryFromArray($index, $repository);
			$repo->setWriteable(false);
			$objList[$index] = $repo;
		}
		// LOAD FROM DRIVER
		$confDriver = ConfService::getConfStorageImpl();
		$drvList = $confDriver->listRepositories();
		if(is_array($drvList)){
			$objList = array_merge($objList, $drvList);
		}
		return $objList;
	}
	
	/**
	 * Create a repository object from a config options array
	 *
	 * @param integer $index
	 * @param Array $repository
	 * @return Repository
	 */
	function createRepositoryFromArray($index, $repository){
		$repo = new Repository($index, $repository["DISPLAY"], $repository["DRIVER"]);
		if(array_key_exists("DRIVER_OPTIONS", $repository) && is_array($repository["DRIVER_OPTIONS"])){
			foreach ($repository["DRIVER_OPTIONS"] as $oName=>$oValue){
				$repo->addOption($oName, $oValue);
			}
		}
		// BACKWARD COMPATIBILITY!
		if(array_key_exists("PATH", $repository)){
			$repo->addOption("PATH", $repository["PATH"]);
			$repo->addOption("CREATE", $repository["CREATE"]);
			$repo->addOption("RECYCLE_BIN", $repository["RECYCLE_BIN"]);
		}
		return $repo;
	}
	
	/**
	 * Add dynamically created repository
	 *
	 * @param Repository $oRepository
	 * @return -1 if error
	 */
	function addRepository($oRepository){
		$confStorage = ConfService::getConfStorageImpl();
		$res = $confStorage->saveRepository($oRepository);		
		if($res == -1){
			return $res;
		}
		AJXP_Logger::logAction("Create Repository", array("repo_name"=>$oRepository->getDisplay()));
		global $G_DEFAULT_REPOSITORIES, $G_REPOSITORIES;
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);
	}
	
	/**
	 * Retrieve a repository object
	 *
	 * @param String $repoId
	 * @return Repository
	 */
	function getRepositoryById($repoId){
		global $G_REPOSITORIES;
		if(isSet($G_REPOSITORIES[$repoId])) return $G_REPOSITORIES[$repoId];
		/*
		$confStorage = ConfService::getConfStorageImpl();
		return $confStorage->getRepositoryById($repoId);
		*/
	}
	
	/**
	 * Replace a repository by an update one.
	 *
	 * @param String $oldId
	 * @param Repository $oRepositoryObject
	 * @return mixed
	 */
	function replaceRepository($oldId, $oRepositoryObject){
		$confStorage = ConfService::getConfStorageImpl();
		$res = $confStorage->saveRepository($oRepositoryObject, true);
		if($res == -1){
			return $res;
		}
		AJXP_Logger::logAction("Edit Repository", array("repo_name"=>$oRepositoryObject->getDisplay()));
		global $G_DEFAULT_REPOSITORIES, $G_REPOSITORIES;
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);				
	}
	
	function deleteRepository($repoId){
		$confStorage = ConfService::getConfStorageImpl();
		$res = $confStorage->deleteRepository($repoId);
		if($res == -1){
			return $res;
		}				
		global $G_DEFAULT_REPOSITORIES, $G_REPOSITORIES;
		AJXP_Logger::logAction("Delete Repository", array("repo_id"=>$repoId));
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);		
	}
		
	function zipEnabled()
	{
		return (function_exists("gzopen")?true:false);		
	}
	
	function getMessages()
	{
		global $G_MESSAGES, $G_LANGUE;		
		if(!isset($G_MESSAGES))
		{			
			require(INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/i18n/${G_LANGUE}.php");
			$G_MESSAGES = $mess;
			$xml = ConfService::availableDriversToXML("i18n");
			$results = array();
			preg_match_all("<i18n [^\>]*\/>", $xml, $results);			
			$libs = array();
			//print_r($xml);
			if(isSet($results[0]) && count($results[0])){
				foreach ($results[0] as $found){
					$parts = split(" ", $found);
					$nameSpace = "";
					$path = "";
					foreach($parts as $attPart){
						if(strstr($attPart, "=") === false) continue;
						$split = split("=", $attPart);
						$attName = $split[0];						
						$attValue = substr($split[1], 1, strlen($split[1])-2);
						if($attName == "namespace") $nameSpace = $attValue;
						else if($attName == "path") $path = $attValue;						
					}
					$libs[$nameSpace] = $path;
				}
			}
			//print_r($libs);
			foreach ($libs as $nameSpace => $path){
				$lang = $G_LANGUE;
				if(!is_file($path."/".$G_LANGUE.".php")){
					$lang = "en"; // Default language, minimum required.
				}
				if(is_file($path."/".$lang.".php")){
					require($path."/".$lang.".php");					
					foreach ($mess as $key => $message){
						$G_MESSAGES[$nameSpace.".".$key] = $message;
					}
				}
			}
		}
		
		return $G_MESSAGES;
	}
	
	function listAvailableLanguages(){
		// Cache in session!
		if(isSet($_SESSION["AJXP_LANGUAGES"]) && !isSet($_GET["refresh_langs"])){
			return $_SESSION["AJXP_LANGUAGES"];
		}
		$langDir = INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/i18n";
		$languages = array();
		if($dh = opendir($langDir)){
			while (($file = readdir($dh)) !== false) {
				$matches = array();
				if(preg_match("/(.*)\.php/", $file, $matches) == 1){
					$fRadical = $matches[1];
					include($langDir."/".$fRadical.".php");
					$langName = isSet($mess["languageLabel"])?$mess["languageLabel"]:"Not Found";
					$languages[$fRadical] = $langName;
				}
			}
			closedir($dh);
		}
		if(count($languages)){
			$_SESSION["AJXP_LANGUAGES"] = $languages;
		}
		return $languages;
	}

	function getConf($varName)	
	{
		global $G_LANGUE,$G_AVAILABLE_LANG,$G_MESSAGES,$G_USE_HTTPS,$G_WM_EMAIL,$G_MAX_CHAR, $G_UPLOAD_MAX_NUMBER, $G_UPLOAD_MAX_TOTAL, $G_UPLOAD_MAX_FILE;
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
		
	/**
	 * @return Repository
	 */
	function getRepository()
	{
		global $G_REPOSITORY;
		return $G_REPOSITORY;
	}
	
	/**
	 * Returns the repository access driver
	 *
	 * @return AbstractDriver
	 */
	function getRepositoryDriver()
	{
		global $G_ACCESS_DRIVER;
		if(isSet($G_ACCESS_DRIVER) && is_a($G_ACCESS_DRIVER, "AbstractDriver")){			
			return $G_ACCESS_DRIVER;
		}
        ConfService::switchRootDir();
		$crtRepository = ConfService::getRepository();
		$accessType = $crtRepository->getAccessType();
		$driverName = $accessType."AccessDriver";
		$path = INSTALL_PATH."/plugins/access.".$accessType;
		$filePath = $path."/class.".$driverName.".php";
		$xmlPath = $path."/".$accessType."Actions.xml";
		if(is_file($filePath)){
			include_once($filePath);
			if(class_exists($driverName)){
				$G_ACCESS_DRIVER = new $driverName($accessType, $xmlPath, $crtRepository);
				$res = $G_ACCESS_DRIVER->initRepository();
				if($res!=null && is_a($res, "AJXP_Exception")){
					$G_ACCESS_DRIVER = null;
					return $res;
				}				
				return $G_ACCESS_DRIVER;
			}
		}
		
	}
	
	function availableDriversToXML($filterByTagName = "", $filterByDriverName=""){
		$manifests = array();
		$base = INSTALL_PATH."/plugins";
		$xmlString = "";
		if($fp = opendir($base)){
			while (($subdir = readdir($fp))!==false) {
				$manifName = $base."/".$subdir."/manifest.xml";
				if(is_file($manifName) && is_readable($manifName) && substr($subdir,0,strlen("access."))=="access."){
					$dName = substr($subdir, strlen("access."));
					if($dName == "ajxp_conf") continue;
					if($filterByDriverName != ""){						
						if($dName!=$filterByDriverName) continue;
					}					
					$lines = file($manifName);
					if($filterByTagName!=""){
						$filterLines = array();
						foreach ($lines as $line){
							if(strstr(trim($line), "<$filterByTagName")!==false || strstr(trim($line), "<ajxpdriver")!==false || strstr(trim($line), "</ajxpdriver>")!==false){
								$filterLines[] = $line;
							}
						}
						$lines = $filterLines;
					}else{
						array_shift($lines);// Remove first line (xml declaration);
					}
					$xmlString .= implode("", $lines);
				}
			}
			closedir($fp);
		}
		return str_replace("\t", "", str_replace("\n", "", $xmlString));
	}
		
}


?>