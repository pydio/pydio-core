<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Configuration stored in an SQL Database
 * @package AjaXplorer_Plugins
 * @subpackage Conf
 */
class sqlConfDriver extends AbstractConfDriver
{
    public $sqlDriver = array();

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
    public function init($options)
    {
        parent::init($options);
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        $this->sqlDriver = AJXP_Utils::cleanDibiDriverParameters($options["SQL_DRIVER"]);
        try {
            dibi::connect($this->sqlDriver);
        } catch (DibiException $e) {
            //throw $e;
            echo get_class($e), ': ', $e->getMessage(), "\n";
            exit(1);
        }
    }

    public function performChecks()
    {
        if(!isSet($this->options)) return;
        $test = AJXP_Utils::cleanDibiDriverParameters($this->options["SQL_DRIVER"]);
        if (!count($test)) {
            throw new Exception("You probably did something wrong! To fix this issue you have to remove the file \"bootsrap.json\" and rename the backup file \"bootstrap.json.bak\" into \"bootsrap.json\" in data/plugins/boot.conf/");
        }
    }

    public function _loadPluginConfig($pluginId, &$options)
    {
        $res_opts = dibi::query('SELECT * FROM [ajxp_plugin_configs] WHERE [id] = %s', $pluginId);
        $config_row = $res_opts->fetchPairs();
        $confOpt = unserialize($config_row[$pluginId]);
        if (is_array($confOpt)) {
            foreach($confOpt as $key => $value) $options[$key] = $value;
        }
    }

    /**
     *
     * @param String $pluginId
     * @param String $options
     */
    public function _savePluginConfig($pluginId, $options)
    {
        $res_opts = dibi::query('SELECT COUNT(*) FROM [ajxp_plugin_configs] WHERE [id] = %s', $pluginId);
        if ($res_opts->fetchSingle()) {
            dibi::query('UPDATE [ajxp_plugin_configs] SET [configs] = %bin WHERE [id] = %s', serialize($options), $pluginId);
        } else {
            dibi::query('INSERT INTO [ajxp_plugin_configs] ([id],[configs]) VALUES (%s,%bin)', $pluginId, serialize($options));
        }
    }

    /**
     * Create a Repository object from a Database Result
     *
     * The method expects the following schema:
     * CREATE TABLE ajxp_repo ( uuid VARCHAR(33) PRIMARY KEY,
     *                             path VARCHAR(255),
     *                             display VARCHAR(255),
     *                             accessType VARCHAR(20),
     *                             recycle VARCHAR(255) ,
     *                             bcreate BOOLEAN, -- For some reason 'create' is a reserved keyword
     *                             writeable BOOLEAN,
     *                             enabled BOOLEAN );
     *
     * Additionally, the options are stored in a separate table:
     * CREATE TABLE ajxp_repo_options ( oid INTEGER PRIMARY KEY, uuid VARCHAR(33), name VARCHAR(50), val VARCHAR(255) );
     *
     * I recommend an index to increase performance of uuid lookups:
     * CREATE INDEX ajxp_repo_options_uuid_idx ON ajxp_repo_options ( uuid );
     *
     *
     * @param $result Result of a dibi::query() as array
     * @param array|\Result $options_result Result of dibi::query() for options as array
     * @return Repository object
     */
    public function repoFromDb($result, $options_result = Array())
    {
        $repo = new Repository($result['id'], $result['display'], $result['accessType']);
        $repo->uuid = $result['uuid'];
        $repo->setOwnerData($result['parent_uuid'], $result['owner_user_id'], $result['child_user_id']);
        $repo->path = $result['path'];
        $repo->create = (bool) $result['bcreate'];
        $repo->writeable = (bool) $result['writeable'];
        $repo->enabled = (bool) $result['enabled'];
        $repo->recycle = "";
        $repo->setSlug($result['slug']);
        if (isSet($result['groupPath']) && !empty($result['groupPath'])) {
            $repo->setGroupPath($result['groupPath']);
        }
        $repo->isTemplate = (bool) $result['isTemplate'];
        $repo->setInferOptionsFromParent((bool) $result['inferOptionsFromParent']);

        foreach ($options_result as $k => $v) {
            if (strpos($v, '$phpserial$') !== false && strpos($v, '$phpserial$') === 0) {
                $v = unserialize(substr($v, strlen('$phpserial$')));
            } else if ($k == "META_SOURCES") {
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
    public function repoToArray($repository)
    {

        $repository_row = Array(
                'uuid'                      => $repository->getUniqueId(),
                'parent_uuid'               => $repository->getParentId(),
                'owner_user_id'             => $repository->getOwner(),
                'child_user_id'             => $repository->getUniqueUser(),
                'path'                      => $repository->options['PATH'],
                'display'                   => $repository->getDisplay(),
                'accessType'                => $repository->getAccessType(),
                'recycle'                   => $repository->recycle,
                'bcreate'                   => $repository->getCreate(),
                'writeable'                 => $repository->isWriteable(),
                'enabled'                   => $repository->isEnabled(),
                'options'                   => $repository->options,
                'groupPath'                 => $repository->getGroupPath(),
                'slug'		                => $repository->getSlug(),
                'isTemplate'                => (bool) $repository->isTemplate,
                'inferOptionsFromParent'    => $repository->getInferOptionsFromParent()
        );

        return $repository_row;
    }


    /**
     * Get a list of repositories
     *
     * The list is an associative array of Array( 'uuid' => [Repository Object] );
     * @param AbstractAjxpUser $user
     * @return Repository[]
     * @see AbstractConfDriver#listRepositories()
     */
    public function listRepositories($user = null)
    {
        if ($user != null) {
            $acls = $user->mergedRole->listAcls();
            $limitRepositories = array_keys($acls);
            if(!count($limitRepositories)) return array();
            // we use (%s) instead of %in to pass int as string ('1' instead of 1)
            $res = dibi::query('SELECT * FROM [ajxp_repo] WHERE [uuid] IN (%s) ORDER BY [display] ASC', $limitRepositories);
        } else {
            $res = dibi::query('SELECT * FROM [ajxp_repo] ORDER BY [display] ASC');
        }

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
    public function getRepositoryById($repositoryId)
    {
        $res = dibi::query('SELECT * FROM [ajxp_repo] WHERE [uuid] = %s', $repositoryId);

        $repo_row = $res->fetchAll();
        if (count($repo_row) > 0) {
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
    public function getRepositoryByAlias($repositorySlug)
    {
        $res = dibi::query('SELECT * FROM [ajxp_repo] WHERE [slug] = %s', $repositorySlug);

        $repo_row = $res->fetchAll();
        if (count($repo_row) > 0) {
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
    public function saveRepository($repositoryObject, $update = false)
    {
        try {
                $repository_array = $this->repoToArray($repositoryObject);
                $options = $repository_array['options'];
                unset($repository_array['options']);
            if (!$update) {
                dibi::query('INSERT INTO [ajxp_repo]', $repository_array);

                foreach ($options as $k => $v) {
                    if (!is_string($v)) {
                        $v = '$phpserial$'.serialize($v);
                    }
                    dibi::query('INSERT INTO [ajxp_repo_options] ([uuid],[name],[val]) VALUES (%s,%s,%bin)', $repositoryObject->getUniqueId(), $k,$v);
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
                foreach ($options as $k => $v) {
                    if (!is_string($v)) {
                        $v = '$phpserial$'.serialize($v);
                    }
                    dibi::query('INSERT INTO [ajxp_repo_options] ([uuid],[name],[val]) VALUES (%s,%s,%bin)',$repositoryObject->getUniqueId(),$k,$v);
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
    public function deleteRepository($repositoryId)
    {
        try {
            $result = dibi::query('DELETE FROM [ajxp_repo] WHERE [uuid] = %s', $repositoryId);
            $result_opts = dibi::query('DELETE FROM [ajxp_repo_options] WHERE [uuid] = %s', $repositoryId);
            $result_opts_rights = dibi::query('DELETE FROM [ajxp_user_rights] WHERE [repo_uuid] = %s',$repositoryId); //jcg

            switch ($this->sqlDriver["driver"]) {
                case "sqlite":
                case "sqlite3":
                case "postgre":
                    $children_results = dibi::query('SELECT * FROM [ajxp_roles] WHERE [searchable_repositories] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                    break;
                case "mysql":
                    $children_results = dibi::query('SELECT * FROM [ajxp_roles] WHERE [serial_role] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                    break;
                default:
                    return "ERROR!, DB driver "+ $this->sqlDriver["driver"] +" not supported yet in __FUNCTION__";
            }
            $all = $children_results->fetchAll();
            foreach ($all as $item) {
                $role = unserialize($item["serial_role"]);
                $role->setAcl($repositoryId, "");
                $this->updateRole($role);
            }

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

    public function getUserChildren( $userId )
    {
        $children = array();
        $children_results = dibi::query('SELECT [ajxp_users].[login] FROM [ajxp_user_rights],[ajxp_users] WHERE [repo_uuid] = %s AND [rights] = %s AND [ajxp_user_rights].[login] = [ajxp_users].[login]', "ajxp.parent_user", $userId);
        $all = $children_results->fetchAll();
        foreach ($all as $item) {
            $children[] = $this->createUserObject($item["login"]);
        }
        return $children;

    }

    /**
     * @param string $repositoryId
     * @return array()
     */
    public function getUsersForRepository($repositoryId)
    {
        $result = array();
        // OLD METHOD
        $children_results = dibi::query('SELECT [ajxp_users].[login] FROM [ajxp_user_rights],[ajxp_users] WHERE [repo_uuid] = %s AND [ajxp_user_rights].[login] = [ajxp_users].[login] GROUP BY [ajxp_users].[login]', $repositoryId);
        $all = $children_results->fetchAll();
        foreach ($all as $item) {
            $result[$item["login"]] = $this->createUserObject($item["login"]);
        }
        // NEW METHOD : SEARCH PERSONAL ROLE
        switch ($this->sqlDriver["driver"]) {
            case "sqlite":
            case "sqlite3":
            case "postgre":
                $children_results = dibi::query('SELECT [role_id] FROM [ajxp_roles] WHERE [searchable_repositories] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                break;
            case "mysql":
                $children_results = dibi::query('SELECT [role_id] FROM [ajxp_roles] WHERE [serial_role] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                break;
            default:
                return "ERROR!, DB driver "+ $this->sqlDriver["driver"] +" not supported yet in __FUNCTION__";
        }
        $all = $children_results->fetchAll();
        foreach ($all as $item) {
            $rId = $item["role_id"];
            if (strpos($rId, "AJXP_USR/") == 0) {
                $id = substr($rId, strlen("AJXP_USR/")+1);
                $result[$id] = $this->createUserObject($id);
            }
        }

        return $result;
    }

    // SAVE / EDIT / CREATE / DELETE USER OBJECT (except password)
    /**
     * Instantiate the right class
     *
     * @param AbstractAjxpUser $userId
     */
    public function instantiateAbstractUserImpl($userId)
    {
        return new AJXP_SqlUser($userId, $this);
    }

    /**
     * Get the full path to the Ajxp user class.
     *
     * @see AbstractConfDriver#getUserClassFileName()
     */
    public function getUserClassFileName()
    {
        return AJXP_INSTALL_PATH."/plugins/conf.sql/class.AJXP_SqlUser.php";
    }


    public function listRoles($roleIds = array(), $excludeReserved = false)
    {
        $wClauses = array();
        if (count($roleIds)) {
            // We use (%s) instead of %in to pass everyting as string ('1' instead of 1)
            $wClauses[] = array('[role_id] IN (%s)', $roleIds);
        }
        if ($excludeReserved) {
            $wClauses[] = array('[role_id] NOT LIKE %like~', 'AJXP_');
        }
        $res = dibi::query('SELECT * FROM [ajxp_roles] %if', count($wClauses), 'WHERE %and', $wClauses);
        $all = $res->fetchAll();

        $roles = Array();

        foreach ($all as $role_row) {
            $id = $role_row['role_id'];
            $serialized = $role_row['serial_role'];
            $object = unserialize($serialized);
            if (is_a($object, "AjxpRole") || is_a($object, "AJXP_Role")) {
                $roles[$id] = $object;
            }
        }

        return $roles;

    }

    /**
     * @param AJXP_Role[] $roles
     */
    public function saveRoles($roles)
    {
        dibi::query("DELETE FROM [ajxp_roles]");
        foreach ($roles as $roleId => $roleObject) {
            switch ($this->sqlDriver["driver"]) {
                case "sqlite":
                case "sqlite3":
                case "postgre":
                    dibi::query("INSERT INTO [ajxp_roles] ([role_id],[serial_role],[searchable_repositories]) VALUES (%s, %bin, %s)", $roleId, serialize($roleObject), serialize($roleObject->listAcls()));
                    break;
                case "mysql":
                    dibi::query("INSERT INTO [ajxp_roles] ([role_id],[serial_role]) VALUES (%s, %s)", $roleId, serialize($roleObject));
                    break;
                default:
                    return "ERROR!, DB driver "+ $this->sqlDriver["driver"] +" not supported yet in __FUNCTION__";
            }
        }
    }

    /**
     * @param AJXP_Role $role
     */
    public function updateRole($role, $userObject = null)
    {
        dibi::query("DELETE FROM [ajxp_roles] WHERE [role_id]=%s", $role->getId());
        switch ($this->sqlDriver["driver"]) {
            case "sqlite":
            case "sqlite3":
            case "postgre":
                dibi::query("INSERT INTO [ajxp_roles] ([role_id],[serial_role],[searchable_repositories]) VALUES (%s, %bin,%s)", $role->getId(), serialize($role), serialize($role->listAcls()));
                break;
            case "mysql":
                dibi::query("INSERT INTO [ajxp_roles] ([role_id],[serial_role]) VALUES (%s, %s)", $role->getId(), serialize($role));
                break;
            default:
                return "ERROR!, DB driver "+ $this->sqlDriver["driver"] +" not supported yet in __FUNCTION__";
        }
    }

    /**
     * @param AJXP_Role $role
     */
    public function deleteRole($role)
    {
        // Mixed input Object or ID
        if(is_a($role, "AJXP_Role")) $roleId = $role->getId();
        else $roleId = $role;

        dibi::query("DELETE FROM [ajxp_roles] WHERE [role_id]=%s", $roleId);
    }

    public function countAdminUsers()
    {
        $rows = dibi::query("SELECT COUNT(*) FROM ajxp_user_rights WHERE [repo_uuid] = %s AND [rights] = %s", "ajxp.admin", "1");
        return $rows->fetchSingle();
    }

    /**
     * @param AbstractAjxpUser[] $flatUsersList
     * @param string $baseGroup
     * @param bool $fullTree
     * @return void
     * @todo
     */
    public function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false)
    {
    }

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return mixed
     */
    public function createGroup($groupPath, $groupLabel)
    {
        $test = dibi::query("SELECT COUNT(*) FROM [ajxp_groups] WHERE [groupPath] = %s", $groupPath);
        if ($test->fetchSingle()) {
            dibi::query("UPDATE [ajxp_groups] SET [groupLabel]=%s WHERE [groupPath]=%s", $groupLabel, $groupPath);
        } else {
            dibi::query("INSERT INTO [ajxp_groups]",array("groupPath" => $groupPath, "groupLabel" => $groupLabel));
        }
    }

    public function relabelGroup($groupPath, $groupLabel)
    {
        dibi::query("UPDATE [ajxp_groups] SET [groupLabel]=%s WHERE [groupPath]=%s", $groupLabel, $groupPath);
    }


    public function deleteGroup($groupPath)
    {
        // Delete users of this group, as well as subgroups
        $res = dibi::query("SELECT * FROM [ajxp_users] WHERE [groupPath] LIKE %like~ OR [groupPath] = %s ORDER BY [login] ASC", $groupPath."/", $groupPath);
        $rows = $res->fetchAll();
        $subUsers = array();
        foreach ($rows as $row) {
            $this->deleteUser($row["login"], $subUsers);
            dibi::query("DELETE FROM [ajxp_users] WHERE [login] = %s", $row["login"]);
        }
        dibi::query("DELETE FROM [ajxp_groups] WHERE [groupPath] LIKE %like~ OR [groupPath] = %s", $groupPath."/", $groupPath);
        dibi::query('DELETE FROM [ajxp_roles] WHERE [role_id] = %s', 'AJXP_GRP_'.$groupPath);
    }

    /**
     * @param string $baseGroup
     * @return string[]
     */
    public function getChildrenGroups($baseGroup = "/")
    {
        $searchGroup = $baseGroup;
        if($baseGroup[strlen($baseGroup)-1] != "/") $searchGroup.="/";
        $res = dibi::query("SELECT * FROM [ajxp_groups] WHERE [groupPath] LIKE %like~ AND [groupPath] NOT LIKE %s", $searchGroup, $searchGroup."%/%");
        $pairs = $res->fetchPairs("groupPath", "groupLabel");
        foreach ($pairs as $path => $label) {
            if(strlen($path) <= strlen($baseGroup)) unset($pairs[$path]);
            if ($baseGroup != "/") {
                unset($pairs[$path]);
                $pairs[substr($path, strlen($baseGroup))] = $label;
            }
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
    public function deleteUser($userId, &$deletedSubUsers)
    {
        $children = array();
        try {
            // FIND ALL CHILDREN FIRST
            $children_results = dibi::query('SELECT [login] FROM [ajxp_user_rights] WHERE [repo_uuid] = %s AND [rights] = %s', "ajxp.parent_user", $userId);
            $all = $children_results->fetchAll();
            foreach ($all as $item) {
                $children[] = $item["login"];
            }
            dibi::begin();
            //This one is done by AUTH_DRIVER, not CONF_DRIVER
            //dibi::query('DELETE FROM [ajxp_users] WHERE [login] = %s', $userId);
            dibi::query('DELETE FROM [ajxp_user_rights] WHERE [login] = %s', $userId);
            dibi::query('DELETE FROM [ajxp_user_prefs] WHERE [login] = %s', $userId);
            dibi::query('DELETE FROM [ajxp_user_bookmarks] WHERE [login] = %s', $userId);
            dibi::query('DELETE FROM [ajxp_roles] WHERE [role_id] = %s', 'AJXP_USR_/'.$userId);
            dibi::commit();
            foreach ($children as $childId) {
                $this->deleteUser($childId, $deletedSubUsers);
                $deletedSubUsers[] = $childId;
            }
        } catch (DibiException $e) {
            throw new Exception('Failed to delete user, Reason: '.$e->getMessage());
        }
    }

    protected function simpleStoreSet($storeID, $dataID, $data, $dataType = "serial", $relatedObjectId = null)
    {
        $values = array(
            "store_id" => $storeID,
            "object_id" => $dataID
        );
        if ($relatedObjectId != null) {
            $values["related_object_id"] = $relatedObjectId;
        }
        if ($dataType == "serial") {
            $values["serialized_data"] = serialize($data);
        } else if ($dataType == "binary") {
            $values["binary_data"] = $data;
        } else {
            throw new Exception("Unsupported format type ".$dataType);
        }
        dibi::query("DELETE FROM [ajxp_simple_store] WHERE [store_id]=%s AND [object_id]=%s", $storeID, $dataID);
        dibi::query("INSERT INTO [ajxp_simple_store] ([object_id],[store_id],[serialized_data],[binary_data],[related_object_id]) VALUES (%s,%s,%bin,%bin,%s)",
            $dataID, $storeID, $values["serialized_data"], $values["binary_data"], $values["related_object_id"]);
    }

    protected function simpleStoreClear($storeID, $dataID)
    {
        dibi::query("DELETE FROM [ajxp_simple_store] WHERE [store_id]=%s AND [object_id]=%s", $storeID, $dataID);
    }

    //$dataType = "serial"
    protected function simpleStoreGet($storeID, $dataID, $dataType, &$data)
    {
        $children_results = dibi::query("SELECT * FROM [ajxp_simple_store] WHERE [store_id]=%s AND [object_id]=%s", $storeID, $dataID);
        $value = $children_results->fetchAll();
        if(!count($value)) return false;
        $value = $value[0];
        if ($dataType == "serial") {
            $data = unserialize($value["serialized_data"]);
        } else {
            $data = $value["binary_data"];
        }
        if (isSet($value["related_object_id"])) {
            return $value["related_object_id"];
        } else {
            return false;
        }
    }

    protected function binaryContextToStoreID($context)
    {
        $storage = "binaries";
        if (isSet($context["USER"])) {
            $storage ="users_binaries.".$context["USER"];
        } else if (isSet($context["REPO"])) {
            $storage ="repos_binaries.".$context["REPO"];
        } else if (isSet($context["ROLE"])) {
            $storage ="roles_binaries.".$context["ROLE"];
        } else if (isSet($context["PLUGIN"])) {
            $storage ="plugins_binaries.".$context["PLUGIN"];
        }
        return $storage;
    }
    /**
     * @param array $context
     * @param String $fileName
     * @param String $ID
     * @return String $ID
     */
    public function saveBinary($context, $fileName, $ID = null)
    {
        if (empty($ID)) {
            $ID = substr(md5(microtime()*rand(0,100)), 0, 12);
            $ID .= ".".pathinfo($fileName, PATHINFO_EXTENSION);
        }
        $store = $this->binaryContextToStoreID($context);
        $this->simpleStoreSet($store, $ID, file_get_contents($fileName), "binary");
        return $ID;
    }

    /**
     * @abstract
     * @param array $context
     * @param String $ID
     * @return boolean
     */
    public function deleteBinary($context, $ID)
    {
        $store = $this->binaryContextToStoreID($context);
        $this->simpleStoreClear($store, $ID);
    }


    /**
     * @param array $context
     * @param String $ID
     * @param Resource $outputStream
     * @return boolean
     */
    public function loadBinary($context, $ID, $outputStream = null)
    {
        $store = $this->binaryContextToStoreID($context);
        $data = "";
        $this->simpleStoreGet($store, $ID, "binary", $data);
        if ($outputStream != null) {
            fwrite($outputStream, $data, strlen($data));
        } else {
            header("Content-Type: ".AJXP_Utils::getImageMimeType($ID));
            echo $data;
        }
    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @param String $userId
     * @param Array $data
     * @return boolean
     */
    public function saveTemporaryKey($keyType, $keyId, $userId, $data)
    {
        $this->simpleStoreSet("temporakey_".$keyType, $keyId, $data, "serial", $userId);
    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @return array
     */
    public function loadTemporaryKey($keyType, $keyId)
    {
        $data = array();
        $userId = $this->simpleStoreGet("temporakey_".$keyType, $keyId, "serial", $data);
        $data['user_id'] = $userId;
        return $data;
    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @return boolean
     */
    public function deleteTemporaryKey($keyType, $keyId)
    {
        $this->simpleStoreClear("temporakey_".$keyType, $keyId);
    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $expiration
     * @return null
     */
    public function pruneTemporaryKeys($keyType, $expiration)
    {
        dibi::query("DELETE FROM [ajxp_simple_store] WHERE [store_id] = %s AND [insertion_date] < (CURRENT_TIMESTAMP - %i)", "temporakey_".$keyType, $expiration*60);
    }


    public function installSQLTables($param)
    {
        $p = AJXP_Utils::cleanDibiDriverParameters($param["SQL_DRIVER"]);
        return AJXP_Utils::runCreateTablesQuery($p, $this->getBaseDir()."/create.sql");
    }

    public function supportsUserTeams()
    {
        return true;
    }

    public function listUserTeams()
    {
        if (AuthService::getLoggedUser() == null) {
            return array();
        }
        dibi::query("DELETE FROM [ajxp_user_teams] WHERE [user_id] NOT IN (SELECT [login] FROM [ajxp_users])");
        $res = dibi::query("SELECT * FROM [ajxp_user_teams] WHERE [owner_id] = %s ORDER BY [team_id]", AuthService::getLoggedUser()->getId());
        $data = $res->fetchAll();
        $all = array();
        foreach ($data as $row) {
            $teamId = $row["team_id"];
            $userId = $row["user_id"];
            $teamLabel = $row["team_label"];
            if(!isSet($all[$teamId])) $all[$teamId] = array("LABEL" => $teamLabel, "USERS" => array());
            $all[$teamId]["USERS"][$userId] = $userId;
        }
        return $all;
    }

    public function teamIdToUsers($teamId)
    {
        $res = array();
        $teams = $this->listUserTeams();
        $teamData = $teams[$teamId];
        foreach ($teamData["USERS"] as $userId) {
            if (AuthService::userExists($userId)) {
                $res[] = $userId;
            } else {
                $this->removeUserFromTeam($teamId, $userId);
            }
        }
        return $res;
    }

    private function addUserToTeam($teamId, $userId, $teamLabel = null)
    {
        if ($teamLabel == null) {
            $res = dibi::query("SELECT [team_label] FROM [ajxp_user_teams] WHERE [team_id] = %s AND  [owner_id] = %s",
                $teamId, AuthService::getLoggedUser()->getId());
            $teamLabel = $res->fetchSingle();
        }
        dibi::query("INSERT INTO [ajxp_user_teams] ([team_id],[user_id],[team_label],[owner_id]) VALUES (%s,%s,%s,%s)",
            $teamId, $userId, $teamLabel, AuthService::getLoggedUser()->getId()
        );
    }

    private function removeUserFromTeam($teamId, $userId = null)
    {
        if ($userId == null) {
            dibi::query("DELETE FROM [ajxp_user_teams] WHERE [team_id] = %s AND  [owner_id] = %s",
                $teamId, AuthService::getLoggedUser()->getId()
            );
        } else {
            dibi::query("DELETE FROM [ajxp_user_teams] WHERE [team_id] = %s AND  [user_id] = %s AND [owner_id] = %s",
                $teamId, $userId, AuthService::getLoggedUser()->getId()
            );
        }
    }

    public function userTeamsActions($actionName, $httpVars, $fileVars)
    {
        switch ($actionName) {
            case "user_team_create":
                $userIds = $httpVars["user_ids"];
                $teamLabel = $httpVars["team_label"];
                $teamId = AJXP_Utils::slugify($teamLabel)."-".intval(rand(0,1000));
                foreach ($userIds as $userId) {
                    $this->addUserToTeam($teamId, $userId, $teamLabel);
                }
                echo 'Created Team $teamId';
                break;
            case "user_team_delete":
                $this->removeUserFromTeam($httpVars["team_id"], null);
                break;
            case "user_team_add_user":
                $this->addUserToTeam($httpVars["team_id"], $httpVars["user_id"], null);
                break;
            case "user_team_delete_user":
                $this->removeUserFromTeam($httpVars["team_id"], $httpVars["user_id"]);
                break;
        }
    }

}
