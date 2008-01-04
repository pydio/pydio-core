<?php

class Repository {

	var $id;
	var $path;
	var $display;
	var $accessType = "fs";
	var $recycle = "";
	var $create = true;
	
	function Repository($id, $path, $display){
		$this->setPath($path);
		$this->setDisplay($display);
		$this->setId($id);
		$this->options = array();
	}
	/**
	 * @return String
	 */
	function getPath() {
		return $this->path;
	}
	
	/**
	 * @param String $path
	 */
	function setPath($path) {
		$path = str_replace("\\", "/", $path); // windows like
		$this->path = $path;
	}
	/**
	 * @return unknown
	 */
	function getRecycle() {
		return $this->recycle;
	}
	
	/**
	 * @param unknown_type $recycle
	 */
	function setRecycle($recycle) {
		$this->recycle = $recycle;
	}

	function addOption($oName, $oValue){
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
		return $this->create;
	}
	
	/**
	 * @param boolean $create
	 */
	function setCreate($create) {
		$this->create = $create;
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
		
}

?>
