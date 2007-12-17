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
			$client->setCookies(array("PHPSESSID", $remoteSessionId));
			$result = $client->get(AUTH_MODE_REMOTE_URL, array("session_id"=>$remoteSessionId));			
			if($result)
			{
				$user = $client->getContent();
				if(AuthService::userExists($user)) AuthService::logUser($user, "", true);
			}
		}
	}
	
	function logUser($user_id, $pwd, $bypass_pwd = false)
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
			if(!AuthService::checkPassword($user_id, $pwd)) return -1;
		}
		$user = new AJXP_User($user_id);
		if($user_id == "admin")
		{
			$user = AuthService::updateAdminRights($user);
		}
		$_SESSION["AJXP_USER"] = $user;
		return 1;
	}
	
	function updateUser($userObject)
	{
		$_SESSION["AJXP_USER"] = $userObject;
	}
	
	function disconnect()
	{
		if(isSet($_SESSION["AJXP_USER"])) unset($_SESSION["AJXP_USER"]);
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
	
	function checkPassword($userId, $userPass)
	{
		if($userId == "guest") return true;		
		$users = AuthService::loadLocalUsersList();
		if(!array_key_exists($userId, $users) || $users[$userId] != crypt($userPass, "ajxp")) return false;
		else return true;
	}
	
	function updatePassword($userId, $userPass)
	{
		$users = AuthService::loadLocalUsersList();
		if(!is_array($users) || !array_key_exists($userId, $users)) return "Error!";
		$users[$userId] = crypt($userPass, "ajxp");
		AuthService::saveLocalUsersList($users);
		return true;
	}
	
	function createUser($userId, $userPass)
	{
		$users = AuthService::loadLocalUsersList();
		if(!is_array($users)) $users = array();
		if(array_key_exists($userId, $users)) return "exists";
		$users[$userId] = crypt($userPass, "ajxp");
		AuthService::saveLocalUsersList($users);
		return null;
	}
	
	function deleteUser($userId)
	{
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
		return true;
	}
	
	function listUsers()
	{
		$allUsers = array();
		$users = AuthService::loadLocalUsersList();
		foreach (array_keys($users) as $userId)
		{
			if($userId == "guest" && !ALLOW_GUEST_BROWSING) continue;
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