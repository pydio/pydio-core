<?php

require_once(INSTALL_PATH."/plugins/auth.serial/class.serialAuthDriver.php");
class phpbbAuthDriver extends serialAuthDriver  {
	
	var $usersSerFile;
	var $phpbb_root_path;
	var $driverName = "phpbb";
	
	function init($options){
		parent::init($options);

		$this->usersSerFile = $options["USERS_FILEPATH"];
		$this->slaveMode = ($options["SLAVE_MODE"]) ? true : false;
		$this->urls = array($options["LOGIN_URL"], $options["LOGOUT_URL"]);

		global $phpbb_root_path, $phpEx, $user, $db, $config, $cache, $template;
		define('IN_PHPBB', true);
		$phpbb_root_path =  $options["PHPBB_PATH"];
		$phpEx = substr(strrchr(__FILE__, '.'), 1);
		require($phpbb_root_path . 'common.' . $phpEx);
		$user->session_begin();
		
		if(!$user->data['is_registered'])
			$this->disconnect();
		
	}
	
	function disconnect()
	{
		if(!empty($_SESSION["AJXP_USER"])){
			unset($_SESSION["AJXP_USER"]);
			session_destroy();
		}
	}
	
	function usersEditable() { return false; }
	
	function passwordsEditable() { return false; }
	
	function preLogUser($sessionId) {
		global $user;
		
		$username = $user->data['username_clean'];
		$password = md5($user->data['user_password']);
				
		if(!$user->data['is_registered'])
			return false;
		
		if(!$this->userExists($username)){
			if($this->autoCreateUser()){
				$this->createUser($username, $password);
			}else{
				return false;
			}
		}
		
		AuthService::logUser($username, '', true);
		return true;
	}

	function getLoginRedirect(){
		if ($this->slaveMode) {
			if (!empty($_SESSION["AJXP_USER"]))
				return false;

			return $this->urls[0];
		} 
		return false;
	}

	function getLogoutRedirect(){
		if ($this->slaveMode) {
			return $this->urls[1];
		} 
		return false;
	}

}
?>