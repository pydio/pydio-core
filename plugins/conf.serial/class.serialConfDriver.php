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
 * Implementation of the configuration driver on serial files
 */
class serialConfDriver extends AbstractConfDriver {

	var $repoSerialFile;
	var $usersSerialDir;
	var $rolesSerialFile;

	var $aliasesIndexFile;
	var $pluginsConfigsFile;

	function init($options){
		parent::init($options);
		$this->repoSerialFile = AJXP_VarsFilter::filter($options["REPOSITORIES_FILEPATH"]);
		$this->usersSerialDir = AJXP_VarsFilter::filter($options["USERS_DIRPATH"]);
		$this->rolesSerialFile = AJXP_VarsFilter::filter($options["ROLES_FILEPATH"]);
		$this->aliasesIndexFile = dirname($this->repoSerialFile)."/aliases.ser";
		$this->pluginsConfigsFile = dirname($this->repoSerialFile)."/plugins_configs.ser";
	}

	function performChecks(){
        if(isSet($this->options["FAST_CHECKS"]) && $this->options["FAST_CHECKS"] === true) return;
		$this->performSerialFileCheck($this->repoSerialFile, "repositories file");
		$this->performSerialFileCheck($this->usersSerialDir, "users file", true);
		$this->performSerialFileCheck($this->rolesSerialFile, "roles file");
	}

	function performSerialFileCheck($file, $fileLabel, $isDir = false){
		if($isDir){
			if(!is_dir($file) || !is_writable($file)){
				throw new Exception("Folder for storing $fileLabel is either inexistent or not writeable.");
			}
			return ;
		}
		$dir = dirname($file);
		if(!is_dir($dir) || !is_writable($dir)){
			throw new Exception("Parent folder for $fileLabel is either inexistent or not writeable.");
		}
		if(is_file($file) && !is_writable($file)){
			throw new Exception(ucfirst($fileLabel)." exists but is not writeable!");
		}
	}

	// SAVE / LOAD PLUGINS CONF
	function _loadPluginConfig($pluginId, &$options){
		$data = AJXP_Utils::loadSerialFile($this->pluginsConfigsFile);
		if(isSet($data[$pluginId]) && is_array($data[$pluginId])){
			foreach ($data[$pluginId] as $key => $value){
                if(isSet($options[$key])) continue;
                if(is_string($value)){
                    if(strpos($value, "\\n")){
                        $value = str_replace("\\n", "\n", $value);
                    }
                    if(strpos($value, "\\r")){
                        $value = str_replace("\\r", "\r", $value);
                    }
                }
                $options[$key] = $value;
			}
		}
	}

	function savePluginConfig($pluginId, $options){
		$data = AJXP_Utils::loadSerialFile($this->pluginsConfigsFile);
        foreach ($options as $k=>$v){
            if(is_string($v)){
                $options[$k] = addcslashes($v, "\r\n");
            }
        }
		$data[$pluginId] = $options;
		AJXP_Utils::saveSerialFile($this->pluginsConfigsFile, $data);
	}

	// SAVE / EDIT / CREATE / DELETE REPOSITORY
	function listRepositories(){
		return AJXP_Utils::loadSerialFile($this->repoSerialFile);

	}

	function listRoles(){
		return AJXP_Utils::loadSerialFile($this->rolesSerialFile);
	}

	function saveRoles($roles){
		AJXP_Utils::saveSerialFile($this->rolesSerialFile, $roles);
	}

	function countAdminUsers(){
		$confDriver = ConfService::getConfStorageImpl();
		$authDriver = ConfService::getAuthDriverImpl();
		$count = 0;
		$users = $authDriver->listUsers();
		foreach (array_keys($users) as $userId){
			$userObject = $confDriver->createUserObject($userId);
			$userObject->load();
			if($userObject->isAdmin()) $count++;
		}
		return $count;
	}

	/**
	 * Unique ID of the repositor
	 *
	 * @param String $repositoryId
	 * @return Repository
	 */
	function getRepositoryById($repositoryId){
		$repositories = AJXP_Utils::loadSerialFile($this->repoSerialFile);
		if(isSet($repositories[$repositoryId])){
			return $repositories[$repositoryId];
		}
		return null;
	}
	/**
	 * Retrieve a Repository given its alias.
	 *
	 * @param String $repositorySlug
	 * @return Repository
	 */
	function getRepositoryByAlias($repositorySlug){
		$data = AJXP_Utils::loadSerialFile($this->aliasesIndexFile);
		if(isSet($data[$repositorySlug])){
			return $this->getRepositoryById($data[$repositorySlug]);
		}
		return null;
	}

	/**
	 * Store a newly created repository
	 *
	 * @param Repository $repositoryObject
	 * @param Boolean $update
	 * @return -1 if failed
	 */
	function saveRepository($repositoryObject, $update = false){
		$repositories = AJXP_Utils::loadSerialFile($this->repoSerialFile);
		if(!$update){
			$repositoryObject->writeable = true;
			$repositories[$repositoryObject->getUniqueId()] = $repositoryObject;
		}else{
			foreach ($repositories as $index => $repo){
				if($repo->getUniqueId() == $repositoryObject->getUniqueId()){
					$repositories[$index] = $repositoryObject;
					break;
				}
			}
		}
		$res = AJXP_Utils::saveSerialFile($this->repoSerialFile, $repositories);
		if($res == -1){
			return $res;
		}else{
			$this->updateAliasesIndex($repositoryObject->getUniqueId(), $repositoryObject->getSlug());
		}
	}
	/**
	 * Delete a repository, given its unique ID.
	 *
	 * @param String $repositoryId
	 */
	function deleteRepository($repositoryId){
		$repositories = AJXP_Utils::loadSerialFile($this->repoSerialFile);
		$newList = array();
		foreach ($repositories as $repo){
			if($repo->getUniqueId() != $repositoryId){
				$newList[$repo->getUniqueId()] = $repo;
			}
		}
		AJXP_Utils::saveSerialFile($this->repoSerialFile, $newList);
	}
	/**
	 * Serial specific method : indexes repositories by slugs, for better performances
	 */
	function updateAliasesIndex($repositoryId, $repositorySlug){
		$data = AJXP_Utils::loadSerialFile($this->aliasesIndexFile);
		$byId = array_flip($data);
		$byId[$repositoryId] = $repositorySlug;
		AJXP_Utils::saveSerialFile($this->aliasesIndexFile, array_flip($byId));
	}

    /**
     * @abstract
     * @param $userId
     * @return array()
     */
    function getUserChildren($userId){
        $result = array();
        $authDriver = ConfService::getAuthDriverImpl();
        $confDriver = ConfService::getConfStorageImpl();
        $users = $authDriver->listUsers();
        foreach (array_keys($users) as $id){
            $object = $confDriver->createUserObject($id);
            if($object->hasParent() && $object->getParent() == $userId){
                $result[] = $object;
            }
        }
        return $result;
    }

    /**
     * @param string $repositoryId
     * @return array()
     */
    function getUsersForRepository($repositoryId){
        $result = array();
        $authDriver = ConfService::getAuthDriverImpl();
        $confDriver = ConfService::getConfStorageImpl();
        $users = $authDriver->listUsers();
        foreach (array_keys($users) as $id){
            $object = $confDriver->createUserObject($id);
            if($object->canSwitchTo($repositoryId)){
                $result[] = $object;
            }
        }
        return $result;
    }


    function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false){
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser");
        foreach($flatUsersList as $userid => $userdata){
            if(array_key_exists($userid, $groups)){
                $path = $groups[$userid];
                if(substr($path, 0, strlen($baseGroup)) != $baseGroup){
                    unset($flatUsersList[$userid]);
                }else if(strlen($path) > strlen($baseGroup)){
                    if(!$fullTree) unset($flatUsersList[$userid]);
                }
            }else{
                if($baseGroup != "/"){
                    unset($flatUsersList[$userid]);
                }
            }
        }
    }

    function getChildrenGroups($baseGroup = "/"){

        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser");
        $levelGroups = array();
        $labels = array();
        asort($groups);
        foreach($groups as $id => $path){
            if(substr($path, 0, strlen($baseGroup)) == $baseGroup && strlen($path) >  strlen($baseGroup)){
                $parts = explode("/", ltrim(substr($path, strlen($baseGroup)), "/"));
                $sub = array_shift($parts);
                if(!isset($levelGroups[$sub])) $levelGroups[$sub] = $path;
                if(substr($id, 0, strlen("AJXP_GROUP:")) == "AJXP_GROUP:"){
                    $labels[$path] = array_pop(explode(":", $id, 2));
                }
            }
        }
        foreach($levelGroups as $gId => $grPath){
            if(isSet($labels[$grPath])) $levelGroups[$gId] = $labels[$grPath];
            else $levelGroups[$gId] = $gId;
        }
        return $levelGroups;
    }

    function createGroup($groupPath, $groupLabel){
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser");
        $groups["AJXP_GROUP:$groupLabel"] = $groupPath;
        AJXP_Utils::saveSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser", $groups);
    }

    /**
	 * Instantiate the right class
	 *
	 * @param String $userId
     * @return AbstractAjxpUser
	 */
	function instantiateAbstractUserImpl($userId){
		return new AJXP_User($userId, $this);
	}

	function getUserClassFileName(){
		return AJXP_INSTALL_PATH."/plugins/conf.serial/class.AJXP_User.php";
	}
}
