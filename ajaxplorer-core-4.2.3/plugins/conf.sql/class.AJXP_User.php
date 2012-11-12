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
require_once(AJXP_BIN_FOLDER."/dibi.compact.php");

/**
 * AJXP_User class for the conf.sql driver.
 * 
 * Stores rights, preferences and bookmarks in various database tables.
 * 
 * The class will expect these schema objects to be present:
 *
 * CREATE TABLE ajxp_user_rights ( rid INTEGER PRIMARY KEY, login VARCHAR(255), repo_uuid VARCHAR(33), rights VARCHAR(20));
 * CREATE TABLE ajxp_user_prefs ( rid INTEGER PRIMARY KEY, login VARCHAR(255), name VARCHAR(255), val VARCHAR(255));
 * CREATE TABLE ajxp_user_bookmarks ( rid INTEGER PRIMARY KEY, login VARCHAR(255), repo_uuid VARCHAR(33), path VARCHAR(255), title VARCHAR(255));
 * 
 * 		
 * @author ebrosnan
 *
 */
class AJXP_User extends AbstractAjxpUser 
{
	/**
	 * Whether queries will be logged with AJXP_Logger
	 */
	var $debugEnabled = false;
	
	/**
	 * Login name of the user.
	 * @var String
	 */
	var $id;
	
	/**
	 * User is an admin?
	 * @var Boolean
	 */
	var $hasAdmin = false;
	
	/**
	 * User rights map. In the format Array( "repoid" => "rw | r | nothing" )
	 * @var Array
	 */
	var $rights;
	
	/**
	 * User preferences array in the format Array( "preference_key" => "preference_value" )
	 * @var Array
	 */
	var $prefs; 
	
	/**
	 * User bookmarks array in the format Array( "repoid" => Array( Array( "path"=>"/path/to/bookmark", "title"=>"bookmark" )))
	 * @var Array
	 */
	var $bookmarks;

	/**
	 * User version(?) possibly deprecated.
	 * 
	 * @var unknown_type
	 */
	var $version;
	
	/**
	 * Conf Storage implementation - Any class/plugin implementing AbstractConfDriver.
	 * 
	 * This is set by the constructor.
	 *
	 * @var AbstractConfDriver
	 */
	var $storage;
	
	/**
	 * AJXP_User Constructor
	 * @param $id String User login name.
	 * @param $storage AbstractConfDriver User storage implementation.
	 * @return AJXP_User
	 */
	function AJXP_User($id, $storage=null, $debugEnabled = false){
		parent::AbstractAjxpUser($id, $storage);
		//$this->debugEnabled = true;
		
		$this->log('Instantiating User');
	}
	
	/**
	 * Does the configuration storage exist?
	 * Will return true if all schema objects are available.
	 * 
	 * @see AbstractAjxpUser#storageExists()
	 * @return boolean false if storage does not exist
	 */
	function storageExists(){		
		$this->log('Checking for existence of AJXP_User storage...');
		
		try {
			$dbinfo = dibi::getDatabaseInfo();
			$dbtables = $dbinfo->getTableNames();
			
			if (!in_array('ajxp_user_rights', $dbtables) || 
				!in_array('ajxp_user_prefs', $dbtables) ||
				!in_array('ajxp_user_bookmarks', $dbtables)) {

				return false;
			}
			
			//$result_rights = dibi::query('SELECT [repo_uuid], [rights] FROM  [ajxp_user_rights] WHERE [login] = %s', $this->getId());    
			//$this->rights = $result_rights->fetchPairs('repo_uuid', 'rights');
			$this->load();
			if(! isSet($this->rights["ajxp.admin"])){
				return false;
			}			
		
		} catch (DibiException $e) {

			return false;
		}
		
		return true;
	}
	
	/**
	 * Log a message if the logQueries property is true.
	 * 
	 * @param $textMessage String text of the message to log
	 * @param $severityLevel Integer constant of the logging severity
	 * @return null
	 */
	function log($textMessage, $severityLevel = LOG_LEVEL_DEBUG) {
		if ($this->debugEnabled) {
			$logger = AJXP_Logger::getInstance();
			$logger->write($textMessage, $severityLevel);
		}
	}
	
	/**
	 * Set the access rights of the user to a repository.
	 * 
	 * In the SQL implementation, the query is performed straight away.
	 * Performing the query on AJXP_User::save() provides no speed benefit, as operations are usually carried out
	 * one at a time and then saved.
	 * 
	 * @param $rootDirId String Repository Unique ID
	 * @param $rightString String String containing access rights, one of '' | 'r' | 'rw'
	 * @return null or -1 on error
	 * 
	 * @see AbstractAjxpUser#setRight($rootDirId, $rightString)
	 */
	function setRight($rootDirId, $rightString){
		
		// Prevent a query if the rights are identical to the existing rights.
		if (array_key_exists($rootDirId, $this->rights) && $this->rights[$rootDirId] == $rightString) {
			return;
		}
		
		// Try/Catch DibiException
		try {
			// Update an existing right
			if (array_key_exists($rootDirId, $this->rights)) {
				
				// Delete an existing rights row, because we have no permission at all to this repository.
				if ('' == $rightString) {
	
					dibi::query('DELETE FROM [ajxp_user_rights] WHERE [login] = %s AND [repo_uuid] = %s', $this->getId(), $rootDirId);
	
					$this->log('DELETE RIGHTS: [Login]: '.$this->getId().' [Repository UUID]:'.$rootDirId.' [Rights]:'.$rightString);
					unset($this->rights[$rootDirId]); 
					
				// Update an existing rights row, because only some of the rights have changed.
				} else {
	
					dibi::query('UPDATE [ajxp_user_rights] SET ', Array('rights'=>$rightString), 'WHERE [login] = %s AND [repo_uuid] = %s', $this->getId(), $rootDirId);
	
					$this->log('UPDATE RIGHTS: [Login]: '.$this->getId().' [Repository UUID]:'.$rootDirId.' [Rights]:'.$rightString);
					$this->rights[$rootDirId] = $rightString;
				}
			
			// The repository supplied does not exist, so insert the right.
			} else {
	
				dibi::query('INSERT INTO [ajxp_user_rights]', Array(
					'login' => $this->getId(),
					'repo_uuid' => $rootDirId,
					'rights' => $rightString		
				));
				
				$this->log('INSERT RIGHTS: [Login]: '.$this->getId().' [Repository UUID]:'.$rootDirId.' [Rights]:'.$rightString);
				$this->rights[$rootDirId] = $rightString;
			}
			
		} catch (DibiException $e) {
			$this->log('MODIFY RIGHTS FAILED: Reason: '.$e->getMessage());
		}
	}
	
	/**
	 * Remove rights to the specified repository unique id.
	 * 
	 * @param $rootDirId String Repository Unique ID
	 * @return null or -1 on error
	 * @see AbstractAjxpUser#removeRights($rootDirId)
	 */
	function removeRights($rootDirId){
		if (array_key_exists($rootDirId, $this->rights)) {
			try {
				dibi::query('DELETE FROM [ajxp_user_rights] WHERE [login] = %s AND [repo_uuid] = %s', $this->getId(), $rootDirId);
			} catch (DibiException $e) {
				$this->log('DELETE RIGHTS: FAILED Reason: '.$e->getMessage());
				return -1;
			}
			
			$this->log('REMOVE RIGHTS: [Login]: '.$this->getId().' [Repository UUID]:'.$rootDirId.' [Rights]:'.$this->rights[$rootDirId]);
			unset($this->rights[$rootDirId]);
		}
	}
	
	/**
	 * Set a user preference.
	 * 
	 * @param $prefName String Name of the preference.
	 * @param $prefValue String Value to assign to the preference.
	 * @return null or -1 on error.
	 * @see AbstractAjxpUser#setPref($prefName, $prefValue)
	 */
	function setPref($prefName, $prefValue){

        if(!is_string($prefValue)){
            $prefValue = '$phpserial$'.serialize($prefValue);
        }
		// Prevent a query if the preferences are identical to the existing preferences.
		if (array_key_exists($prefName, $this->prefs) && $this->prefs[$prefName] == $prefValue) {
			return;
		}
		
		// Try/Catch DibiException
		try {
			// Update an existing preference
			if (array_key_exists($prefName, $this->prefs)) {
				
				// Delete an existing preferences row, because the value has been unset.
				if ('' == $prefValue) {
	
					dibi::query('DELETE FROM [ajxp_user_prefs] WHERE [login] = %s AND [name] = %s', $this->getId(), $prefName);
					
					$this->log('DELETE PREFERENCE: [Login]: '.$this->getId().' [Preference]:'.$prefName.' [Value]:'.$prefValue);
					unset($this->prefs[$prefName]); // Update the internal array only if successful.
					
				// Update an existing rights row, because only some of the rights have changed.
				} else {
	
					dibi::query('UPDATE [ajxp_user_prefs] SET ', Array('val'=>$prefValue), 'WHERE [login] = %s AND [name] = %s', $this->getId(), $prefName);
					
					$this->log('UPDATE PREFERENCE: [Login]: '.$this->getId().' [Preference]:'.$prefName.' [Value]:'.$prefValue);
					$this->prefs[$prefName] = $prefValue;
				}
			
			// The repository supplied does not exist, so insert the right.
			} else {
	
				dibi::query('INSERT INTO [ajxp_user_prefs]', Array(
					'login' => $this->getId(),
					'name' => $prefName,
					'val' => $prefValue		
				));
				
				$this->log('INSERT PREFERENCE: [Login]: '.$this->getId().' [Preference]:'.$prefName.' [Value]:'.$prefValue);
				$this->prefs[$prefName] = $prefValue;
			}
			
		} catch (DibiException $e) {
			$this->log('MODIFY PREFERENCE FAILED: Reason: '.$e->getMessage());
		}
		
	}

    function getPref($prefName){
        $p = parent::getPref($prefName);
        if(isSet($p)){
            if(strpos($p, '$phpserial$') !== false && strpos($p, '$phpserial$') === 0){
                $p = substr($p, strlen('$phpserial$'));
                return unserialize($p);
            }
            // old method
            if($prefName == "CUSTOM_PARAMS"){
                return unserialize($p);
            }
        }
        return $p;
    }
	
	/**
	 * Add a user bookmark.
	 * 
	 * @param $path String Relative path to bookmarked location.
	 * @param $title String Title of the bookmark
	 * @param $repId String Repository Unique ID
	 * @return null or -1 on error.
	 * @see AbstractAjxpUser#addBookmark($path, $title, $repId)
	 */
	function addBookmark($path, $title="", $repId = -1){
		if(!isSet($this->bookmarks)) $this->bookmarks = array();
		if($repId == -1) $repId = ConfService::getCurrentRootDirIndex();
		if($title == "") $title = basename($path);
		if(!isSet($this->bookmarks[$repId])) $this->bookmarks[$repId] = array();
		foreach ($this->bookmarks[$repId] as $v)
		{
			$toCompare = "";
			if(is_string($v)) $toCompare = $v;
			else if(is_array($v)) $toCompare = $v["PATH"];
			if($toCompare == trim($path)) return ; // RETURN IF ALREADY HERE!
		}
		
		try {
			dibi::query('INSERT INTO [ajxp_user_bookmarks]', Array(
				'login' => $this->getId(),
				'repo_uuid' => $repId,
				'path' => $path,
				'title' => $title
			));
			
		} catch (DibiException $e) {
			$this->log('BOOKMARK ADD FAILED: Reason: '.$e->getMessage());
			return -1;
		}
		$this->bookmarks[$repId][] = array("PATH"=>trim($path), "TITLE"=>$title);
	}
	
	/**
	 * Remove a user bookmark.
	 * 
	 * @param $path String String of the path of the bookmark to remove.
	 * @return null or -1 on error.
	 * @see AbstractAjxpUser#removeBookmark($path)
	 */
	function removeBookmark($path){
		$repId = ConfService::getCurrentRootDirIndex();
		if(isSet($this->bookmarks) 
			&& isSet($this->bookmarks[$repId])
			&& is_array($this->bookmarks[$repId]))
			{
				foreach ($this->bookmarks[$repId] as $k => $v)
				{
					$toCompare = "";
					if(is_string($v)) $toCompare = $v;
					else if(is_array($v)) $toCompare = $v["PATH"];					
					if($toCompare == trim($path)) {
						try {
							dibi::query('DELETE FROM [ajxp_user_bookmarks] WHERE [login] = %s AND [repo_uuid] = %s AND [title] = %s', $this->getId(), $repId, $v["TITLE"]);
						} catch (DibiException $e) {
							$this->log('BOOKMARK REMOVE FAILED: Reason: '.$e->getMessage());
							return -1;
						}
						
						unset($this->bookmarks[$repId][$k]);
					}
				}
			} 		
	}
	
	/**
	 * Rename a user bookmark.
	 * 
	 * @param $path String Path of the bookmark to rename.
	 * @param $title New title to give the bookmark.
	 * @return null or -1 on error.
	 * @see AbstractAjxpUser#renameBookmark($path, $title)
	 */
	function renameBookmark($path, $title){
		$repId = ConfService::getCurrentRootDirIndex();
		if(isSet($this->bookmarks) 
			&& isSet($this->bookmarks[$repId])
			&& is_array($this->bookmarks[$repId]))
			{
				foreach ($this->bookmarks[$repId] as $k => $v)
				{
					$toCompare = "";
					if(is_string($v)) $toCompare = $v;
					else if(is_array($v)) $toCompare = $v["PATH"];					
					if($toCompare == trim($path)){
						 try {
						 	dibi::query('UPDATE [ajxp_user_bookmarks] SET ', 
						 				Array('path'=>trim($path), 'title'=>$title), 
						 				'WHERE [login] = %s AND [repo_uuid] = %s AND [title] = %s',
						 				$this->getId(), $repId, $v["TITLE"]
						 	);
						 	
						 } catch (DibiException $e) {
						 	$this->log('BOOKMARK RENAME FAILED: Reason: '.$e->getMessage());
						 	return -1;
						 }
						 $this->bookmarks[$repId][$k] = array("PATH"=>trim($path), "TITLE"=>$title);
					}
				}
			} 		
	}

	/**
	 * Load initial user data (Rights, Preferences and Bookmarks).
	 * 
	 * @see AbstractAjxpUser#load()
	 */
	function load(){
		$this->log('Loading all user data..');
		$result_rights = dibi::query('SELECT [repo_uuid], [rights] FROM [ajxp_user_rights] WHERE [login] = %s', $this->getId());
		
		$this->rights = $result_rights->fetchPairs('repo_uuid', 'rights');
		
		// Db field returns integer or string so we are required to cast it in order to make the comparison
		if(isSet($this->rights["ajxp.admin"]) && (bool)$this->rights["ajxp.admin"] === true){
			$this->setAdmin(true);
		}
		if(isSet($this->rights["ajxp.parent_user"])){
			$this->setParent($this->rights["ajxp.parent_user"]);
		}		
		
		$result_prefs = dibi::query('SELECT [name], [val] FROM [ajxp_user_prefs] WHERE [login] = %s', $this->getId());
		$this->prefs = $result_prefs->fetchPairs('name', 'val');
		
		$result_bookmarks = dibi::query('SELECT [repo_uuid], [path], [title] FROM [ajxp_user_bookmarks] WHERE [login] = %s', $this->getId());
		$all_bookmarks = $result_bookmarks->fetchAll();
		if (!is_array($this->bookmarks)) { 
			$this->bookmarks = Array();
		}

        $this->bookmarks = array();
		foreach ($all_bookmarks as $b) {
			if (!is_array($this->bookmarks[$b['repo_uuid']])) {
				$this->bookmarks[$b['repo_uuid']] = Array();
			}
			
			$this->bookmarks[$b['repo_uuid']][] = Array('PATH'=>$b['path'], 'TITLE'=>$b['title']);
		}
		
		// Load roles
		if(isSet($this->rights["ajxp.roles"])){
			$object = unserialize($this->rights["ajxp.roles"]);
			if(is_array($object)){
				$this->rights["ajxp.roles"] = $object;
				$allRoles = AuthService::getRolesList(); // Maintained as instance variable
				foreach (array_keys($this->rights["ajxp.roles"]) as $roleId){
					if(isSet($allRoles[$roleId])){
						$this->roles[$roleId] = $allRoles[$roleId];
					}else{
						unset($this->rights["ajxp.roles"][$roleId]);
					}
				}
			}else{
				$this->rights["ajxp.roles"] = array();
			}
		}
        if(isSet($this->rights["ajxp.actions"])){
            $object = unserialize($this->rights["ajxp.actions"]);
            if(is_array($object)){
                $this->rights["ajxp.actions"] = $object;
            }else{
                unset($this->rights["ajxp.actions"]);
            }
        }

	}

    function clearRights(){
        $this->rights = array();
        dibi::query("DELETE FROM [ajxp_user_rights] WHERE [login]='".$this->getId()."'");
    }

	/**
	 * Save user rights, preferences and bookmarks.
	 * @param String $context
	 * @see AbstractAjxpUser#save()
	 */
	function save($context = "superuser"){
        if($context != "superuser"){
            // Nothing specific to do, prefs and bookmarks are saved on-the-fly.
            return;
        }
		$this->log('Saving user...');
		
		if($this->isAdmin() === true){
			$this->setRight("ajxp.admin", "1");
		}else{
			$this->setRight("ajxp.admin", "0");
		}
		if($this->hasParent()){
			$this->setRight("ajxp.parent_user", $this->parentUser);
		}
		// update roles
		dibi::query("DELETE FROM [ajxp_user_rights] WHERE [login]='".$this->getId()."' AND [repo_uuid]='ajxp.roles'");
		if($this->rights["ajxp.roles"] && is_array($this->rights["ajxp.roles"]) && count($this->rights["ajxp.roles"])){
			dibi::query("INSERT INTO [ajxp_user_rights]", array(
				'login' => $this->getId(), 
				'repo_uuid' => 'ajxp.roles', 
				'rights'	=> serialize($this->rights['ajxp.roles'])));
		}
        // update specific actions rights
        dibi::query("DELETE FROM [ajxp_user_rights] WHERE [login]='".$this->getId()."' AND [repo_uuid]='ajxp.actions'");
        if($this->rights["ajxp.actions"] && is_array($this->rights["ajxp.actions"]) && count($this->rights["ajxp.actions"])){
            dibi::query("INSERT INTO [ajxp_user_rights]", array(
                'login' => $this->getId(),
                'repo_uuid' => 'ajxp.actions',
                'rights'	=> serialize($this->rights['ajxp.actions'])));
        }
	}
	
	/**
	 * Get Temporary Data.
	 * Implementation uses serialised files because of the overhead incurred with a full db implementation.
	 * 
	 * @param $key String key of data to retrieve
	 * @return Requested value
	 */
	function getTemporaryData($key){
		$dirPath = $this->storage->getOption("USERS_DIRPATH");
		if($dirPath == ""){
			$dirPath = AJXP_INSTALL_PATH."/data/users";
			AJXP_Logger::logAction("getTemporaryData", array("Warning" => "The conf.sql driver is missing a mandatory option USERS_DIRPATH!"));
		}
		return AJXP_Utils::loadSerialFile($dirPath."/".$this->getId()."-temp-".$key.".ser");
	}
	
	/**
	 * Save Temporary Data.
	 * Implementation uses serialised files because of the overhead incurred with a full db implementation.
	 * 
	 * @param $key String key of data to save.
	 * @param $value Value to save
	 * @return null (AJXP_Utils::saveSerialFile() returns nothing)
	 */
	function saveTemporaryData($key, $value){
		$dirPath = $this->storage->getOption("USERS_DIRPATH");
		if($dirPath == ""){
			$dirPath = AJXP_INSTALL_PATH."/data/users";
			AJXP_Logger::logAction("setTemporaryData", array("Warning" => "The conf.sql driver is missing a mandatory option USERS_DIRPATH!"));
		}
		return AJXP_Utils::saveSerialFile($dirPath."/".$this->getId()."-temp-".$key.".ser", $value);
	}
	
	/**
	 * Static function for deleting a user.
	 * Also removes associated rights, preferences and bookmarks.
	 * WARNING : must also delete the children!
	 *
	 * @param String $userId Login to delete.
	 * @param Array $deletedSubUsers
	 * @return null or -1 on error.
	 */
	static function deleteUser($userId, &$deletedSubUsers){
		$children = array();
		try {
			// FIND ALL CHILDREN FIRST
			$children_results = dibi::query('SELECT [login] FROM [ajxp_user_rights] WHERE [repo_uuid] = %s AND [rights] = %s', "ajxp.parent_user", $userId);
			$all = $children_results->fetchAll();
			foreach ($all as $item){
				$children[] = $item["login"];
			}
			dibi::begin();
			//This one is done by AUTH_DRIVER, not CONF_DRIVER
			//dibi::query('DELETE FROM [ajxp_users] WHERE [login] = %s', $userId);
			dibi::query('DELETE FROM [ajxp_user_rights] WHERE [login] = %s', $userId);
			dibi::query('DELETE FROM [ajxp_user_prefs] WHERE [login] = %s', $userId);
			dibi::query('DELETE FROM [ajxp_user_bookmarks] WHERE [login] = %s', $userId);					
			dibi::commit();
			foreach ($children as $childId){
				AJXP_User::deleteUser($childId, $deletedSubUsers);
				$deletedSubUsers[] = $childId;
			}
		} catch (DibiException $e) {
			throw new Exception('Failed to delete user, Reason: '.$e->getMessage());
		}
	}

}
