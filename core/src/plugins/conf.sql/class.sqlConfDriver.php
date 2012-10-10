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
 * @package info.ajaxplorer.plugins
 * Configuration stored in an SQL Database
 */
class sqlConfDriver extends AbstractConfDriver {
		
	
	/**
	 * Initialise the driver.
	 * 
	 * Expects options containing a key 'SQL_DRIVER' with constructor values from dibi::connect()
	 * 
	 * Example:
	 * 		"SQL_DRIVER" => Array(
	 *		'driver' => 'sqlite',
	 *			'file' => "./server/ajxp.db"
	 *		)
	 *
	 * Example 2:
	 * 		"SQL_DRIVER" => Array(
	 * 		'driver' => 'mysql',
	 * 		'host' => 'localhost',
	 * 		'username' => 'root',
	 * 		'password' => '***',
	 * 		'database' => 'dbname'
	 * 		)
	 * 
	 * @see AbstractConfDriver#init($options)
	 */
	function init($options){
		parent::init($options);
		require_once(AJXP_BIN_FOLDER."/dibi.compact.php");		
		$this->sqlDriver = $options["SQL_DRIVER"];
		try {
			dibi::connect($this->sqlDriver);		
		} catch (DibiException $e) {
			echo get_class($e), ': ', $e->getMessage(), "\n";
			exit(1);
		}
	}
	
	function _loadPluginConfig($pluginId, &$options){
		$res_opts = dibi::query('SELECT * FROM [ajxp_plugin_configs] WHERE [id] = %s', $pluginId);
		if (count($res_opts) > 0) {
			$config_row = $res_opts->fetchPairs();
			$confOpt = unserialize($config_row[$pluginId]);
			if(is_array($confOpt)){
				foreach($confOpt as $key => $value) $options[$key] = $value;
			}
		}
	}
	
	/**
	 * 
	 * @param String $pluginType
	 * @param String $pluginId
	 * @param String $options
	 */
	function savePluginConfig($pluginId, $options){
		$res_opts = dibi::query('SELECT * FROM [ajxp_plugin_configs] WHERE [id] = %s', $pluginId);
		if(count($res_opts)){
			dibi::query('UPDATE [ajxp_plugin_configs] SET [configs] = %s WHERE [id] = %s', serialize($options), $pluginId);
		}else{
			dibi::query('INSERT INTO [ajxp_plugin_configs]', array('id' => $pluginId, 'configs' => serialize($options)));
		}
	}
	
	/**
	 * Create a Repository object from a Database Result
	 * 
	 * The method expects the following schema:
	 * CREATE TABLE ajxp_repo ( uuid VARCHAR(33) PRIMARY KEY, 
	 * 							path VARCHAR(255), 
	 * 							display VARCHAR(255), 
	 * 							accessType VARCHAR(20), 
	 * 							recycle VARCHAR(255) , 
	 * 							bcreate BOOLEAN, -- For some reason 'create' is a reserved keyword
	 * 							writeable BOOLEAN, 
	 * 							enabled BOOLEAN );
	 * 
	 * Additionally, the options are stored in a separate table:
	 * CREATE TABLE ajxp_repo_options ( oid INTEGER PRIMARY KEY, uuid VARCHAR(33), name VARCHAR(50), val VARCHAR(255) );
	 * 
	 * I recommend an index to increase performance of uuid lookups:
	 * CREATE INDEX ajxp_repo_options_uuid_idx ON ajxp_repo_options ( uuid );
	 * 
	 * 
	 * @param $result Result of a dibi::query() as array
	 * @param $options_result Result of dibi::query() for options as array
	 * @return Repository object
	 */
	function repoFromDb($result, $options_result = Array())
	{
		$repo = new Repository($result['id'], $result['display'], $result['accessType']);
		$repo->uuid = $result['uuid'];
		$repo->setOwnerData($result['parent_uuid'], $result['owner_user_id'], $result['child_user_id']);
		$repo->path = $result['path'];
		$repo->create = $result['bcreate'];
		$repo->writeable = $result['writeable'];
		$repo->writeable = true;
		$repo->enabled = $result['enabled'];
		$repo->recycle = "";
		$repo->setSlug($result['slug']);
        $repo->isTemplate = intval($result['isTemplate']) == 1 ? true : false;
        $repo->setInferOptionsFromParent(intval($result['inferOptionsFromParent']) == 1 ? true : false);

		foreach ($options_result as $k => $v) {
            if(strpos($v, '$phpserial$') !== false && strpos($v, '$phpserial$') === 0){
                $v = unserialize(substr($v, strlen('$phpserial$')));
            }else if($k == "META_SOURCES"){
                $v = unserialize($v);
            }
			$repo->options[$k] = $v;
		}
		
		return $repo;
	}
	
	/**
	 * Convert a repository object to an array, which will be stored in the database.
	 * 
	 * @param $repository Repository
	 * @return Array containing row values, and another array with the key "options" to be stored as repo options.
	 */
	function repoToArray($repository)
	{

		$repository_row = Array(
				'uuid' => $repository->getUniqueId(),
				'parent_uuid' => $repository->getParentId(), 
				'owner_user_id' => $repository->getOwner(), 
				'child_user_id' => $repository->getUniqueUser(), 
				'path' => $repository->options['PATH'],
				'display' => $repository->getDisplay(),
				'accessType' => $repository->getAccessType(),
				'recycle' => $repository->recycle, 
				'bcreate' => $repository->getCreate(),
				'writeable' => $repository->isWriteable(),
				'enabled' => $repository->isEnabled(),
				'options' => $repository->options,
				'slug'		=> $repository->getSlug(),
                'isTemplate'=> $repository->isTemplate,
                'inferOptionsFromParent'=> ($repository->getInferOptionsFromParent()?1:0)
		);
		
		return $repository_row;
	}
	
	
	/**
	 * Get a list of repositories
	 * 
	 * The list is an associative array of Array( 'uuid' => [Repository Object] );
	 * 
	 * @todo Create a repository object that lazy loads options, so that these list queries don't incur the multiple queries of options.
	 * @see AbstractConfDriver#listRepositories()
	 */
	function listRepositories(){

		$res = dibi::query('SELECT * FROM [ajxp_repo]');
		$all = $res->fetchAll();
		
		$repositories = Array();
		
		foreach ($all as $repo_row) {

			$res_opts = dibi::query('SELECT * FROM [ajxp_repo_options] WHERE [uuid] = %s', $repo_row['uuid']);
			$opts = $res_opts->fetchPairs('name', 'val');
			$repo = $this->repoFromDb($repo_row, $opts);
						
			$repositories[$repo->getUniqueId()] = $repo;	
		}
		
		return $repositories;
	}
	
	/**
	 * Get repository by Unique ID (a hash calculated from the serialised object).
	 *
	 * @param String $repositoryId hash uuid
	 * @return Repository object
	 */
	function getRepositoryById($repositoryId){
		$res = dibi::query('SELECT * FROM [ajxp_repo] WHERE [uuid] = %s', $repositoryId);
		
		if (count($res) > 0) {
            $repo_row = $res->fetchAll();
            $repo_row = $repo_row[0];
			$res_opts = dibi::query('SELECT * FROM [ajxp_repo_options] WHERE [uuid] = %s', $repo_row['uuid']);
			$opts = $res_opts->fetchPairs('name', 'val');
			$repository = $this->repoFromDb($repo_row, $opts);	
			return $repository;
		}
		
		return null;
	}
	
	/**
	 * Retrieve a Repository given its alias.
	 *
	 * @param String $repositorySlug
	 * @return Repository
	 */	
	function getRepositoryByAlias($repositorySlug){
		$res = dibi::query('SELECT * FROM [ajxp_repo] WHERE [slug] = %s', $repositorySlug);
		
		if (count($res) > 0) {
            $repo_row = $res->fetchAll();
            $repo_row = $repo_row[0];
            $res_opts = dibi::query('SELECT * FROM [ajxp_repo_options] WHERE [uuid] = %s', $repo_row['uuid']);
            $opts = $res_opts->fetchPairs('name', 'val');
			$repository = $this->repoFromDb($repo_row, $opts);
			return $repository;
		}
		
		return null;		
	}
	
	
	/**
	 * Store a newly created repository 
	 *
	 * @param Repository $repositoryObject
	 * @param Boolean $update 
	 * @return -1 if failed
	 */
	function saveRepository($repositoryObject, $update = false){
		try {
				$repository_array = $this->repoToArray($repositoryObject);
				$options = $repository_array['options'];
				unset($repository_array['options']);
			if (!$update) {
				dibi::query('INSERT INTO [ajxp_repo]', $repository_array);

				foreach ($options as $k => $v ) {
                    if(!is_string($v)){
                        $v = '$phpserial$'.serialize($v);
                    }
					dibi::query('INSERT INTO [ajxp_repo_options]', 
						Array(
							'uuid' => $repositoryObject->getUniqueId(),
							'name' => $k,
							'val' => $v
						)
					);
				}
				/*
				//set maximum rights to the repositorie's creator jcg
				$user_right['login'] = $_SESSION["AJXP_USER"]->id;
				$user_right['repo_uuid'] = $repository_array['uuid'];
				$user_right['rights'] = 'rw';
				dibi::query('INSERT INTO [ajxp_user_rights]', $user_right);
				$userid=$_SESSION["AJXP_USER"]->id;
				*/
				
			} else {
				dibi::query('DELETE FROM [ajxp_repo] WHERE [uuid] = %s',$repositoryObject->getUniqueId());
				dibi::query('DELETE FROM [ajxp_repo_options] WHERE [uuid] = %s',$repositoryObject->getUniqueId());
				dibi::query('INSERT INTO [ajxp_repo]', $repository_array);
				foreach ($options as $k => $v ) {
                    if(!is_string($v)){
                        $v = '$phpserial$'.serialize($v);
                    }
					dibi::query('INSERT INTO [ajxp_repo_options]', 
						Array(
							'uuid' => $repositoryObject->getUniqueId(),
							'name' => $k,
							'val' => $v
						)
					);
				}
			}
		
		} catch (DibiException $e) {
			
			echo get_class($e), ': ', $e->getMessage(), "\n";
			exit(1);
			
			return -1;
		}
	}
	/**
	 * Delete a repository, given its unique ID.
	 *
	 * @param String $repositoryId
	 */	
	function deleteRepository($repositoryId){
		try {
			$result = dibi::query('DELETE FROM [ajxp_repo] WHERE [uuid] = %s', $repositoryId);
			$result_opts = dibi::query('DELETE FROM [ajxp_repo_options] WHERE [uuid] = %s', $repositoryId);
			$result_opts_rights = dibi::query('DELETE FROM [ajxp_user_rights] WHERE [repo_uuid] = %s',$repositoryId); //jcg

			
		} catch (DibiException $e) {
			return -1;
		}
		
		// Deleting a non-existent repository also qualifies as an error jcg Call to a member function getAffectedRows() on a non-object 
		/*
		if (false === $result->getAffectedRows()) {
			return -1;
		}
		*/
	}

    function getUserChildren( $userId ){

        $children = array();
        $children_results = dibi::query('SELECT [login] FROM [ajxp_user_rights] WHERE [repo_uuid] = %s AND [rights] = %s', "ajxp.parent_user", $userId);
        $all = $children_results->fetchAll();
        foreach ($all as $item){
            $children[] = $this->createUserObject($item["login"]);
        }
        return $children;

    }

    /**
     * @param string $repositoryId
     * @return array()
     */
    function getUsersForRepository($repositoryId){
        $result = array();
        $children_results = dibi::query('SELECT [login] FROM [ajxp_user_rights] WHERE [repo_uuid] = %s GROUP BY [login]', $repositoryId);
        $all = $children_results->fetchAll();
        foreach ($all as $item){
            $result[] = $this->createUserObject($item["login"]);
        }
        return $result;
    }

	// SAVE / EDIT / CREATE / DELETE USER OBJECT (except password)
	/**
	 * Instantiate the right class
	 *
	 * @param AbstractAjxpUser $userId
	 */
	function instantiateAbstractUserImpl($userId){
		return new AJXP_SqlUser($userId, $this);
	}
	
	/**
	 * Get the full path to the Ajxp user class.
	 * 
	 * @see AbstractConfDriver#getUserClassFileName()
	 */
	function getUserClassFileName(){
		return AJXP_INSTALL_PATH."/plugins/conf.sql/class.AJXP_SqlUser.php";
	}	
	
	
	function listRoles($roleIds = array(), $excludeReserved = false){

        $q = 'SELECT * FROM [ajxp_roles]';
        $wClauses = array();
        if(count($roleIds)){
            foreach($roleIds as $id){
                $wClauses[] = "[role_id] = '$id'";
            }
        }
        if(count($wClauses)){
            $wClauses = array("(".implode(" OR ", $wClauses).")");
        }
        if($excludeReserved){
            $wClauses[] = "[role_id] NOT LIKE 'AJXP_%'";
        }
        if(count($wClauses)){
            $q .= " WHERE ".implode(" AND ", $wClauses);
        }
        $res = dibi::query($q);
        $all = $res->fetchAll();
		
		$roles = Array();
		
		foreach ($all as $role_row) {
			$id = $role_row['role_id'];
			$serialized = $role_row['serial_role'];
			$object = unserialize($serialized);
			if(is_a($object, "AjxpRole") || is_a($object, "AJXP_Role")){
				$roles[$id] = $object;
			}
		}
		
		return $roles;
		
	}
	
	function saveRoles($roles){
		dibi::query("DELETE FROM [ajxp_roles]");
		foreach ($roles as $roleId => $roleObject){
			dibi::query("INSERT INTO [ajxp_roles]", array(
				'role_id' => $roleId, 
				'serial_role' => serialize($roleObject))
				);
		}
	}

    /**
     * @param AJXP_Role $role
     */
    function updateRole($role, $userObject = null){
        dibi::query("DELETE FROM [ajxp_roles] WHERE [role_id]=%s", $role->getId());
        dibi::query("INSERT INTO [ajxp_roles]", array(
                'role_id' => $role->getId(),
                'serial_role' => serialize($role))
        );
    }

    /**
     * @param AJXP_Role $role
     */
    function deleteRole($role){
        // Mixed input Object or ID
        if(is_a($role, "AJXP_Role")) $roleId = $role->getId();
        else $roleId = $role;

        dibi::query("DELETE FROM [ajxp_roles] WHERE [role_id]=%s", $roleId);
    }
	
	function countAdminUsers(){
		$rows = dibi::query("SELECT [login] FROM ajxp_user_rights WHERE [repo_uuid] = %s AND [rights] = %s", "ajxp.admin", "1");
		return count($rows);
	}

    /**
     * @param AbstractAjxpUser[] $flatUsersList
     * @param string $baseGroup
     * @param bool $fullTree
     * @return void
     * @todo
     */
    function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false){

    }

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return mixed
     */
    function createGroup($groupPath, $groupLabel){
        dibi::query("INSERT INTO [ajxp_groups]", array("groupPath" => $groupPath, "groupLabel" => $groupLabel));
    }

    function relabelGroup($groupPath, $groupLabel){
        dibi::query("UPDATE [ajxp_groups] SET [groupLabel]=%s WHERE [groupPath]=%s", $groupLabel, $groupPath);
    }


    function deleteGroup($groupPath){
        dibi::query("DELETE FROM [ajxp_groups] WHERE [groupPath] = %s", $groupPath);
    }

    /**
     * @param string $baseGroup
     * @return string[]
     */
    function getChildrenGroups($baseGroup = "/"){
        $res = dibi::query("SELECT * FROM [ajxp_groups] WHERE [groupPath] LIKE %s", $baseGroup."%");
        $pairs = $res->fetchPairs("groupPath", "groupLabel");
        foreach($pairs as $path => $label){
            if(strlen($path) <= strlen($baseGroup)) unset($pairs[$path]);
        }
        return $pairs;
    }

    /**
     * Function for deleting a user
     *
     * @param String $userId
     * @param Array $deletedSubUsers
     * @throws Exception
     * @return void
     */
    function deleteUser($userId, &$deletedSubUsers)
    {
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
                $this->deleteUser($childId, $deletedSubUsers);
                $deletedSubUsers[] = $childId;
            }
        } catch (DibiException $e) {
            throw new Exception('Failed to delete user, Reason: '.$e->getMessage());
        }
    }

    protected function simpleStoreSet($storeID, $dataID, $data, $dataType = "serial", $relatedObjectId = null){
        $values = array(
            "store_id" => $storeID,
            "object_id" => $dataID
        );
        if($relatedObjectId != null){
            $values["related_object_id"] = $relatedObjectId;
        }
        if($dataType == "serial"){
            $values["serialized_data"] = serialize($data);
        }else if($dataType == "binary"){
            $values["binary_data"] = $data;
        }else{
            throw new Exception("Unsupported format type ".$dataType);
        }
        dibi::query("DELETE FROM [ajxp_simple_store] WHERE [store_id]=%s AND [object_id]=%s", $storeID, $dataID);
        dibi::query("INSERT INTO [ajxp_simple_store]", $values);
    }

    protected function simpleStoreGet($storeID, $dataID, $dataType = "serial", &$data){
        $children_results = dibi::query("SELECT * FROM [ajxp_simple_store] WHERE [store_id]=%s AND [object_id]=%s", $storeID, $dataID);
        $value = $children_results->fetchAll();
        if(!count($value)) return false;
        $value = $value[0];
        if($dataType == "serial"){
            $data = unserialize($value["serialized_data"]);
        }else{
            $data = $value["binary_data"];
        }
        if(isSet($value["related_object_id"])){
            return $value["related_object_id"];
        }else{
            return false;
        }
    }

    protected function binaryContextToStoreID($context){
        $storage = null;
        if(isSet($context["USER"])){
            $storage ="users_binaries.".$context["USER"];
        }else if(isSet($context["REPO"])){
            $storage ="repos_binaries.".$context["REPO"];
        }else if(isSet($context["ROLE"])){
            $storage ="roles_binaries.".$context["REPO"];
        }
        return $storage;
    }
    /**
     * @param array $context
     * @param String $fileName
     * @param String $ID
     * @return String $ID
     */
    function saveBinary($context, $fileName, $ID = null)
    {
        if(empty($ID)){
            $ID = substr(md5(microtime()*rand(0,100)), 0, 12);
            $ID .= ".".pathinfo($fileName, PATHINFO_EXTENSION);
        }
        $store = $this->binaryContextToStoreID($context);
        $this->simpleStoreSet($store, $ID, file_get_contents($fileName), "binary");
        return $ID;
    }

    /**
     * @param array $context
     * @param String $ID
     * @param Resource $outputStream
     * @return boolean
     */
    function loadBinary($context, $ID, $outputStream = null)
    {
        $store = $this->binaryContextToStoreID($context);
        $data = "";
        $this->simpleStoreGet($store, $ID, "binary", $data);
        if($outputStream != null ){
            fwrite($outputStream, $data, strlen($data));
        } else{
            header("Content-Type: ".AJXP_Utils::getImageMimeType($ID));
            echo $data;
        }
    }

    /**
     * @param string $queueName
     * @param Object $object
     * @return bool
     */
    function storeObjectToQueue($queueName, $object)
    {
        $r = dibi::query("SELECT MAX( [object_id] ) FROM [ajxp_simple_store] WHERE [store_id]=%s", "queues.$queueName");
        $index = $r->fetchSingle();
        if($index == null) $index = 1;
        else $index = intval($index)+1;
        $values = array(
            "store_id" => "queues.$queueName",
            "object_id" => $index,
            "serialized_data" => serialize($object)
        );
        dibi::query("INSERT INTO [ajxp_simple_store]", $values);
    }

    /**
     * @param string $queueName Name of the queue
     * @return array An array of arbitrary objects, understood by the caller
     */
    function consumeQueue($queueName)
    {
        $results = dibi::query("SELECT * FROM [ajxp_simple_store] WHERE [store_id]=%s ORDER BY [object_id] ASC", "queues.$queueName");
        $rows = $results->fetchAll();
        $arr = array();
        $deleted = array();
        foreach($rows as $row){
            $arr[] = unserialize($row["serialized_data"]);
            $deleted[] = $row["object_id"];
        }
        dibi::query("DELETE FROM [ajxp_simple_store] WHERE [store_id]=%s AND [object_id] IN (%s)", "queues.$queueName", $deleted);
        return $arr;
    }
}
?>