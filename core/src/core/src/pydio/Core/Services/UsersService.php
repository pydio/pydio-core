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

use Pydio\Conf\Core\AbstractAjxpUser;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\UserNotFoundException;
use Pydio\Core\Exception\WorkspaceForbiddenException;
use Pydio\Core\Exception\WorkspaceNotFoundException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\FilteredRepositoriesList;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Utils\CookiesHelper;
use Pydio\Log\Core\AJXP_Logger;

defined('AJXP_EXEC') or die('Access not allowed');

class UsersService
{
    /**
     * @var UsersService
     */
    private static $_instance;
    /**
     * @var array
     */
    private $repositoriesCache = [];
    /**
     * @var array
     */
    private $usersCache = [];

    /**
     * @var array
     */
    private $userParametersCache = [];

    /**
     * @return UsersService
     */
    public static function instance(){
        if(empty(self::$_instance)) self::$_instance = new UsersService();
        return self::$_instance;
    }

    /**
     * @param string $userId
     * @param bool $checkExists
     * @return UserInterface
     * @throws UserNotFoundException
     */
    public static function getUserById($userId, $checkExists = true){

        $self = self::instance();
        // Try to get from memory
        if(isSet($self->usersCache[$userId])){
            return $self->usersCache[$userId];
        }
        // Try to get from cache
        $test = CacheService::fetch("shared", "pydio:user:" . $userId);
        if($test !== false && $test instanceof UserInterface){
            if($test->getPersonalRole() === null){
                $test->updatePersonalRole($test->getRoles()["AJXP_USR_/".$userId]);
            }
            $test->recomputeMergedRole();
            $self->usersCache[$userId] = $test;
            return $test;
        }
        if($checkExists && !self::userExists($userId)){
            throw new UserNotFoundException($userId);
        }
        // Try to get from conf
        $userObject = ConfService::getConfStorageImpl()->createUserObject($userId);
        if($userObject instanceof UserInterface){
            // Save in memory
            $self->usersCache[$userId] = $userObject;
            // Save in cache
            CacheService::save("shared", "pydio:user:" . $userId, $userObject);
        }
        return $userObject;

    }

    /**
     * @param $userObject UserInterface
     */
    public static function updateUser($userObject){
        $self = self::instance();
        $userId = $userObject->getId();
        $self->usersCache[$userId] = $userObject;
        CacheService::save("shared", "pydio:user:" . $userId, $userObject);
    }

    /**
     * @param UserInterface $user
     * @param string $repositoryId
     * @return null|RepositoryInterface
     * @throws WorkspaceNotFoundException
     * @throws WorkspaceForbiddenException
     */
    public static function getRepositoryWithPermission($user, $repositoryId){
        $repo = RepositoryService::findRepositoryByIdOrAlias($repositoryId);
        if($repo == null){
            throw new WorkspaceNotFoundException($repositoryId);
        }
        if(!RepositoryService::repositoryIsAccessible($repo, $user)){
            return new WorkspaceForbiddenException($repositoryId);
        }
        return $repo;
    }

    /**
     * @param UserInterface $user
     * @param bool $includeShared
     * @param bool $details
     * @param bool $labelsOnly
     * @return \Pydio\Core\Model\RepositoryInterface[]
     */
    public static function getRepositoriesForUser($user, $includeShared = true, $details = false, $labelsOnly = false){

        $self = self::instance();
        $repos = $self->getFromCaches($user->getId());
        if($repos !== null) {
            $userRepos =  $repos;
        } else{
            $list = new FilteredRepositoriesList($user);
            $repos = $list->load();
            $self->setInCache($user->getId(), $repos);
            $userRepos = $repos;
        }
        if($includeShared && !$details && !$labelsOnly) {
            return $userRepos;
        }
        $output = [];
        foreach ($userRepos as $repoId => $repoObject){
            if(!RepositoryService::repositoryIsAccessible($repoObject, $user, $details, $includeShared)){
                continue;
            }
            if($labelsOnly) $output[$repoId] = $repoObject->getDisplay();
            else $output[$repoId] = $repoObject;
        }
        return $output;

    }

    /**
     * @param $userId
     * @return mixed|null|\Pydio\Core\Model\RepositoryInterface[]
     */
    private function getFromCaches($userId){

        $fromSesssion = SessionService::getLoadedRepositories();
        if($fromSesssion !== null){
            $this->repositoriesCache[$userId] = $fromSesssion;
            return $fromSesssion;
        }
        if(isSet($this->repositoriesCache[$userId])) {
            $configsNotCorrupted = array_reduce($this->repositoriesCache[$userId], function($carry, $item){ return $carry && is_object($item) && ($item instanceof RepositoryInterface); }, true);
            if($configsNotCorrupted){
                return $this->repositoriesCache[$userId];
            }else{
                $this->repositoriesCache = [];
            }
        }
        return null;

    }

    /**
     * @param string $userId
     * @param RepositoryInterface[] $repoList
     */
    private function setInCache($userId, $repoList){

        $this->repositoriesCache[$userId] = $repoList;
        SessionService::updateLoadedRepositories($repoList);

    }

    public static function invalidateCache(){

        self::instance()->repositoriesCache = [];
        self::instance()->usersCache = [];
        CacheService::deleteAll(AJXP_CACHE_SERVICE_NS_SHARED);
        SessionService::invalidateLoadedRepositories();

    }

    

    /**
     * Whether the whole users management system is enabled or not.
     * @static
     * @return bool
     */
    public static function usersEnabled()
    {
        return ConfService::getCoreConf("ENABLE_USERS", "auth");
    }

    /**
     * Whether the current auth driver supports password update or not
     * @static
     * @return bool
     */
    public static function changePasswordEnabled()
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->passwordsEditable();
    }

    /**
     * Return user to lower case if ignoreUserCase
     * @param $user
     * @return string
     */
    public static function filterUserSensitivity($user)
    {
        if (!ConfService::getCoreConf("CASE_SENSITIVE", "auth")) {
            return strtolower($user);
        } else {
            return $user;
        }
    }

    /**
     * Get config to knwo whether we should ignore user case
     * @return bool
     */
    public static function ignoreUserCase()
    {
        return !ConfService::getCoreConf("CASE_SENSITIVE", "auth");
    }

    /**
     * If the auth driver implementation has a logout redirect URL.
     * @static
     * @return string
     */
    public static function getLogoutAddress()
    {
        $authDriver = ConfService::getAuthDriverImpl();
        $logout = $authDriver->getLogoutRedirect();
        return $logout;
    }

    /**
     * Use driver implementation to check whether the user exists or not.
     * @static
     * @param String $userId
     * @param String $mode "r" or "w"
     * @return bool
     */
    public static function userExists($userId, $mode = "r")
    {
        if ($userId == "guest" && !ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")) {
            return false;
        }
        $userId = self::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        if ($mode == "w") {
            return $authDriver->userExistsWrite($userId);
        }
        return $authDriver->userExists($userId);
    }

    /**
     * Make sure a user id is not reserved for low-level tasks (currently "guest" and "shared").
     * @static
     * @param String $username
     * @return bool
     */
    public static function isReservedUserId($username)
    {
        $username = self::filterUserSensitivity($username);
        return in_array($username, array("guest", "shared"));
    }

    /**
     * Check a password
     * @static
     * @param $userId
     * @param $userPass
     * @param bool $cookieString
     * @param string $returnSeed
     * @return bool|void
     */
    public static function checkPassword($userId, $userPass, $cookieString = false, $returnSeed = "")
    {
        if (ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && $userId == "guest") return true;
        $userId = self::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        if ($cookieString) {
            $userObject = self::getUserById($userId);
            $res = CookiesHelper::checkCookieString($userObject, $userPass);
            return $res;
        }
        if (!$authDriver->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            if ($authDriver->getSeed(false) != $returnSeed) return false;
        }
        return $authDriver->checkPassword($userId, $userPass, $returnSeed);
    }

    /**
     * Update the password in the auth driver implementation.
     * @static
     * @throws \Exception
     * @param $userId
     * @param $userPass
     * @return bool
     */
    public static function updatePassword($userId, $userPass)
    {
        if (strlen($userPass) < ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth")) {
            $messages = LocaleService::getMessages();
            throw new \Exception($messages[378]);
        }
        $userId = self::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        $ctx = Context::emptyContext();
        Controller::applyHook("user.before_password_change", array($ctx, $userId));
        $authDriver->changePassword($userId, $userPass);
        Controller::applyHook("user.after_password_change", array($ctx, $userId));
        if ($authDriver->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            // We can directly update the HA1 version of the WEBDAV Digest
            $realm = ConfService::getCoreConf("WEBDAV_DIGESTREALM");
            $ha1 = md5("{$userId}:{$realm}:{$userPass}");
            $zObj = self::getUserById($userId);
            $wData = $zObj->getPref("AJXP_WEBDAV_DATA");
            if (!is_array($wData)) $wData = array();
            $wData["HA1"] = $ha1;
            $zObj->setPref("AJXP_WEBDAV_DATA", $wData);
            $zObj->save();
        }
        AJXP_Logger::info(__CLASS__, "Update Password", array("user_id" => $userId));
        return true;
    }

    /**
     * Create a user
     * @static
     * @throws \Exception
     * @param $userId
     * @param $userPass
     * @param bool $isAdmin
     * @param bool $isHidden
     * @return UserInterface
     */
    public static function createUser($userId, $userPass, $isAdmin = false, $isHidden = false)
    {
        $userId = self::filterUserSensitivity($userId);
        $localContext = new Context($userId, null);
        Controller::applyHook("user.before_create", array($localContext, $userId, $userPass, $isAdmin, $isHidden));
        if (!ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && $userId == "guest") {
            throw new \Exception("Reserved user id");
        }
        $authDriver = ConfService::getAuthDriverImpl();
        $authDriver->createUser($userId, $userPass);
        $user = self::getUserById($userId, false);
        if ($isAdmin) {
            $user->setAdmin(true);
            $user->save("superuser");
        }
        if ($isHidden) {
            $user->setHidden(true);
            $user->save("superuser");
        }
        if ($authDriver->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $realm = ConfService::getCoreConf("WEBDAV_DIGESTREALM");
            $ha1 = md5("{$userId}:{$realm}:{$userPass}");
            $wData = $user->getPref("AJXP_WEBDAV_DATA");
            if (!is_array($wData)) $wData = array();
            $wData["HA1"] = $ha1;
            $user->setPref("AJXP_WEBDAV_DATA", $wData);
            $user->save();
        }
        Controller::applyHook("user.after_create", array($localContext, $user));
        AJXP_Logger::info(__CLASS__, "Create User", array("user_id" => $userId));
        return $user;
    }

    /**
     * Detect the number of admin users
     * @static
     * @return int|void
     */
    public static function countAdminUsers()
    {
        $confDriver = ConfService::getConfStorageImpl();
        $auth = ConfService::getAuthDriverImpl();
        $count = $confDriver->countAdminUsers();
        if (!$count && $auth->userExists("admin") && $confDriver->getName() == "serial") {
            return -1;
        }
        return $count;
    }

    /**
     * Delete a user in the auth/conf driver impl
     * @static
     * @param $userId
     * @return bool
     */
    public static function deleteUser($userId)
    {
        $ctx = Context::emptyContext();
        Controller::applyHook("user.before_delete", array($ctx, $userId));
        $userId = self::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        $authDriver->deleteUser($userId);
        $subUsers = array();
        ConfService::getConfStorageImpl()->deleteUser($userId, $subUsers);
        foreach ($subUsers as $deletedUser) {
            $authDriver->deleteUser($deletedUser);
        }
        Controller::applyHook("user.after_delete", array($ctx, $userId));
        AJXP_Logger::info(__CLASS__, "Delete User", array("user_id" => $userId, "sub_user" => implode(",", $subUsers)));
        return true;
    }

    /**
     * List children groups of current base
     * @param string $baseGroup
     * @return string[]
     */
    public static function listChildrenGroups($baseGroup = "/")
    {
        return ConfService::getAuthDriverImpl()->listChildrenGroups($baseGroup);

    }

    /**
     * Create a new group at the given path
     *
     * @param $baseGroup
     * @param $groupName
     * @param $groupLabel
     * @throws \Exception
     */
    public static function createGroup($baseGroup, $groupName, $groupLabel)
    {
        if (empty($groupName)) throw new \Exception("Please provide a name for this new group!");
        $fullGroupPath = rtrim($baseGroup, "/") . "/" . $groupName;
        $exists = ConfService::getConfStorageImpl()->groupExists($fullGroupPath);
        if ($exists) {
            throw new \Exception("Group with this name already exists, please pick another name!");
        }
        if (empty($groupLabel)) $groupLabel = $groupName;
        ConfService::getConfStorageImpl()->createGroup(rtrim($baseGroup, "/") . "/" . $groupName, $groupLabel);
    }

    /**
     * Delete group by name
     * @param $baseGroup
     * @param $groupName
     */
    public static function deleteGroup($baseGroup, $groupName)
    {
        ConfService::getConfStorageImpl()->deleteGroup(rtrim($baseGroup, "/") . "/" . $groupName);
    }

    /**
     * Count the number of children a given user has already created
     * @param $parentUserId
     * @return AbstractAjxpUser[]
     */
    public static function getChildrenUsers($parentUserId)
    {
        return ConfService::getConfStorageImpl()->getUserChildren($parentUserId);
    }

    /**
     * Count the number of users who have either read or write access to a repository
     * @param ContextInterface $ctx
     * @param $repositoryId
     * @param bool $details
     * @param bool $admin True if called in an admin context
     * @return array|int
     */
    public static function countUsersForRepository(ContextInterface $ctx, $repositoryId, $details = false, $admin = false)
    {
        return ConfService::getConfStorageImpl()->countUsersForRepository($ctx, $repositoryId, $details, $admin);
    }

    /**
     * List users with a specific filter
     * @param string $baseGroup
     * @param null $regexp
     * @param $offset
     * @param $limit
     * @param bool $cleanLosts
     * @param bool $recursive
     * @param null $countCallback
     * @param null $loopCallback
     * @return AbstractAjxpUser[]
     */
    public static function listUsers($baseGroup = "/", $regexp = null, $offset = -1, $limit = -1, $cleanLosts = true, $recursive = true, $countCallback = null, $loopCallback = null)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        $confDriver = ConfService::getConfStorageImpl();
        /**
         * @var $allUsers AbstractAjxpUser[]
         */
        $allUsers = array();
        $paginated = false;
        if (($regexp != null || $offset != -1 || $limit != -1) && $authDriver->supportsUsersPagination()) {
            $users = $authDriver->listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive);
            $paginated = ($offset != -1 || $limit != -1);
        } else {
            $users = $authDriver->listUsers($baseGroup);
        }
        $index = 0;

        // Callback func for display progression on cli mode
        if ($countCallback != null) {
            call_user_func($countCallback, $index, count($users), "Update users");
        }

        RolesService::enableRolesCache(true);
        foreach (array_keys($users) as $userId) {
            if (($userId == "guest" && !ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")) || $userId == "ajxp.admin.users" || $userId == "") continue;
            if ($regexp != null && !$authDriver->supportsUsersPagination() && !preg_match("/$regexp/i", $userId)) continue;
            $allUsers[$userId] = self::getUserById($userId);
            $index++;

            // Callback func for display progression on cli mode
            if ($countCallback != null) {
                call_user_func($loopCallback, $index);
            }

            if (empty($regexp) && $paginated) {
                // Make sure to reload all children objects
                foreach ($confDriver->getUserChildren($userId) as $childObject) {
                    $allUsers[$childObject->getId()] = $childObject;
                }
            }
        }
        RolesService::enableRolesCache(false);

        if (empty($regexp) && $paginated && $cleanLosts) {
            // Remove 'lost' items (children without parents).
            foreach ($allUsers as $id => $object) {
                if ($object->hasParent() && !array_key_exists($object->getParent(), $allUsers)) {
                    unset($allUsers[$id]);
                }
            }
        }
        return $allUsers;
    }

    /**
     * Depending on the plugin, tried to compute the actual page where a given user can be located
     *
     * @param $baseGroup
     * @param $userLogin
     * @param $usersPerPage
     * @param int $offset
     * @return int
     */
    public static function findUserPage($baseGroup, $userLogin, $usersPerPage, $offset = 0)
    {
        if (ConfService::getAuthDriverImpl()->supportsUsersPagination()) {
            return ConfService::getAuthDriverImpl()->findUserPage($baseGroup, $userLogin, $usersPerPage, $offset);
        } else {
            return -1;
        }
    }

    /**
     * Whether the current auth driver supports paginated listing
     *
     * @return bool
     */
    public static function authSupportsPagination()
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->supportsUsersPagination();
    }

    /**
     * Count the total number of users inside a group (recursive).
     * Regexp can be used to limit the users IDs with a specific expression
     * Property can be used for basic filtering, either on "parent" or "admin".
     *
     * @param string $baseGroup
     * @param string $regexp
     * @param null $filterProperty Can be "parent" or "admin"
     * @param null $filterValue Can be a string, or constants AJXP_FILTER_EMPTY / AJXP_FILTER_NOT_EMPTY
     * @param bool $recursive
     * @return int
     */
    public static function authCountUsers($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null, $recursive = true)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->getUsersCount($baseGroup, $regexp, $filterProperty, $filterValue, $recursive);
    }

    /**
     * Makes a correspondance between a user and its auth scheme, for multi auth
     * @param $userName
     * @return String
     */
    public static function getAuthScheme($userName)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->getAuthScheme($userName);
    }

    /**
     * Check if auth implementation supports schemes detection
     * @return bool
     */
    public static function driverSupportsAuthSchemes()
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->supportsAuthSchemes();
    }

    /**
     * @param string $parameterName Plugin parameter name
     * @param UserInterface|string $userIdOrObject
     * @param string $pluginId Plugin name, core.conf by default
     * @param null $defaultValue
     * @return mixed
     */
    public static function getUserPersonalParameter($parameterName, $userIdOrObject, $pluginId = "core.conf", $defaultValue = null)
    {
        $self = self::instance();

        $cacheId = $pluginId . "-" . $parameterName;
        if (!isSet($self->userParametersCache[$cacheId])) {
            $self->userParametersCache[$cacheId] = [];
        }
        // Passed an already loaded object
        if ($userIdOrObject instanceof UserInterface) {
            $value = $userIdOrObject->getPersonalRole()->filterParameterValue($pluginId, $parameterName, AJXP_REPO_SCOPE_ALL, $defaultValue);
            $self->userParametersCache[$cacheId][$userIdOrObject->getId()] = $value;
            if (empty($value) && !empty($defaultValue)) $value = $defaultValue;
            return $value;
        }
        // Already in memory cache
        if (isSet($self->userParametersCache[$cacheId][$userIdOrObject])) {
            return $self->userParametersCache[$cacheId][$userIdOrObject];
        }

        // Try to load personal role if it was already loaded.
        $uRole = RolesService::getRole("AJXP_USR_/" . $userIdOrObject);
        if ($uRole === false && UsersService::userExists($userIdOrObject)) {
            $uObject = self::getUserById($userIdOrObject, false);
            $uRole = $uObject->getPersonalRole();
        }
        if (empty($uRole)) {
            return $defaultValue;
        }
        $value = $uRole->filterParameterValue($pluginId, $parameterName, AJXP_REPO_SCOPE_ALL, $defaultValue);
        if (empty($value) && !empty($defaultValue)) {
            $value = $userIdOrObject;
        }
        $self->userParametersCache[$cacheId][$userIdOrObject] = $value;
        return $value;

    }
}