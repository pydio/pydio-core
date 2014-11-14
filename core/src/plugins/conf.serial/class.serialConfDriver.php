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
 * Implementation of the configuration driver on serial files
 * @package AjaXplorer_Plugins
 * @subpackage Conf
 */
class serialConfDriver extends AbstractConfDriver
{
    public $repoSerialFile;
    public $usersSerialDir;
    public $rolesSerialFile;

    public $aliasesIndexFile;
    public $pluginsConfigsFile;

    public function init($options)
    {
        parent::init($options);
        $this->repoSerialFile = AJXP_VarsFilter::filter($this->options["REPOSITORIES_FILEPATH"]);
        $this->usersSerialDir = AJXP_VarsFilter::filter($this->options["USERS_DIRPATH"]);
        $this->rolesSerialFile = AJXP_VarsFilter::filter($this->options["ROLES_FILEPATH"]);
        $this->aliasesIndexFile = dirname($this->repoSerialFile)."/aliases.ser";
        $this->pluginsConfigsFile = dirname($this->repoSerialFile)."/plugins_configs.ser";
    }

    public function performChecks()
    {
        if(!isSet($this->options)) return;
        if(isSet($this->options["FAST_CHECKS"]) && $this->options["FAST_CHECKS"] === true) return;
        $this->performSerialFileCheck($this->repoSerialFile, "repositories file");
        $this->performSerialFileCheck($this->usersSerialDir, "users file", true);
        $this->performSerialFileCheck($this->rolesSerialFile, "roles file");
    }

    public function performSerialFileCheck($file, $fileLabel, $isDir = false)
    {
        // Try to replace start path to avoid seeing full paths on screen
        if ($isDir) {
            if (!is_dir($file) || !is_writable($file)) {
                $file = str_replace(AJXP_INSTALL_PATH, "", $file);
                throw new Exception("Folder $file for storing $fileLabel is either inexistent or not writeable.");
            }
            return ;
        }
        $dir = dirname($file);
        if (!is_dir($dir) || !is_writable($dir)) {
            $dir = str_replace(AJXP_INSTALL_PATH, "", $dir);
            throw new Exception("Folder $dir for $fileLabel is either inexistent or not writeable.");
        }
        if (is_file($file) && !is_writable($file)) {
            $file = str_replace(AJXP_INSTALL_PATH, "", $file);
            throw new Exception(ucfirst($fileLabel)." ($file) exists but is not writeable!");
        }
    }

    // SAVE / LOAD PLUGINS CONF
    public function _loadPluginConfig($pluginId, &$options)
    {
        $data = AJXP_Utils::loadSerialFile($this->pluginsConfigsFile);
        if (isSet($data[$pluginId]) && is_array($data[$pluginId])) {
            foreach ($data[$pluginId] as $key => $value) {
                if(isSet($options[$key])) continue;
                if (is_string($value)) {
                    if (strpos($value, "\\n")) {
                        $value = str_replace("\\n", "\n", $value);
                    }
                    if (strpos($value, "\\r")) {
                        $value = str_replace("\\r", "\r", $value);
                    }
                }
                $options[$key] = $value;
            }
        }
    }

    public function _savePluginConfig($pluginId, $options)
    {
        $data = AJXP_Utils::loadSerialFile($this->pluginsConfigsFile);
        foreach ($options as $k=>$v) {
            if (is_string($v)) {
                $options[$k] = addcslashes($v, "\r\n");
            }
        }
        $data[$pluginId] = $options;
        AJXP_Utils::saveSerialFile($this->pluginsConfigsFile, $data);
    }

    // SAVE / EDIT / CREATE / DELETE REPOSITORY
    /**
     * @param AbstractAjxpUser $user
     * @return Array
     */
    public function listRepositories($user = null)
    {
        $all = AJXP_Utils::loadSerialFile($this->repoSerialFile);
        if ($user != null) {
            foreach ($all as $repoId => $repoObject) {
                if (!ConfService::repositoryIsAccessible($repoId, $repoObject, $user)) {
                    unset($all[$repoId]);
                }
            }
        }
        return $all;
    }

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param Array $criteria
     * @return Array
     */
    public function listRepositoriesWithCriteria($criteria){

        $all = AJXP_Utils::loadSerialFile($this->repoSerialFile);
        if ($criteria != null) {
            return ConfService::filterRepositoryListWithCriteria($all, $criteria);
        }else{
            return $all;
        }

    }


    public function listRoles($roleIds = array(), $excludeReserved = false)
    {
        $all = AJXP_Utils::loadSerialFile($this->rolesSerialFile);
        $result = array();
        if (count($roleIds)) {
            foreach ($roleIds as $id) {
                if (isSet($all[$id]) && !($excludeReserved && strpos($id,"AJXP_") === 0)) {
                    $result[$id] = $all[$id];
                }
            }
        } else {
            foreach ($all as $id => $role) {
                if($excludeReserved && strpos($id,"AJXP_") === 0) continue;
                $result[$id] = $role;
            }
        }
        return $result;
    }

    public function saveRoles($roles)
    {
        AJXP_Utils::saveSerialFile($this->rolesSerialFile, $roles);
    }

    /**
     * @param AJXP_Role $role
     * @param AbstractAjxpUser|null $userObject
     */
    public function updateRole($role, $userObject = null)
    {
        if ($userObject != null) {
            // This a personal role, save differently
            $userObject->personalRole = $role;
            $userObject->save("superuser");
        } else {
            $all = AJXP_Utils::loadSerialFile($this->rolesSerialFile);
            $all[$role->getId()] = $role;
            AJXP_Utils::saveSerialFile($this->rolesSerialFile, $all);
        }
    }

    public function deleteRole($role)
    {
        // Mixed input Object or ID
        if(is_a($role, "AJXP_Role")) $roleId = $role->getId();
        else $roleId = $role;

        $all = AJXP_Utils::loadSerialFile($this->rolesSerialFile);
        if(isSet($all[$roleId])) unset($all[$roleId]);
        AJXP_Utils::saveSerialFile($this->rolesSerialFile, $all);
    }

    public function countAdminUsers()
    {
        $confDriver = ConfService::getConfStorageImpl();
        $authDriver = ConfService::getAuthDriverImpl();
        $count = 0;
        $users = $authDriver->listUsers();
        foreach (array_keys($users) as $userId) {
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
    public function getRepositoryById($repositoryId)
    {
        $repositories = AJXP_Utils::loadSerialFile($this->repoSerialFile);
        if (isSet($repositories[$repositoryId])) {
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
    public function getRepositoryByAlias($repositorySlug)
    {
        $data = AJXP_Utils::loadSerialFile($this->aliasesIndexFile);
        if (isSet($data[$repositorySlug])) {
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
    public function saveRepository($repositoryObject, $update = false)
    {
        $repositories = AJXP_Utils::loadSerialFile($this->repoSerialFile);
        if (!$update) {
            $repositoryObject->writeable = true;
            $repositories[$repositoryObject->getUniqueId()] = $repositoryObject;
        } else {
            foreach ($repositories as $index => $repo) {
                if ($repo->getUniqueId() == $repositoryObject->getUniqueId()) {
                    $repositories[$index] = $repositoryObject;
                    break;
                }
            }
        }
        try {
            AJXP_Utils::saveSerialFile($this->repoSerialFile, $repositories);
        } catch (Exception $e) {
            return -1;
        }
        $this->updateAliasesIndex($repositoryObject->getUniqueId(), $repositoryObject->getSlug());
    }
    /**
     * Delete a repository, given its unique ID.
     *
     * @param String $repositoryId
     */
    public function deleteRepository($repositoryId)
    {
        $repositories = AJXP_Utils::loadSerialFile($this->repoSerialFile);
        $newList = array();
        foreach ($repositories as $repo) {
            if ($repo->getUniqueId() != $repositoryId) {
                $newList[$repo->getUniqueId()] = $repo;
            }
        }
        AJXP_Utils::saveSerialFile($this->repoSerialFile, $newList);
        $this->updateAliasesIndex($repositoryId, null);
        $us = $this->getUsersForRepository($repositoryId);
        foreach ($us as $user) {
            $user->personalRole->setAcl($repositoryId, "");
            $user->save("superuser");
        }
    }
    /**
     * Serial specific method : indexes repositories by slugs, for better performances
     */
    public function updateAliasesIndex($repositoryId, $repositorySlug)
    {
        $data = AJXP_Utils::loadSerialFile($this->aliasesIndexFile);
        $byId = array_flip($data);
        if ($repositorySlug == null) {
            if (isSet($byId[$repositoryId])) {
                unset($byId[$repositoryId]);
                AJXP_Utils::saveSerialFile($this->aliasesIndexFile, array_flip($byId));
            }
        } else {
            $byId[$repositoryId] = $repositorySlug;
            AJXP_Utils::saveSerialFile($this->aliasesIndexFile, array_flip($byId));
        }
    }

    /**
     * @abstract
     * @param $userId
     * @return array()
     */
    public function getUserChildren($userId)
    {
        $result = array();
        $authDriver = ConfService::getAuthDriverImpl();
        $confDriver = ConfService::getConfStorageImpl();
        $parent = $confDriver->createUserObject($userId);
        $pointer = $parent->getChildrenPointer(); // SERIAL USER SPECIFIC METHOD
        if (!is_array($pointer)) { // UPDATE FIRST TIME
            $users = $authDriver->listUsers();
            $pointer = array();
            foreach (array_keys($users) as $id) {
                $object = $confDriver->createUserObject($id);
                if ($object->hasParent() && $object->getParent() == $userId) {
                    $result[] = $object;
                    $pointer[$object->getId()] = $object->getId();
                }
            }
            $parent->setChildrenPointer($pointer);
            $parent->save("superuser");
        } else {
            foreach ($pointer as $childId) {
                if (!AuthService::userExists($childId)) {
                    $clean = true;
                    unset($pointer[$childId]);
                    continue;
                }
                $object = $confDriver->createUserObject($childId);
                if ($object->hasParent() && $object->getParent() == $userId) {
                    $result[] = $object;
                }
            }
            if ($clean) {
                $parent->setChildrenPointer($pointer);
                $parent->save("superuser");
            }
        }

        return $result;
    }

    /**
     * @param string $repositoryId
     * @return AbstractAjxpUser[]
     */
    public function getUsersForRepository($repositoryId)
    {
        $result = array();
        $authDriver = ConfService::getAuthDriverImpl();
        $confDriver = ConfService::getConfStorageImpl();
        $users = $authDriver->listUsers(AuthService::filterBaseGroup("/"));
        foreach (array_keys($users) as $id) {
            $object = $confDriver->createUserObject($id);
            if ($object->canSwitchTo($repositoryId)) {
                $result[$id] = $object;
            }
        }
        return $result;
    }

    /**
     * @abstract
     * @param string $repositoryId
     * @param string $rolePrefix
     * @param bool $countOnly
     * @return array()
     */
    public function getRolesForRepository($repositoryId, $rolePrefix = '', $countOnly = false){
        return array();
    }

    /**
     * @param string $repositoryId
     * @param boolean $details
     * @return array('internal' => count, 'external' => count)
     */
    public function countUsersForRepository($repositoryId, $details = false){
        $c = count($this->getUsersForRepository($repositoryId));
        if($details) return array("internal" => $c);
        else return $c;
    }


    public function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false)
    {
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser");
        foreach ($flatUsersList as $userid => $userdata) {
            if (is_array($groups) && array_key_exists($userid, $groups)) {
                $path = $groups[$userid];
                if (substr($path, 0, strlen($baseGroup)) != $baseGroup) {
                    unset($flatUsersList[$userid]);
                } else if (strlen($path) > strlen($baseGroup)) {
                    if(!$fullTree) unset($flatUsersList[$userid]);
                }
            } else {
                if ($baseGroup != "/") {
                    unset($flatUsersList[$userid]);
                }
            }
        }
    }

    public function getChildrenGroups($baseGroup = "/")
    {
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser");
        $levelGroups = array();
        $labels = array();
        asort($groups);
        foreach ($groups as $id => $path) {
            $testGroup = $baseGroup;
            if($baseGroup != "/") $testGroup .= "/";
            if (substr($path, 0, strlen($testGroup)) == $testGroup && strlen($path) >  strlen($testGroup)) {
                $parts = explode("/", ltrim(substr($path, strlen($baseGroup)), "/"));
                $sub = "/".array_shift($parts);
                if(!isset($levelGroups[$sub])) $levelGroups[$sub] = $path;
                if (substr($id, 0, strlen("AJXP_GROUP:")) == "AJXP_GROUP:") {
                    $labels[$path] = array_pop(explode(":", $id, 2));
                }
            }
        }
        foreach ($levelGroups as $gId => $grPath) {
            if(isSet($labels[$grPath])) $levelGroups[$gId] = $labels[$grPath];
            else $levelGroups[$gId] = $gId;
        }
        return $levelGroups;
    }

    public function createGroup($groupPath, $groupLabel)
    {
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser");
        $reverse = array_flip($groups);
        if (isSet($reverse[$groupPath])) {
            $oldLabel = $reverse[$groupPath];
            unset($groups[$oldLabel]);
            $groups["AJXP_GROUP:$groupLabel"] = $groupPath;
        } else {
            $groups["AJXP_GROUP:$groupLabel"] = $groupPath;
        }
        AJXP_Utils::saveSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser", $groups);
    }

    public function relabelGroup($groupPath, $groupLabel)
    {
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser");
        $reverse = array_flip($groups);
        if (isSet($reverse[$groupPath])) {
            $oldLabel = $reverse[$groupPath];
            unset($groups[$oldLabel]);
            $groups["AJXP_GROUP:$groupLabel"] = $groupPath;
            AJXP_Utils::saveSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser", $groups);
        }
    }

    public function deleteGroup($groupPath)
    {
        $gUsers = AuthService::listUsers($groupPath);
        $gGroups = AuthService::listChildrenGroups($groupPath);
        if (count($gUsers) || count($gGroups)) {
            throw new Exception("Group is not empty, please do something with its content before trying to delete it!");
        }
        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser");
        foreach ($groups as $key => $value) {
            if($value == $groupPath) unset($groups[$key]);
        }
        AJXP_Utils::saveSerialFile(AJXP_VarsFilter::filter($this->getOption("USERS_DIRPATH"))."/groups.ser", $groups);
    }

    /**
     * Instantiate the right class
     *
     * @param String $userId
     * @return AbstractAjxpUser
     */
    public function instantiateAbstractUserImpl($userId)
    {
        return new AJXP_SerialUser($userId, $this);
    }

    public function getUserClassFileName()
    {
        return AJXP_INSTALL_PATH."/plugins/conf.serial/class.AJXP_SerialUser.php";
    }

    /**
     * Function for deleting a user
     *
     * @param String $userId
     * @param Array $deletedSubUsers
     */
    public function deleteUser($userId, &$deletedSubUsers)
    {
        $user = $this->createUserObject($userId);
        $files = glob($user->getStoragePath()."/*.ser");
        if (is_array($files) && count($files)) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($user->getStoragePath())) {
            rmdir($user->getStoragePath());
        }

        // DELETE CHILDREN USING POINTER IF POSSIBLE
        $users = $this->getUserChildren($userId); // $authDriver->listUsers();
        foreach (array_keys($users) as $id) {
            $object = $this->createUserObject($id);
            if ($object->hasParent() && $object->getParent() == $userId) {
                $this->deleteUser($id, $deletedSubUsers);
                $deletedSubUsers[] = $id;
            }
        }


        // CLEAR PARENT POINTER IF NECESSARY
        if ($user->hasParent()) {
            $parentObject = $this->createUserObject($user->getParent());
            $pointer = $parentObject->getChildrenPointer();
            if ($pointer !== null) {
                unset($pointer[$userId]);
                $parentObject->setChildrenPointer($pointer);
                $parentObject->save("superuser");
                if (AuthService::getLoggedUser() != null && AuthService::getLoggedUser()->getId() == $parentObject->getId()) {
                    AuthService::updateUser($parentObject);
                }
            }
        }

        $groups = AJXP_Utils::loadSerialFile(AJXP_VarsFilter::filter($user->storage->getOption("USERS_DIRPATH"))."/groups.ser");
        if (isSet($groups[$userId])) {
            unset($groups[$userId]);
            AJXP_Utils::saveSerialFile(AJXP_VarsFilter::filter($user->storage->getOption("USERS_DIRPATH"))."/groups.ser", $groups);

        }
    }

    protected function getBinaryPathStorage($context)
    {
        $storage = $this->getPluginWorkDir()."/binaries";
        if (isSet($context["USER"])) {
            $storage.="/users/".$context["USER"];
        } else if (isSet($context["REPO"])) {
            $storage.="/repos/".$context["REPO"];
        } else if (isSet($context["ROLE"])) {
            $storage.="/roles/".$context["ROLE"];
        } else if (isSet($context["PLUGIN"])) {
            $storage.="/plugins/".$context["PLUGIN"];
        }
        if (!isSet($this->options["FAST_CHECKS"]) || $this->options["FAST_CHECKS"] !== true) {
            if(!is_dir($storage)) @mkdir($storage, 0755, true);
        }
        return $storage;
    }

    /**
     * @param array $context
     * @param String $fileName
     * @param String $ID
     * @return String $ID
     */
    public function saveBinary($context, $fileName, $ID = null)
    {
        if (empty($ID)) {
            $ID = substr(md5(microtime()*rand(0,100)), 0, 12);
            $ID .= ".".pathinfo($fileName, PATHINFO_EXTENSION);
        }
        copy($fileName, $this->getBinaryPathStorage($context)."/".$ID);

        return $ID;
    }

    /**
     * @abstract
     * @param array $context
     * @param String $ID
     * @return boolean
     */
    public function deleteBinary($context, $ID)
    {
        if (is_file($this->getBinaryPathStorage($context)."/".$ID)) {
            unlink($this->getBinaryPathStorage($context)."/".$ID);
        }
    }

    /**
     * @param array $context
     * @param String $ID
     * @param Resource $outputStream
     * @return boolean
     */
    public function loadBinary($context, $ID, $outputStream = null)
    {
        $fileName = $this->getBinaryPathStorage($context)."/".$ID;
        if (is_file($fileName)) {
            if ($outputStream == null) {
                header("Content-Type: ".AJXP_Utils::getImageMimeType($ID));
                // PROBLEM AT STARTUP
                header('Pragma:');
                header('Cache-Control: public');
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", filemtime($fileName)) . " GMT");
                header("Expires: " . gmdate("D, d M Y H:i:s", filemtime($fileName)+5*24*3600) . " GMT");
                readfile($fileName);
            } else if (is_resource($outputStream)) {
                fwrite($outputStream, file_get_contents($this->getBinaryPathStorage($context)."/".$ID));
            }
        }
    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @param String $userId
     * @param Array $data
     * @return boolean
     */
    public function saveTemporaryKey($keyType, $keyId, $userId, $data)
    {
        $storage = $this->getPluginWorkDir()."/temporary_keys";
        $list = AJXP_Utils::loadSerialFile($storage, false, "ser");
        if(isSEt($list[$keyType])) $list[$keyType] = array();
        $data["user_id"] = $userId;
        $data["date"] = time();
        $list[$keyType][$keyId] = $data;
        AJXP_Utils::saveSerialFile($storage, $list);
    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @return array
     */
    public function loadTemporaryKey($keyType, $keyId)
    {
        $storage = $this->getPluginWorkDir()."/temporary_keys";
        $list = AJXP_Utils::loadSerialFile($storage, false, "ser");
        if (iSset($list[$keyType]) && iSset($list[$keyType][$keyId])) {
            return $list[$keyType][$keyId];
        } else {
            return null;
        }

    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @return boolean
     */
    public function deleteTemporaryKey($keyType, $keyId)
    {
        $storage = $this->getPluginWorkDir()."/temporary_keys";
        $list = AJXP_Utils::loadSerialFile($storage, false, "ser");
        if (iSset($list[$keyType]) && iSset($list[$keyType][$keyId])) {
            unset($list[$keyType][$keyId]);
            if (count($list[$keyType]) == 0) {
                unset($list[$keyType]);
            }
        }
        AJXP_Utils::saveSerialFile($storage, $list);
        return true;
    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $expiration
     * @return null
     */
    public function pruneTemporaryKeys($keyType, $expiration)
    {
        $storage = $this->getPluginWorkDir()."/temporary_keys";
        $list = AJXP_Utils::loadSerialFile($storage, false, "ser");
        foreach ($list as $type => &$keys) {
            foreach ($keys as $key => $data) {
                if ($data["date"] < time() - $expiration*60) {
                    unset($keys[$key]);
                }
            }
            if (count($keys) == 0) {
                unset($list[$type]);
            }
        }
        AJXP_Utils::saveSerialFile($storage, $list);
    }


}
