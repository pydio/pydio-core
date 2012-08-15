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
 * Implementation of the AbstractUser for serial 
 */
class AJXP_User extends AbstractAjxpUser 
{
	var $id;
	var $hasAdmin = false;
	var $rights;
	var $prefs;
	var $bookmarks;
	var $version;
	
	/**
	 * Conf Storage implementation
	 *
	 * @var AbstractConfDriver
	 */
	var $storage;
	var $registerForSave = array();
    var $create = true;

    /**
     * @param $id
     * @param serialConfDriver $storage
     */
    function AJXP_User($id, $storage=null){
		parent::AbstractAjxpUser($id, $storage);
        $this->registerForSave = array();
    }

    function setGroupPath($groupPath){
        parent::setGroupPath($groupPath);
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/groups.ser");
        $groups[$this->getId()] = $groupPath;
        AJXP_Utils::saveSerialFile(AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/groups.ser", $groups);
    }

    function __wakeup(){
        $this->registerForSave = array();
    }

    protected function getStoragePath(){
        $subDir = trim($this->getGroupPath(), "/");
        $id = $this->getId();
        if(AuthService::ignoreUserCase()) $id = strtolower($id);
        $res = AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/".(empty($subDir)?"":$subDir."/").$id;
        return $res;
    }

	function storageExists(){
        return is_dir($this->getStoragePath());
	}

	function load(){
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/groups.ser");
        if(isSet($groups[$this->getId()])) $this->groupPath = $groups[$this->getId()];

        $this->create = false;
        $this->rights = AJXP_Utils::loadSerialFile($this->getStoragePath()."/rights.ser");
        if(count($this->rights) == 0) $this->create = true;
		$this->prefs = AJXP_Utils::loadSerialFile($this->getStoragePath()."/prefs.ser");
		$this->bookmarks = AJXP_Utils::loadSerialFile($this->getStoragePath()."/bookmarks.ser");
		if(isSet($this->rights["ajxp.admin"]) && $this->rights["ajxp.admin"] === true){
			$this->setAdmin(true);
		}
		if(isSet($this->rights["ajxp.parent_user"])){
			$this->setParent($this->rights["ajxp.parent_user"]);
		}
        if(isSet($this->rights["ajxp.group_path"])){
            $this->setGroupPath($this->rights["ajxp.group_path"]);
        }
        $allRoles = AuthService::getRolesList(); // Maintained as instance variable
        // LOAD GROUP ROLES IF THEY EXIST
        if($this->groupPath != null){
            $base = "/";
            $exp = explode("/", $this->groupPath);
            foreach($exp as $pathPart){
                $base = $base . $pathPart;
                if(isSet($allRoles["AJXP_GRP_".$base])){
                    $this->roles["AJXP_GRP_".$base] = $allRoles["AJXP_GRP_".$base];
                    $this->rights["ajxp.roles"]["AJXP_GRP_".$base] = true;
                }
            }
        }
		// Load roles
		if(isSet($this->rights["ajxp.roles"])){
			foreach (array_keys($this->rights["ajxp.roles"]) as $roleId){
				if(isSet($allRoles[$roleId])){
					$this->roles[$roleId] = $allRoles[$roleId];
				}else{
					unset($this->rights["ajxp.roles"][$roleId]);
				}
			}
		}
	}
	
	function save($context = "superuser"){
		if($this->isAdmin() === true){
			$this->rights["ajxp.admin"] = true;
		}else{
			$this->rights["ajxp.admin"] = false;
		}
		if($this->hasParent()){
			$this->rights["ajxp.parent_user"] = $this->parentUser;
		}
        $this->rights["ajxp.group_path"] = $this->getGroupPath();

        if($context == "superuser"){
            $this->registerForSave["rights"] = true;
        }
        $this->registerForSave["prefs"] = true;
        $this->registerForSave["bookmarks"] = true;
	}

    function __destruct(){
        if(count($this->registerForSave)==0) return;
        $fastCheck = $this->storage->getOption("FAST_CHECKS");
        $fastCheck = ($fastCheck == "true" || $fastCheck == true);
        if(isSet($this->registerForSave["rights"]) || $this->create){
            AJXP_Utils::saveSerialFile($this->getStoragePath()."/rights.ser", $this->rights, !$fastCheck);
        }
        if(isSet($this->registerForSave["prefs"])){
            AJXP_Utils::saveSerialFile($this->getStoragePath()."/prefs.ser", $this->prefs, !$fastCheck);
        }
        if(isSet($this->registerForSave["bookmarks"])){
            AJXP_Utils::saveSerialFile($this->getStoragePath()."/bookmarks.ser", $this->bookmarks, !$fastCheck);
        }
        $this->registerForSave = array();
    }
	
	function getTemporaryData($key){
        $fastCheck = $this->storage->getOption("FAST_CHECKS");
        $fastCheck = ($fastCheck == "true" || $fastCheck == true);
        return AJXP_Utils::loadSerialFile($this->getStoragePath()."/".$key.".ser",$fastCheck);
	}
	
	function saveTemporaryData($key, $value){
        $fastCheck = $this->storage->getOption("FAST_CHECKS");
        $fastCheck = ($fastCheck == "true" || $fastCheck == true);
        return AJXP_Utils::saveSerialFile($this->getStoragePath()."/".$key.".ser", $value, !$fastCheck);
	}
	
	/**
	 * Static function for deleting a user
	 * 
	 * @param String $userId
	 * @param Array $deletedSubUsers
	 */
	static function deleteUser($userId, &$deletedSubUsers){
		$storage = ConfService::getConfStorageImpl();
        $user = ConfService::getConfStorageImpl()->createUserObject($userId);
        $files = glob($user->getStoragePath()."/*.ser");
		if(is_array($files) && count($files)){
			foreach ($files as $file){
				unlink($file);
			}
		}
		if(is_dir($user->getStoragePath())) rmdir($user->getStoragePath());
		
		$authDriver = ConfService::getAuthDriverImpl();
		$confDriver = ConfService::getConfStorageImpl();		
		$users = $authDriver->listUsers();
		foreach (array_keys($users) as $id){
			$object = $confDriver->createUserObject($id);
			if($object->hasParent() && $object->getParent() == $userId){
				AJXP_User::deleteUser($id, $deletedSubUsers);
				$deletedSubUsers[] = $id;
			}
		}

        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($user->storage->getOption("USERS_DIRPATH"))."/groups.ser");
        if(isSet($groups[$userId])){
            unset($groups[$userId]);
            AJXP_Utils::saveSerialFile(AJXP_VarsFilter::filter($user->storage->getOption("USERS_DIRPATH"))."/groups.ser", $groups);

        }

    }

}


?>
