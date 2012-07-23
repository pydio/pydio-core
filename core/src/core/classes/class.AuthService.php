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
 * @package info.ajaxplorer.core
 */
/**
 * Static access to the authentication mechanism. Encapsulates the authDriver implementation
 */
class AuthService
{
	static $roles;
	/**
     * Whether the whole users management system is enabled or not.
     * @static
     * @return bool
     */
	static function usersEnabled()
	{
		return ConfService::getCoreConf("ENABLE_USERS", "auth");
	}
	/**
     * Whether the current auth driver supports password update or not
     * @static
     * @return void
     */
	static function changePasswordEnabled()
	{
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->passwordsEditable();
	}
	/**
     * Get a unique seed from the current auth driver
     * @static
     * @return int|string
     */
	static function generateSeed(){
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->getSeed(true);
	}
	
	/**
     * Put a secure token in the session
     * @static
     * @return
     */
	static function generateSecureToken(){
		$_SESSION["SECURE_TOKEN"] = md5(time());
		return $_SESSION["SECURE_TOKEN"];
	}
	/**
     * Get the secure token from the session
     * @static
     * @return string|bool
     */
	static function getSecureToken(){
		return (isSet($_SESSION["SECURE_TOKEN"])?$_SESSION["SECURE_TOKEN"]:FALSE);
	}
	/**
     * Verify a secure token value from the session
     * @static
     * @param string $token
     * @return bool
     */
	static function checkSecureToken($token){
		if(isSet($_SESSION["SECURE_TOKEN"]) && $_SESSION["SECURE_TOKEN"] == $token){
			return true;
		}
		return false;
	}
	
	/**
	 * Get the currently logged user object
	 * @return AbstractAjxpUser
	 */
	static function getLoggedUser()
	{
		if(isSet($_SESSION["AJXP_USER"])) return $_SESSION["AJXP_USER"];
		return null;
	}
	/**
     * Call the preLogUser() functino on the auth driver implementation
     * @static
     * @param string $remoteSessionId
     * @return
     */
	static function preLogUser($remoteSessionId = "")
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
    static function getBruteForceLoginArray()
    {
        $failedLog = AJXP_Utils::getAjxpTmpDir()."/failedAJXP.log";
        $loginAttempt = @file_get_contents($failedLog);
        $loginArray = unserialize($loginAttempt);
        $ret = array();
        $curTime = time();
        if (is_array($loginArray)){
	        // Filter the array (all old time are cleaned)
            foreach($loginArray as $key => $login)
            {
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
    static function setBruteForceLoginArray($loginArray)
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
    static function checkBruteForceLogin(&$loginArray)
    {
    	$serverAddress = "";
    	if(isSet($_SERVER['REMOTE_ADDR'])){
    		$serverAddress = $_SERVER['REMOTE_ADDR'];
    	}else{
    		$serverAddress = $_SERVER['SERVER_ADDR'];
    	}
    	$login = null;
    	if(isSet($loginArray[$serverAddress])){
	        $login = $loginArray[$serverAddress];		
    	}
        if (is_array($login)){
            $login["count"]++;
        } else $login = array("count"=>1, "time"=>time());
        $loginArray[$serverAddress] = $login;
        if ($login["count"] > 3) {
            if(AJXP_SERVER_DEBUG){
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
    static function suspectBruteForceLogin(){
        $loginAttempt = AuthService::getBruteForceLoginArray();
        return !AuthService::checkBruteForceLogin($loginAttempt);
    }

    static function filterUserSensitivity($user){
        if(!ConfService::getCoreConf("CASE_SENSITIVE", "auth")){
            return strtolower($user);
        }else{
            return $user;
        }
    }

    static function ignoreUserCase(){
        return !ConfService::getCoreConf("CASE_SENSITIVE", "auth");
    }

    /**
     * @static
     * @param AbstractAjxpUser $user
     */
    static function refreshRememberCookie($user){
        $current = $_COOKIE["AjaXplorer-remember"];
        if(!empty($current)){
            $user->invalidateCookieString(substr($current, strpos($current, ":")+1));
        }
        $rememberPass = $user->getCookieString();
        setcookie("AjaXplorer-remember", $user->id.":".$rememberPass, time()+3600*24*10);
    }

    /**
     * @static
     * @return bool
     */
    static function hasRememberCookie(){
        return (isSet($_COOKIE["AjaXplorer-remember"]) && !empty($_COOKIE["AjaXplorer-remember"]));
    }

    /**
     * @static
     * Warning, must be called before sending other headers!
     */
    static function clearRememberCookie(){
        $current = $_COOKIE["AjaXplorer-remember"];
        $user = AuthService::getLoggedUser();
        if(!empty($current) && $user != null){
            $user->invalidateCookieString(substr($current, strpos($current, ":")+1));
        }
        setcookie("AjaXplorer-remember", "", time()-3600);
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
	static function logUser($user_id, $pwd, $bypass_pwd = false, $cookieLogin = false, $returnSeed="")
	{
        $user_id = self::filterUserSensitivity($user_id);
        if($cookieLogin && !isSet($_COOKIE["AjaXplorer-remember"])){
            return -5; // SILENT IGNORE
        }
        if($cookieLogin){
            list($user_id, $pwd) = explode(":", $_COOKIE["AjaXplorer-remember"]);
        }
		$confDriver = ConfService::getConfStorageImpl();
		if($user_id == null)
		{
			if(isSet($_SESSION["AJXP_USER"]) && is_object($_SESSION["AJXP_USER"])) return 1; 
			if(ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth"))
			{
				$authDriver = ConfService::getAuthDriverImpl();
				if(!$authDriver->userExists("guest"))
				{
					AuthService::createUser("guest", "");
					$guest = $confDriver->createUserObject("guest");
					$guest->save("superuser");
				}
				AuthService::logUser("guest", null);
				return 1;
			}
			return 0;
		}
		$authDriver = ConfService::getAuthDriverImpl();
		// CHECK USER PASSWORD HERE!
        $loginAttempt = AuthService::getBruteForceLoginArray();
        $bruteForceLogin = AuthService::checkBruteForceLogin($loginAttempt);
        AuthService::setBruteForceLoginArray($loginAttempt);

		if(!$authDriver->userExists($user_id)){
	        if ($bruteForceLogin === FALSE){
	            return -4;    
	        }else{
				return 0;
	        }
        }
		if(!$bypass_pwd){
			if(!AuthService::checkPassword($user_id, $pwd, $cookieLogin, $returnSeed)){
		        if ($bruteForceLogin === FALSE){
		            return -4;    
		        }else{
                    if($cookieLogin) return -5;
					return -1;
		        }
			}
		}
        // Successful login attempt
        unset($loginAttempt[$_SERVER["REMOTE_ADDR"]]);
        AuthService::setBruteForceLoginArray($loginAttempt);

        // Setting session credentials if asked in config
        if(ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")) {
        	list($authId, $authPwd) = $authDriver->filterCredentials($user_id, $pwd);
        	AJXP_Safe::storeCredentials($authId, $authPwd);
        }

        $user = $confDriver->createUserObject($user_id);
		if($authDriver->isAjxpAdmin($user_id)){
			$user->setAdmin(true);
		}
		if($user->isAdmin())
		{
			$user = AuthService::updateAdminRights($user);
		}
		else{
			if(!$user->hasParent() && $user_id != "guest"){
				//$user->setRight("ajxp_shared", "rw");
			}
		}
		$_SESSION["AJXP_USER"] = $user;
		if($authDriver->autoCreateUser() && !$user->storageExists()){
			$user->save("superuser"); // make sure update rights now
		}
		AJXP_Logger::logAction("Log In");
		return 1;
	}
	/**
     * Store the object in the session
     * @static
     * @param $userObject
     * @return void
     */
	static function updateUser($userObject)
	{
		$_SESSION["AJXP_USER"] = $userObject;
	}
	/**
     * Clear the session
     * @static
     * @return void
     */
	static function disconnect()
	{
		if(isSet($_SESSION["AJXP_USER"])){
            AuthService::clearRememberCookie();
			AJXP_Logger::logAction("Log Out");
			unset($_SESSION["AJXP_USER"]);
			if(ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")){
				AJXP_Safe::clearCredentials();
			}
		}
	}
	/**
     * Specific operations to perform at boot time
     * @static
     * @param array $START_PARAMETERS A HashTable of parameters to send back to the client
     * @return void
     */
	public static function bootSequence(&$START_PARAMETERS){

        if(@file_exists(AJXP_CACHE_DIR."/admin_counted")) return;
		$adminCount = AuthService::countAdminUsers();
		if($adminCount == 0){
			$authDriver = ConfService::getAuthDriverImpl();
			$adminPass = ADMIN_PASSWORD;
			if($authDriver->getOption("TRANSMIT_CLEAR_PASS") !== true){
				$adminPass = md5(ADMIN_PASSWORD);
			}
			 AuthService::createUser("admin", $adminPass, true);
			 if(ADMIN_PASSWORD == INITIAL_ADMIN_PASSWORD)
			 {
				 $START_PARAMETERS["ALERT"] .= "Warning! User 'admin' was created with the initial common password 'admin'. \\nPlease log in as admin and change the password now!";
			 }
		}else if($adminCount == -1){
			// Here we may come from a previous version! Check the "admin" user and set its right as admin.
			$confStorage = ConfService::getConfStorageImpl();
			$adminUser = $confStorage->createUserObject("admin"); 
			$adminUser->setAdmin(true);
			$adminUser->save("superuser");
			$START_PARAMETERS["ALERT"] .= "There is an admin user, but without admin right. Now any user can have the administration rights, \\n your 'admin' user was set with the admin rights. Please check that this suits your security configuration.";
    	}
        @file_put_contents(AJXP_CACHE_DIR."/admin_counted", "true");
	}
    /**
     * If the auth driver implementatino has a logout redirect URL, clear session and return it.
     * @static
     * @param bool $logUserOut
     * @return bool
     */
    static function getLogoutAddress($logUserOut = true)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        $logout = $authDriver->getLogoutRedirect();
        if($logUserOut && isSet($_SESSION["AJXP_USER"])){
			AJXP_Logger::logAction("Log Out");
			unset($_SESSION["AJXP_USER"]);
			if(ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")){
				AJXP_Safe::clearCredentials();
			}			
		}
        return $logout;
    }
	/**
     * Compute the default repository id to log the current user
     * @static
     * @return int|string
     */
	static function getDefaultRootId()
	{
		$loggedUser = AuthService::getLoggedUser();
		if($loggedUser == null) return 0;
		$repoList = ConfService::getRootDirsList();
		foreach ($repoList as $rootDirIndex => $rootDirObject)
		{			
			if($loggedUser->canRead($rootDirIndex."") || $loggedUser->canWrite($rootDirIndex."")) {
				// Warning : do not grant access to admin repository to a non admin, or there will be 
				// an "Empty Repository Object" error.
				if($rootDirObject->getAccessType()=="ajxp_conf" && AuthService::usersEnabled() && !$loggedUser->isAdmin()){
					continue;
				}
				if($rootDirObject->getAccessType() == "ajxp_shared" && count($repoList) > 1){
					continue;
				}
				return $rootDirIndex;
			}
		}
		return 0;
	}
	
	/**
     * Update a user with admin rights and return it
	* @param AJXP_User $adminUser
     * @return AJXP_User
	*/
	static function updateAdminRights($adminUser)
	{
		foreach (array_keys(ConfService::getRootDirsList()) as $rootDirIndex)
		{			
			$adminUser->setRight($rootDirIndex, "rw");
		}
		$adminUser->save();
		return $adminUser;
	}
	
	/**
	 * Update a user object with the default repositories rights
	 *
	 * @param AbstractAjxpUser $userObject
	 */
	static function updateDefaultRights(&$userObject){
		if(!$userObject->hasParent()){
			foreach (ConfService::getRepositoriesList() as $repositoryId => $repoObject)
			{
                if($repoObject->isTemplate) continue;
				if($repoObject->getDefaultRight() != ""){
					$userObject->setRight($repositoryId, $repoObject->getDefaultRight());
				}
			}
            foreach(AuthService::getRolesList() as $roleId => $roleObject){
                if($roleObject->isDefault()){
                    $userObject->addRole($roleId);
                }
            }
		}
	}
	/**
     * Use driver implementation to check whether the user exists or not.
     * @static
     * @param $userId
     * @return void
     */
	static function userExists($userId)
	{
        if($userId == "guest" && !ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")){
            return false;
        }
        $userId = AuthService::filterUserSensitivity($userId);
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->userExists($userId);
	}

    /**
     * Make sure a user id is not reserved for low-level tasks (currently "guest" and "shared").
     * @static
     * @param String $username
     * @return bool
     */
    static function isReservedUserId($username){
        $username = AuthService::filterUserSensitivity($username);
        return in_array($username, array("guest", "shared"));
    }

	/**
     * Simple password encoding, should be deported in a more complex/configurable function
     * @static
     * @param $pass
     * @return string
     */
	static function encodePassword($pass){
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
	static function checkPassword($userId, $userPass, $cookieString = false, $returnSeed = "")
	{
		if(ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && $userId == "guest") return true;
        $userId = AuthService::filterUserSensitivity($userId);
		$authDriver = ConfService::getAuthDriverImpl();
		if($cookieString){		
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
	static function updatePassword($userId, $userPass)
	{
		if(strlen($userPass) < ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth")){
			$messages = ConfService::getMessages();
			throw new Exception($messages[378]);
		}
        $userId = AuthService::filterUserSensitivity($userId);
		$authDriver = ConfService::getAuthDriverImpl();
		$authDriver->changePassword($userId, $userPass);
		AJXP_Logger::logAction("Update Password", array("user_id"=>$userId));
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
	static function createUser($userId, $userPass, $isAdmin=false)
	{
        $userId = AuthService::filterUserSensitivity($userId);
        AJXP_Controller::applyHook("user.before_create", array($userId, $userPass, $isAdmin));
        if(!ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && $userId == "guest"){
            throw new Exception("Reserved user id");
        }
		if(strlen($userPass) < ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth") && $userId != "guest"){
			$messages = ConfService::getMessages();
			throw new Exception($messages[378]);
		}
		$authDriver = ConfService::getAuthDriverImpl();
		$confDriver = ConfService::getConfStorageImpl();
		$authDriver->createUser($userId, $userPass);
		if($isAdmin){
			$user = $confDriver->createUserObject($userId);
			$user->setAdmin(true);			
			$user->save("superuser");
		}
        AJXP_Controller::applyHook("user.after_create", array($user));
		AJXP_Logger::logAction("Create User", array("user_id"=>$userId));
		return null;
	}
	/**
     * Detect the number of admin users
     * @static
     * @return int|void
     */
	static function countAdminUsers(){
		$confDriver = ConfService::getConfStorageImpl();
		$auth = ConfService::getAuthDriverImpl();	
		$count = $confDriver->countAdminUsers();
		if(!$count && $auth->userExists("admin") && $confDriver->getName() == "serial"){
			return -1;
		}
		return $count;
	}

    /**
     * Delete a user in the auth driver impl
     * @static
     * @param $userId
     * @return bool
     */
	static function deleteUser($userId)
	{
        $userId = AuthService::filterUserSensitivity($userId);
        AJXP_Controller::applyHook("user.before_delete", array($userId));
		$authDriver = ConfService::getAuthDriverImpl();
		$authDriver->deleteUser($userId);
		$subUsers = array();
		AJXP_User::deleteUser($userId, $subUsers);
		foreach ($subUsers as $deletedUser){
			$authDriver->deleteUser($deletedUser);
		}

        AJXP_Controller::applyHook("user.after_delete", array($userId));
        AJXP_Logger::logAction("Delete User", array("user_id"=>$userId, "sub_user" => implode(",", $subUsers)));
		return true;
	}
	/**
     * Call the auth driver impl to list all existing users
     * @static
     * @return array
     */
	static function listUsers($regexp = null, $offset = -1, $limit = -1, $cleanLosts = true)
	{
		$authDriver = ConfService::getAuthDriverImpl();
		$confDriver = ConfService::getConfStorageImpl();
		$allUsers = array();
        $paginated = false;
        if(($regexp != null || $offset != -1 || $limit != -1) && $authDriver->supportsUsersPagination()){
            $users = $authDriver->listUsersPaginated($regexp, $offset, $limit);
            $paginated = true;
        }else{
            $users = $authDriver->listUsers();
        }
		foreach (array_keys($users) as $userId)
		{
			if(($userId == "guest" && !ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")) || $userId == "ajxp.admin.users" || $userId == "") continue;
            if($regexp != null && !$authDriver->supportsUsersPagination() && !preg_match($regexp, $userId)) continue;
			$allUsers[$userId] = $confDriver->createUserObject($userId);
            if($paginated){
                // Make sure to reload all children objects
                foreach($confDriver->getUserChildren($userId) as $childObject){
                    $allUsers[$childObject->getId()] = $childObject;
                }
            }
		}
        if($paginated && $cleanLosts){
            // Remove 'lost' items (children without parents).
            foreach($allUsers as $id => $object){
                if($object->hasParent() && !array_key_exists($object->getParent(), $allUsers)){
                    unset($allUsers[$id]);
                }
            }
        }
		return $allUsers;
	}

    static function authSupportsPagination(){
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->supportsUsersPagination();
    }

    static function authCountUsers(){
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->getUsersCount();
    }

    static function getAuthScheme($userName){
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->getAuthScheme($userName);
    }

    static function driverSupportsAuthSchemes(){
        $authDriver = ConfService::getAuthDriverImpl();
        return $authDriver->supportsAuthSchemes();
    }

	/**
	 * Get Role by Id
	 *
	 * @param string $roleId
	 * @return AjxpRole
	 */
	static function getRole($roleId){
		$roles = self::getRolesList();
		if(isSet($roles[$roleId])) return $roles[$roleId];
		return false;
	}
	
	/**
	 * Create or update role
	 *
	 * @param AjxpRole $roleObject
	 */
	static function updateRole($roleObject){
		$roles = self::getRolesList();
		$roles[$roleObject->getId()] = $roleObject;
		self::saveRolesList($roles);
	}
	/**
     * Delete a role by its id
     * @static
     * @param string $roleId
     * @return void
     */
	static function deleteRole($roleId){
		$roles = self::getRolesList();
		if(isSet($roles[$roleId])){
			unset($roles[$roleId]);
			self::saveRolesList($roles);
		}
	}
	/**
     * Get all defined roles
     * @static
     * @return array
     */
	static function getRolesList(){
		if(isSet(self::$roles)) return self::$roles;
		$confDriver = ConfService::getConfStorageImpl();
		self::$roles = $confDriver->listRoles();
		return self::$roles;
	}
	/**
     * Update the roles list
     * @static
     * @param array $roles
     * @return void
     */
	static function saveRolesList($roles){
		$confDriver = ConfService::getConfStorageImpl();
		$confDriver->saveRoles($roles);
		self::$roles = $roles;		
	}
	
}

?>
