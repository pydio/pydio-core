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
     * @return
     */
    public static function generateSecureToken()
    {
        $_SESSION["SECURE_TOKEN"] = AJXP_Utils::generateRandomString(32); //md5(time());
        return $_SESSION["SECURE_TOKEN"];
    }
    /**
     * Get the secure token from the session
     * @static
     * @return string|bool
     */
    public static function getSecureToken()
    {
        return (isSet($_SESSION["SECURE_TOKEN"])?$_SESSION["SECURE_TOKEN"]:FALSE);
    }
    /**
     * Verify a secure token value from the session
     * @static
     * @param string $token
     * @return bool
     */
    public static function checkSecureToken($token)
    {
        if (isSet($_SESSION["SECURE_TOKEN"]) && $_SESSION["SECURE_TOKEN"] == $token) {
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
     * @param string $remoteSessionId
     * @return void
     */
    public static function preLogUser($remoteSessionId = "")
    {
        if(AuthService::getLoggedUser() != null) return ;
        $authDriver = ConfService::getAuthDriverImpl();
        $authDriver->preLogUser($remoteSessionId);
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
            if (AJXP_SERVER_DEBUG) {
                AJXP_Logger::debug("DEBUG : IGNORING BRUTE FORCE ATTEMPTS!");
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
        $loginAttempt = AuthService::getBruteForceLoginArray();
        return !AuthService::checkBruteForceLogin($loginAttempt);
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
        $user = AuthService::getLoggedUser();
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
            if (ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")) {
                $authDriver = ConfService::getAuthDriverImpl();
                if (!$authDriver->userExists("guest")) {
                    AuthService::createUser("guest", "");
                    $guest = $confDriver->createUserObject("guest");
                    $guest->save("superuser");
                }
                AuthService::logUser("guest", null);
                return 1;
            }
            return -1;
        }
        $authDriver = ConfService::getAuthDriverImpl();
        // CHECK USER PASSWORD HERE!
        $loginAttempt = AuthService::getBruteForceLoginArray();
        $bruteForceLogin = AuthService::checkBruteForceLogin($loginAttempt);
        AuthService::setBruteForceLoginArray($loginAttempt);

        if (!$authDriver->userExists($user_id)) {
            if ($bruteForceLogin === FALSE) {
                return -4;
            } else {
                return -1;
            }
        }
        if (!$bypass_pwd) {
            if (!AuthService::checkPassword($user_id, $pwd, $cookieLogin, $returnSeed)) {
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
        AuthService::setBruteForceLoginArray($loginAttempt);

        // Setting session credentials if asked in config
        if (ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")) {
            list($authId, $authPwd) = $authDriver->filterCredentials($user_id, $pwd);
            AJXP_Safe::storeCredentials($authId, $authPwd);
        }

        $user = $confDriver->createUserObject($user_id);
        if ($user->getLock() == "logout") {
            return -1;
        }
        if ($authDriver->isAjxpAdmin($user_id)) {
            $user->setAdmin(true);
        }
        if ($user->isAdmin()) {
            $user = AuthService::updateAdminRights($user);
        } else {
            if (!$user->hasParent() && $user_id != "guest") {
                //$user->setAcl("ajxp_shared", "rw");
            }
        }
        if(self::$useSession) $_SESSION["AJXP_USER"] = $user;
        else self::$currentUser = $user;

        if ($authDriver->autoCreateUser() && !$user->storageExists()) {
            $user->save("superuser"); // make sure update rights now
        }
        AJXP_Logger::info(__CLASS__, "Log In", "");
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
            AuthService::clearRememberCookie();
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
        $rootRole = AuthService::getRole("ROOT_ROLE", false);
        if ($rootRole === false) {
            $rootRole = new AJXP_Role("ROOT_ROLE");
            $rootRole->setLabel("Root Role");
            $rootRole->setAutoApplies(array("standard", "admin"));
            $dashId = "";
            foreach (ConfService::getRepositoriesList("all") as $repositoryId => $repoObject) {
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
            AuthService::updateRole($rootRole);
        }
        $miniRole = AuthService::getRole("MINISITE", false);
        if ($miniRole === false) {
            $rootRole = new AJXP_Role("MINISITE");
            $rootRole->setLabel("Minisite Users");
            $actions = array(
                "access.fs" => array("ajxp_link", "chmod", "purge"),
                "meta.watch" => array("toggle_watch"),
                "conf.serial"=> array("get_bookmarks"),
                "conf.sql"=> array("get_bookmarks"),
                "index.lucene" => array("index"),
                "action.share" => array("share"),
                "gui.ajax" => array("bookmark"),
                "auth.serial" => array("pass_change"),
                "auth.sql" => array("pass_change"),
            );
            foreach ($actions as $pluginId => $acts) {
                foreach ($acts as $act) {
                    $rootRole->setActionState($pluginId, $act, AJXP_REPO_SCOPE_SHARED, false);
                }
            }
            AuthService::updateRole($rootRole);
        }
        $miniRole = AuthService::getRole("MINISITE_NODOWNLOAD", false);
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
            AuthService::updateRole($rootRole);
        }
        $miniRole = AuthService::getRole("GUEST", false);
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
            AuthService::updateRole($rootRole);
        }
        $adminCount = AuthService::countAdminUsers();
        if ($adminCount == 0) {
            $authDriver = ConfService::getAuthDriverImpl();
            $adminPass = ADMIN_PASSWORD;
            if ($authDriver->getOption("TRANSMIT_CLEAR_PASS") !== true) {
                $adminPass = md5(ADMIN_PASSWORD);
            }
             AuthService::createUser("admin", $adminPass, true);
             if (ADMIN_PASSWORD == INITIAL_ADMIN_PASSWORD) {
                 $userObject = ConfService::getConfStorageImpl()->createUserObject("admin");
                 $userObject->setAdmin(true);
                 AuthService::updateAdminRights($userObject);
                 if (AuthService::changePasswordEnabled()) {
                     $userObject->setLock("pass_change");
                 }
                 $userObject->save("superuser");
                 $START_PARAMETERS["ALERT"] .= "Warning! User 'admin' was created with the initial password '". INITIAL_ADMIN_PASSWORD ."'. \\nPlease log in as admin and change the password now!";
             }
            AuthService::updateUser($userObject);
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
        $loggedUser = AuthService::getLoggedUser();
        if($loggedUser == null) return 0;
        $repoList = ConfService::getRepositoriesList();
        foreach ($repoList as $rootDirIndex => $rootDirObject) {
            if ($loggedUser->canRead($rootDirIndex."") || $loggedUser->canWrite($rootDirIndex."")) {
                // Warning : do not grant access to admin repository to a non admin, or there will be
                // an "Empty Repository Object" error.
                if ($rootDirObject->getAccessType()=="ajxp_conf" && AuthService::usersEnabled() && !$loggedUser->isAdmin()) {
                    continue;
                }
                if ($rootDirObject->getAccessType() == "ajxp_shared" && count($repoList) > 1) {
                    continue;
                }
                return $rootDirIndex;
            }
        }
        return 0;
    }

    /**
     * Update a user with admin rights and return it
    * @param AbstractAjxpUser $adminUser
     * @return AbstractAjxpUser
    */
    public static function updateAdminRights($adminUser)
    {
        foreach (ConfService::getRepositoriesList() as $repoId => $repoObject) {
            if(!self::allowedForCurrentGroup($repoObject, $adminUser)) continue;
            if($repoObject->hasParent() && $repoObject->getParentId() != $adminUser->getId()) continue;
            $adminUser->personalRole->setAcl($repoId, "rw");
            $adminUser->recomputeMergedRole();
        }
        $adminUser->save("superuser");
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
            foreach (ConfService::getRepositoriesList() as $repositoryId => $repoObject) {
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
            foreach (AuthService::getRolesList(array(), true) as $roleId => $roleObject) {
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
        foreach (AuthService::getRolesList(array(), true) as $roleId => $roleObject) {
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
        $userId = AuthService::filterUserSensitivity($userId);
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
        $username = AuthService::filterUserSensitivity($username);
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
        $userId = AuthService::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        if ($cookieString) {
            $confDriver = ConfService::getConfStorageImpl();
            $userObject = $confDriver->createUserObject($userId);
            $res = $userObject->checkCookieString($userPass);
            return $res;
        }
        $seed = $authDriver->getSeed(false);
        if($seed != $returnSeed) return false;
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
        $userId = AuthService::filterUserSensitivity($userId);
        $authDriver = ConfService::getAuthDriverImpl();
        $authDriver->changePassword($userId, $userPass);
        if ($authDriver->getOption("TRANSMIT_CLEAR_PASS") === true) {
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
        $userId = AuthService::filterUserSensitivity($userId);
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
        if ($authDriver->getOption("TRANSMIT_CLEAR_PASS") === true) {
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
        $userId = AuthService::filterUserSensitivity($userId);
        AJXP_Controller::applyHook("user.before_delete", array($userId));
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

    public static function listChildrenGroups($baseGroup = "/")
    {
        return ConfService::getAuthDriverImpl()->listChildrenGroups(self::filterBaseGroup($baseGroup));

    }

    public static function createGroup($baseGroup, $groupName, $groupLabel)
    {
        if(empty($groupName)) throw new Exception("Please provide a name for this new group!");
        if(empty($groupLabel)) $groupLabel = $groupName;
        ConfService::getConfStorageImpl()->createGroup(rtrim(self::filterBaseGroup($baseGroup), "/")."/".$groupName, $groupLabel);
    }

    public static function deleteGroup($baseGroup, $groupName)
    {
        ConfService::getConfStorageImpl()->deleteGroup(rtrim(self::filterBaseGroup($baseGroup), "/")."/".$groupName);
    }

    public static function getChildrenUsers($parentUserId)
    {
        return ConfService::getConfStorageImpl()->getUserChildren($parentUserId);
    }

    public static function getUsersForRepository($repositoryId)
    {
        return ConfService::getConfStorageImpl()->getUsersForRepository($repositoryId);
    }

    /**
     * @static
     * @param string $baseGroup
     * @param null $regexp
     * @param $offset
     * @param $limit
     * @param bool $cleanLosts
     * @return array
     */
    public static function listUsers($baseGroup = "/", $regexp = null, $offset = -1, $limit = -1, $cleanLosts = true)
    {
        $baseGroup = self::filterBaseGroup($baseGroup);
        $authDriver = ConfService::getAuthDriverImpl();
        $confDriver = ConfService::getConfStorageImpl();
        $allUsers = array();
        $paginated = false;
        if (($regexp != null || $offset != -1 || $limit != -1) && $authDriver->supportsUsersPagination()) {
            $users = $authDriver->listUsersPaginated($baseGroup, $regexp, $offset, $limit);
            $paginated = true;
        } else {
            $users = $authDriver->listUsers($baseGroup);
        }
        foreach (array_keys($users) as $userId) {
            if(($userId == "guest" && !ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")) || $userId == "ajxp.admin.users" || $userId == "") continue;
            if($regexp != null && !$authDriver->supportsUsersPagination() && !preg_match("/$regexp/i", $userId)) continue;
            $allUsers[$userId] = $confDriver->createUserObject($userId);
            if ($paginated) {
                // Make sure to reload all children objects
                foreach ($confDriver->getUserChildren($userId) as $childObject) {
                    $allUsers[$childObject->getId()] = $childObject;
                }
            }
        }
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

    public static function authSupportsPagination()
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->supportsUsersPagination();
    }

    public static function authCountUsers($baseGroup="/", $regexp="", $filterProperty = null, $filterValue = null)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->getUsersCount($baseGroup, $regexp, $filterProperty, $filterValue);
    }

    public static function getAuthScheme($userName)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->getAuthScheme($userName);
    }

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
     * @param AJXP_Role $parentRole
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
        //if(isSet(self::$roles)) return self::$roles;
        $confDriver = ConfService::getConfStorageImpl();
        self::$roles = $confDriver->listRoles($roleIds, $excludeReserved);
        $repoList = null;
        foreach (self::$roles as $roleId => $roleObject) {
            if (is_a($roleObject, "AjxpRole")) {
                if($repoList == null) $repoList = ConfService::getRepositoriesList("all");
                $newRole = new AJXP_Role($roleId);
                $newRole->migrateDeprectated($repoList, $roleObject);
                self::$roles[$roleId] = $newRole;
                self::updateRole($newRole);
            }
        }
        return self::$roles;
    }

    public static function allowedForCurrentGroup(AjxpGroupPathProvider $provider, $userObject = null)
    {
        $l = ($userObject == null ? self::getLoggedUser() : $userObject);
        $pGP = $provider->getGroupPath();
        if(empty($pGP)) $pGP = "/";
        if($l == null || $l->getGroupPath() == null || $pGP == null) return true;
        return (strpos($l->getGroupPath(), $pGP, 0) === 0);
    }

    public static function canAdministrate(AjxpGroupPathProvider $provider, $userObject = null)
    {
        $l = ($userObject == null ? self::getLoggedUser() : $userObject);
        $pGP = $provider->getGroupPath();
        if(empty($pGP)) $pGP = "/";
        if($l == null || $l->getGroupPath() == null || $pGP == null) return true;
        return (strpos($pGP, $l->getGroupPath(), 0) === 0);
    }

    public static function canAssign(AjxpGroupPathProvider $provider, $userObject = null)
    {
        $l = ($userObject == null ? self::getLoggedUser() : $userObject);
        $pGP = $provider->getGroupPath();
        if(empty($pGP)) $pGP = "/";
        if($l == null || $l->getGroupPath() == null || $pGP == null) return true;
        return (strpos($l->getGroupPath(), $pGP, 0) === 0);
    }

}
