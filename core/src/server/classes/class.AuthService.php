<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Users management for authentification.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class AuthService
{
	static $roles;
	
	static function usersEnabled()
	{
		return ENABLE_USERS;
	}
	
	static function changePasswordEnabled()
	{
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->passwordsEditable();
	}
	
	static function generateSeed(){
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->getSeed(true);
	}
	
	
	static function generateSecureToken(){
		$_SESSION["SECURE_TOKEN"] = md5(time());
		return $_SESSION["SECURE_TOKEN"];
	}
	
	static function getSecureToken(){
		return (isSet($_SESSION["SECURE_TOKEN"])?$_SESSION["SECURE_TOKEN"]:FALSE);
	}
	
	static function checkSecureToken($token){
		if(isSet($_SESSION["SECURE_TOKEN"]) && $_SESSION["SECURE_TOKEN"] == $token){
			return true;
		}
		return false;
	}
	
	/**
	 * Get the currently logged user object
	 *
	 * @return AbstractAjxpUser
	 */
	static function getLoggedUser()
	{
		if(isSet($_SESSION["AJXP_USER"])) return $_SESSION["AJXP_USER"];
		return null;
	}
	
	static function preLogUser($remoteSessionId = "")
	{
		if(AuthService::getLoggedUser() != null) return ;
		$authDriver = ConfService::getAuthDriverImpl();
		$authDriver->preLogUser($remoteSessionId);
		return ;
	}

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
    static function setBruteForceLoginArray($loginArray)
    {
        $failedLog = AJXP_Utils::getAjxpTmpDir()."/failedAJXP.log";
        @file_put_contents($failedLog, serialize($loginArray));
    }

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
        if ($login["count"] > 3) return FALSE;
        return TRUE;
    }
    
    static function suspectBruteForceLogin(){
        $loginAttempt = AuthService::getBruteForceLoginArray();
        return !AuthService::checkBruteForceLogin($loginAttempt);
    }

	static function logUser($user_id, $pwd, $bypass_pwd = false, $cookieLogin = false, $returnSeed="")
	{		
		$confDriver = ConfService::getConfStorageImpl();
		if($user_id == null)
		{
			if(isSet($_SESSION["AJXP_USER"]) && is_object($_SESSION["AJXP_USER"])) return 1; 
			if(ALLOW_GUEST_BROWSING)
			{
				$authDriver = ConfService::getAuthDriverImpl();
				if(!$authDriver->userExists("guest"))
				{
					AuthService::createUser("guest", "");
					$guest = $confDriver->createUserObject("guest");
					$guest->save();
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
					return -1;
		        }
			}
		}
        // Successful login attempt
        unset($loginAttempt[$_SERVER["REMOTE_ADDR"]]);
        AuthService::setBruteForceLoginArray($loginAttempt);

        // Setting session credentials if asked in config
        if(ConfService::getConf("SESSION_SET_CREDENTIALS")) {
        	$_SESSION["AJXP_SESSION_REMOTE_USER"] = $user_id;
        	$_SESSION["AJXP_SESSION_REMOTE_PASS"] = $pwd;
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
			$user->save();
		}
		AJXP_Logger::logAction("Log In");
		return 1;
	}
	
	static function updateUser($userObject)
	{
		$_SESSION["AJXP_USER"] = $userObject;
	}
	
	static function disconnect()
	{
		if(isSet($_SESSION["AJXP_USER"])){
			AJXP_Logger::logAction("Log Out");
			unset($_SESSION["AJXP_USER"]);
			if(ConfService::getConf("SESSION_SET_CREDENTIALS")){
				unset($_SESSION["AJXP_SESSION_REMOTE_USER"]);
				unset($_SESSION["AJXP_SESSION_REMOTE_PASS"]);			
			}
		}
	}
	
	public static function bootSequence(&$START_PARAMETERS){
		if(!is_readable(USERS_DIR)) $START_PARAMETERS["ALERT"] = "Warning, the users directory is not readable!";
		else if(!is_writeable(USERS_DIR)) $START_PARAMETERS["ALERT"] = "Warning, the users directory is not writeable!";
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
			$adminUser->save();
			$START_PARAMETERS["ALERT"] .= "You may come from a previous version. Now any user can have the administration rights, \\n your 'admin' user was set with the admin rights. Please check that this suits your security configuration.";
		}
	}
    
    static function getLogoutAddress($logUserOut = true)
    {
        $authDriver = ConfService::getAuthDriverImpl();
        $logout = $authDriver->getLogoutRedirect();
        if($logUserOut && isSet($_SESSION["AJXP_USER"])){
			AJXP_Logger::logAction("Log Out");
			unset($_SESSION["AJXP_USER"]);
			if(ConfService::getConf("SESSION_SET_CREDENTIALS")){
				unset($_SESSION["AJXP_SESSION_REMOTE_USER"]);
				unset($_SESSION["AJXP_SESSION_REMOTE_PASS"]);			
			}			
		}
        return $logout;
    }
	
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
				if($rootDirObject->getAccessType()=="ajxp_conf" && ENABLE_USERS && !$loggedUser->isAdmin()){
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
	* @param AJXP_User $adminUser
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
				if($repoObject->getDefaultRight() != ""){
					$userObject->setRight($repositoryId, $repoObject->getDefaultRight());
				}
			}
		}
	}
	
	static function userExists($userId)
	{
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->userExists($userId);
	}
	
	static function encodePassword($pass){
		return md5($pass);
	}
	
	static function checkPassword($userId, $userPass, $cookieString = false, $returnSeed = "")
	{
		if($userId == "guest") return true;		
		$authDriver = ConfService::getAuthDriverImpl();
		if($cookieString){		
			$confDriver = ConfService::getConfStorageImpl();
			$userObject = $confDriver->createUserObject($userId);	
			$userCookieString = $userObject->getCookieString();
			return ($userCookieString == $userPass);
		}		
		$seed = $authDriver->getSeed(false);
		if($seed != $returnSeed) return false;					
		return $authDriver->checkPassword($userId, $userPass, $returnSeed);
	}
	
	static function updatePassword($userId, $userPass)
	{
		if(defined('AJXP_PASSWORD_MINLENGTH') && strlen($userPass) < AJXP_PASSWORD_MINLENGTH){
			$messages = ConfService::getMessages();
			throw new Exception($messages[378]);
		}
		$authDriver = ConfService::getAuthDriverImpl();
		$authDriver->changePassword($userId, $userPass);
		AJXP_Logger::logAction("Update Password", array("user_id"=>$userId));
		return true;
	}
	
	static function createUser($userId, $userPass, $isAdmin=false)
	{
		if(defined('AJXP_PASSWORD_MINLENGTH') && strlen($userPass) < AJXP_PASSWORD_MINLENGTH){
			$messages = ConfService::getMessages();
			throw new Exception($messages[378]);
		}
		$authDriver = ConfService::getAuthDriverImpl();
		$confDriver = ConfService::getConfStorageImpl();
		$authDriver->createUser($userId, $userPass);
		if($isAdmin){
			$user = $confDriver->createUserObject($userId);
			$user->setAdmin(true);			
			$user->save();
		}
		AJXP_Logger::logAction("Create User", array("user_id"=>$userId));
		return null;
	}
	
	static function countAdminUsers(){
		$confDriver = ConfService::getConfStorageImpl();
		$auth = ConfService::getAuthDriverImpl();	
		$count = $confDriver->countAdminUsers();
		if(!$count && $auth->userExists("admin")){
			return -1;
		}
		return $count;
	}
		
	static function deleteUser($userId)
	{
		$authDriver = ConfService::getAuthDriverImpl();
		$authDriver->deleteUser($userId);
		$subUsers = array();
		AJXP_User::deleteUser($userId, $subUsers);
		foreach ($subUsers as $deletedUser){
			$authDriver->deleteUser($deletedUser);
		}

		AJXP_Logger::logAction("Delete User", array("user_id"=>$userId, "sub_user" => implode(",", $subUsers)));
		return true;
	}
	
	static function listUsers()
	{
		$authDriver = ConfService::getAuthDriverImpl();		
		$confDriver = ConfService::getConfStorageImpl();
		$allUsers = array();
		$users = $authDriver->listUsers();
		foreach (array_keys($users) as $userId)
		{
			if(($userId == "guest" && !ALLOW_GUEST_BROWSING) || $userId == "ajxp.admin.users" || $userId == "") continue;
			$allUsers[$userId] = $confDriver->createUserObject($userId);
		}
		return $allUsers;
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
	
	static function deleteRole($roleId){
		$roles = self::getRolesList();
		if(isSet($roles[$roleId])){
			unset($roles[$roleId]);
			self::saveRolesList($roles);
		}
	}
	
	static function getRolesList(){
		if(isSet(self::$roles)) return self::$roles;
		$confDriver = ConfService::getConfStorageImpl();
		self::$roles = $confDriver->listRoles();
		return self::$roles;
	}
	
	static function saveRolesList($roles){
		$confDriver = ConfService::getConfStorageImpl();
		$confDriver->saveRoles($roles);
		self::$roles = $roles;		
	}
	
}

?>
