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
		if(array_key_exists("PATH", $this->options)) {
			return $this->options["PATH"];
		}else{
			return "";
		}
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
		if(array_key_exists("RECYCLE_BIN", $this->options)) {
			return $this->options["RECYCLE_BIN"];
		}else{
			return "";
		}		
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
		if($this->options[$oName]){
			return $this->options[$oName];
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
		if(array_key_exists("CREATE", $this->options)) {
			return $this->options["CREATE"];
		}else{
			return false;
		}
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
