<?php

class Repository {

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
	}
	/**
	 * @return String
	 */
	function getPath() {		
		return $this->getOption("PATH");
	}
	
	/**
	 * @param String $path
	 */
	function setPath($path) {
		$path = str_replace("\\", "/", $path); // windows like
		//$this->path = $path;
		$this->options["PATH"] = $path;
	}
	/**
	 * @return unknown
	 */
	function getRecycle() {
		return $this->getOption("RECYCLE_BIN");
	}
	
	function hasIcon(){
		return is_file(INSTALL_PATH."/".$this->getIconPath());
	}
	
	function getIconPath(){
		return "plugins/ajxp.".$this->accessType."/".$this->accessType."_icon.png";
	}
	
	/**
	 * @param unknown_type $recycle
	 */
	function setRecycle($recycle) {
		$this->options["RECYCLE_BIN"] = $recycle;
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
