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
 * Static access to the authentication mechanism. Encapsulates the authDriver implementation
 * @package Pydio
 * @subpackage Core
 */
class AuthService
{
    public static $cacheRoles = false;
    public static $roles;
    public static $useSession = true;
    private static $currentUser;
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
     * Get a unique seed from the current auth driver
     * @static
     * @return int|string
     */
    public static function generateSeed()
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->getSeed(true);
    }

    /**
     * Put a secure token in the session
     * @static
     * @return string
     */
    public static function generateSecureToken()
    {
        if(!isSet($_SESSION["SECURE_TOKENS"])){
            $_SESSION["SECURE_TOKENS"] = array();
        }
        if(isSet($_SESSION["FORCE_SECURE_TOKEN"])){
            $_SESSION["SECURE_TOKENS"][] = $_SESSION["FORCE_SECURE_TOKEN"];
            return $_SESSION["FORCE_SECURE_TOKEN"];
        }
        $newToken = AJXP_Utils::generateRandomString(32); //md5(time());
        $_SESSION["SECURE_TOKENS"][] = $newToken;
        return $newToken;
    }
    /**
     * Get the secure token from the session
     * @static
     * @return string|bool
     */
    public static function getSecureToken()
    {
        if(isSet($_SESSION["SECURE_TOKENS"]) && count($_SESSION["SECURE_TOKENS"])){
            return true;
        }
        return false;
        //return (isSet($_SESSION["SECURE_TOKENS"])?$_SESSION["SECURE_TOKEN"]:FALSE);
    }
    /**
     * Verify a secure token value from the session
     * @static
     * @param string $token
     * @return bool
     */
    public static function checkSecureToken($token)
    {
        if (isSet($_SESSION["SECURE_TOKENS"]) && in_array($token, $_SESSION["SECURE_TOKENS"])) {
            return true;
        }
        return false;
    }

    /**
     * Get the currently logged user object
     * @return AbstractAjxpUser
     */
    public static function getLoggedUser()
    {
        if (self::$useSession && isSet($_SESSION["AJXP_USER"])) {
            if (is_a($_SESSION["AJXP_USER"], "__PHP_Incomplete_Class")) {
                session_unset();
                return null;
            }
            return $_SESSION["AJXP_USER"];
        }
        if(!self::$useSession && isSet(self::$currentUser)) return self::$currentUser;
        return null;
    }
    /**
     * Call the preLogUser() functino on the auth driver implementation
     * @static
     * @param Array $httpVars
     * @return void
     */
    public static function preLogUser($httpVars)
    {
        if(self::getLoggedUser() != null && self::getLoggedUser()->getId() != "guest") return ;

        $frontends = AJXP_PluginsService::getInstance()->getActivePluginsForType("authfront");
        $index = 0;
        foreach($frontends as $frontendPlugin){
            if(!$frontendPlugin->isEnabled()) continue;
            $res = $frontendPlugin->tryToLogUser($httpVars, ($index == count($frontends)-1));
            $index ++;
            if($res) break;
        }
        // Keep old-fashioned test, should be removed
        $authDriver = ConfService::getAuthDriverImpl();
        $authDriver->preLogUser((isSet($httpVars["remote_session"])?$httpVars["remote_session"]:""));

        return ;
    }
    /**
     * The array is located in the AjxpTmpDir/failedAJXP.log
     * @static
     * @return array
     */
    public static function getBruteForceLoginArray()
    {
        $failedLog = AJXP_Utils::getAjxpTmpDir()."/failedAJXP.log";
        $loginAttempt = @file_get_contents($failedLog);
        $loginArray = unserialize($loginAttempt);
        $ret = array();
        $curTime = time();
        if (is_array($loginArray)) {
            // Filter the array (all old time are cleaned)
            foreach ($loginArray as $key => $login) {
                if (($curTime - $login["time"]) <= 60 * 60 * 24) $ret[$key] = $login;
            }
        }
        return $ret;
    }
    /**
     * Store the array
     * @static
     * @param $loginArray
     * @return void
     */
    public static function setBruteForceLoginArray($loginArray)
    {
        $failedLog = AJXP_Utils::getAjxpTmpDir()."/failedAJXP.log";
        @file_put_contents($failedLog, serialize($loginArray));
    }
    /**
     * Determines whether the user is try to make many attemps
     * @static
     * @param $loginArray
     * @return bool
     */
    public static function checkBruteForceLogin(&$loginArray)
    {
        if (isSet($_SERVER['REMOTE_ADDR'])) {
            $serverAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $serverAddress = $_SERVER['SERVER_ADDR'];
        }
        $login = null;
        if (isSet($loginArray[$serverAddress])) {
            $login = $loginArray[$serverAddress];
        }
        if (is_array($login)) {
            $login["count"]++;
        } else $login = array("count"=>1, "time"=>time());
        $loginArray[$serverAddress] = $login;
        if ($login["count"] > 3) {
            if (AJXP_SERVER_DEBUG || ConfService::getCoreConf("DISABLE_BRUTE_FORCE_CHECK", "auth") === true) {
                AJXP_Logger::debug("Warning: failed login 3 time for $login from address $serverAddress! Captcha is disabled.");
                return true;
            }
            return FALSE;
        }
        return TRUE;
    }
    /**
     * Is there a brute force login attempt?
     * @static
     * @return bool
     */
    public static function suspectBruteForceLogin()
    {
        $loginAttempt = self::getBruteForceLoginArray();
        return !self::checkBruteForceLogin($loginAttempt);
    }

    public static function filterUserSensitivity($user)
    {
        if (!ConfService::getCoreConf("CASE_SENSITIVE", "auth")) {
            return strtolower($user);
        } else {
            return $user;
        }
    }

    public static function ignoreUserCase()
    {
        return !ConfService::getCoreConf("CASE_SENSITIVE", "auth");
    }

    /**
     * @static
     * @param AbstractAjxpUser $user
     */
    public static function refreshRememberCookie($user)
    {
        $current = $_COOKIE["AjaXplorer-remember"];
        if (!empty($current)) {
            $user->invalidateCookieString(substr($current, strpos($current, ":")+1));
        }
        $rememberPass = $user->getCookieString();
        setcookie("AjaXplorer-remember", $user->id.":".$rememberPass, time()+3600*24*10, null, null, (isSet($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) == "on"), true);
    }

    /**
     * @static
     * @return bool
     */
    public static function hasRememberCookie()
    {
        return (isSet($_COOKIE["AjaXplorer-remember"]) && !empty($_COOKIE["AjaXplorer-remember"]));
    }

    /**
     * @static
     * Warning, must be called before sending other headers!
     */
    public static function clearRememberCookie()
    {
        $current = $_COOKIE["AjaXplorer-remember"];
        $user = self::getLoggedUser();
        if (!empty($current) && $user != null) {
            $user->invalidateCookieString(substr($current, strpos($current, ":")+1));
        }
        setcookie("AjaXplorer-remember", "", time()-3600, null, null, (isSet($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) == "on"), true);
    }

    public static function logTemporaryUser($parentUserId, $temporaryUserId)
    {
        $parentUserId = self::filterUserSensitivity($parentUserId);
        $temporaryUserId = self::filterUserSensitivity($temporaryUserId);
        $confDriver = ConfService::getConfStorageImpl();
        $parentUser = $confDriver->createUserObject($parentUserId);
        $temporaryUser = $confDriver->createUserObject($temporaryUserId);
        $temporaryUser->mergedRole = $parentUser->mergedRole;
        $temporaryUser->rights = $parentUser->rights;
        $temporaryUser->setGroupPath($parentUser->getGroupPath());
        $temporaryUser->setParent($parentUserId);
        $temporaryUser->setResolveAsParent(true);
        AJXP_Logger::info(__CLASS__,"Log in", array("temporary user" => $temporaryUserId, "owner" => $parentUserId));
        self::updateUser($temporaryUser);
    }

    public static function clearTemporaryUser($temporaryUserId)
    {
        AJXP_Logger::info(__CLASS__,"Log out", array("temporary user" => $temporaryUserId));
        if (isSet($_SESSION["AJXP_USER"]) || isSet(self::$currentUser)) {
            AJXP_Logger::info(__CLASS__, "Log Out", "");
            unset($_SESSION["AJXP_USER"]);
            if(isSet(self::$currentUser)) unset(self::$currentUser);
            if (ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")) {
                AJXP_Safe::clearCredentials();
            }
        }
    }

    /**
     * Log the user from its credentials
     * @static
     * @param string $user_id The user id
     * @param string $pwd The password
     * @param bool $bypass_pwd Ignore password or not
     * @param bool $cookieLogin Is it a logging from the remember me cookie?
     * @param string $returnSeed The unique seed
     * @return int
     */
    public static function logUser($user_id, $pwd, $bypass_pwd = false, $cookieLogin = false, $returnSeed="")
    {
        $user_id = self::filterUserSensitivity($user_id);
        if ($cookieLogin && !isSet($_COOKIE["AjaXplorer-remember"])) {
            return -5; // SILENT IGNORE
        }
        if ($cookieLogin) {
            list($user_id, $pwd) = explode(":", $_COOKIE["AjaXplorer-remember"]);
        }
        $confDriver = ConfService::getConfStorageImpl();
        if ($user_id == null) {
            if (self::$useSession) {
                if(isSet($_SESSION["AJXP_USER"]) && is_object($_SESSION["AJXP_USER"])) return 1;
            } else {
                if(isSet(self::$currentUser) && is_object(self::$currentUser)) return 1;
            }
            if (ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && !isSet($_SESSION["CURRENT_MINISITE"])) {
                $authDriver = ConfService::getAuthDriverImpl();
                if (!$authDriver->userExists("guest")) {
                    self::createUser("guest", "");
                    $guest = $confDriver->createUserObject("guest");
                    $guest->save("superuser");
                }
                self::logUser("guest", null);
                return 1;
            }
            return -1;
        }
        $authDriver = ConfService::getAuthDriverImpl();
        // CHECK USER PASSWORD HERE!
        $loginAttempt = self::getBruteForceLoginArray();
        $bruteForceLogin = self::checkBruteForceLogin($loginAttempt);
        self::setBruteForceLoginArray($loginAttempt);

        if (!$authDriver->userExists($user_id)) {
            AJXP_Logger::warning(__CLASS__, "Login failed", array("user" => $user_id, "error" => "Invalid user"));
            if ($bruteForceLogin === FALSE) {
                return -4;
            } else {
                return -1;
            }
        }
        if (!$bypass_pwd) {
            if (!self::checkPassword($user_id, $pwd, $cookieLogin, $returnSeed)) {
                AJXP_Logger::warning(__CLASS__, "Login failed", array("user" => $user_id, "error" => "Invalid password"));
                if ($bruteForceLogin === FALSE) {
                    return -4;
                } else {
                    if($cookieLogin) return -5;
                    return -1;
                }
            }
        }
        // Successful login attempt
        unset($loginAttempt[$_SERVER["REMOTE_ADDR"]]);
        self::setBruteForceLoginArray($loginAttempt);

        // Setting session credentials if asked in config
        if (ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")) {
            list($authId, $authPwd) = $authDriver->filterCredentials($user_id, $pwd);
            AJXP_Safe::storeCredentials($authId, $authPwd);
        }

        $user = $confDriver->createUserObject($user_id);
        if ($user->getLock() == "logout") {
            AJXP_Logger::warning(__CLASS__, "Login failed", array("user" => $user_id, "error" => "Locked user"));
            return -1;
        }
        if ($authDriver->isAjxpAdmin($user_id)) {
            $user->setAdmin(true);
        }
        if(self::$useSession) $_SESSION["AJXP_USER"] = $user;
        else self::$currentUser = $user;

        if ($user->isAdmin()) {
            $user = self::updateAdminRights($user);
            self::updateUser($user);
        }

        if ($authDriver->autoCreateUser() && !$user->storageExists()) {
            $user->save("superuser"); // make sure update rights now
        }
        AJXP_Logger::info(__CLASS__, "Log In", array("context"=>self::$useSession?"WebUI":"API"));
        return 1;
    }
    /**
     * Store the object in the session
     * @static
     * @param $userObject
     * @return void
     */
    public static function updateUser($userObject)
    {
        if(self::$useSession) $_SESSION["AJXP_USER"] = $userObject;
        else self::$currentUser = $userObject;
    }
    /**
     * Clear the session
     * @static
     * @return void
     */
    public static function disconnect()
    {
        if (isSet($_SESSION["AJXP_USER"]) || isSet(self::$currentUser)) {
            $user = isSet($_SESSION["AJXP_USER"]) ? $_SESSION["AJXP_USER"] : self::$currentUser;
            $userId = $user->id;
            AJXP_Controller::applyHook("user.before_disconnect", array($user));
            self::clearRememberCookie();
            AJXP_Logger::info(__CLASS__, "Log Out", "");
            unset($_SESSION["AJXP_USER"]);
            if(isSet(self::$currentUser)) unset(self::$currentUser);
            if (ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")) {
                AJXP_Safe::clearCredentials();
            }
            AJXP_Controller::applyHook("user.after_disconnect", array($userId));
        }
    }
    /**
     * Specific operations to perform at boot time
     * @static
     * @param array $START_PARAMETERS A HashTable of parameters to send back to the client
     * @return void
     */
    public static function bootSequence(&$START_PARAMETERS)
    {
        if(AJXP_Utils::detectApplicationFirstRun()) return;
        if(file_exists(AJXP_CACHE_DIR."/admin_counted")) return;
        $rootRole = self::getRole("ROOT_ROLE", false);
        if ($rootRole === false) {
            $rootRole = new AJXP_Role("ROOT_ROLE");
            $rootRole->setLabel("Root Role");
            $rootRole->setAutoApplies(array("standard", "admin"));
            $dashId = "";
            $allRepos = ConfService::getRepositoriesList("all", false);
            foreach ($allRepos as $repositoryId => $repoObject) {
                if($repoObject->isTemplate) continue;
                if($repoObject->getAccessType() == "ajxp_user") $dashId = $repositoryId;
                $gp = $repoObject->getGroupPath();
                if (empty($gp) || $gp == "/") {
                    if ($repoObject->getDefaultRight() != "") {
                        $rootRole->setAcl($repositoryId, $repoObject->getDefaultRight());
                    }
                }
            }
            if(!empty($dashId)) $rootRole->setParameterValue("core.conf", "DEFAULT_START_REPOSITORY", $dashId);
            $paramNodes = AJXP_PluginsService::searchAllManifests("//server_settings/param[@scope]", "node", false, false, true);
            if (is_array($paramNodes) && count($paramNodes)) {
                foreach ($paramNodes as $xmlNode) {
                    $default = $xmlNode->getAttribute("default");
                    if(empty($default)) continue;
                    $parentNode = $xmlNode->parentNode->parentNode;
                    $pluginId = $parentNode->getAttribute("id");
                    if (empty($pluginId)) {
                        $pluginId = $parentNode->nodeName.".".$parentNode->getAttribute("name");
                    }
                    $rootRole->setParameterValue($pluginId, $xmlNode->getAttribute("name"), $default);
                }
            }
            self::updateRole($rootRole);
        }
        $miniRole = self::getRole("MINISITE", false);
        if ($miniRole === false) {
            $rootRole = new AJXP_Role("MINISITE");
            $rootRole->setLabel("Minisite Users");
            $actions = array(
                "access.fs" => array("ajxp_link", "chmod", "purge"),
                "meta.watch" => array("toggle_watch"),
                "conf.serial"=> array("get_bookmarks"),
                "conf.sql"=> array("get_bookmarks"),
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
            self::updateRole($rootRole);
        }
        $miniRole = self::getRole("MINISITE_NODOWNLOAD", false);
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
            self::updateRole($rootRole);
        }
        $miniRole = self::getRole("GUEST", false);
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
            self::updateRole($rootRole);
        }
        $adminCount = self::countAdminUsers();
        if ($adminCount == 0) {
            $authDriver = ConfService::getAuthDriverImpl();
            $adminPass = ADMIN_PASSWORD;
            if (!$authDriver->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
                $adminPass = md5(ADMIN_PASSWORD);
            }
             self::createUser("admin", $adminPass, true);
             if (ADMIN_PASSWORD == INITIAL_ADMIN_PASSWORD) {
                 $userObject = ConfService::getConfStorageImpl()->createUserObject("admin");
                 $userObject->setAdmin(true);
                 self::updateAdminRights($userObject);
                 if (self::changePasswordEnabled()) {
                     $userObject->setLock("pass_change");
                 }
                 $userObject->save("superuser");
                 $START_PARAMETERS["ALERT"] .= "Warning! User 'admin' was created with the initial password '". INITIAL_ADMIN_PASSWORD ."'. \\nPlease log in as admin and change the password now!";
             }
            self::updateUser($userObject);
        } else if ($adminCount == -1) {
            // Here we may come from a previous version! Check the "admin" user and set its right as admin.
            $confStorage = ConfService::getConfStorageImpl();
            $adminUser = $confStorage->createUserObject("admin");
            $adminUser->setAdmin(true);
            $adminUser->save("superuser");
            $START_PARAMETERS["ALERT"] .= "There is an admin user, but without admin right. Now any user can have the administration rights, \\n your 'admin' user was set with the admin rights. Please check that this suits your security configuration.";
        }
        file_put_contents(AJXP_CACHE_DIR."/admin_counted", "true");

    }
    /**
     * If the auth driver implementatino has a logout redirect URL, clear session and return it.
     * @static
     * @param bool $logUserOut
     * @return bool
     */
    public static function getLogoutAddress($logUserOut = true)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        $logout = $authDriver->getLogoutRedirect();
        if ($logUserOut) {
            self::disconnect();
        }
        return $logout;
    }
    /**
     * Compute the default repository id to log the current user
     * @static
     * @return int|string
     */
    public static function getDefaultRootId()
    {
        $loggedUser = self::getLoggedUser();
        if($loggedUser == null) return 0;
        $acls = $loggedUser->mergedRole->listAcls();
        foreach($acls as $key => $right){
            if (!empty($right) && ConfService::getRepositoryById($key) != null) return $key;
        }
        return 0;
        /*
        $repoList = ConfService::getRepositoriesList();
        foreach ($repoList as $rootDirIndex => $rootDirObject) {
            if ($loggedUser->canRead($rootDirIndex."") || $loggedUser->canWrite($rootDirIndex."")) {
                // Warning : do not grant access to admin repository to a non admin, or there will be
                // an "Empty Repository Object" error.
                if ($rootDirObject->getAccessType()=="ajxp_conf" && self::usersEnabled() && !$loggedUser->isAdmin()) {
                    continue;
                }
                if ($rootDirObject->getAccessType() == "ajxp_shared" && count($repoList) > 1) {
                    continue;
                }
                return $rootDirIndex;
            }
        }
        return 0;
        */
    }

    /**
     * Update a user with admin rights and return it
    * @param AbstractAjxpUser $adminUser
     * @return AbstractAjxpUser
    */
    public static function updateAdminRights($adminUser)
    {
        if(ConfService::getCoreConf("SKIP_ADMIN_RIGHTS_ALL_REPOS") !== true){
            $allRepoList = ConfService::getRepositoriesList("all", false);
            foreach ($allRepoList as $repoId => $repoObject) {
                if(!self::allowedForCurrentGroup($repoObject, $adminUser)) continue;
                if($repoObject->hasParent() && $repoObject->getParentId() != $adminUser->getId()) continue;
                $adminUser->personalRole->setAcl($repoId, "rw");
            }
            $adminUser->recomputeMergedRole();
            $adminUser->save("superuser");
        }else if($adminUser->personalRole->getAcl('ajxp_conf') != "rw"){
            $adminUser->personalRole->setAcl('ajxp_conf', 'rw');
            $adminUser->recomputeMergedRole();
            $adminUser->save("superuser");
        }
        return $adminUser;
    }

    /**
     * Update a user object with the default repositories rights
     *
     * @param AbstractAjxpUser $userObject
     */
    public static function updateDefaultRights(&$userObject)
    {
        if (!$userObject->hasParent()) {
            $changes = false;
            $repoList = ConfService::getRepositoriesList();
            foreach ($repoList as $repositoryId => $repoObject) {
                if(!self::allowedForCurrentGroup($repoObject, $userObject)) continue;
                if($repoObject->isTemplate) continue;
                if ($repoObject->getDefaultRight() != "") {
                    $changes = true;
                    $userObject->personalRole->setAcl($repositoryId, $repoObject->getDefaultRight());
                }
            }
            if ($changes) {
                $userObject->recomputeMergedRole();
            }
            $rolesList = self::getRolesList(array(), true);
            foreach ($rolesList as $roleId => $roleObject) {
                if(!self::allowedForCurrentGroup($roleObject, $userObject)) continue;
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
     * @param AbstractAjxpUser $userObject
     */
    public static function updateAutoApplyRole(&$userObject)
    {
        $roles = self::getRolesList(array(), true);
        foreach ($roles as $roleObject) {
            if(!self::allowedForCurrentGroup($roleObject, $userObject)) continue;
            if ($roleObject->autoAppliesTo($userObject->getProfile()) || $roleObject->autoAppliesTo("all")) {
                $userObject->addRole($roleObject);
            }
        }
    }

    public static function updateAuthProvidedData(&$userObject)
    {
        ConfService::getAuthDriverImpl()->updateUserObject($userObject);
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
     * Simple password encoding, should be deported in a more complex/configurable function
     * @static
     * @param $pass
     * @return string
     */
    public static function encodePassword($pass)
    {
        return md5($pass);
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
        if(ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && $userId == "guest") return true;
        $userId = self::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        if ($cookieString) {
            $confDriver = ConfService::getConfStorageImpl();
            $userObject = $confDriver->createUserObject($userId);
            $res = $userObject->checkCookieString($userPass);
            return $res;
        }
        if(!$authDriver->getOptionAsBool("TRANSMIT_CLEAR_PASS")){
            if($authDriver->getSeed(false) != $returnSeed) return false;
        }
        return $authDriver->checkPassword($userId, $userPass, $returnSeed);
    }
    /**
     * Update the password in the auth driver implementation.
     * @static
     * @throws Exception
     * @param $userId
     * @param $userPass
     * @return bool
     */
    public static function updatePassword($userId, $userPass)
    {
        if (strlen($userPass) < ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth")) {
            $messages = ConfService::getMessages();
            throw new Exception($messages[378]);
        }
        $userId = self::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        AJXP_Controller::applyHook("user.before_password_change", array($userId));
        $authDriver->changePassword($userId, $userPass);
        AJXP_Controller::applyHook("user.after_password_change", array($userId));
        if ($authDriver->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            // We can directly update the HA1 version of the WEBDAV Digest
            $realm = ConfService::getCoreConf("WEBDAV_DIGESTREALM");
            $ha1 = md5("{$userId}:{$realm}:{$userPass}");
            $zObj = ConfService::getConfStorageImpl()->createUserObject($userId);
            $wData = $zObj->getPref("AJXP_WEBDAV_DATA");
            if(!is_array($wData)) $wData = array();
            $wData["HA1"] = $ha1;
            $zObj->setPref("AJXP_WEBDAV_DATA", $wData);
            $zObj->save();
        }
        AJXP_Logger::info(__CLASS__,"Update Password", array("user_id"=>$userId));
        return true;
    }
    /**
     * Create a user
     * @static
     * @throws Exception
     * @param $userId
     * @param $userPass
     * @param bool $isAdmin
     * @return null
     * @todo the minlength check is probably causing problem with the bridges
     */
    public static function createUser($userId, $userPass, $isAdmin=false)
    {
        $userId = self::filterUserSensitivity($userId);
        AJXP_Controller::applyHook("user.before_create", array($userId, $userPass, $isAdmin));
        if (!ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && $userId == "guest") {
            throw new Exception("Reserved user id");
        }
        /*
        if (strlen($userPass) < ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth") && $userId != "guest") {
            $messages = ConfService::getMessages();
            throw new Exception($messages[378]);
        }
        */
        $authDriver = ConfService::getAuthDriverImpl();
        $confDriver = ConfService::getConfStorageImpl();
        $authDriver->createUser($userId, $userPass);
        $user = null;
        if ($isAdmin) {
            $user = $confDriver->createUserObject($userId);
            $user->setAdmin(true);
            $user->save("superuser");
        }
        if ($authDriver->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $realm = ConfService::getCoreConf("WEBDAV_DIGESTREALM");
            $ha1 = md5("{$userId}:{$realm}:{$userPass}");
            if (!isSet($user)) {
                $user = $confDriver->createUserObject($userId);
            }
            $wData = $user->getPref("AJXP_WEBDAV_DATA");
            if(!is_array($wData)) $wData = array();
            $wData["HA1"] = $ha1;
            $user->setPref("AJXP_WEBDAV_DATA", $wData);
            $user->save();
        }
        AJXP_Controller::applyHook("user.after_create", array($user));
        AJXP_Logger::info(__CLASS__,"Create User", array("user_id"=>$userId));
        return null;
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
        AJXP_Controller::applyHook("user.before_delete", array($userId));
        $userId = self::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        $authDriver->deleteUser($userId);
        $subUsers = array();
        ConfService::getConfStorageImpl()->deleteUser($userId, $subUsers);
        foreach ($subUsers as $deletedUser) {
            $authDriver->deleteUser($deletedUser);
        }
        AJXP_Controller::applyHook("user.after_delete", array($userId));
        AJXP_Logger::info(__CLASS__,"Delete User", array("user_id"=>$userId, "sub_user" => implode(",", $subUsers)));
        return true;
    }

    private static $groupFiltering = true;

    /**
     * @param boolean $boolean
     */
    public static function setGroupFiltering($boolean){
        self::$groupFiltering = $boolean;
    }

    /**
     * Automatically set the group to the current user base
     * @param $baseGroup
     * @return string
     */
    public static function filterBaseGroup($baseGroup)
    {
        if(!self::$groupFiltering) {
            return $baseGroup;
        }

        $u = self::getLoggedUser();
        // make sure it starts with a slash.
        $baseGroup = "/".ltrim($baseGroup, "/");
        if($u == null) return $baseGroup;
        if ($u->getGroupPath() != "/") {
            if($baseGroup == "/") return $u->getGroupPath();
            else return $u->getGroupPath().$baseGroup;
        } else {
            return $baseGroup;
        }
    }

    /**
     * List children groups of current base
     * @param string $baseGroup
     * @return string[]
     */
    public static function listChildrenGroups($baseGroup = "/")
    {
        return ConfService::getAuthDriverImpl()->listChildrenGroups(self::filterBaseGroup($baseGroup));

    }

    /**
     * Create a new group at the given path
     *
     * @param $baseGroup
     * @param $groupName
     * @param $groupLabel
     * @throws Exception
     */
    public static function createGroup($baseGroup, $groupName, $groupLabel)
    {
        if(empty($groupName)) throw new Exception("Please provide a name for this new group!");
        if(empty($groupLabel)) $groupLabel = $groupName;
        ConfService::getConfStorageImpl()->createGroup(rtrim(self::filterBaseGroup($baseGroup), "/")."/".$groupName, $groupLabel);
    }

    /**
     * Delete group by name
     * @param $baseGroup
     * @param $groupName
     */
    public static function deleteGroup($baseGroup, $groupName)
    {
        ConfService::getConfStorageImpl()->deleteGroup(rtrim(self::filterBaseGroup($baseGroup), "/")."/".$groupName);
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
     * Retrieve the current users who have either read or write access to a repository
     * @param $repositoryId
     * @return array
     */
    public static function getUsersForRepository($repositoryId)
    {
        return ConfService::getConfStorageImpl()->getUsersForRepository($repositoryId);
    }

    /**
     * Retrieve the current users who have either read or write access to a repository
     * @param $repositoryId
     * @param string $rolePrefix
     * @param bool $countOnly
     * @return array
     */
    public static function getRolesForRepository($repositoryId, $rolePrefix = '', $countOnly = false)
    {
        return ConfService::getConfStorageImpl()->getRolesForRepository($repositoryId, $rolePrefix, $countOnly);
    }

    /**
     * Count the number of users who have either read or write access to a repository
     * @param $repositoryId
     * @param bool $details
     * @return Array|int
     */
    public static function countUsersForRepository($repositoryId, $details = false)
    {
        return ConfService::getConfStorageImpl()->countUsersForRepository($repositoryId, $details);
    }

    /**
     * @static
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
        $baseGroup = self::filterBaseGroup($baseGroup);
        $authDriver = ConfService::getAuthDriverImpl();
        $confDriver = ConfService::getConfStorageImpl();
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
        if($countCallback != null){
            call_user_func($countCallback, $index, count($users), "Update users");
        }

        self::$cacheRoles = true;
        self::$roles = null;
        foreach (array_keys($users) as $userId) {
            if(($userId == "guest" && !ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")) || $userId == "ajxp.admin.users" || $userId == "") continue;
            if($regexp != null && !$authDriver->supportsUsersPagination() && !preg_match("/$regexp/i", $userId)) continue;
            $allUsers[$userId] = $confDriver->createUserObject($userId);
            $index++;

            // Callback func for display progression on cli mode
            if($countCallback != null){
                call_user_func($loopCallback, $index);
            }

            if ($paginated) {
                // Make sure to reload all children objects
                foreach ($confDriver->getUserChildren($userId) as $childObject) {
                    $allUsers[$childObject->getId()] = $childObject;
                }
            }
        }
        self::$cacheRoles = false;

        if ($paginated && $cleanLosts) {
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
     * @return int
     */
    public static function findUserPage($baseGroup, $userLogin, $usersPerPage, $offset = 0){
        if(ConfService::getAuthDriverImpl()->supportsUsersPagination()){
            return ConfService::getAuthDriverImpl()->findUserPage($baseGroup, $userLogin, $usersPerPage, $offset);
        }else{
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
    public static function authCountUsers($baseGroup="/", $regexp="", $filterProperty = null, $filterValue = null, $recursive = true)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->getUsersCount(self::filterBaseGroup($baseGroup), $regexp, $filterProperty, $filterValue, $recursive);
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
     * Get Role by Id
     *
     * @param string $roleId
     * @param boolean $createIfNotExists
     * @return AJXP_Role
     */
    public static function getRole($roleId, $createIfNotExists = false)
    {
        $roles = self::getRolesList(array($roleId));
        if(isSet($roles[$roleId])) return $roles[$roleId];
        if ($createIfNotExists) {
            $role = new AJXP_Role($roleId);
            if (self::getLoggedUser()!=null && self::getLoggedUser()->getGroupPath()!=null) {
                $role->setGroupPath(self::getLoggedUser()->getGroupPath());
            }
            self::updateRole($role);
            return $role;
        }
        return false;
    }

    /**
     * Create or update role
     *
     * @param AJXP_Role $roleObject
     */
    public static function updateRole($roleObject, $userObject = null)
    {
        ConfService::getConfStorageImpl()->updateRole($roleObject, $userObject);
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
    }

    public static function filterPluginParameters($pluginId, $params, $repoId = null)
    {
        $logged = self::getLoggedUser();
        if($logged == null) return $params;
        if ($repoId == null) {
            $repo = ConfService::getRepository();
            if($repo!=null) $repoId = $repo->getId();
        }
        if($logged == null || $logged->mergedRole == null) return $params;
        $roleParams = $logged->mergedRole->listParameters();
        if (iSSet($roleParams[AJXP_REPO_SCOPE_ALL][$pluginId])) {
            $params = array_merge($params, $roleParams[AJXP_REPO_SCOPE_ALL][$pluginId]);
        }
        if ($repoId != null && isSet($roleParams[$repoId][$pluginId])) {
            $params = array_merge($params, $roleParams[$repoId][$pluginId]);
        }
        return $params;
    }

    /**
     * @param String $pluginId
     * @param Repository $repository
     * @param String $optionName
     * @param bool $safe
     * @return Mixed
     */
    public static function getFilteredRepositoryOption($pluginId, $repository, $optionName, $safe = false){
        $logged = self::getLoggedUser();
        $test = null;
        if($logged != null){
            $test = $logged->mergedRole->filterParameterValue($pluginId, $optionName, $repository->getId(), null);
            if(!empty($test) && !$safe) $test = AJXP_VarsFilter::filter($test);
        }
        if(empty($test)){
            return $repository->getOption($optionName, $safe);
        }else{
            return $test;
        }
    }

    /**
     * @param AJXP_User $parentUser
     * @return AJXP_Role
     */
    public static function limitedRoleFromParent($parentUser)
    {
        $parentRole = self::getRole("AJXP_USR_/".$parentUser);
        if($parentRole === false) return null;

        // Inherit actions
        $inheritActions = array();
        $cacheInherit = AJXP_PluginsService::getInstance()->loadFromPluginQueriesCache("//server_settings/param[@inherit='true']");
        if ($cacheInherit !== null && is_array($cacheInherit)) {
            $inheritActions = $cacheInherit;
        } else {
            $paramNodes = AJXP_PluginsService::searchAllManifests("//server_settings/param[@inherit='true']", "node", false, false, true);
            if (is_array($paramNodes) && count($paramNodes)) {
                foreach ($paramNodes as $node) {
                    $paramName = $node->getAttribute("name");
                    $pluginId = $node->parentNode->parentNode->getAttribute("id");
                    if(isSet($inheritActions[$pluginId])) $inheritActions[$pluginId] = array();
                    $inheritActions[$pluginId][] = $paramName;
                }
            }
            AJXP_PluginsService::getInstance()->storeToPluginQueriesCache("//server_settings/param[@inherit='true']", $inheritActions);
        }

        // Clear ACL, Keep disabled actions, keep 'inherit' parameters.
        $childRole =  new AJXP_Role("AJXP_PARENT_USR_/");
        $childRole->bunchUpdate(array(
            "ACL"       => array(),
            "ACTIONS"   => $parentRole->listAllActionsStates(),
            "APPLIES"   => array(),
            "PARAMETERS"=> array()));
        $params = $parentRole->listParameters();

        foreach ($params as $scope => $plugData) {
            foreach ($plugData as $pId => $paramData) {
                if(!isSet($inheritActions[$pId])) continue;
                foreach ($paramData as $pName => $pValue) {
                    $childRole->setParameterValue($pId, $pName, $pValue, $scope);
                }
            }
        }

        return $childRole;
    }

    /**
     * Get all defined roles
     * @static
     * @param array $roleIds
     * @param boolean $excludeReserved,
     * @return AJXP_Role[]
     */
    public static function getRolesList($roleIds = array(), $excludeReserved = false)
    {
        if(self::$cacheRoles && !count($roleIds) && $excludeReserved == true && self::$roles != null) {
            return self::$roles;
        }
        $confDriver = ConfService::getConfStorageImpl();
        $roles = $confDriver->listRoles($roleIds, $excludeReserved);
        $repoList = null;
        foreach ($roles as $roleId => $roleObject) {
            if (is_a($roleObject, "AjxpRole")) {
                if($repoList == null) $repoList = ConfService::getRepositoriesList("all");
                $newRole = new AJXP_Role($roleId);
                $newRole->migrateDeprectated($repoList, $roleObject);
                $roles[$roleId] = $newRole;
                self::updateRole($newRole);
            }
        }
        if(self::$cacheRoles && !count($roleIds) && $excludeReserved == true) {
            self::$roles = $roles;
        }
        return $roles;
    }

    /**
     * Check if the current user is allowed to see the GroupPathProvider object
     * @param AjxpGroupPathProvider $provider
     * @param AbstractAjxpUser $userObject
     * @return bool
     */
    public static function allowedForCurrentGroup(AjxpGroupPathProvider $provider, $userObject = null)
    {
        $l = ($userObject == null ? self::getLoggedUser() : $userObject);
        $pGP = $provider->getGroupPath();
        if(empty($pGP)) $pGP = "/";
        if($l == null || $l->getGroupPath() == null || $pGP == null) return true;
        return (strpos($l->getGroupPath(), $pGP, 0) === 0);
    }

    /**
     * Check if the current user can administrate the GroupPathProvider object
     * @param AjxpGroupPathProvider $provider
     * @param AbstractAjxpUser $userObject
     * @return bool
     */
    public static function canAdministrate(AjxpGroupPathProvider $provider, $userObject = null)
    {
        $l = ($userObject == null ? self::getLoggedUser() : $userObject);
        $pGP = $provider->getGroupPath();
        if(empty($pGP)) $pGP = "/";
        if($l == null || $l->getGroupPath() == null || $pGP == null) return true;
        return (strpos($pGP, $l->getGroupPath(), 0) === 0);
    }

    /**
     * Check if the current user can assign administration for the GroupPathProvider object
     * @param AjxpGroupPathProvider $provider
     * @param AbstractAjxpUser $userObject
     * @return bool
     */
    public static function canAssign(AjxpGroupPathProvider $provider, $userObject = null)
    {
        $l = ($userObject == null ? self::getLoggedUser() : $userObject);
        $pGP = $provider->getGroupPath();
        if(empty($pGP)) $pGP = "/";
        if($l == null || $l->getGroupPath() == null || $pGP == null) return true;
        return (strpos($l->getGroupPath(), $pGP, 0) === 0);
    }

}
