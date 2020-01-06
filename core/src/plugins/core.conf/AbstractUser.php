<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Conf\Core;

use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;


defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Abstract class to be extended by conf.* implementations
 * @package Pydio\Conf\Core
 */
abstract class AbstractUser implements UserInterface
{
    /**
     * @var string
     */
    public $id;
    public $hasAdmin = false;
    public $rights;
    /**
     * @var AJXP_Role[]
     */
    public $roles;
    public $prefs;
    public $bookmarks;
    public $version;
    /**
     * @var string
     */
    public $parentUser;
    /**
     * @var bool
     */
    protected $hidden;

    /**
     * Set user as "hidden" ( = shared )
     * @param bool $hidden
     */
    public function setHidden($hidden)
    {
        $this->hidden = $hidden;
    }

    /**
     * Check if user is hidden
     * @return bool
     */
    public function isHidden()
    {
        return $this->hidden;
    }

    public $groupPath = "/";
    /**
     * @var AJXP_Role
     */
    public $mergedRole;

    /**
     * @var AJXP_Role
     */
    public $parentRole;

    /**
     * @var AJXP_Role Accessible for update
     */
    public $personalRole;

    /**
     * Conf Storage implementation
     *
     * @var AbstractConfDriver
     */
    public $storage;

    /**
     * AbstractUser constructor.
     * @param $id
     * @param null $storage
     */
    public function __construct($id, $storage=null)
    {
        $this->id = $id;
        if ($storage == null) {
            $storage = ConfService::getConfStorageImpl();
        }
        $this->storage = $storage;
        $this->load();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id ;
    }

    /**
     *
     */
    public function storageExists(){
    }

    /**
     * @param AJXP_Role $roleObject
     * @throws \Exception
     */
    public function addRole($roleObject)
    {
        if (isSet($this->roles[$roleObject->getId()])) {
            // Role may have been updated, but ajxp.roles does not need reload.
            $this->roles[$roleObject->getId()] = $roleObject;
            $this->recomputeMergedRole();
            return;
        }
        if(!isSet($this->rights["ajxp.roles"])) $this->rights["ajxp.roles"] = array();
        $this->rights["ajxp.roles"][$roleObject->getId()] = true;
        if(!isSet($this->rights["ajxp.roles.order"])){
            $this->rights["ajxp.roles.order"] = array();
        }
        $this->rights["ajxp.roles.order"][$roleObject->getId()] = count($this->rights["ajxp.roles"]);
        if($roleObject->alwaysOverrides()){
            if(!isSet($this->rights["ajxp.roles.sticky"])){
                $this->rights["ajxp.roles.sticky"] = array();
            }
            $this->rights["ajxp.roles.sticky"][$roleObject->getId()] = true;
        }
        uksort($this->rights["ajxp.roles"], array($this, "orderRoles"));
        $this->roles[$roleObject->getId()] = $roleObject;
        $this->recomputeMergedRole();
    }

    /**
     * @param string $roleId
     * @throws \Exception
     */
    public function removeRole($roleId)
    {
        if (isSet($this->rights["ajxp.roles"]) && isSet($this->rights["ajxp.roles"][$roleId])) {
            unset($this->rights["ajxp.roles"][$roleId]);
            if(isSet($this->roles[$roleId])) unset($this->roles[$roleId]);
            if(isSet($this->rights["ajxp.roles.sticky"]) && isSet($this->rights["ajxp.roles.sticky"][$roleId])){
                unset($this->rights["ajxp.roles.sticky"][$roleId]);
            }
            if(isset($this->rights["ajxp.roles.order"]) && isset($this->rights["ajxp.roles.order"][$roleId])){
                $previousPos = $this->rights["ajxp.roles.order"][$roleId];
                $ordered = array_flip($this->rights["ajxp.roles.order"]);
                ksort($ordered);
                unset($ordered[$previousPos]);
                $reordered = array();
                $p = 0;
                foreach($ordered as $id) {
                    $reordered[$id] = $p;
                    $p++;
                }
                $this->rights["ajxp.roles.order"] = $reordered;
            }
            uksort($this->rights["ajxp.roles"], array($this, "orderRoles"));
        }
        $this->recomputeMergedRole();
    }

    /**
     * @param array $orderedRolesIds
     */
    public function updateRolesOrder($orderedRolesIds){
        // check content
        $saveRoleOrders = array();
        foreach($orderedRolesIds as $position => $rId){
            if(isSet($this->rights["ajxp.roles"][$rId])) $saveRoleOrders[$rId] = $position;
        }
        $this->rights["ajxp.roles.order"] = $saveRoleOrders;
    }

    /**
     * @return AJXP_Role[]
     */
    public function getRoles()
    {
        if (isSet($this->roles)) {
            uksort($this->roles, array($this, "orderRoles"));
            return $this->roles;
        } else {
            return array();
        }
    }

    /**
     * @return string
     */
    public function getProfile()
    {
        if (isSet($this->rights["ajxp.profile"])) {
            return $this->rights["ajxp.profile"];
        }
        if($this->isAdmin()) return "admin";
        if($this->hasParent()) return "shared";
        if($this->getId() == "guest") return "guest";
        return "standard";
    }

    /**
     * @param string $profile
     */
    public function setProfile($profile)
    {
        $this->rights["ajxp.profile"] = $profile;
        RolesService::updateAutoApplyRole($this);
    }

    /**
     * @param string $lockAction
     * @throws \Exception
     */
    public function setLock($lockAction)
    {
        $sLock = $this->getLock();
        $currentLocks = !empty($sLock) ? explode(",", $sLock) : [] ;
        if(!in_array($lockAction, $currentLocks)){
            array_unshift($currentLocks, $lockAction);
        }
        $locks = implode(",", $currentLocks);
        $this->personalRole->setParameterValue('core.conf', 'USER_LOCK_ACTION', $locks);
        $this->recomputeMergedRole();
    }

    /**
     * @param $lockAction
     * @throws \Exception
     */
    public function removeLock($lockAction)
    {
        $sLock = $this->getLock();
        $currentLocks = !empty($sLock) ? explode(",", $sLock) : [] ;
        $pos = array_search($lockAction, $currentLocks);
        if($pos !== false){
            unset($currentLocks[$pos]);
        }
        $this->rights["ajxp.lock"] = !count($currentLocks) ? false: implode(",", $currentLocks);
        $newValue = !count($currentLocks) ? AJXP_VALUE_CLEAR : implode(",", $currentLocks);
        $this->personalRole->setParameterValue('core.conf', 'USER_LOCK_ACTION', $newValue);
        $this->recomputeMergedRole();
    }

    /**
     * @return bool|mixed
     */
    public function getLock()
    {
        if(AJXP_SERVER_DEBUG && $this->isAdmin() && $this->getGroupPath() === "/") return false;
        if (!empty($this->rights["ajxp.lock"]) && ($this->rights["ajxp.lock"] !== "false")) {
            return $this->rights["ajxp.lock"];
        }
        return $this->mergedRole->filterParameterValue('core.conf', 'USER_LOCK_ACTION', AJXP_REPO_SCOPE_ALL, false);
    }

    /**
     * @param $lockAction
     * @return string|false
     */
    public function hasLockByName($lockAction){
        $sLock = $this->getLock();
        $currentLocks = !empty($sLock) ? explode(",", $sLock) : [] ;
        return array_search($lockAction, $currentLocks) !== false;
    }


    /**
     * @return bool
     */
    public function isAdmin()
    {
        return $this->hasAdmin;
    }

    /**
     * @param bool $boolean
     */
    public function setAdmin($boolean)
    {
        $this->hasAdmin = $boolean;
    }

    /**
     * @return bool
     */
    public function hasParent()
    {
        return isSet($this->parentUser);
    }

    /**
     * @param string $user
     */
    public function setParent($user)
    {
        $this->parentUser = $user;
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return $this->parentUser;
    }

    /**
     * @param string $repositoryId
     * @return bool
     */
    public function canRead($repositoryId)
    {
        if($this->getLock() != false) {
            if(ApplicationState::getSapiRestBase() !== null && !$this->hasLockByName("logout")){
                return $this->mergedRole->canRead($repositoryId);
            }
            return false;
        }
        return $this->mergedRole->canRead($repositoryId);
    }

    /**
     * @param string $repositoryId
     * @return bool
     */
    public function canWrite($repositoryId)
    {
        if($this->getLock() != false) {
            if(ApplicationState::getSapiRestBase() !== null && !$this->hasLockByName("logout")){
                return $this->mergedRole->canWrite($repositoryId);
            }
            return false;
        }
        return $this->mergedRole->canWrite($repositoryId);
    }

    /**
     * @param RepositoryInterface|string $idOrObject
     * @return bool
     */
    public function canAccessRepository($idOrObject){
        if($idOrObject instanceof RepositoryInterface){
            $repository = RepositoryService::getRepositoryById($idOrObject);
            if(empty($repository)) return false;
        }else{
            $repository = $idOrObject;
        }
        return RepositoryService::repositoryIsAccessible($repository, $this, false, true);
    }

    /**
     * @param int $repositoryId
     * @return bool
     */
    public function canSwitchTo($repositoryId)
    {
        $repositoryObject = RepositoryService::getRepositoryById($repositoryId);
        if($repositoryObject == null) return false;
        return RepositoryService::repositoryIsAccessible($repositoryObject, $this, false, true);
    }

    /**
     * @param $prefName
     * @return mixed|string
     */
    public function getPref($prefName)
    {
        if ($prefName == "lang") {
            // Migration path
            if (isSet($this->mergedRole)) {
                $l = $this->mergedRole->filterParameterValue("core.conf", "lang", AJXP_REPO_SCOPE_ALL, "");
                if($l != "") return $l;
            }
        }
        if(isSet($this->prefs[$prefName])) return $this->prefs[$prefName];
        return "";
    }

    /**
     * @param $prefName
     * @param $prefValue
     */
    public function setPref($prefName, $prefValue)
    {
        $this->prefs[$prefName] = $prefValue;
    }

    /**
     * @param string $prefName
     * @param string $prefPath
     * @param mixed $prefValue
     */
    public function setArrayPref($prefName, $prefPath, $prefValue)
    {
        $data = $this->getPref($prefName);
        if(!is_array($data)){
            $data = array();
        }
        $data[$prefPath] = $prefValue;
        $this->setPref($prefName, $data);
    }

    /**
     * @param $prefName
     * @param $prefPath
     * @return mixed|string
     */
    public function getArrayPref($prefName, $prefPath)
    {
        $prefArray = $this->getPref($prefName);
        if(empty($prefArray) || !is_array($prefArray) || !isSet($prefArray[$prefPath])) return "";
        return $prefArray[$prefPath];
    }

    /**
     * @param $repositoryId
     * @param string $path
     * @param string $title
     */
    public function addBookmark($repositoryId, $path, $title)
    {
        if(!isSet($this->bookmarks)) $this->bookmarks = array();
        if(!isSet($this->bookmarks[$repositoryId])) $this->bookmarks[$repositoryId] = array();
        foreach ($this->bookmarks[$repositoryId] as $v) {
            $toCompare = "";
            if(is_string($v)) $toCompare = $v;
            else if(is_array($v)) $toCompare = $v["PATH"];
            if($toCompare == trim($path)) return ; // RETURN IF ALREADY HERE!
        }
        $this->bookmarks[$repositoryId][] = array("PATH"=>trim($path), "TITLE"=>$title);
    }

    /**
     * @param string $repositoryId
     * @param string $path
     */
    public function removeBookmark($repositoryId, $path)
    {
        if(isSet($this->bookmarks)
            && isSet($this->bookmarks[$repositoryId])
            && is_array($this->bookmarks[$repositoryId]))
            {
                foreach ($this->bookmarks[$repositoryId] as $k => $v) {
                    $toCompare = "";
                    if(is_string($v)) $toCompare = $v;
                    else if(is_array($v)) $toCompare = $v["PATH"];
                    if($toCompare == trim($path)) unset($this->bookmarks[$repositoryId][$k]);
                }
            }
    }

    /**
     * @param string $repositoryId
     * @param string $path
     * @param string $title
     */
    public function renameBookmark($repositoryId, $path, $title)
    {
        if(isSet($this->bookmarks)
            && isSet($this->bookmarks[$repositoryId])
            && is_array($this->bookmarks[$repositoryId]))
            {
                foreach ($this->bookmarks[$repositoryId] as $k => $v) {
                    $toCompare = "";
                    if(is_string($v)) $toCompare = $v;
                    else if(is_array($v)) $toCompare = $v["PATH"];
                    if ($toCompare == trim($path)) {
                         $this->bookmarks[$repositoryId][$k] = array("PATH"=>trim($path), "TITLE"=>$title);
                    }
                }
            }
    }

    /**
     * @param $repositoryId
     * @return array
     */
    public function getBookmarks($repositoryId)
    {
        if(isSet($this->bookmarks)
            && isSet($this->bookmarks[$repositoryId]))
            return $this->bookmarks[$repositoryId];
        return array();
    }

    /**
     * @return mixed
     */
    abstract public function load();

    /**
     * @param string $context
     */
    public function save($context = "superuser"){
        $this->_save($context);
        UsersService::updateUser($this, $context);
    }

    /**
     * @param string $context
     * @return mixed
     */
    abstract protected function _save($context = "superuser");

    /**
     * @param string $key
     * @return mixed
     */
    abstract public function getTemporaryData($key);

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    abstract public function saveTemporaryData($key, $value);

    /**
     * @param String $groupPath
     * @param bool $update
     */
    public function setGroupPath($groupPath, $update = false)
    {
        if(strlen($groupPath) > 1) $groupPath = rtrim($groupPath, "/");
        $this->groupPath = $groupPath;
    }

    /**
     * @return null|string
     */
    public function getGroupPath()
    {
        if(!isSet($this->groupPath)) return null;
        return $this->groupPath;
    }


    /**
     * Automatically set the group to the current user base
     * @param $baseGroup
     * @return string
     */
    public function getRealGroupPath($baseGroup)
    {
        // make sure it starts with a slash.
        $baseGroup = "/".ltrim($baseGroup, "/");
        $groupPath = $this->getGroupPath();
        if(empty($groupPath)) $groupPath = "/";
        if ($groupPath != "/") {
            if($baseGroup == "/") return $groupPath;
            else return $groupPath.$baseGroup;
        } else {
            return $baseGroup;
        }
    }

    /**
     * Check if the current user can administrate the GroupPathProvider object
     * @param IGroupPathProvider $provider
     * @return bool
     */
    public function canAdministrate(IGroupPathProvider $provider)
    {
        $pGP = $provider->getGroupPath();
        if(empty($pGP)) $pGP = "/";
        if($this->getGroupPath() == null) return true;
        return (strpos($pGP, $this->getGroupPath(), 0) === 0);
    }

    /**
     * Check if the current user can assign administration for the GroupPathProvider object
     * @param IGroupPathProvider $provider
     * @return bool
     */
    public function canSee(IGroupPathProvider $provider)
    {
        $pGP = $provider->getGroupPath();
        if(empty($pGP)) $pGP = "/";
        if($this->getGroupPath() == null || $pGP == null) return true;
        return (strpos($this->getGroupPath(), $pGP, 0) === 0);
    }

    public function recomputeMergedRole()
    {
        if (!count($this->roles)) {
            throw new \Exception("Empty role, this is not normal");
        }
        uksort($this->roles, array($this, "orderRoles"));
        $keys = array_keys($this->roles);
        $this->mergedRole =  clone $this->roles[array_shift($keys)];
        if (count($this->roles) > 1) {
            $this->parentRole = $this->mergedRole;
        }
        $index = 0;
        foreach ($this->roles as $role) {
            if ($index > 0) {
                $this->mergedRole = $role->override($this->mergedRole);
                if($index < count($this->roles) -1 ) $this->parentRole = $role->override($this->parentRole);
            }
            $index ++;
        }
        if ($this->hasParent() && isSet($this->parentRole)) {
            // It's a shared user, we don't want it to inherit the rights...
            $this->parentRole->clearAcls();
            //... but we want the parent user's role, filtered with inheritable properties only.
            $stretchedParentUserRole = RolesService::limitedRoleFromParent($this->parentUser);
            if ($stretchedParentUserRole !== null) {
                $this->parentRole = $stretchedParentUserRole->override($this->parentRole);  //$this->parentRole->override($stretchedParentUserRole);
                // REAPPLY SPECIFIC "SHARED" ROLES & "OWNED" ROlES ( = teams )
                foreach ($this->roles as $role) {
                    if($role->autoAppliesTo("shared") || $role->hasOwner()) {
                        $this->parentRole = $role->override($this->parentRole);
                    }
                }
            }
            $this->mergedRole = $this->personalRole->override($this->parentRole);  // $this->parentRole->override($this->personalRole);
        }
    }

    /**
     * @return AJXP_Role
     */
    public function getMergedRole()
    {
        return $this->mergedRole;
    }

    /**
     * @return AJXP_Role
     */
    public function getPersonalRole()
    {
        return $this->personalRole;
    }

    /**
     * @param AJXP_Role $role
     */
    public function updatePersonalRole(AJXP_Role $role)
    {
        $this->personalRole = $role;
    }

    /**
     * @return int
     */
    protected function migrateRightsToPersonalRole()
    {
        $changes = 0;
        $this->personalRole = new AJXP_Role("AJXP_USR_"."/".$this->id);
        $this->roles["AJXP_USR_"."/".$this->id] = $this->personalRole;
        foreach ($this->rights as $rightKey => $rightValue) {
            if ($rightKey == "ajxp.actions" && is_array($rightValue)) {
                foreach ($rightValue as $repoId => $repoData) {
                    foreach ($repoData as $actionName => $actionState) {
                        $this->personalRole->setActionState("plugin.all", $actionName, $repoId, $actionState);
                        $changes++;
                    }
                }
                unset($this->rights[$rightKey]);
            }
            if(strpos($rightKey, "ajxp.") === 0) continue;
            $this->personalRole->setAcl($rightKey, $rightValue);
            $changes++;
            unset($this->rights[$rightKey]);
        }
        // Move old CUSTOM_DATA values to personal role parameter
        $customValue = $this->getPref("CUSTOM_PARAMS");
        $custom = ConfService::getConfStorageImpl()->getOption("CUSTOM_DATA");
        if (is_array($custom) && count($custom)) {
            foreach ($custom as $key => $value) {
                if (isSet($customValue[$key])) {
                    $this->personalRole->setParameterValue(ConfService::getConfStorageImpl()->getId(), $key, $customValue[$key]);
                }
            }
        }
        
        return $changes;
    }

    /**
     * @param $r1
     * @param $r2
     * @return int
     */
    protected function orderRoles($r1, $r2)
    {
        // One group and something else
        if(strpos($r1, "AJXP_GRP_") === 0 && strpos($r2, "AJXP_GRP_") === FALSE) return -1;
        if(strpos($r2, "AJXP_GRP_") === 0 && strpos($r1, "AJXP_GRP_") === FALSE) return 1;

        // Usr role and something else
        if(strpos($r1, "AJXP_USR_") === 0) return 1;
        if(strpos($r2, "AJXP_USR_") === 0) return -1;

        // Two groups, sort by string, will magically keep group hierarchy
        if(strpos($r1, "AJXP_GRP_") === 0 && strpos($r2, "AJXP_GRP_") === 0) {
            return strcmp($r1,$r2);
        }

        // Two roles: if sticky and something else, always last.
        if(isSet($this->rights["ajxp.roles.sticky"])){
            $sticky = $this->rights["ajxp.roles.sticky"];
            if(isSet($sticky[$r1]) && !isSet($sticky[$r2])){
                return 1;
            }
            if(isSet($sticky[$r2]) && !isSet($sticky[$r1])){
                return -1;
            }
        }

        // Two roles - Try to get sorting order
        if(isSet($this->rights["ajxp.roles.order"])){
            return $this->rights["ajxp.roles.order"][$r1] - $this->rights["ajxp.roles.order"][$r2];
        }else{
            return strcmp($r1,$r2);
        }
    }

    /**
     * @param array $roles
     * @param boolean $checkBoolean
     * @return array
     */
    protected function filterRolesForSaving($roles, $checkBoolean)
    {
        $res = array();
        foreach ($roles as $rName => $status) {
            if($checkBoolean &&  !$status) continue;
            if(strpos($rName, "AJXP_GRP_/") === 0) continue;
            $res[$rName] = $status;
        }
        return $res;
    }

    protected $lastSessionSerialization = 0;

    /**
     * @return array
     */
    public function getRolesKeys(){
        return array_keys($this->roles);
    }

    /**
     * @return array
     */
    public function __sleep(){
        $this->lastSessionSerialization = time();
        return array("id", "hasAdmin", "rights", "prefs", "bookmarks", "version", "roles", "parentUser", "hidden", "groupPath", "personalRole", "lastSessionSerialization");
    }

    public function __wakeup(){
        $this->storage = ConfService::getConfStorageImpl();
        if(!is_object($this->personalRole)){
            $this->personalRole = RolesService::getRole("AJXP_USR_/" . $this->getId());
        }
        $this->recomputeMergedRole();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function reloadRolesIfRequired(){
        if($this->lastSessionSerialization && count($this->roles)
            && $this->storage->rolesLastUpdated(array_keys($this->roles)) > $this->lastSessionSerialization){

            $newRoles = RolesService::getRolesList(array_keys($this->roles), false, true);
            foreach($newRoles as $rId => $newRole){
                if(strpos($rId, "AJXP_USR_/") === 0){
                    $this->personalRole = $newRole;
                    $this->roles[$rId] = $this->personalRole;
                }else{
                    $this->roles[$rId] = $newRole;
                }
            }
            $this->recomputeMergedRole();
            return true;

        }
        return false;
    }

}
