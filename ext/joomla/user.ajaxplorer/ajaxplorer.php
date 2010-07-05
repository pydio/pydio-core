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
		global $plugInAction, $result, $secret, $user;
		$secret = $this->secret;

		$user = array();
		$user['name']	= $joomlaUser['username'];
		$user['password']	= $joomlaUser['password'];
		$user['right'] = '';
		
		if($joomlaUser['usertype'] == "Super Administrator" || $joomlaUser['usertype'] == "Administrator"){
			$user['right'] = 'admin';
		}else{
			$user['right'] = '';
		}

		$plugInAction = ($isnew?"addUser":"updateUser");

	   	include($this->glueCode);
		return $result;		
		
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
		global $plugInAction, $result, $secret, $userName;
		$secret = $this->secret;
		
		$userName = $joomlaUser['username'];
		$plugInAction = "delUser";
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
		global $plugInAction, $login, $result, $secret, $autoCreate;
		$secret = $this->secret;
		$autoCreate = $this->autoCreate;
		$plugInAction = "login";
		$login = array("name"=>$user["username"], "password"=>$user["password"]);  
	   	include($this->glueCode);
	   	// Update default rights (this could go in the trunk...)
	   	if($result == 1){
		   	$userObject = AuthService::getLoggedUser();
		   	if($userObject->isAdmin()){
		   		AuthService::updateAdminRights($userObject);
		   	}else{
				foreach (ConfService::getRepositoriesList() as $repositoryId => $repoObject)
				{			
					if($repoObject->getDefaultRight() != ""){
						$userObject->setRight($repositoryId, $repoObject->getDefaultRight());
					}
				}
		   	}
			$userObject->save();		   	
	   	}
		
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
		global $plugInAction, $result, $secret;
		$secret = $this->secret;
		
		$plugInAction = "logout";
	   	include($this->glueCode);

		return $result;
	}
			
}
