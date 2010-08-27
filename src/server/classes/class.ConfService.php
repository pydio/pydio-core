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
defined('AJXP_EXEC') or die( 'Access not allowed');

class ConfService
{	
	private static $instance;
	private $configs = array();
 	
	public static function init($confFile){
		$inst = self::getInstance();
		$inst->initInst($confFile);
	}
	
	public function initInst($confFile)
	{
		include($confFile);
		// INIT AS GLOBAL
		if(!isset($langue) || $langue=="") {$langue=$default_language;}
		$this->configs["LANGUE"] = $langue;
		if(isSet($available_languages)){
			$this->configs["AVAILABLE_LANG"] = $available_languages;
		}else{
			$this->configs["AVAILABLE_LANG"] = self::listAvailableLanguages();
		}
		$this->configs["USE_HTTPS"] = $use_https;
		$this->configs["WM_EMAIL"] = $webmaster_email;
		$this->configs["MAX_CHAR"] = $max_caracteres;
		$this->configs["JS_DEBUG"] = $AJXP_JS_DEBUG;
		$this->configs["SERVER_DEBUG"] = $AJXP_SERVER_DEBUG OR false;
		$this->configs["UPLOAD_MAX_NUMBER"] = $upload_max_number;
		$this->configs["UPLOAD_ENABLE_FLASH"] = $upload_enable_flash;
		$this->configs["UPLOAD_MAX_FILE"] = AJXP_Utils::convertBytes($upload_max_size_per_file);
		$this->configs["UPLOAD_MAX_TOTAL"] = AJXP_Utils::convertBytes($upload_max_size_total);
        $this->configs["PROBE_REAL_SIZE"] = $allowRealSizeProbing;
        $this->configs["WELCOME_CUSTOM_MSG"] = $welcomeCustomMessage;

		if(isSet($PLUGINS)){
			$this->configs["PLUGINS"] = $PLUGINS;
		}else{
			/* OLD SYNTAX */
			$this->configs["AUTH_DRIVER_DEF"] = $AUTH_DRIVER;
			$this->configs["LOG_DRIVER_DEF"] = $LOG_DRIVER;
	        $this->configs["CONF_PLUGINNAME"] = $CONF_STORAGE["NAME"];
	        $this->configs["ACTIVE_PLUGINS"] = $ACTIVE_PLUGINS;
	        
	        $this->configs["PLUGINS"] = array(
	        	"CONF_DRIVER" => $CONF_STORAGE,
	        	"AUTH_DRIVER" => $AUTH_DRIVER, 
	        	"LOG_DRIVER"  => $LOG_DRIVER,
	        	"ACTIVE_PLUGINS" => $ACTIVE_PLUGINS
	        );
		}        
		$this->initUniquePluginImplInst("CONF_DRIVER", "conf");
		$this->initUniquePluginImplInst("AUTH_DRIVER", "auth");
		        
		$this->configs["DEFAULT_REPOSITORIES"] = $REPOSITORIES;
		$this->configs["REPOSITORIES"] = $this->initRepositoriesListInst($this->configs["DEFAULT_REPOSITORIES"]);
		$this->switchRootDirInst();
	}
	
	public static function initActivePlugins(){
		$inst = self::getInstance();
		$inst->initActivePluginsInst();
	}
	
	public function initActivePluginsInst(){
		$pServ = AJXP_PluginsService::getInstance();
		foreach($this->configs["PLUGINS"]["ACTIVE_PLUGINS"] as $plugs){
			$ex = explode(".", $plugs);
			if($ex[1] == "*"){
				$all = $pServ->getPluginsByType($ex[0]);
				foreach($all as $pName => $pObject){
					$pObject->init(array());
					$pServ->setPluginActiveInst($ex[0], $pName, true);
				}
			}else{
				$pObject = $pServ->getPluginByTypeName($ex[0], $ex[1]);
				if(!is_object($pObject)) throw new Exception("Cannot find plugin $plugs");
				$pObject->init(array());
				$pServ->setPluginActiveInst($ex[0], $ex[1], true);
			}
		}
	}
	
	public function initUniquePluginImplInst($key, $plugType){
		$name = $this->configs["PLUGINS"][$key]["NAME"];
		$options = $this->configs["PLUGINS"][$key]["OPTIONS"];
		$instance = AJXP_PluginsService::findPlugin($plugType, $name);
		if(!is_object($instance)){
			throw new Exception("Cannot find plugin $key for type $plugType");
		}
		$instance->init($options);
		$this->configs[$key] = $instance;
		$pServ = AJXP_PluginsService::getInstance();
		$pServ->setPluginUniqueActiveForType($plugType, $name);
	}
	
	public function getUniquePluginImplInst($key, $plugType = null){
		if(!isSet($this->configs[$key]) && $plugType != null){
			$this->initUniquePluginImplInst($key, $plugType);
		}
		return $this->configs[$key];
	}
	
	public static function getConfStorageImpl(){
		return self::getInstance()->getUniquePluginImplInst("CONF_DRIVER");
	}

	public static function getAuthDriverImpl(){
		return self::getInstance()->getUniquePluginImplInst("AUTH_DRIVER");
	}
	
	public static function getLogDriverImpl(){
		return self::getInstance()->getUniquePluginImplInst("LOG_DRIVER", "log");
	}

	

	public static function switchRootDir($rootDirIndex = -1, $temporary = false){
		self::getInstance()->switchRootDirInst($rootDirIndex, $temporary);
	}
	
	public function switchRootDirInst($rootDirIndex=-1, $temporary=false)
	{
		if($rootDirIndex == -1){
			if(isSet($_SESSION['REPO_ID']) && array_key_exists($_SESSION['REPO_ID'], $this->configs["REPOSITORIES"]))
			{			
				$this->configs["REPOSITORY"] = $this->configs["REPOSITORIES"][$_SESSION['REPO_ID']];
			}
			else 
			{
				$keys = array_keys($this->configs["REPOSITORIES"]);
				$this->configs["REPOSITORY"] = $this->configs["REPOSITORIES"][$keys[0]];
				$_SESSION['REPO_ID'] = $keys[0];
			}
		}
		else 
		{
			if($temporary && isSet($_SESSION['REPO_ID'])){
				$crtId = $_SESSION['REPO_ID'];
				register_shutdown_function(array("ConfService","switchRootDir"), $crtId);
			}
			$this->configs["REPOSITORY"] = $this->configs["REPOSITORIES"][$rootDirIndex];			
			$_SESSION['REPO_ID'] = $rootDirIndex;
			if(isSet($this->configs["ACCESS_DRIVER"])) unset($this->configs["ACCESS_DRIVER"]);
		}
		
		if(isSet($this->configs["REPOSITORY"]) && $this->configs["REPOSITORY"]->getOption("CHARSET")!=""){
			$_SESSION["AJXP_CHARSET"] = $this->configs["REPOSITORY"]->getOption("CHARSET");
		}else{
			if(isSet($_SESSION["AJXP_CHARSET"])){
				unset($_SESSION["AJXP_CHARSET"]);
			}
		}
		
		
		if($rootDirIndex!=-1 && AuthService::usersEnabled() && AuthService::getLoggedUser()!=null){
			$loggedUser = AuthService::getLoggedUser();
			$loggedUser->setPref("history_last_repository", $rootDirIndex);
			$loggedUser->save();
		}		
		
	}
		
	public static function getRepositoriesList(){
		return self::getInstance()->getRepositoriesListInst();
	}
	
	public function getRepositoriesListInst()
	{
		return $this->configs["REPOSITORIES"];
	}
	
	/**
	 * Deprecated, use getRepositoriesList instead.
	 * @return Array
	 */
	public static function getRootDirsList(){
		return self::getInstance()->getRepositoriesListInst();
	}
	
	public static function getCurrentRootDirIndex(){
		return self::getInstance()->getCurrentRootDirIndexInst();
	}
	public function getCurrentRootDirIndexInst()
	{
		if(isSet($_SESSION['REPO_ID']) &&  isSet($this->configs["REPOSITORIES"][$_SESSION['REPO_ID']]))
		{
			return $_SESSION['REPO_ID'];
		}
		$keys = array_keys($this->configs["REPOSITORIES"]);
		return $keys[0];
	}
	
	public static function getCurrentRootDirDisplay(){
		return self::getInstance()->getCurrentRootDirDisplayInst();
	}
	public function getCurrentRootDirDisplayInst()
	{
		if(isSet($this->configs["REPOSITORIES"][$_SESSION['REPO_ID']])){
			$repo = $this->configs["REPOSITORIES"][$_SESSION['REPO_ID']];
			return $repo->getDisplay();
		}
		return "";
	}
	
	/**
	 * @param array $repositories
	 * @return array
	 */
	public static function initRepositoriesList($defaultRepositories){
		return self::getInstance()->initRepositoriesListInst($defaultRepositories);
	}
	public function initRepositoriesListInst($defaultRepositories)
	{
		// APPEND CONF FILE REPOSITORIES
		$objList = array();
		foreach($defaultRepositories as $index=>$repository)
		{
			$repo = self::createRepositoryFromArray($index, $repository);
			$repo->setWriteable(false);
			$objList[$repo->getId()] = $repo;
		}
		// LOAD FROM DRIVER
		$confDriver = self::getConfStorageImpl();
		$drvList = $confDriver->listRepositories();
		if(is_array($drvList)){
			foreach ($drvList as $repoId=>$repoObject){
				$repoObject->setId($repoId);
				$drvList[$repoId] = $repoObject;
			}
			$objList = array_merge($objList, $drvList);
		}
		return $objList;
	}
	
	public static function detectRepositoryStreams($register = false){
		return self::getInstance()->detectRepositoryStreamsInst($register);
	}
	public function detectRepositoryStreamsInst($register = false){
		$streams = array();
		foreach ($this->configs["REPOSITORIES"] as $repository) {
			$repository->detectStreamWrapper($register, $streams);
		}
		return $streams;
	}
	
	/**
	 * Create a repository object from a config options array
	 *
	 * @param integer $index
	 * @param Array $repository
	 * @return Repository
	 */
	public static function createRepositoryFromArray($index, $repository){
		return self::getInstance()->createRepositoryFromArrayInst($index, $repository);
	}
	public function createRepositoryFromArrayInst($index, $repository){
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
	public static function addRepository($oRepository){
		return self::getInstance()->addRepositoryInst($oRepository);
	}
	public function addRepositoryInst($oRepository){
		$confStorage = self::getConfStorageImpl();
		$res = $confStorage->saveRepository($oRepository);		
		if($res == -1){
			return $res;
		}
		AJXP_Logger::logAction("Create Repository", array("repo_name"=>$oRepository->getDisplay()));
		$this->configs["REPOSITORIES"] = self::initRepositoriesList($this->configs["DEFAULT_REPOSITORIES"]);
	}
	
	/**
	 * Retrieve a repository object
	 *
	 * @param String $repoId
	 * @return Repository
	 */
	public static function getRepositoryById($repoId){
		return self::getInstance()->getRepositoryByIdInst($repoId);
	}
	public function getRepositoryByIdInst($repoId){
		if(isSet($this->configs["REPOSITORIES"][$repoId])){ 
			return $this->configs["REPOSITORIES"][$repoId];
		}
	}
	
	/**
	 * Replace a repository by an update one.
	 *
	 * @param String $oldId
	 * @param Repository $oRepositoryObject
	 * @return mixed
	 */
	public static function replaceRepository($oldId, $oRepositoryObject){
		return self::getInstance()->replaceRepositoryInst($oldId, $oRepositoryObject);
	}
	public function replaceRepositoryInst($oldId, $oRepositoryObject){
		$confStorage = self::getConfStorageImpl();
		$res = $confStorage->saveRepository($oRepositoryObject, true);
		if($res == -1){
			return $res;
		}
		AJXP_Logger::logAction("Edit Repository", array("repo_name"=>$oRepositoryObject->getDisplay()));		
		$this->configs["REPOSITORIES"] = self::initRepositoriesList($this->configs["DEFAULT_REPOSITORIES"]);				
	}
	
	public static function deleteRepository($repoId){
		return self::getInstance()->deleteRepositoryInst($repoId);
	}
	public function deleteRepositoryInst($repoId){
		$confStorage = self::getConfStorageImpl();
		$res = $confStorage->deleteRepository($repoId);
		if($res == -1){
			return $res;
		}				
		AJXP_Logger::logAction("Delete Repository", array("repo_id"=>$repoId));
		$this->configs["REPOSITORIES"] = self::initRepositoriesList($this->configs["DEFAULT_REPOSITORIES"]);				
	}
		
	public function zipEnabled()
	{
		return (function_exists("gzopen")?true:false);		
	}
	
	public static function getMessages(){
		return self::getInstance()->getMessagesInst();
	}
	public function getMessagesInst()
	{
		if(!isset($this->configs["MESSAGES"]))
		{			
			require(INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/i18n/".$this->configs["LANGUE"].".php");
			$this->configs["MESSAGES"] = $mess;
			$nodes = AJXP_PluginsService::getInstance()->searchAllManifests("//i18n", "nodes");
			foreach ($nodes as $node){
				$nameSpace = $node->getAttribute("namespace");
				$path = $node->getAttribute("path");
				$lang = $this->configs["LANGUE"];
				if(!is_file($path."/".$this->configs["LANGUE"].".php")){
					$lang = "en"; // Default language, minimum required.
				}
				if(is_file($path."/".$lang.".php")){
					require($path."/".$lang.".php");					
					foreach ($mess as $key => $message){
						$this->configs["MESSAGES"][$nameSpace.".".$key] = $message;
					}
				}
			}
		}
		
		return $this->configs["MESSAGES"];
	}
	
	public static function listAvailableLanguages(){
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

	public static function getConf($varName){
		return self::getInstance()->getConfInst($varName);
	}
	public static function setConf($varName, $varValue){
		return self::getInstance()->setConfInst($varName, $varValue);
	}
	public function getConfInst($varName)	
	{
		if(isSet($this->configs[$varName])){
			return $this->configs[$varName];
		}
		return null;
	}
	public function setConfInst($varName, $varValue)	
	{
		$this->configs[$varName] = $varValue;
	}
	
	public static function setLanguage($lang){
		return self::getInstance()->setLanguageInst($lang);
	}
	public function setLanguageInst($lang)
	{
		if(array_key_exists($lang, $this->configs["AVAILABLE_LANG"]))
		{
			$this->configs["LANGUE"] = $lang;
		}
	}
	
	public static function getLanguage()
	{		
		return self::getInstance()->getConfInst("LANGUE");
	}
		
	/**
	 * @return Repository
	 */
	public static function getRepository(){
		return self::getInstance()->getRepositoryInst();
	}
	public function getRepositoryInst()
	{
		if(isSet($_SESSION['REPO_ID']) && isSet($this->configs["REPOSITORIES"][$_SESSION['REPO_ID']])){
			return $this->configs["REPOSITORIES"][$_SESSION['REPO_ID']];
		}
		return $this->configs["REPOSITORY"];
	}
	
	/**
	 * Returns the repository access driver
	 *
	 * @return AJXP_Plugin
	 */
	public static function loadRepositoryDriver(){
		return self::getInstance()->loadRepositoryDriverInst();
	}
	public function loadRepositoryDriverInst()
	{
		if(isSet($this->configs["ACCESS_DRIVER"]) && is_a($this->configs["ACCESS_DRIVER"], "AbstractAccessDriver")){			
			return $this->configs["ACCESS_DRIVER"];
		}
        $this->switchRootDirInst();
		$crtRepository = $this->getRepositoryInst();
		$accessType = $crtRepository->getAccessType();
		$pServ = AJXP_PluginsService::getInstance();
		$plugInstance = $pServ->getPluginByTypeName("access", $accessType);
		$plugInstance->init($crtRepository);
		try{
			$plugInstance->initRepository();
		}catch (Exception $e){
			// Remove repositories from the lists
			unset($this->configs["REPOSITORIES"][$crtRepository->getId()]);
			$this->switchRootDir();
			throw $e;
		}
		$pServ->setPluginUniqueActiveForType("access", $accessType);			
		
		$metaSources = $crtRepository->getOption("META_SOURCES");
		if(isSet($metaSources) && is_array($metaSources) && count($metaSources)){
			$keys = array_keys($metaSources);			
			foreach ($keys as $plugId){
				if($plugId == "") continue;
				$split = explode(".", $plugId);				
				$instance = $pServ->getPluginById($plugId);
				$instance->init($metaSources[$plugId]);
				$instance->initMeta($plugInstance);
				$pServ->setPluginActive($split[0], $split[1]);
			}
		}
		
		$this->configs["ACCESS_DRIVER"] = $plugInstance;	
		return $this->configs["ACCESS_DRIVER"];
	}
	
	public static function availableDriversToXML($filterByTagName = "", $filterByDriverName=""){
		$manifests = array();
		$base = INSTALL_PATH."/plugins";
		$xmlString = "";
		if($fp = opendir($base)){
			while (($subdir = readdir($fp))!==false) {
				if($subdir == "index.html") continue;
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

 	/**
 	 * Singleton method
 	 *
 	 * @return ConfService the service instance
 	 */
 	public static function getInstance()
 	{
 		if(!isSet(self::$instance)){
 			$c = __CLASS__;
 			self::$instance = new $c;
 		}
 		return self::$instance;
 	}
 	private function __construct(){}
	public function __clone(){
        trigger_error("Cannot clone me, i'm a singleton!", E_USER_ERROR);
    } 	
	
}
?>