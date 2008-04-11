<?php

global $G_DEFAULT_REPOSITORIES;

global $G_LANGUE;
global $G_AVAILABLE_LANG;
global $G_MESSAGES;
global $G_REPOSITORIES;
global $G_REPOSITORY;
global $G_USE_HTTPS;
global $G_WM_EMAIL;
global $G_SIZE_UNIT;
global $G_MAX_CHAR;
global $G_SHOW_HIDDEN;
global $G_BOTTOM_PAGE;
global $G_UPLOAD_MAX_NUMBER;
global $G_RECYCLE_BIN;
global $G_ACCESS_DRIVER;


class ConfService
{
	function init($confFile)
	{
		include_once($confFile);
		// INIT AS GLOBAL
		global $G_LANGUE, $G_AVAILABLE_LANG, $G_REPOSITORIES, $G_REPOSITORY, $G_USE_HTTPS,$G_WM_EMAIL,$G_SIZE_UNIT,$G_MAX_CHAR,$G_SHOW_HIDDEN,$G_BOTTOM_PAGE, $G_UPLOAD_MAX_NUMBER, $G_RECYCLE_BIN, $G_DEFAULT_REPOSITORIES;
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
		$G_DEFAULT_REPOSITORIES = $REPOSITORIES;
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);
		ConfService::switchRootDir();
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
				$G_REPOSITORY = $G_REPOSITORIES[0];
				$_SESSION['REPO_ID'] = 0;
			}
		}
		else 
		{
			$G_REPOSITORY = $G_REPOSITORIES[$rootDirIndex];			
			$_SESSION['REPO_ID'] = $rootDirIndex;
			if(isSet($G_ACCESS_DRIVER)) unset($G_ACCESS_DRIVER);
		}

		if($G_REPOSITORY->getCreate() == true){
			if(!is_dir($G_REPOSITORY->getPath())) @mkdir($G_REPOSITORY->getPath());
			if($G_REPOSITORY->getRecycle()!= "" && !is_dir($G_REPOSITORY->getPath()."/".$G_REPOSITORY->getRecycle())){
				@mkdir($G_REPOSITORY->getPath()."/".$G_REPOSITORY->getRecycle());
			}
		}
		// INIT RECYCLE BIN
		global $G_RECYCLE_BIN;
		if($G_REPOSITORY->getRecycle()!= "" && is_dir($G_REPOSITORY->getPath()."/".$G_REPOSITORY->getRecycle())){			
			$G_RECYCLE_BIN = $G_REPOSITORY->getRecycle();
		}		
	}
	
	function getRootDirsList()
	{
		global $G_REPOSITORIES;
		return $G_REPOSITORIES;
	}
	
	function getCurrentRootDirIndex()
	{
		if(isSet($_SESSION['REPO_ID']))
		{
			return $_SESSION['REPO_ID'];
		}
		return 0;
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
		$objList =  array();
		foreach($defaultRepositories as $index=>$repository)
		{
			$repo = ConfService::createRepositoryFromArray($index, $repository);
			$repo->setWriteable(false);
			$objList[$index] = $repo;
		}
		$confRepo = ConfService::loadRepoFile();
		foreach ($confRepo as $repo){			
			$repo->setWriteable(true);
			$objList[] = $repo;
		}
		return $objList;
	}
	
	function createRepositoryFromArray($index, $repository){
		$repo = new Repository($index, $repository["DISPLAY"], $repository["DRIVER"]);
		if(array_key_exists("DRIVER_OPTIONS", $repository) && is_array($repository["DRIVER_OPTIONS"])){
			foreach ($repository["DRIVER_OPTIONS"] as $oName=>$oValue){
				$repo->addOption($oName, $oValue);
			}
		}			
		// Old grammar, this is now a fs driver option
		if(array_key_exists("RECYCLE_BIN", $repository)) $repo->setRecycle($repository["RECYCLE_BIN"]);
		if(array_key_exists("PATH", $repository)) $repo->setPath($repository["PATH"]);
		if(array_key_exists("CREATE", $repository)) $repo->setCreate($repository["CREATE"]);
		return $repo;
	}
	
	function addRepository($oRepository){
		// update list
		$confRepoList = ConfService::loadRepoFile();
		$confRepoList[] = $oRepository;
		$res = ConfService::saveRepoFile($confRepoList);
		if($res == -1){
			return $res;
		}
		global $G_DEFAULT_REPOSITORIES, $G_REPOSITORIES;
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);
	}
	
	function deleteRepository($repoLabel){
		$confRepoList = ConfService::loadRepoFile();
		$newList = array();
		foreach ($confRepoList as $repo){
			if($repo->getDisplay() == $repoLabel){
				continue;
			}
			$newList[] = $repo;
		}
		$res = ConfService::saveRepoFile($newList);
		if($res == -1){
			return $res;
		}
		global $G_DEFAULT_REPOSITORIES, $G_REPOSITORIES;
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);		
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
		}
		return $G_MESSAGES;
	}

	function getConf($varName)	
	{
		global $G_LANGUE,$G_AVAILABLE_LANG,$G_MESSAGES,$G_USE_HTTPS,$G_WM_EMAIL,$G_SIZE_UNIT,$G_MAX_CHAR,$G_SHOW_HIDDEN,$G_BOTTOM_PAGE, $G_UPLOAD_MAX_NUMBER;
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
		global $G_REPOSITORY;
		return $G_REPOSITORY->getPath();
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
		$crtRepository = ConfService::getRepository();
		$accessType = $crtRepository->getAccessType();
		$driverName = $accessType."Driver";
		$path = INSTALL_PATH."/plugins/ajxp.".$accessType;
		$filePath = $path."/class.".$driverName.".php";
		$xmlPath = $path."/".$accessType."Actions.xml";
		if(is_file($filePath)){
			include_once($filePath);
			if(class_exists($driverName)){
				$G_ACCESS_DRIVER = new $driverName($accessType, $xmlPath, $crtRepository);
				return $G_ACCESS_DRIVER;
			}
		}
		
	}
	
	function availableDriversToXML(){
		$manifests = array();
		$base = INSTALL_PATH."/plugins";
		$xmlString = "";
		if($fp = opendir($base)){
			while (($subdir = readdir($fp))!==false) {
				$manifName = $base."/".$subdir."/manifest.xml";
				if(is_file($manifName) && is_readable($manifName)){
					$lines = file($manifName);
					array_shift($lines);// Remove first line (xml declaration);					
					$xmlString .= implode("", $lines);
				}
			}
			closedir($fp);
		}
		return str_replace("\t", "", str_replace("\n", "", $xmlString));
	}
	
	function loadRepoFile(){
		$result = array();
		if(is_file(INSTALL_PATH."/server/conf/repo.ser"))
		{
			$fileLines = file(INSTALL_PATH."/server/conf/repo.ser");
			$result = unserialize($fileLines[0]);
		}
		return $result;
	}
	
	function saveRepoFile($value){		
		if(!is_writeable(INSTALL_PATH."/server/conf")) return -1;
		$fp = @fopen(INSTALL_PATH."/server/conf/repo.ser", "w");
		fwrite($fp, serialize($value));
		fclose($fp);
	}
	
	
}


?>