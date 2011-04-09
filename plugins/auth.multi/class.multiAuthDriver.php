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
	
	public function init($options){
		//parent::init($options);
		$this->options = $options;
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
		// THE "LOAD REGISTRY CONTRIBUTIONS" METHOD
		// WILL BE CALLED LATER, TO BE SURE THAT THE
		// SESSION IS ALREADY STARTED.
	}
	
	public function getRegistryContributions(){
		AJXP_Logger::debug("get contributions NOW");
		$this->loadRegistryContributions();
		return parent::getRegistryContributions();
	}
		
	private function detectCurrentDriver(){
		//if(isSet($this->currentDriver)) return;
		$authSource = $this->getOption("MASTER_DRIVER");
		if(isSet($_POST["auth_source"])){
			$_SESSION["AJXP_MULTIAUTH_SOURCE"] = $_POST["auth_source"];
			$authSource = $_POST["auth_source"];
			AJXP_Logger::debug("Auth source from POST");
		}else if(isSet($_SESSION["AJXP_MULTIAUTH_SOURCE"])){
			$authSource = $_SESSION["AJXP_MULTIAUTH_SOURCE"];
			AJXP_Logger::debug("Auth source from SESSION");
		}else {
			AJXP_Logger::debug("Auth source from MASTER");
		}
		$this->setCurrentDriverName($authSource);		
	}
	
	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if($contribNode->nodeName != "actions") return ;
		// Replace callback code
		$actionXpath=new DOMXPath($contribNode->ownerDocument);
		$loginCallbackNodeList = $actionXpath->query('action[@name="login"]/processing/clientCallback', $contribNode);
		if(!$loginCallbackNodeList->length) return ;
		$xmlContent = file_get_contents(AJXP_INSTALL_PATH."/plugins/auth.multi/login_patch.xml");
		$sources = array();
		foreach($this->getOption("DRIVERS") as $driverDef){
			$dName = $driverDef["NAME"];
			if(isSet($driverDef["LABEL"])){
				$dLabel = $driverDef["LABEL"];
			}else{
				$dLabel = $driverDef["NAME"];
			}
			$sources[$dName] = $dLabel;
		}
		$xmlContent = str_replace("AJXP_MULTIAUTH_SOURCES", json_encode($sources), $xmlContent);
		$xmlContent = str_replace("AJXP_MULTIAUTH_MASTER", $this->getOption("MASTER_DRIVER"), $xmlContent);
		$patchDoc = DOMDocument::loadXML($xmlContent);
		$patchNode = $patchDoc->documentElement;
		$imported = $contribNode->ownerDocument->importNode($patchNode, true);
		$loginCallback = $loginCallbackNodeList->item(0);
		$loginCallback->parentNode->replaceChild($imported, $loginCallback);
		//var_dump($contribNode->ownerDocument->saveXML($contribNode));
	}
		
	protected function setCurrentDriverName($name){
		$this->currentDriver = $name;
	}
	
	protected function getCurrentDriver(){
		$this->detectCurrentDriver();
		if(isSet($this->currentDriver) && isSet($this->drivers[$this->currentDriver])){
			return $this->drivers[$this->currentDriver];
		}else{
			return false;
		}
	}
	
	protected function extractRealId($userId){
		$parts = explode("::", $userId);
		if(count($parts) == 2){
			return $parts[1];
		}
		return $userId;
	}

	public function performChecks(){
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
		$login = $this->extractRealId($login);
		AJXP_Logger::debug("user exists ".$login);
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->userExists($login);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}	
	
	function checkPassword($login, $pass, $seed){
		$login = $this->extractRealId($login);
		AJXP_Logger::debug("check pass ".$login);
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
			AJXP_Logger::debug("passEditable no current driver set??");
			return false;
		}		
	}
	
	function createUser($login, $passwd){
		$login = $this->extractRealId($login);		
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
	
	function filterCredentials($userId, $pwd){
		return array($this->extractRealId($userId), $pwd);
	}	

}
?>