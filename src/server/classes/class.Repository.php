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
 * Description : Repository abstraction.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class Repository {

	var $uuid;
	var $id;
	var $path;
	var $display;
	var $displayStringId;
	var $accessType = "fs";
	var $recycle = "";
	var $create = true;
	var $writeable = false;
	var $enabled = true;
	var $options = array();
	
	private $owner;
	private $parentId;
	private $uniqueUser;
	
	public $streamData;
	
	function Repository($id, $display, $driver){
		$this->setAccessType($driver);
		$this->setDisplay($display);
		$this->setId($id);
		$this->uuid = md5(time());
	}
	
	function createSharedChild($newLabel, $newOptions, $parentId, $owner, $uniqueUser){
		$repo = new Repository(0, $newLabel, $this->accessType);
		$newOptions = array_merge($this->options, $newOptions);
		$repo->options = $newOptions;
		$repo->setOwnerData($parentId, $owner, $uniqueUser);
		return $repo;
	}
	
	function upgradeId(){
		if(!isSet($this->uuid)) {
			$this->uuid = md5(serialize($this));
			//$this->uuid = md5(time());
			return true;
		}
		return false;
	}
	
	function getUniqueId($serial=false){
		if($serial){
			return md5(serialize($this));
		}
		return $this->uuid;
	}
	
	function getClientSettings(){
		$fileName = INSTALL_PATH."/plugins/access.".$this->accessType."/manifest.xml";
		$settingLine = "";
		if(is_readable($fileName)){
			$lines = file($fileName);	
			$inside = false;		
			foreach ($lines as $line){
				$compareLine = strtolower($line);				
				if(preg_match('/\<client_settings/', trim($compareLine)) > 0){
					$settingLine = trim($line);
					if(preg_match("/\/\>/", trim($compareLine))>0 || preg_match("/\<\/client_settings\>/", trim($compareLine)>0)){
						return $settingLine;
					}
					$inside = true;					
				}else{
					if($inside) $settingLine.=trim($line);
					if(preg_match("/\<\/client_settings\>/", trim(strtolower($line)))>0) return $settingLine;
				}
			}
		}
		return $settingLine;
	}
	
	function detectStreamWrapper($register = false, &$streams=null){
		$plugin = AJXP_PluginsService::findPlugin("access", $this->accessType);
		$streamData = $plugin->detectStreamWrapper($register);
		if(!$register && $streamData !== false && is_array($streams)){
			$streams[$this->accessType] = $this->accessType;
		}
		if($streamData !== false) $this->streamData = $streamData;
		return ($streamData !== false);
	}
	

	function addOption($oName, $oValue){
		if(strpos($oName, "PATH") !== false){
			$oValue = str_replace("\\", "/", $oValue);
		}
		$this->options[$oName] = $oValue;
	}
	
	function getOption($oName, $safe=false){		
		if(isSet($this->options[$oName])){
			$value = $this->options[$oName];
			if(is_string($value) && strpos($value, "AJXP_USER")!==false && !$safe){
				if(AuthService::usersEnabled()){
					$loggedUser = AuthService::getLoggedUser();
					if($loggedUser != null){
						$loggedUser = $loggedUser->getId();
						$value = str_replace("AJXP_USER", $loggedUser, $value);
					}else{
						return "";
					}
				}else{
					$value = str_replace("AJXP_USER", "shared", $value);
				}
			}
			if(is_string($value) && strpos($value, "AJXP_INSTALL_PATH") !== false){
				$value = str_replace("AJXP_INSTALL_PATH", INSTALL_PATH, $value);
			}
			return $value;
		}
		return "";
	}
	
	function getDefaultRight(){
		$opt = $this->getOption("DEFAULT_RIGHTS");
		return (isSet($opt)?$opt:"");
	}
	
	
	/**
	 * @return String
	 */
	function getAccessType() {
		return $this->accessType;
	}
	
	/**
	 * @return String
	 */
	function getDisplay() {
		if(isSet($this->displayStringId)){
			$mess = ConfService::getMessages();
			if(isSet($mess[$this->displayStringId])){
				return SystemTextEncoding::fromUTF8($mess[$this->displayStringId]);
			}
		}
		return $this->display;
	}
	
	/**
	 * @return int
	 */
	function getId() {
		return $this->id;
	}
	
	/**
	 * @return boolean
	 */
	function getCreate() {
		return $this->getOption("CREATE");
	}
	
	/**
	 * @param boolean $create
	 */
	function setCreate($create) {
		$this->options["CREATE"] = $create;
	}

	
	/**
	 * @param String $accessType
	 */
	function setAccessType($accessType) {
		$this->accessType = $accessType;
	}
	
	/**
	 * @param String $display
	 */
	function setDisplay($display) {
		$this->display = $display;
	}
	
	/**
	 * @param int $id
	 */
	function setId($id) {
		$this->id = $id;
	}
	
	function isWriteable(){
		return $this->writeable;
	}
	
	function setWriteable($w){
		$this->writeable = $w;
	}
	
	function isEnabled(){
		return $this->enabled;
	}
	
	function setEnabled($e){
		$this->enabled = $e;
	}
	
	function setDisplayStringId($id){
		$this->displayStringId = $id;
	}
	
	function setOwnerData($repoParentId, $ownerUserId, $childUserId){
		$this->owner = $ownerUserId;
		$this->uniqueUser = $childUserId;
		$this->parentId = $repoParentId;
	}
	
	function getOwner(){
		return $this->owner;
	}
	
	function getParentId(){
		return $this->parentId;
	}
	
	function getUniqueUser(){
		return $this->uniqueUser;
	}
	
	function hasOwner(){
		return isSet($this->owner);
	}
		
}

?>
