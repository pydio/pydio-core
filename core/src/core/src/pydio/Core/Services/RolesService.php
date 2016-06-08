<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Services;

use Pydio\Conf\Core\AJXP_Role;
use Pydio\Conf\Core\AjxpRole;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\PluginFramework\PluginsService;

defined('AJXP_EXEC') or die('Access not allowed');


class RolesService
{

    /**
     * Update a user with admin rights and return it
    * @param UserInterface $adminUser
     * @return UserInterface
    */
    public static function updateAdminRights($adminUser)
    {
        if ($adminUser->getPersonalRole()->getAcl('ajxp_conf') != "rw") {
            $adminUser->getPersonalRole()->setAcl('ajxp_conf', 'rw');
            $adminUser->recomputeMergedRole();
            $adminUser->save("superuser");
        }
        return $adminUser;
    }

    /**
     * Update a user object with the default repositories rights
     *
     * @param UserInterface $userObject
     */
    public static function updateDefaultRights(&$userObject)
    {
        if (!$userObject->hasParent()) {
            $rolesList = self::getRolesList(array(), true);
            foreach ($rolesList as $roleId => $roleObject) {
                if (!$userObject->canSee($roleObject)) continue;
                if ($userObject->getProfile() == "shared" && $roleObject->autoAppliesTo("shared")) {
                    $userObject->addRole($roleObject);
                } else if ($roleObject->autoAppliesTo("standard")) {
                    $userObject->addRole($roleObject);
                }
            }
        }
    }

    /**
     * @static
     * @param UserInterface $userObject
     */
    public static function updateAutoApplyRole(&$userObject)
    {
        $roles = self::getRolesList(array(), true);
        foreach ($roles as $roleObject) {
            if (!$userObject->canSee($roleObject)) continue;
            if ($roleObject->autoAppliesTo($userObject->getProfile()) || $roleObject->autoAppliesTo("all")) {
                $userObject->addRole($roleObject);
            }
        }
    }

    /**
     * @param UserInterface $userObject
     */
    public static function updateAuthProvidedData(&$userObject)
    {
        ConfService::getAuthDriverImpl()->updateUserObject($userObject);
    }

    /**
     * Retrieve the current users who have either read or write access to a repository
     * @param $repositoryId
     * @param string $rolePrefix
     * @param bool $splitByType
     * @return array
     */
    public static function getRolesForRepository($repositoryId, $rolePrefix = '', $splitByType = false)
    {
        return ConfService::getConfStorageImpl()->getRolesForRepository($repositoryId, $rolePrefix, $splitByType);
    }

    /**
     * Get Role by Id
     *
     * @param string $roleId
     * @return AJXP_Role
     */
    public static function getRole($roleId)
    {
        $roles = self::getRolesList(array($roleId));
        if (isSet($roles[$roleId])) return $roles[$roleId];
        return false;
    }

    /**
     * @param string $roleId Id of the role
     * @param string $groupPath GroupPath to be applied
     * @return AJXP_Role
     */
    public static function getOrCreateRole($roleId, $groupPath)
    {
        $roles = self::getRolesList(array($roleId));
        if (isSet($roles[$roleId])) return $roles[$roleId];
        $role = new AJXP_Role($roleId);
        $role->setGroupPath("/");
        self::updateRole($role);
        return $role;
    }

    /**
     * Create or update role
     *
     * @param AJXP_Role $roleObject
     * @param null $userObject
     */
    public static function updateRole($roleObject, $userObject = null)
    {
        ConfService::getConfStorageImpl()->updateRole($roleObject, $userObject);
        //CacheService::deleteAll(AJXP_CACHE_SERVICE_NS_SHARED);
        ConfService::getInstance()->invalidateLoadedRepositories();
    }

    /**
     * Delete a role by its id
     * @static
     * @param string $roleId
     * @return void
     */
    public static function deleteRole($roleId)
    {
        ConfService::getConfStorageImpl()->deleteRole($roleId);
        //CacheService::deleteAll(AJXP_CACHE_SERVICE_NS_SHARED);
        ConfService::getInstance()->invalidateLoadedRepositories();
    }

    /**
     * @param string $parentUserId
     * @return AJXP_Role
     */
    public static function limitedRoleFromParent($parentUserId)
    {
        $parentRole = self::getRole("AJXP_USR_/" . $parentUserId);
        if ($parentRole === false) return null;

        $inheritActions = PluginsService::searchManifestsWithCache("//server_settings/param[@inherit='true']", function ($nodes) {
            $result = [];
            if (is_array($nodes) && count($nodes)) {
                foreach ($nodes as $node) {
                    $paramName = $node->getAttribute("name");
                    $pluginId = $node->parentNode->parentNode->getAttribute("id");
                    if (isSet($result[$pluginId])) $result[$pluginId] = array();
                    $result[$pluginId][] = $paramName;
                }
            }
            return $result;
        });

        // Clear ACL, Keep disabled actions, keep 'inherit' parameters.
        $childRole = new AJXP_Role("AJXP_PARENT_USR_/");
        $childRole->bunchUpdate(array(
            "ACL" => array(),
            "ACTIONS" => $parentRole->listAllActionsStates(),
            "APPLIES" => array(),
            "PARAMETERS" => array()));
        $params = $parentRole->listParameters();

        foreach ($params as $scope => $plugData) {
            foreach ($plugData as $pId => $paramData) {
                if (!isSet($inheritActions[$pId])) continue;
                foreach ($paramData as $pName => $pValue) {
                    $childRole->setParameterValue($pId, $pName, $pValue, $scope);
                }
            }
        }

        return $childRole;
    }

    /**
     * @param boolean $status
     */
    public static function enableRolesCache($status){
        self::$useCache = $status;
        if($status){
            self::$rolesCache = null;
        }
    }

    /** @var  boolean */
    private static $useCache;

    /** @var  array */
    private static $rolesCache;

    /**
     * Get all defined roles
     * @static
     * @param array $roleIds
     * @param boolean $excludeReserved,
     * @return AJXP_Role[]
     */
    public static function getRolesList($roleIds = array(), $excludeReserved = false)
    {
        if (self::$useCache && !count($roleIds) && $excludeReserved == true && self::$rolesCache != null) {
            return self::$rolesCache;
        }
        $confDriver = ConfService::getConfStorageImpl();
        $roles = $confDriver->listRoles($roleIds, $excludeReserved);
        $repoList = null;
        foreach ($roles as $roleId => $roleObject) {
            if ($roleObject instanceof AjxpRole) {
                if ($repoList == null) $repoList = ConfService::getRepositoriesList("all");
                $newRole = new AJXP_Role($roleId);
                $newRole->migrateDeprecated($repoList, $roleObject);
                $roles[$roleId] = $newRole;
                self::updateRole($newRole);
            }
        }
        if (self::$useCache && !count($roleIds) && $excludeReserved == true) {
            self::$rolesCache = $roles;
        }
        return $roles;
    }

    /**
     * Specific operations to perform at boot time
     * @static
     * @throws PydioException
     * @throws \Exception
     */
    public static function bootSequence()
    {
        if (file_exists(AJXP_CACHE_DIR . "/admin_counted")) return;
        $rootRole = RolesService::getRole("AJXP_GRP_/");
        if ($rootRole === false) {
            $rootRole = new AJXP_Role("AJXP_GRP_/");
            $rootRole->setLabel("Root Group");
            //$rootRole->setAutoApplies(array("standard", "admin"));
            //$dashId = "";
            $allRepos = ConfService::getRepositoriesList("all", false);
            foreach ($allRepos as $repositoryId => $repoObject) {
                if ($repoObject->isTemplate) continue;
                //if($repoObject->getAccessType() == "ajxp_user") $dashId = $repositoryId;
                $gp = $repoObject->getGroupPath();
                if (empty($gp) || $gp == "/") {
                    if ($repoObject->getDefaultRight() != "") {
                        $rootRole->setAcl($repositoryId, $repoObject->getDefaultRight());
                    }
                }
            }
            //if(!empty($dashId)) $rootRole->setParameterValue("core.conf", "DEFAULT_START_REPOSITORY", $dashId);
            $parameters = PluginsService::searchManifestsWithCache("//server_settings/param[@scope]", function ($paramNodes) {
                $result = [];
                /** @var \DOMElement $xmlNode */
                foreach ($paramNodes as $xmlNode) {
                    $default = $xmlNode->getAttribute("default");
                    if (empty($default)) continue;
                    $parentNode = $xmlNode->parentNode->parentNode;
                    $pluginId = $parentNode->getAttribute("id");
                    if (empty($pluginId)) {
                        $pluginId = $parentNode->nodeName . "." . $parentNode->getAttribute("name");
                    }
                    $result[] = ["pluginId" => $pluginId, "name" => $xmlNode->getAttribute("name"), "default" => $default];
                }
                return $result;
            });
            foreach ($parameters as $parameter) {
                $rootRole->setParameterValue($parameter["pluginId"], $parameter["name"], $parameter["default"]);
            }
            RolesService::updateRole($rootRole);
        }
        $miniRole = RolesService::getRole("MINISITE");
        if ($miniRole === false) {
            $rootRole = new AJXP_Role("MINISITE");
            $rootRole->setLabel("Minisite Users");
            $actions = array(
                "access.fs" => array("ajxp_link", "chmod", "purge"),
                "meta.watch" => array("toggle_watch"),
                "conf.serial" => array("get_bookmarks"),
                "conf.sql" => array("get_bookmarks"),
                "index.lucene" => array("index"),
                "action.share" => array("share", "share-edit-shared", "share-folder-workspace", "share-file-minisite", "share-selection-minisite", "share-folder-minisite-public"),
                "gui.ajax" => array("bookmark"),
                "auth.serial" => array("pass_change"),
                "auth.sql" => array("pass_change"),
            );
            foreach ($actions as $pluginId => $acts) {
                foreach ($acts as $act) {
                    $rootRole->setActionState($pluginId, $act, AJXP_REPO_SCOPE_SHARED, false);
                }
            }
            RolesService::updateRole($rootRole);
        }
        $miniRole = RolesService::getRole("MINISITE_NODOWNLOAD");
        if ($miniRole === false) {
            $rootRole = new AJXP_Role("MINISITE_NODOWNLOAD");
            $rootRole->setLabel("Minisite Users - No Download");
            $actions = array(
                "access.fs" => array("download", "download_chunk", "prepare_chunk_dl", "download_all")
            );
            foreach ($actions as $pluginId => $acts) {
                foreach ($acts as $act) {
                    $rootRole->setActionState($pluginId, $act, AJXP_REPO_SCOPE_SHARED, false);
                }
            }
            RolesService::updateRole($rootRole);
        }
        $miniRole = RolesService::getRole("GUEST");
        if ($miniRole === false) {
            $rootRole = new AJXP_Role("GUEST");
            $rootRole->setLabel("Guest user role");
            $actions = array(
                "access.fs" => array("purge"),
                "meta.watch" => array("toggle_watch"),
                "index.lucene" => array("index"),
            );
            $rootRole->setAutoApplies(array("guest"));
            foreach ($actions as $pluginId => $acts) {
                foreach ($acts as $act) {
                    $rootRole->setActionState($pluginId, $act, AJXP_REPO_SCOPE_ALL);
                }
            }
            RolesService::updateRole($rootRole);
        }

        // Legacy, should never happen
        $adminCount = UsersService::countAdminUsers();
        if ($adminCount == 0) {
            $authDriver = ConfService::getAuthDriverImpl();
            $adminPass = ADMIN_PASSWORD;
            if (!$authDriver->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
                $adminPass = md5(ADMIN_PASSWORD);
            }
            UsersService::createUser("admin", $adminPass, true);
            if (ADMIN_PASSWORD == INITIAL_ADMIN_PASSWORD) {
                $userObject = ConfService::getConfStorageImpl()->createUserObject("admin");
                $userObject->setAdmin(true);
                RolesService::updateAdminRights($userObject);
                if (UsersService::changePasswordEnabled()) {
                    $userObject->setLock("pass_change");
                }
                $userObject->save("superuser");
                AuthService::updateUser($userObject);
                file_put_contents(AJXP_CACHE_DIR . "/admin_counted", "true");
                throw new PydioException("Warning! User 'admin' was created with the initial password '" . INITIAL_ADMIN_PASSWORD . "'. \\nPlease log in as admin and change the password now!");
            }
        } else if ($adminCount == -1) {
            // Here we may come from a previous version! Check the "admin" user and set its right as admin.
            $confStorage = ConfService::getConfStorageImpl();
            $adminUser = $confStorage->createUserObject("admin");
            $adminUser->setAdmin(true);
            $adminUser->save("superuser");
            file_put_contents(AJXP_CACHE_DIR . "/admin_counted", "true");
            throw new PydioException("There is an admin user, but without admin right. Now any user can have the administration rights, \\n your 'admin' user was set with the admin rights. Please check that this suits your security configuration.");
        }
        file_put_contents(AJXP_CACHE_DIR . "/admin_counted", "true");

    }
}