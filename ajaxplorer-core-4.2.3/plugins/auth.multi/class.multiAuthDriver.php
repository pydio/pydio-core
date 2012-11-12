<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * Ability to encapsulate many auth drivers and choose the right one at login.
 */
class multiAuthDriver extends AbstractAuthDriver {
	
	var $driverName = "multi";
	var $driversDef = array();
	var $currentDriver;

    var $masterSlaveMode = false;
    var $masterName;
    var $slaveName;
    var $baseName;

    static $schemesCache = array();

	/**
	 * @var $drivers AbstractAuthDriver[]
	 */
	var $drivers =  array();
	
	public function init($options){
		//parent::init($options);
		$this->options = $options;
		$this->driversDef = $this->getOption("DRIVERS");
        $this->masterSlaveMode = ($this->getOption("MODE") == "MASTER_SLAVE");
        $this->masterName = $this->getOption("MASTER_DRIVER");
        $this->baseName = $this->getOption("USER_BASE_DRIVER");
		foreach($this->driversDef as $def){
			$name = $def["NAME"];
			$options = $def["OPTIONS"];
			$options["TRANSMIT_CLEAR_PASS"] = $this->options["TRANSMIT_CLEAR_PASS"];
			$options["LOGIN_REDIRECT"] = $this->options["LOGIN_REDIRECT"];			
			$instance = AJXP_PluginsService::findPlugin("auth", $name);
			if(!is_object($instance)){
				throw new Exception("Cannot find plugin $name for type 'auth'");
			}
			$instance->init($options);
            if($this->masterSlaveMode && $name != $this->getOption("MASTER_DRIVER")){
                $this->slaveName = $name;
            }
			$this->drivers[$name] = $instance;
		}
		// THE "LOAD REGISTRY CONTRIBUTIONS" METHOD
		// WILL BE CALLED LATER, TO BE SURE THAT THE
		// SESSION IS ALREADY STARTED.
	}
	
	public function getRegistryContributions( $extendedVersion = true ){
		AJXP_Logger::debug("get contributions NOW");
		$this->loadRegistryContributions();
		return parent::getRegistryContributions( $extendedVersion );
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
        if($this->masterSlaveMode) return;
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
		$xmlContent = str_replace("AJXP_USER_ID_SEPARATOR", $this->getOption("USER_ID_SEPARATOR"), $xmlContent);
        $patchDoc = new DOMDocument();
        $patchDoc->loadXML($xmlContent);
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
		$parts = explode($this->getOption("USER_ID_SEPARATOR"), $userId);
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

    function getAuthScheme($login){
        if(isSet(multiAuthDriver::$schemesCache[$login])){
            return multiAuthDriver::$schemesCache[$login];
        }
        return null;
    }

    function supportsAuthSchemes(){
        return true;
    }

    function addToCache($usersList, $scheme){
        foreach($usersList as $userName){
            multiAuthDriver::$schemesCache[$userName] = $scheme;
        }
    }

    function supportsUsersPagination(){
        return (!empty($this->baseName) && $this->drivers[$this->baseName]->supportsUsersPagination());
    }

    function listUsersPaginated($regexp, $offset, $limit){
        return $this->drivers[$this->baseName]->listUsersPaginated($regexp, $offset, $limit);
    }

    function getUsersCount(){
        if(empty($this->baseName)){
            return $this->drivers[$this->slaveName]->getUsersCount() +  $this->drivers[$this->masterName]->getUsersCount();
        }else{
            return $this->drivers[$this->baseName]->getUsersCount();
        }
    }

	function listUsers(){
        if($this->masterSlaveMode){
            if(!empty($this->baseName)) {
                $users = $this->drivers[$this->baseName]->listUsers();
                $this->addToCache(array_keys($users), $this->baseName);
                return $users;
            }
            $masterUsers = $this->drivers[$this->slaveName]->listUsers();
            $this->addToCache(array_keys($masterUsers), $this->slaveName);
            $slaveUsers = $this->drivers[$this->masterName]->listUsers();
            $this->addToCache(array_keys($slaveUsers), $this->masterName);
            return array_merge($masterUsers, $slaveUsers);
        }
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->listUsers();
		}
		$allUsers = array();
		foreach($this->drivers as $driver){
			$allUsers = array_merge($driver->listUsers());
		}
		return $allUsers;
	}
	
	function preLogUser($remoteSessionId){
        if($this->masterSlaveMode){
            $this->drivers[$this->slaveName]->preLogUser($remoteSessionId);
            if(AuthService::getLoggedUser() == null){
                return $this->drivers[$this->masterName]->preLogUser($remoteSessionId);
            }
            return;
        }

		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->preLogUser($remoteSessionId);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}	
	
	function userExists($login){
        if($this->masterSlaveMode){
            if($this->drivers[$this->slaveName]->userExists($login)){
                return true;
            }
            if($this->drivers[$this->masterName]->userExists($login)){
                return true;
            }
            return false;
        }
		$login = $this->extractRealId($login);
		AJXP_Logger::debug("user exists ".$login);
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->userExists($login);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}	
	
	function checkPassword($login, $pass, $seed){
        if($this->masterSlaveMode){
            if($this->drivers[$this->masterName]->userExists($login)){
                // check master, and refresh slave if necessary
                if($this->drivers[$this->masterName]->checkPassword($login, $pass, $seed)){
                    if($this->drivers[$this->slaveName]->userExists($login)){
                        $this->drivers[$this->slaveName]->changePassword($login, $pass);
                    }else{
                        $this->drivers[$this->slaveName]->createUser($login, $pass);
                        // Make sure we apply defaultRights (roles)
                        $confDriver = ConfService::getConfStorageImpl();
                        $user = $confDriver->createUserObject($login);
                        $user->save("superuser");
                    }
                    return true;
                }else{
                    return false;
                }
            }else{
                $res = $this->drivers[$this->slaveName]->checkPassword($login, $pass, $seed);
                return $res;
            }
        }

		$login = $this->extractRealId($login);
		AJXP_Logger::debug("check pass ".$login);
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->checkPassword($login, $pass, $seed);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}
	
	function usersEditable(){
        if($this->masterSlaveMode) return true;

		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->usersEditable();
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}
	
	function passwordsEditable(){
        if($this->masterSlaveMode) return true;

		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->passwordsEditable();
		}else{
			//throw new Exception("No driver instanciated in multi driver!");
			AJXP_Logger::debug("passEditable no current driver set??");
			return false;
		}		
	}
	
	function createUser($login, $passwd){
        if($this->masterSlaveMode){
            return $this->drivers[$this->slaveName]->createUser($login, $passwd);
        }

		$login = $this->extractRealId($login);		
		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->createUser($login, $passwd);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}				
	}	
	
	function changePassword($login, $newPass){
        if($this->masterSlaveMode){
            return $this->drivers[$this->slaveName]->changePassword($login, $newPass);
        }

		if($this->getCurrentDriver() && $this->getCurrentDriver()->usersEditable()){
			return $this->getCurrentDriver()->changePassword($login, $newPass);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}	

	function deleteUser($login){
        if($this->masterSlaveMode){
            return $this->drivers[$this->slaveName]->deleteUser($login);
        }

		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->deleteUser($login);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}

	function getUserPass($login){
        if($this->masterSlaveMode){
            return $this->drivers[$this->slaveName]->getUserPass($login);
        }

		if($this->getCurrentDriver()){
			return $this->getCurrentDriver()->getUserPass($login);
		}else{
			throw new Exception("No driver instanciated in multi driver!");
		}		
	}
	
	function filterCredentials($userId, $pwd){
        if($this->masterSlaveMode) return array($userId, $pwd);
		return array($this->extractRealId($userId), $pwd);
	}	

}
?>
