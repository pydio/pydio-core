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
 * Description : Abstract representation of an access to an authentication system (ajxp, ldap, etc).
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(INSTALL_PATH."/server/classes/class.AbstractAuthDriver.php");
class multiAuthDriver extends AbstractAuthDriver {
	
	var $driverName = "multi";
	var $driversDef = array();
	var $currentDriver;
	/**
	 * @var $drivers AbstractAuthDriver[]
	 */
	var $drivers =  array();
	
	function init($options){
		parent::init($options);
		$this->driversDef = $this->getOption("DRIVERS");
		foreach($this->driversDef as $def){
			$name = $def["NAME"];
			$options = $def["OPTIONS"];
			$instance = AJXP_PluginsService::findPlugin("auth", $name);
			if(!is_object($instance)){
				throw new Exception("Cannot find plugin $name for type $plugType");
			}
			$instance->init($options);
			$this->drivers[$name] = $instance;
		}
		$this->setCurrentDriverName($this->getOption("MASTER_DRIVER"));
	}
	
	public function getRegistryContributions(){
		return $this->getCurrentDriver()->getRegistryContributions();
	}
	
	function setCurrentDriverName($name){
		$this->currentDriver = $name;
	}
	
	function getCurrentDriver(){
		if(isSet($this->currentDriver) && isSet($this->drivers[$this->currentDriver])){
			return $this->drivers[$this->currentDriver];
		}else{
			return false;
		}
	}

	function performChecks(){
		foreach($this->drivers as $driver){
			$driver->performChecks();
		}
	}
	
	function listUsers(){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->listUsers();
		}
		$allUsers = array();
		foreach($this->drivers as $driver){
			$allUsers = array_merge($driver->listUsers());
		}		
	}
	
	function preLogUser($remoteSessionId){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->preLogUser($remoteSessionId);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}	
	
	function userExists($login){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->userExists($login);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}	
	
	function checkPassword($login, $pass, $seed){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->checkPassword($login, $pass, $seed);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}
	
	function usersEditable(){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->usersEditable();
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}
	
	function passwordsEditable(){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->passwordsEditable();
		}else{
			//throw new Exception("No driver instanciated in multi driver!");
			return false;
		}		
	}
	
	function createUser($login, $passwd){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->createUser($login, $passwd);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}				
	}	
	
	function changePassword($login, $newPass){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->usersEditable();
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}	

	function deleteUser($login){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->deleteUser($login);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}

	function getUserPass($login){
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->getUserPass($login);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}

}
?>