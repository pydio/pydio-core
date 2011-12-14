<?php

class DrupalAjxp{
		
	
	function __construct( $params ){
		
        $this->_params = $params;
        			
		$this->secret = $this->_params["ajxp_secret_key"] OR '';
        $installPath = $this->_params["ajxp_install_path"] OR '';
        $this->glueCode = $installPath."/plugins/auth.remote/glueCode.php";
        $this->glueCodeFound = file_exists($this->glueCode);
        $this->autoCreate = $this->_params["ajxp_auto_create"];		
	}
	
	/**
	 * This method should handle any login logic and report back to the subject
	 *
	 * @access	public
	 * @param 	array 	holds the user data
	 * @param 	array    extra options
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function onLoginUser($name, $password)
	{
		// Initialize variables
		$success = false;
		if(!$this->glueCodeFound) return false;
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		//$plugInAction, $login, $result, $secret, $autoCreate;
		$AJXP_GLUE_GLOBALS["secret"] = $this->secret;
		$AJXP_GLUE_GLOBALS["autoCreate"] = $this->autoCreate;
		$AJXP_GLUE_GLOBALS["plugInAction"] = "login";
		$AJXP_GLUE_GLOBALS["login"] = array("name"=>$name, "password"=>"");

	   	include($this->glueCode);
		return true;
	}

	/**
	 * This method should handle any logout logic and report back to the subject
	 *
	 * @access public
	 * @param array holds the user data
	 * @return boolean True on success
	 * @since 1.5
	 */
	function onLogout($user)
	{
		// Initialize variables
		$success = false;

		if(!$this->glueCodeFound) return false;
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		$AJXP_GLUE_GLOBALS["secret"] = $this->secret;		
		$AJXP_GLUE_GLOBALS["plugInAction"] = "logout";

        $orig = session_name();
	   	include($this->glueCode);

        new SessionSwitcher($orig);
        session_destroy();

		return $AJXP_GLUE_GLOBALS["result"];
	}	
	
	/**
	 * Example store user method
	 *
	 * Method is called after user data is stored in the database
	 *
	 * @param 	array		holds the new user data
	 * @param 	boolean		true if a new user is stored
	 * @param	boolean		true if user was succesfully stored in the database
	 * @param	string		message
	 */
	function onAfterStoreUser($name, $password, $isAdmin, $isnew)
	{
		// convert the user parameters passed to the event
		// to a format the external application
		if(!$this->glueCodeFound) return false;
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		//global $plugInAction, $result, $secret, $user;
		$AJXP_GLUE_GLOBALS["secret"] = $this->secret;

		$AJXP_GLUE_GLOBALS["user"] = array();
		$AJXP_GLUE_GLOBALS["user"]['name']	= $name;
		$AJXP_GLUE_GLOBALS["user"]['password']	= $password;
		$AJXP_GLUE_GLOBALS["user"]['right'] = ($isAdmin?'admin':'');	
		$AJXP_GLUE_GLOBALS["plugInAction"] = ($isnew?"addUser":"updateUser");

	   	include($this->glueCode);
		return $AJXP_GLUE_GLOBALS["result"];		
		
	}

	/**
	 * Example store user method
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param 	array		holds the user data
	 * @param	boolean		true if user was succesfully stored in the database
	 * @param	string		message
	 */
	function onAfterDeleteUser($name)
	{
	 	// only the $user['id'] exists and carries valid information

		// Call a function in the external app to delete the user
		// ThirdPartyApp::deleteUser($user['id']);
		if(!$this->glueCodeFound) return false;
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		
		$AJXP_GLUE_GLOBALS["secret"] = $this->secret;		
		$AJXP_GLUE_GLOBALS["userName"] = $name;
		$AJXP_GLUE_GLOBALS["plugInAction"] = "delUser";
		
	   	include($this->glueCode);
		return true;
	}
	
	
}