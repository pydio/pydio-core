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
        if($this->sqlDriver["driver"] == "postgre"){
            dibi::query("SET bytea_output=escape");
        }
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
        if(isSet($repo->options["content_filter"]) && is_a($repo->options["content_filter"], "ContentFilter")){
            $repo->setContentFilter($repo->options["content_filter"]);
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
     * @param Array $array
     * @return Repository[]
     */
    protected function initRepoArrayFromDbFetch($array){
        $repositories = array();
        foreach ($array as $repo_row) {
            if($this->sqlDriver["driver"] == "postgre"){
                dibi::query("SET bytea_output=escape");
            }
            $res_opts = dibi::query('SELECT * FROM [ajxp_repo_options] WHERE [uuid] = %s', $repo_row['uuid']);
            $opts = $res_opts->fetchPairs('name', 'val');
            $repo = $this->repoFromDb($repo_row, $opts);

            $repositories[$repo->getUniqueId()] = $repo;
        }
        return $repositories;
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
            return $this->listRepositoriesForRole($user->mergedRole);
        } else {
            $res = dibi::query('SELECT * FROM [ajxp_repo] ORDER BY [display] ASC');
        }
        $all = $res->fetchAll();
        return $this->initRepoArrayFromDbFetch($all);
    }

    /**
     * @param AJXP_Role $role
     * @return Repository[]
     */
    public function listRepositoriesForRole($role){
        $acls = $role->listAcls();
        if(!count($acls)) return array();
        $limitRepositories = array_keys($acls);
        $res = dibi::query('SELECT * FROM [ajxp_repo] WHERE [uuid] IN (%s) ORDER BY [display] ASC', $limitRepositories);
        $all = $res->fetchAll();
        return $this->initRepoArrayFromDbFetch($all);
    }

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param Array $criteria
     * @param int $count possible total count
     * @return Repository[]
     */
    public function listRepositoriesWithCriteria($criteria, &$count = null){

        $wheres = array();
        $limit = $groupBy = "";
        $order = "ORDER BY display ASC";

        if(isSet($criteria["role"]) && is_a($criteria["role"], "AJXP_Role")){
            return $this->listRepositoriesForRole($criteria["role"]);
        }

        $searchableKeys = array("uuid", "parent_uuid", "owner_user_id", "display", "accessType", "isTemplate", "slug", "groupPath");
        foreach($criteria as $cName => $cValue){
            if(in_array($cName, $searchableKeys) || in_array(substr($cName,1), $searchableKeys)){
                if(is_array($cValue)){
                    if($cName[0] == "!"){
                        $cName = substr($cName, 1);
                        $wheres[] = array("[$cName] NOT IN (%s)", $cValue);
                    }else{
                        $wheres[] = array("[$cName] IN (%s)", $cValue);
                    }
                }else if(strpos($cValue, "regexp:") === 0){
                    $regexp = str_replace("regexp:", "", $cValue);
                    $wheres[] = array("[$cName] ".AJXP_Utils::regexpToLike($regexp), AJXP_Utils::cleanRegexp($regexp));
                }else if ($cValue == AJXP_FILTER_NOT_EMPTY){
                    $wheres[] = array("[$cName] IS NOT NULL");
                }else if ($cValue == AJXP_FILTER_EMPTY){
                    $wheres[] = array("[$cName] IS NULL");
                }else{
                    $type = "%s";
                    if($cName == 'isTemplate') $type = "%b";
                    $wheres[] = array("[$cName] = $type", $cValue);
                }
            }else if($cName == "CURSOR"){
                $limit = $cValue;
            }else if($cName == "ORDERBY"){
                $order = "ORDER BY ".$cValue["KEY"]." ".$cValue["DIR"];
            }else if($cName == "GROUPBY"){
                $groupBy = "GROUP BY ".$cValue;
            }
        }

        if(isset($criteria["CURSOR"])){
            $res = dibi::query("SELECT COUNT(uuid) FROM [ajxp_repo] WHERE %and", $wheres);
            $count = $res->fetchSingle();
        }

        if(!empty($limit) && is_array($limit)){
            $res = dibi::query("SELECT * FROM [ajxp_repo] WHERE %and $groupBy $order %lmt %ofs", $wheres, $limit["LIMIT"], $limit["OFFSET"]);
        }else{
            $res = dibi::query("SELECT * FROM [ajxp_repo] WHERE %and $groupBy $order", $wheres);
        }
        $all = $res->fetchAll();
        return $this->initRepoArrayFromDbFetch($all);

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
            if($this->sqlDriver["driver"] == "postgre"){
                dibi::nativeQuery("SET bytea_output=escape");
            }
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
            if($this->sqlDriver["driver"] == "postgre"){
                dibi::nativeQuery("SET bytea_output=escape");
            }
            $res_opts = dibi::query('SELECT * FROM [ajxp_repo_options] WHERE [uuid] = %s', $repo_row['uuid']);
            $opts = $res_opts->fetchPairs('name', 'val');
            $repository = $this->repoFromDb($repo_row, $opts);
            return $repository;
        }

        return null;
    }

    /**
     * @param String $slug
     * @param String|null $repositoryId
     * @return String mixed
     */
    protected function uniquifySlug($slug, $repositoryId = null){

        if($repositoryId != null){
            $res = dibi::query("SELECT [slug],[uuid] FROM [ajxp_repo] WHERE [uuid] != %s AND [slug] LIKE '".$slug."%'", $repositoryId);
        }else{
            $res = dibi::query("SELECT [slug],[uuid] FROM [ajxp_repo] WHERE [slug] LIKE '".$slug."%'");
        }
        $existingSlugs = $res->fetchPairs();
        $configSlugs = ConfService::reservedSlugsFromConfig();
        if(in_array($slug, $configSlugs)){
            $existingSlugs[$slug] = $slug;
        }
        if(!count($existingSlugs)) return $slug;
        $index = 1;
        $base = $slug;
        $slug = $base."-".$index;
        while(isSet($existingSlugs[$slug])){
            $index++;
            $slug = $base."-".$index;
        }

        return $slug;
    }

    /**
     * Store a newly created repository
     *
     * @param Repository $repositoryObject
     * @param Boolean $update
     * @return int -1 if failed
     */
    public function saveRepository($repositoryObject, $update = false)
    {
        try {
            if($update){
                $repositoryObject->setSlug($this->uniquifySlug(
                    $repositoryObject->getSlug(),
                    $repositoryObject->getUniqueId()
                ));
            }else{
                $repositoryObject->setSlug($this->uniquifySlug($repositoryObject->getSlug()));
            }
            $repository_array = $this->repoToArray($repositoryObject);
            $options = $repository_array['options'];
            if($repositoryObject->hasContentFilter()){
                $options["content_filter"] = $repositoryObject->getContentFilter();
            }
            unset($repository_array['options']);
            if (!$update) {
                dibi::query('INSERT INTO [ajxp_repo]', $repository_array);

                foreach ($options as $k => $v) {
                    if (!is_string($v)) {
                        $v = '$phpserial$'.serialize($v);
                    }
                    dibi::query('INSERT INTO [ajxp_repo_options] ([uuid],[name],[val]) VALUES (%s,%s,%bin)', $repositoryObject->getUniqueId(), $k,$v);
                }

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
            dibi::query('DELETE FROM [ajxp_repo] WHERE [uuid] = %s', $repositoryId);
            dibi::query('DELETE FROM [ajxp_repo_options] WHERE [uuid] = %s', $repositoryId);
            dibi::query('DELETE FROM [ajxp_user_rights] WHERE [repo_uuid] = %s',$repositoryId);

            switch ($this->sqlDriver["driver"]) {
                case "postgre":
                    dibi::nativeQuery("SET bytea_output=escape");
                    $children_results = dibi::query('SELECT * FROM [ajxp_roles] WHERE [searchable_repositories] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                    break;
                case "sqlite":
                case "sqlite3":
                    $children_results = dibi::query('SELECT * FROM [ajxp_roles] WHERE [searchable_repositories] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                    break;
                case "mysql":
                    $children_results = dibi::query('SELECT * FROM [ajxp_roles] WHERE [serial_role] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                    break;
                default:
                    return "ERROR!, DB driver ". $this->sqlDriver["driver"] ." not supported yet in __FUNCTION__";
            }
            $all = $children_results->fetchAll();
            foreach ($all as $item) {
                $role = unserialize($item["serial_role"]);
                $role->setAcl($repositoryId, "");
                $this->updateRole($role);
            }

        } catch (DibiException $e) {
            $this->logError(__FUNCTION__, $e->getMessage());
            return -1;
        }
        return 1;
    }

    public function getUserChildren( $userId )
    {
        $ignoreHiddens = "NOT EXISTS (SELECT * FROM [ajxp_user_rights] WHERE [ajxp_user_rights.login]=[ajxp_users.login] AND [ajxp_user_rights.repo_uuid] = 'ajxp.hidden')";
        $children = array();
        $children_results = dibi::query('SELECT [ajxp_users].[login] FROM [ajxp_user_rights],[ajxp_users] WHERE [repo_uuid] = %s AND [rights] = %s AND [ajxp_user_rights].[login] = [ajxp_users].[login] AND '.$ignoreHiddens, "ajxp.parent_user", $userId);
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
        $usersRoles = $this->getRolesForRepository($repositoryId, "AJXP_USR_/");
        foreach($usersRoles as $rId){
            $id = substr($rId, strlen("AJXP_USR/")+1);
            $result[$id] = $this->createUserObject($id);
        }
        return $result;
    }

    /**
     * @abstract
     * @param string $repositoryId
     * @param string $rolePrefix
     * @param bool $countOnly
     * @return array()
     */
    public function getRolesForRepository($repositoryId, $rolePrefix = '', $countOnly = false){
        $allRoles = array();

        switch ($this->sqlDriver["driver"]) {
            case "sqlite":
            case "sqlite3":
            case "postgre":
                if(!empty($rolePrefix)){
                    $children_results = dibi::query('SELECT [role_id] FROM [ajxp_roles] WHERE [searchable_repositories] LIKE %~like~ AND [role_id] LIKE %like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:', $rolePrefix);
                }else{
                    $children_results = dibi::query('SELECT [role_id] FROM [ajxp_roles] WHERE [searchable_repositories] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                }
                break;
            case "mysql":
                if(!empty($rolePrefix)){
                    $children_results = dibi::query('SELECT [role_id] FROM [ajxp_roles] WHERE [serial_role] LIKE %~like~ AND [role_id] LIKE %like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:', $rolePrefix);
                }else{
                    $children_results = dibi::query('SELECT [role_id] FROM [ajxp_roles] WHERE [serial_role] LIKE %~like~ GROUP BY [role_id]', '"'.$repositoryId.'";s:');
                }
                break;
            default:
                return "ERROR!, DB driver ". $this->sqlDriver["driver"] ." not supported yet in __FUNCTION__";
        }
        $all = $children_results->fetchAll();
        foreach ($all as $item) {
            $rId = $item["role_id"];
            $allRoles[] = $rId;
        }

        return $allRoles;
    }


    public function getUsersForRole($roleId, $countOnly = false){
        if($countOnly){
            $res =  dibi::query("SELECT count([login]) FROM [ajxp_user_rights] WHERE [repo_uuid] = %s AND [rights] LIKE %~like~", "ajxp.roles", '"'.$roleId.'";b:1');
            return $res->fetchSingle();
        }else{
            $res =  dibi::query("SELECT [login] FROM [ajxp_user_rights] WHERE [repo_uuid] = %s AND [rights] LIKE %~like~", "ajxp.roles", '"'.$roleId.'";b:1');
            return $res->fetchAll();
        }
    }

    /**
     * @param string $repositoryId
     * @param boolean $details
     * @return Integer|Array
     */
    public function countUsersForRepository($repositoryId, $details = false){
        $object = ConfService::getRepositoryById($repositoryId);
        if($object->securityScope() == "USER"){
            if($details) return array('internal' => 1);
            else return 1;
        }else if($object->securityScope() == "GROUP"){
            // Count users from current group
            $groupUsers = AuthService::authCountUsers(AuthService::getLoggedUser()->getGroupPath());
            if($details) return array('internal' => $groupUsers);
            else return $groupUsers;
        }
        // Users from roles
        $internal = 0;
        $roles = $this->getRolesForRepository($repositoryId);
        foreach($roles as $rId){
            if(strpos($rId, "AJXP_USR_/") === 0) continue;
            $internal += $this->getUsersForRole($rId, true);
        }

        // NEW METHOD : SEARCH PERSONAL ROLE
        if(is_numeric($repositoryId)){
            $likeValue = "i:$repositoryId;s:";
        }else{
            $likeValue = '"'.$repositoryId.'";s:';
        }
        switch ($this->sqlDriver["driver"]) {
            case "sqlite":
            case "sqlite3":
            case "postgre":
                $q = 'SELECT count([role_id]) FROM [ajxp_roles] WHERE [role_id] LIKE \'AJXP_USR_/%\' AND [searchable_repositories] LIKE %~like~';
                break;
            case "mysql":
                $q = 'SELECT count([role_id]) as c FROM [ajxp_roles] WHERE [role_id] LIKE \'AJXP_USR_/%\' AND [serial_role] LIKE %~like~';
                break;
            default:
                return "ERROR!, DB driver ". $this->sqlDriver["driver"] ." not supported yet in __FUNCTION__";
        }
        if($details){
            if($this->sqlDriver["driver"] == "sqlite" || $this->sqlDriver["driver"] == "sqlite3"){
                $internalClause = " AND NOT EXISTS (SELECT * FROM [ajxp_user_rights] WHERE [ajxp_roles].[role_id]='AJXP_USR_/'||[ajxp_user_rights].[login] AND [ajxp_user_rights].[repo_uuid] = 'ajxp.parent_user')";
                $externalClause = " AND EXISTS (SELECT * FROM [ajxp_user_rights] WHERE [ajxp_roles].[role_id]='AJXP_USR_/'||[ajxp_user_rights].[login] AND [ajxp_user_rights].[repo_uuid] = 'ajxp.parent_user')";
            }else{
                $internalClause = " AND NOT EXISTS (SELECT * FROM [ajxp_user_rights] WHERE [ajxp_roles].[role_id]=CONCAT('AJXP_USR_/',[ajxp_user_rights].[login]) AND [ajxp_user_rights].[repo_uuid] = 'ajxp.parent_user')";
                $externalClause = " AND EXISTS (SELECT * FROM [ajxp_user_rights] WHERE [ajxp_roles].[role_id]=CONCAT('AJXP_USR_/',[ajxp_user_rights].[login]) AND [ajxp_user_rights].[repo_uuid] = 'ajxp.parent_user')";
            }
            $intRes = dibi::query($q.$internalClause, $likeValue);
            $extRes = dibi::query($q.$externalClause, $likeValue);
            return array(
                'internal' => $internal + $intRes->fetchSingle(),
                'external' => $extRes->fetchSingle()
            );
        }else{
            $res = dibi::query($q, $likeValue);
            return $internal + $res->fetchSingle();

        }
        //$all = $res->fetchAll();
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
        if($this->sqlDriver["driver"] == "postgre"){
            dibi::nativeQuery("SET bytea_output=escape");
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
                    return "ERROR!, DB driver " . $this->sqlDriver["driver"] . " not supported yet in __FUNCTION__";
            }
        }
    }

    /**
     * @param AJXP_Role $role
     */
    public function updateRole($role, $userObject = null)
    {
        // if role is not existed => insert into
        switch ($this->sqlDriver["driver"]) {
            case "sqlite":
            case "sqlite3":
            case "postgre":
                $row = dibi::query("SELECT [role_id] FROM [ajxp_roles] WHERE [role_id]=%s", $role->getId());
                $res = $row->fetchSingle();
                if($res != null){
                    dibi::query("UPDATE [ajxp_roles] SET [serial_role]=%bin,[searchable_repositories]=%s WHERE [role_id]=%s", serialize($role), serialize($role->listAcls()), $role->getId());
                }
                else{
                    dibi::query("INSERT INTO [ajxp_roles] ([role_id],[serial_role],[searchable_repositories]) VALUES (%s, %bin,%s)", $role->getId(), serialize($role), serialize($role->listAcls()));
                }
                break;
            case "mysql":
                dibi::query("INSERT INTO [ajxp_roles] ([role_id],[serial_role]) VALUES (%s, %s) ON DUPLICATE KEY UPDATE [serial_role]=VALUES([serial_role])", $role->getId(), serialize($role));
                break;
            default:
                return "ERROR!, DB driver ". $this->sqlDriver["driver"] ." not supported yet in __FUNCTION__";
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

    public function simpleStoreSet($storeID, $dataID, $data, $dataType = "serial", $relatedObjectId = null)
    {
        $values = array(
            "store_id" => $storeID,
            "object_id" => $dataID
        );
        if ($relatedObjectId !== null) {
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

    public function simpleStoreClear($storeID, $dataID)
    {
        dibi::query("DELETE FROM [ajxp_simple_store] WHERE [store_id]=%s AND [object_id]=%s", $storeID, $dataID);
    }

    public function simpleStoreGet($storeID, $dataID, $dataType, &$data)
    {
        if($this->sqlDriver["driver"] == "postgre"){
            dibi::nativeQuery("SET bytea_output=escape");
        }
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

    public function simpleStoreList($storeId, $cursor=null, $dataIdLike="", $dataType="serial", $serialDataLike="", $relatedObjectId=""){
        $wheres = array();
        $wheres[] = array('[store_id]=%s', $storeId);
        if(!empty($dataIdLike)){
            $wheres[] = array('[object_id] LIKE %s', $dataIdLike);
        }
        if(!empty($serialDataLike)){
            $wheres[] = array('[serialized_data] LIKE %s', $serialDataLike);
        }
        if($relatedObjectId != ""){
            $wheres[] = array('[related_object_id] = %s', $relatedObjectId);
        }
        $limit = '';
        if($cursor != null){
            $children_results = dibi::query("SELECT * FROM [ajxp_simple_store] WHERE %and %lmt %ofs", $wheres, $cursor[1], $cursor[0]);
        }else{
            $children_results = dibi::query("SELECT * FROM [ajxp_simple_store] WHERE %and", $wheres);

        }
        $values = $children_results->fetchAll();
        $result = array();
        foreach($values as $value){
            if ($dataType == "serial") {
                $data = unserialize($value["serialized_data"]);
            } else {
                $data = $value["binary_data"];
            }
            $result[$value['object_id']] = $data;
        }
        return $result;
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
        if($this->sqlDriver["driver"] == "postgre"){
            dibi::query("DELETE FROM [ajxp_simple_store] WHERE [store_id] = %s AND [insertion_date] < (CURRENT_TIMESTAMP - time '0:$expiration')", "temporakey_".$keyType);
        }else{
            dibi::query("DELETE FROM [ajxp_simple_store] WHERE [store_id] = %s AND [insertion_date] < (CURRENT_TIMESTAMP - %i)", "temporakey_".$keyType, $expiration*60);
        }
    }


    public function installSQLTables($param)
    {
        $p = AJXP_Utils::cleanDibiDriverParameters($param["SQL_DRIVER"]);
        $res = AJXP_Utils::runCreateTablesQuery($p, $this->getBaseDir()."/create.sql");
        // SET DB VERSION
        if(defined('AJXP_VERSION_DB') && AJXP_VERSION_DB != "##DB_VERSION##"){
            dibi::connect($p);
            dibi::query("UPDATE [ajxp_version] SET [db_build]=%i", intval(AJXP_VERSION_DB));
            dibi::disconnect();
        }
        return $res;
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

    private function editTeamUsers($teamId, $users, $teamLabel = null)
    {
        if ($teamLabel == null) {
            $res = dibi::query("SELECT [team_label] FROM [ajxp_user_teams] WHERE [team_id] = %s AND  [owner_id] = %s",
                $teamId, AuthService::getLoggedUser()->getId());
            $teamLabel = $res->fetchSingle();
        }
        // Remove old users
        dibi::query("DELETE FROM [ajxp_user_teams] WHERE [team_id] = %s", $teamId);
        foreach($users as $userId){
            if(!AuthService::userExists($userId, "r")) continue;
            dibi::query("INSERT INTO [ajxp_user_teams] ([team_id],[user_id],[team_label],[owner_id]) VALUES (%s,%s,%s,%s)",
                $teamId, $userId, $teamLabel, AuthService::getLoggedUser()->getId()
            );
        }
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
            case "user_team_edit_users":
                $this->editTeamUsers($httpVars["team_id"], $httpVars["users"], $httpVars["team_label"]);
                break;
            case "user_team_delete_user":
                $this->removeUserFromTeam($httpVars["team_id"], $httpVars["user_id"]);
                break;
        }
    }

}
