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
 * Description : Abstract representation of Ajaxplorer Data Access
 */
class AbstractConfDriver extends AbstractDriver {
		
	var $options;
	var $driverType = "conf";

	/**
	 * Initialize the driver with a given set of options
	 *
	 * @param Array $options Array of options as defined by the manifest.xml file
	 */
	function init($options){		
		$this->options = $options;
		$this->initXmlActionsFile(CLIENT_RESOURCES_FOLDER."/xml/standard_conf_actions.xml");
		unset($this->actions["get_driver_actions"]);		
	}
	
	// SAVE / EDIT / CREATE / DELETE REPOSITORY
	/**
	 * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
	 * @return Array
	 */
	function listRepositories(){
		
	}
	/**
	 * Retrieve a Repository given its unique ID.
	 *
	 * @param String $repositoryId
	 * @return Repository
	 */	
	function getRepositoryById($repositoryId){
		
	}
	/**
	 * Stores a repository, new or not.
	 *
	 * @param Repository $repositoryObject
	 * @param Boolean $update 
	 * @return -1 if failed
	 */	
	function saveRepository($repositoryObject, $update = false){
		
	}
	/**
	 * Delete a repository, given its unique ID.
	 *
	 * @param String $repositoryId
	 */
	function deleteRepository($repositoryId){
		
	}
	
	// SAVE / EDIT / CREATE / DELETE USER OBJECT (except password)
	/**
	 * Retrieve the list of available users
	 * @return Array
	 */
	function listUsers(){
		
	}
	
	
	/**
	 * Instantiate a new AJXP_User
	 *
	 * @param String $userId
	 * @return AbstractAjxpUser
	 */
	function createUserObject($userId){
		$abstractUser = $this->instantiateAbstractUserImpl($userId);
		if(!$abstractUser->storageExists()){			
			AuthService::updateDefaultRights($abstractUser);
		}
		return $abstractUser;
	}
	
	/**
	 * Instantiate the right class
	 *
	 * @param AbstractAjxpUser $userId
	 */
	function instantiateAbstractUserImpl($userId){
		
	}
	
	function getUserClassFileName(){		
	}
	
	function getOption($optionName){	
		return (isSet($this->options[$optionName])?$this->options[$optionName]:"");	
	}
	
	
	// SAVE / EDIT / CREATE / DELETE CONF ??
	

}
?>