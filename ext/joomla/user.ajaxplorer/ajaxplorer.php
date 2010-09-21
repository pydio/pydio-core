<?php
/**
 * @version		$Id: example.php 11720 2009-03-27 21:27:42Z ian $
 * @package		Joomla
 * @subpackage	JFramework
 * @copyright	Copyright (C) 2010 Charles du Jeu
 * @license		LGPL
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );
define('AJXP_EXEC', true);
jimport('joomla.event.plugin');

/**
 * AjaXplorer User Plugin
 *
 * @package		Joomla
 * @subpackage	JFramework
 * @since 		1.5
 */
class plgUserAjaxplorer extends JPlugin {

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 1.5
	 */
	function plgUserAjaxplorer(& $subject, $config)
	{
		parent::__construct($subject, $config);
        $this->_plugin = JPluginHelper::getPlugin( 'user', 'ajaxplorer' );
        $this->_params = new JParameter( $this->_plugin->params );	
        			
		$this->secret = $this->_params->get("ajxp_secret_key") OR '';
        $installPath = $this->_params->get("ajxp_install_path") OR '';
        $this->glueCode = $installPath."/plugins/auth.remote/glueCode.php";
        $this->glueCodeFound = file_exists($this->glueCode);
        $this->autoCreate = $this->_params->get("ajxp_auto_create");
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
	function onAfterStoreUser($joomlaUser, $isnew, $success, $msg)
	{
		global $mainframe;

		// convert the user parameters passed to the event
		// to a format the external application
		if(!$this->glueCodeFound) return false;
		if(!$success) return true;
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		
		$AJXP_GLUE_GLOBALS["secret"] = $this->secret;
		$AJXP_GLUE_GLOBALS["user"] = array();
		$AJXP_GLUE_GLOBALS["user"]['name']	= $joomlaUser['username'];
		$AJXP_GLUE_GLOBALS["user"]['password']	= $joomlaUser['password'];
		if($joomlaUser['usertype'] == "Super Administrator" || $joomlaUser['usertype'] == "Administrator"){
			$AJXP_GLUE_GLOBALS["user"]['right'] = 'admin';
		}else{
			$AJXP_GLUE_GLOBALS["user"]['right'] = '';
		}
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
	function onAfterDeleteUser($joomlaUser, $succes, $msg)
	{
		global $mainframe;

	 	// only the $user['id'] exists and carries valid information

		// Call a function in the external app to delete the user
		// ThirdPartyApp::deleteUser($user['id']);
		if(!$this->glueCodeFound) return false;
		if(!$succes) return true;
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		
		$AJXP_GLUE_GLOBALS["secret"] = $this->secret;		
		$AJXP_GLUE_GLOBALS["userName"] = $joomlaUser['username'];
		$AJXP_GLUE_GLOBALS["plugInAction"] = "delUser";
	   	include($this->glueCode);

		return true;
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
	function onLoginUser($user, $options)
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
		$AJXP_GLUE_GLOBALS["login"] = array("name"=>$user["username"], "password"=>$user["password"]); 
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
	function onLogoutUser($user)
	{
		// Initialize variables
		$success = false;

		if(!$this->glueCodeFound) return false;
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		$AJXP_GLUE_GLOBALS["secret"] = $this->secret;		
		$AJXP_GLUE_GLOBALS["plugInAction"] = "logout";
	   	include($this->glueCode);

		return $AJXP_GLUE_GLOBALS["result"];
	}
			
}