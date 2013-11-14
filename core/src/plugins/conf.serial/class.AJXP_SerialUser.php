<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Implementation of the AbstractUser for serial
 * @package AjaXplorer_Plugins
 * @subpackage Conf
 */
class AJXP_SerialUser extends AbstractAjxpUser
{
    public $id;
    public $hasAdmin = false;
    public $rights;
    public $prefs;
    public $bookmarks;
    public $version;

    /**
     * Conf Storage implementation
     *
     * @var AbstractConfDriver
     */
    public $storage;
    public $registerForSave = array();
    public $create = true;

    public $childrenPointer = null;

    /**
     * @param $id
     * @param serialConfDriver $storage
     */
    public function AJXP_SerialUser($id, $storage=null)
    {
        parent::AbstractAjxpUser($id, $storage);
        $this->registerForSave = array();
    }

    public function setGroupPath($groupPath, $update = false)
    {
        if ($update && isSet($this->groupPath) && $this->groupPath != $groupPath) {
            $children = $this->getChildrenPointer();
            if (is_array($children)) {
                foreach ($children as $userId) {
                    // UPDATE USER GROUP AND ROLES
                    $u = ConfService::getConfStorageImpl()->createUserObject($userId);
                    $u->setGroupPath($groupPath);
                    $r = $u->getRoles();
                    // REMOVE OLD GROUP ROLES
                    foreach (array_keys($r) as $role) {
                        if(strpos($role, "AJXP_GRP_/") === 0) $u->removeRole($role);
                    }
                    $u->recomputeMergedRole();
                    $u->save("superuser");
                }
            }
        }
        parent::setGroupPath($groupPath);
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/groups.ser");
        $groups[$this->getId()] = $groupPath;
        AJXP_Utils::saveSerialFile(AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/groups.ser", $groups);
    }

    public function __wakeup()
    {
        $this->registerForSave = array();
    }

    public function getStoragePath()
    {
        $subDir = trim($this->getGroupPath(), "/");
        $id = $this->getId();
        if(AuthService::ignoreUserCase()) $id = strtolower($id);
        $res = AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/".(empty($subDir)?"":$subDir."/").$id;
        return $res;
    }

    public function storageExists()
    {
        return is_dir($this->getStoragePath());
    }

    public function load()
    {
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->storage->getOption("USERS_DIRPATH"))."/groups.ser");
        if(isSet($groups[$this->getId()])) $this->groupPath = $groups[$this->getId()];

        $this->create = false;
        $this->rights = AJXP_Utils::loadSerialFile($this->getStoragePath()."/rights.ser");
        if(count($this->rights) == 0) $this->create = true;
        $this->prefs = AJXP_Utils::loadSerialFile($this->getStoragePath()."/prefs.ser");
        $this->bookmarks = AJXP_Utils::loadSerialFile($this->getStoragePath()."/bookmarks.ser");
        if (isSet($this->rights["ajxp.admin"]) && $this->rights["ajxp.admin"] === true) {
            $this->setAdmin(true);
        }
        if (isSet($this->rights["ajxp.parent_user"])) {
            //$this->setParent($this->rights["ajxp.parent_user"]);
            parent::setParent($this->rights["ajxp.parent_user"]);
        }
        if (isSet($this->rights["ajxp.group_path"])) {
            $this->setGroupPath($this->rights["ajxp.group_path"]);
        }
        if (isSet($this->rights["ajxp.children_pointer"])) {
            $this->childrenPointer = $this->rights["ajxp.children_pointer"];
        }

        // LOAD ROLES
        $rolesToLoad = array();
        if (isSet($this->rights["ajxp.roles"])) {
            $rolesToLoad = array_keys($this->rights["ajxp.roles"]);
        }
        if ($this->groupPath != null) {
            $base = "";
            $exp = explode("/", $this->groupPath);
            foreach ($exp as $pathPart) {
                if(empty($pathPart)) continue;
                $base = $base . "/" . $pathPart;
                $rolesToLoad[] = "AJXP_GRP_".$base;
            }
        }
        // Load roles
        if (count($rolesToLoad)) {
            $allRoles = AuthService::getRolesList($rolesToLoad);
            foreach ($rolesToLoad as $roleId) {
                if (isSet($allRoles[$roleId])) {
                    $this->roles[$roleId] = $allRoles[$roleId];
                    $this->rights["ajxp.roles"][$roleId] = true;
                } else if (is_array($this->rights["ajxp.roles"]) && isSet($this->rights["ajxp.roles"][$roleId])) {
                    unset($this->rights["ajxp.roles"][$roleId]);
                }
            }
        }

        // LOAD USR ROLE LOCALLY
        $personalRole = AJXP_Utils::loadSerialFile($this->getStoragePath()."/role.ser");
        if (is_a($personalRole, "AJXP_Role")) {
            $this->personalRole = $personalRole;
            $this->roles["AJXP_USR_"."/".$this->id] = $personalRole;
        } else {
            // MIGRATE NOW !
            $changes = $this->migrateRightsToPersonalRole();
            if ($changes) {
                AJXP_Utils::saveSerialFile($this->getStoragePath()."/role.ser", $this->personalRole, true);
                AJXP_Utils::saveSerialFile($this->getStoragePath()."/rights.ser", $this->rights, true);
            }
        }

        $this->recomputeMergedRole();
    }

    public function save($context = "superuser")
    {
        if ($this->isAdmin() === true) {
            $this->rights["ajxp.admin"] = true;
        } else {
            $this->rights["ajxp.admin"] = false;
        }
        if ($this->hasParent()) {
            $this->rights["ajxp.parent_user"] = $this->parentUser;
        }
        if (isSet($this->childrenPointer)) {
            $this->rights["ajxp.children_pointer"] = $this->childrenPointer;
        }
        $this->rights["ajxp.group_path"] = $this->getGroupPath();

        if ($context == "superuser") {
            $this->registerForSave["rights"] = true;
        }
        $this->registerForSave["prefs"] = true;
        $this->registerForSave["bookmarks"] = true;
    }

    public function __destruct()
    {
        if(count($this->registerForSave)==0) return;
        $fastCheck = $this->storage->getOption("FAST_CHECKS");
        $fastCheck = ($fastCheck == "true" || $fastCheck == true);
        if (isSet($this->registerForSave["rights"]) || $this->create) {
            $filteredRights = $this->rights;
            if(isSet($filteredRights["ajxp.roles"])) $filteredRights["ajxp.roles"] = $this->filterRolesForSaving($filteredRights["ajxp.roles"]);
            AJXP_Utils::saveSerialFile($this->getStoragePath()."/rights.ser", $this->rights, !$fastCheck);
            AJXP_Utils::saveSerialFile($this->getStoragePath()."/role.ser", $this->personalRole, !$fastCheck);
        }
        if (isSet($this->registerForSave["prefs"])) {
            AJXP_Utils::saveSerialFile($this->getStoragePath()."/prefs.ser", $this->prefs, !$fastCheck);
        }
        if (isSet($this->registerForSave["bookmarks"])) {
            AJXP_Utils::saveSerialFile($this->getStoragePath()."/bookmarks.ser", $this->bookmarks, !$fastCheck);
        }
        $this->registerForSave = array();
    }

    public function getTemporaryData($key)
    {
        $fastCheck = $this->storage->getOption("FAST_CHECKS");
        $fastCheck = ($fastCheck == "true" || $fastCheck == true);
        return AJXP_Utils::loadSerialFile($this->getStoragePath()."/".$key.".ser",$fastCheck);
    }

    public function saveTemporaryData($key, $value)
    {
        $fastCheck = $this->storage->getOption("FAST_CHECKS");
        $fastCheck = ($fastCheck == "true" || $fastCheck == true);
        return AJXP_Utils::saveSerialFile($this->getStoragePath()."/".$key.".ser", $value, !$fastCheck);
    }

    /**
     * Override parent method to keep a reference to the child users
     * @param $parentId
     */
    public function setParent($parentId)
    {
        $u = ConfService::getConfStorageImpl()->createUserObject($parentId);
        $p = $u->getChildrenPointer();
        if($p == null) $p = array();
        $p[$this->getId()] = $this->getId();
        $u->setChildrenPointer($p);
        $u->save("superuser");
        if (AuthService::getLoggedUser() != null && AuthService::getLoggedUser()->getId() == $parentId) {
            AuthService::updateUser($u);
        }
        parent::setParent($parentId);
    }

    /**
     * @return null|Array
     */
    public function getChildrenPointer()
    {
        return $this->childrenPointer;
    }

    /**
     * @param Array $array
     */
    public function setChildrenPointer($array)
    {
        $this->childrenPointer = $array;
    }

}
