<?php

class Repository {

	var $uuid;
	var $id;
	var $path;
	var $display;
	var $accessType = "fs";
	var $recycle = "";
	var $create = true;
	var $writeable = false;
	var $enabled = true;
	var $options = array();
	
	function Repository($id, $display, $driver){
		$this->setAccessType($driver);
		$this->setDisplay($display);
		$this->setId($id);
		$this->uuid = md5(time());
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
		$fileName = INSTALL_PATH."/plugins/ajxp.".$this->accessType."/manifest.xml";
		$settingLine = "";
		if(is_readable($fileName)){
			$lines = file($fileName);			
			foreach ($lines as $line){
				if(eregi("client_settings", trim($line)) > -1){
					$settingLine = str_replace(array("<client_settings","/>"), "", trim($line));
				}
			}
		}
		return $settingLine;
	}
	

	function addOption($oName, $oValue){
		if(strpos($oName, "PATH") !== false){
			$oValue = str_replace("\\", "/", $oValue);
		}
		$this->options[$oName] = $oValue;
	}
	
	function getOption($oName){		
		if(isSet($this->options[$oName])){
			$value = $this->options[$oName];
			if(is_string($value) && strpos($value, "AJXP_USER")!==false){
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
		
}

?>
