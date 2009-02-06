<?php

class AuthService
{
	function usersEnabled()
	{
		return ENABLE_USERS;
	}
	
	function changePasswordEnabled()
	{
		return (AUTH_MODE == "ajaxplorer");
	}
	
	function generateSeed(){
		$seed = md5(time());
		$_SESSION["AJXP_CURRENT_SEED"] = $seed;
		return $seed;
	}
	
	function encodeCookiePass($user, $pass = null){
		if($pass == null){
			$users = AuthService::loadLocalUsersList();
			$pass = $users[$user];
		}
		return md5($user.":".$pass.":ajxp");
	}
		
	function getLoggedUser()
	{
		if(isSet($_SESSION["AJXP_USER"])) return $_SESSION["AJXP_USER"];
		return null;
	}
	
	function preLogUser($remoteSessionId = "")
	{
		if(AuthService::getLoggedUser() != null) return ;
		if(AUTH_MODE == "local_http")
		{
			$localHttpLogin = $_SERVER["REMOTE_USER"];
			if(isSet($localHttpLogin) && AuthService::userExists($localHttpLogin))
			{
				AuthService::logUser($localHttpLogin, "", true);
			}
		}
		else if(AUTH_MODE == "remote" && $remoteSessionId != "")
		{
			require_once("class.HttpClient.php");
			$client = new HttpClient(AUTH_MODE_REMOTE_SERVER, AUTH_MODE_REMOTE_PORT);
			$client->setDebug(false);
			if(AUTH_MODE_REMOTE_USER != ""){
				$client->setAuthorization(AUTH_MODE_REMOTE_USER, AUTH_MODE_REMOTE_PASSWORD);
			}						
			$client->setCookies(array((AUTH_MODE_REMOTE_SESSION_NAME ? AUTH_MODE_REMOTE_SESSION_NAME : "PHPSESSID") => $remoteSessionId));
			$result = $client->get(AUTH_MODE_REMOTE_URL, array("session_id"=>$remoteSessionId));			
			if($result)
			{
				$user = $client->getContent();
				if(AuthService::userExists($user)) AuthService::logUser($user, "", true);
			}
		}
		else if(AUTH_MODE == "wordpress"){
			global $current_user;
			wp_get_current_user();
			if($current_user->user_login == '' || $current_user->wp_user_level < 8 || !function_exists('ajxp_content')){
				die("You are not allowed to see this page!");
			}
			AuthService::logUser($current_user->user_login, "", true);
		}
	}
	
	function logUser($user_id, $pwd, $bypass_pwd = false, $cookieLogin = false, $returnSeed="")
	{
		if($user_id == null)
		{
			if(isSet($_SESSION["AJXP_USER"])) return 1; 
			if(ALLOW_GUEST_BROWSING)
			{
				if(!AuthService::userExists("guest"))
				{
					AuthService::createUser("guest", "");
					$guest = new AJXP_User("guest");
					$guest->save();
				}
				AuthService::logUser("guest", null);
				return 1;
			}
			return 0;
		}
		// CHECK USER PASSWORD HERE!
		if(!AuthService::userExists($user_id)) return 0;
		if(!$bypass_pwd){
			if(!AuthService::checkPassword($user_id, $pwd, $cookieLogin, $returnSeed)) return -1;
		}
		$user = new AJXP_User($user_id);
		if($user->isAdmin())
		{
			$user = AuthService::updateAdminRights($user);
		}
		$_SESSION["AJXP_USER"] = $user;
		AJXP_Logger::logAction("Log In");
		return 1;
	}
	
	function updateUser($userObject)
	{
		$_SESSION["AJXP_USER"] = $userObject;
	}
	
	function disconnect()
	{
		if(isSet($_SESSION["AJXP_USER"])){
			AJXP_Logger::logAction("Log Out");
			unset($_SESSION["AJXP_USER"]);
		}
	}
	
	function getDefaultRootId()
	{
		$loggedUser = AuthService::getLoggedUser();
		if($loggedUser == null) return 0;
		foreach (array_keys(ConfService::getRootDirsList()) as $rootDirIndex)
		{			
			if($loggedUser->canRead($rootDirIndex."")) return $rootDirIndex;
		}
		return 0;
	}
	
	/**
	* @param AJXP_User $adminUser
	*/
	function updateAdminRights($adminUser)
	{
		foreach (array_keys(ConfService::getRootDirsList()) as $rootDirIndex)
		{			
			$adminUser->setRight($rootDirIndex, "rw");
		}
		$adminUser->save();
		return $adminUser;
	}
	
	function userExists($userId)
	{
		$users = AuthService::loadLocalUsersList();
		if(!is_array($users) || !array_key_exists($userId, $users)) return false;
		return true;
		//return(is_dir(USERS_DIR."/".$userId));
	}
	
	function encodePassword($pass){
		return md5($pass);
	}
	
	function checkPassword($userId, $userPass, $encodedPass = false, $returnSeed = "")
	{
		if($userId == "guest") return true;		
		$users = AuthService::loadLocalUsersList();
		if(!array_key_exists($userId, $users)) return false;
		if($encodedPass){			
			return (AuthService::encodeCookiePass($userId, $users[$userId]) == $userPass);
		}else{
			$seed = $_SESSION["AJXP_CURRENT_SEED"];
			if($seed != $returnSeed) return false;
			return (md5($users[$userId].''.$returnSeed) == $userPass);
		}		
	}
	
	function updatePassword($userId, $userPass)
	{
		$users = AuthService::loadLocalUsersList();
		if(!is_array($users) || !array_key_exists($userId, $users)) return "Error!";
		$users[$userId] = $userPass; // AuthService::encodePassword($userPass); it is already encoded
		AuthService::saveLocalUsersList($users);
		AJXP_Logger::logAction("Update Password", array("user_id"=>$userId));
		return true;
	}
	
	function createUser($userId, $userPass, $isAdmin=false)
	{
		$users = AuthService::loadLocalUsersList();
		if(!is_array($users)) $users = array();
		if(array_key_exists($userId, $users)) return "exists";
		$users[$userId] = AuthService::encodePassword($userPass);
		AuthService::saveLocalUsersList($users);
		if($isAdmin){
			$user = new AJXP_User($userId);
			$user->setAdmin(true);			
			$user->save();
		}
		AJXP_Logger::logAction("Create User", array("user_id"=>$userId));
		return null;
	}
	
	function countAdminUsers(){
		$users = AuthService::loadLocalUsersList();
		if(!is_array($users)) return 0;
		if(!array_key_exists("ajxp.admin.users", $users)){			
			if(AuthService::userExists("admin")) return -1;
			return 0;
		}
		return count($users["ajxp.admin.users"]);
	}
	
	function setUserAdmin($userId, $isAdmin){
		$users = AuthService::loadLocalUsersList();
		if($isAdmin){
			if(!array_key_exists("ajxp.admin.users", $users)){
				$users["ajxp.admin.users"] = array();
			}
			$users["ajxp.admin.users"][$userId] = true;
			AuthService::saveLocalUsersList($users);
		}else{
			if(array_key_exists("ajxp.admin.users", $users) && array_key_exists($userId, $users["ajxp.admin.users"])){
				unset($users["ajxp.admin.users"][$userId]);
				AuthService::saveLocalUsersList($users);
			}
		}
	}
	
	function deleteUser($userId)
	{
		AuthService::setUserAdmin($userId, false);
		$users = AuthService::loadLocalUsersList();
		if(is_array($users) && array_key_exists($userId, $users))
		{
			unset($users[$userId]);
			AuthService::saveLocalUsersList($users);
		}
		if(is_dir(USERS_DIR."/".$userId))
		{
			$rp = opendir(USERS_DIR."/".$userId);
			while ($file = readdir($rp)) {
				if($file != "." && $file != "..")
				{
					unlink(USERS_DIR."/".$userId."/".$file);
				}
			}
			@rmdir(USERS_DIR."/".$userId);
		}
		AJXP_Logger::logAction("Delete User", array("user_id"=>$userId));
		return true;
	}
	
	function listUsers()
	{
		$allUsers = array();
		$users = AuthService::loadLocalUsersList();
		foreach (array_keys($users) as $userId)
		{
			if(($userId == "guest" && !ALLOW_GUEST_BROWSING) || $userId == "ajxp.admin.users") continue;
			$allUsers[$userId] = new AJXP_User($userId);
		}
		return $allUsers;
	}
	
	function loadLocalUsersList()
	{
		$result = array();
		if(is_file(USERS_DIR."/users.ser") && is_readable(USERS_DIR."/users.ser"))
		{
			$fileLines = file(USERS_DIR."/users.ser");
			$result = unserialize($fileLines[0]);
		}
		return $result;		
	}
	
	function saveLocalUsersList($usersList)
	{
		$fp = fopen(USERS_DIR."/users.ser", "w");
		fwrite($fp, serialize($usersList));
		fclose($fp);
	}
}

?>
