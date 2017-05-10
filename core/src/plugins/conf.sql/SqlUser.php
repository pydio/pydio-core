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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Conf\Sql;

use dibi;
use DibiException;
use Pydio\Conf\Core\AbstractUser;
use Pydio\Conf\Core\AbstractConfDriver;
use Pydio\Conf\Core\AJXP_Role;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\FileHelper;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * AJXP_User class for the conf.sql driver.
 *
 * Stores rights, preferences and bookmarks in various database tables.
 *
 * The class will expect these schema objects to be present:
 *
 */
class SqlUser extends AbstractUser
{
    /**
     * Whether queries will be logged with AJXP_Logger
     */
    public $debugEnabled = false;

    /**
     * Login name of the user.
     * @var String
     */
    public $id;

    /**
     * User is an admin?
     * @var Boolean
     */
    public $hasAdmin = false;

    /**
     * User rights map. In the format Array( "repoid" => "rw | r | nothing" )
     * @var array
     */
    public $rights;

    /**
     * User preferences array in the format Array( "preference_key" => "preference_value" )
     * @var array
     */
    public $prefs;

    /**
     * User bookmarks array in the format Array( "repoid" => Array( Array( "path"=>"/path/to/bookmark", "title"=>"bookmark" )))
     * @var array
     */
    public $bookmarks;

    /**
     * User version(?) possibly deprecated.
     *
     * @var string
     */
    public $version;

    /**
     * Conf Storage implementation - Any class/plugin implementing AbstractConfDriver.
     *
     * This is set by the constructor.
     *
     * @var AbstractConfDriver
     */
    public $storage;

    /**
     * AJXP_User Constructor
     * @param $id String User login name.
     * @param $storage AbstractConfDriver User storage implementation.
     * @return SqlUser
     */
    public function __construct($id, $storage=null, $debugEnabled = false)
    {
        parent::__construct($id, $storage);
        //$this->debugEnabled = true;

        $this->log('Instantiating User');
    }

    /**
     * Does the configuration storage exist?
     * Will return true if all schema objects are available.
     *
     * @see UserInterface#storageExists()
     * @return boolean false if storage does not exist
     */
    public function storageExists()
    {
        $this->log('Checking for existence of SqlUser storage...');

        try {

            if (isSet($this->rights["ajxp.admin"])) {
                // already loaded!
                return true;
            }
            $result_rights = dibi::query('SELECT [rights] FROM [ajxp_user_rights] WHERE [login] = %s AND [repo_uuid] = %s', $this->getId(), 'ajxp.admin');
            $testRight = $result_rights->fetchSingle();
            if ($testRight === false) {
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
     * @param string $textMessage String text of the message to log
     * @param string $severityLevel Integer constant of the logging severity
     * @return null
     */
    public function log($textMessage, $severityLevel = LOG_LEVEL_DEBUG)
    {
        if ($this->debugEnabled) {
            $logger = Logger::getInstance();
            $logger->logDebug("AJXP_SQLUSER", $textMessage);
        }
    }

    /**
     * Set a user preference.
     *
     * @param string $prefName Name of the preference.
     * @param string|object $prefValue Value to assign to the preference.
     * @return null or -1 on error.
     * @see UserInterface#setPref($prefName, $prefValue)
     */
    public function setPref($prefName, $prefValue)
    {
        if (!is_string($prefValue)) {
            $prefValue = '$phpserial$'.serialize($prefValue);
        }
        // Prevent a query if the preferences are identical to the existing preferences.
        if (array_key_exists($prefName, $this->prefs) && $this->prefs[$prefName] == $prefValue) {
            return;
        }

        // Try/Catch DibiException
        try {

            if(empty($prefValue)){

                dibi::query('DELETE FROM [ajxp_user_prefs] WHERE [login] = %s AND [name] = %s', $this->getId(), $prefName);
                unset($this->prefs[$prefName]); // Update the internal array only if successful.

            }else{

                try{

                    dibi::query('INSERT INTO [ajxp_user_prefs] ([login],[name],[val]) VALUES (%s, %s, %bin)', $this->getId(),$prefName,$prefValue);

                }catch(DibiException $dibiException){

                    dibi::query('UPDATE [ajxp_user_prefs] SET [val] = %bin WHERE [login] = %s AND [name] = %s', $prefValue, $this->getId(), $prefName);

                }

                $this->prefs[$prefName] = $prefValue;
            }

        } catch (DibiException $e) {
            $this->log('MODIFY PREFERENCE FAILED: Reason: '.$e->getMessage());
        }

    }

    /**
     * @param $prefName
     * @return mixed|string
     */
    public function getPref($prefName)
    {
        $p = parent::getPref($prefName);
        if (isSet($p) && is_string($p)) {
            if (strpos($p, '$phpserial$') !== false && strpos($p, '$phpserial$') === 0) {
                $p = substr($p, strlen('$phpserial$'));
                return unserialize($p);
            }
            if (strpos($p, '$json$') !== false && strpos($p, '$json$') === 0) {
                $p = substr($p, strlen('$json$'));
                return json_decode($p, true);
            }
            // By default, unserialize
            if ($prefName == "CUSTOM_PARAMS") {
                return unserialize($p);
            }
        }
        return $p;
    }

    /**
     * Add a user bookmark.
     * @inheritdoc
     */
    public function addBookmark($repositoryId, $path, $title)
    {
        if(!isSet($this->bookmarks)) $this->bookmarks = array();
        if($title == "") $title = basename($path);
        if(!isSet($this->bookmarks[$repositoryId])) $this->bookmarks[$repositoryId] = array();
        foreach ($this->bookmarks[$repositoryId] as $v) {
            $toCompare = "";
            if(is_string($v)) $toCompare = $v;
            else if(is_array($v)) $toCompare = $v["PATH"];
            if($toCompare == trim($path)) return null; // RETURN IF ALREADY HERE!
        }

        try {
            dibi::query('INSERT INTO [ajxp_user_bookmarks]', Array(
                'login' => $this->getId(),
                'repo_uuid' => $repositoryId,
                'path' => $path,
                'title' => $title
            ));

        } catch (DibiException $e) {
            $this->log('BOOKMARK ADD FAILED: Reason: '.$e->getMessage());
            return -1;
        }
        $this->bookmarks[$repositoryId][] = array("PATH"=>trim($path), "TITLE"=>$title);
        return null;
    }

    /**
     * @inheritdoc
     */
    public function removeBookmark($repositoryId, $path)
    {
        if(isSet($this->bookmarks)
            && isSet($this->bookmarks[$repositoryId])
            && is_array($this->bookmarks[$repositoryId]))
            {
                foreach ($this->bookmarks[$repositoryId] as $k => $v) {
                    $toCompare = "";
                    if(is_string($v)) $toCompare = $v;
                    else if(is_array($v)) $toCompare = $v["PATH"];
                    if ($toCompare == trim($path)) {
                        try {
                            dibi::query('DELETE FROM [ajxp_user_bookmarks] WHERE [login] = %s AND [repo_uuid] = %s AND [title] = %s', $this->getId(), $repositoryId, $v["TITLE"]);
                        } catch (DibiException $e) {
                            $this->log('BOOKMARK REMOVE FAILED: Reason: '.$e->getMessage());
                            return -1;
                        }

                        unset($this->bookmarks[$repositoryId][$k]);
                    }
                }
            }
        return null;
    }

    /**
     * Rename a user bookmark.
     *
     * @param string $path Path of the bookmark to rename.
     * @param string $title New title to give the bookmark.
     * @return null or -1 on error.
     * @see UserInterface#renameBookmark($path, $title)
     */
    public function renameBookmark($repositoryId, $path, $title)
    {
        if(isSet($this->bookmarks)
            && isSet($this->bookmarks[$repositoryId])
            && is_array($this->bookmarks[$repositoryId]))
            {
                foreach ($this->bookmarks[$repositoryId] as $k => $v) {
                    $toCompare = "";
                    if(is_string($v)) $toCompare = $v;
                    else if(is_array($v)) $toCompare = $v["PATH"];
                    if ($toCompare == trim($path)) {
                         try {
                             dibi::query('UPDATE [ajxp_user_bookmarks] SET ',
                                         Array('path'=>trim($path), 'title'=>$title),
                                         'WHERE [login] = %s AND [repo_uuid] = %s AND [title] = %s',
                                         $this->getId(), $repositoryId, $v["TITLE"]
                             );

                         } catch (DibiException $e) {
                             $this->log('BOOKMARK RENAME FAILED: Reason: '.$e->getMessage());
                             return -1;
                         }
                         $this->bookmarks[$repositoryId][$k] = array("PATH"=>trim($path), "TITLE"=>$title);
                    }
                }
            }
        return null;
    }

    /**
     * Load initial user data (Rights, Preferences and Bookmarks).
     *
     * @see UserInterface#load()
     */
    public function load()
    {
        $this->log('Loading all user data..');
        // update group
        $res = dibi::query('SELECT [groupPath] FROM [ajxp_users] WHERE [login] = %s', $this->getId());
        $this->groupPath = $res->fetchSingle();
        if (empty($this->groupPath)) {
            // Auto migrate from old version
            $this->groupPath = "/";
        }

        $result_rights = dibi::query('SELECT [repo_uuid], [rights] FROM [ajxp_user_rights] WHERE [login] = %s', $this->getId());
        $this->rights = $result_rights->fetchPairs('repo_uuid', 'rights');

        // Db field returns integer or string so we are required to cast it in order to make the comparison
        if (isSet($this->rights["ajxp.admin"]) && (bool) $this->rights["ajxp.admin"] === true) {
            $this->setAdmin(true);
        }
        if (isSet($this->rights["ajxp.parent_user"])) {
            $this->setParent($this->rights["ajxp.parent_user"]);
        }
        if (isSet($this->rights["ajxp.hidden"])){
            $this->setHidden(true);
        }

        if ("postgre" == $this->storage->sqlDriver["driver"]) {
            dibi::nativeQuery('SET bytea_output = escape');
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

        // COLLECT ROLES TO LOAD
        $rolesToLoad = array();
        if (isSet($this->rights["ajxp.roles"])) {
            if (is_string($this->rights["ajxp.roles"])) {
                if (strpos($this->rights["ajxp.roles"], '$phpserial$') === 0) {
                    $this->rights["ajxp.roles"] = unserialize(str_replace('$phpserial$', '', $this->rights["ajxp.roles"]));
                } else if (strpos($this->rights["ajxp.roles"], '$json$') === 0) {
                    $this->rights["ajxp.roles"] = json_decode(str_replace('$json$', '', $this->rights["ajxp.roles"]), true);
                } else {
                    $this->rights["ajxp.roles"] = unserialize($this->rights["ajxp.roles"]);
                }
            }
            if(is_array($this->rights["ajxp.roles"])){
                $rolesToLoad = array_keys($this->rights["ajxp.roles"]);
            }
        }
        $rolesToLoad[] = "AJXP_GRP_/";
        if ($this->groupPath != null) {
            $base = "";
            $exp = explode("/", $this->groupPath);
            foreach ($exp as $pathPart) {
                if(empty($pathPart)) continue;
                $base = $base . "/" . $pathPart;
                $rolesToLoad[] = "AJXP_GRP_".$base;
            }
        }
        $rolesToLoad[] = "AJXP_USR_/".$this->id;

        // NOW LOAD THEM
        if (count($rolesToLoad)) {
            $allRoles = RolesService::getRolesList($rolesToLoad, false, true);
            foreach ($rolesToLoad as $roleId) {
                if (!isSet($allRoles[$roleId]) && strpos($roleId, "AJXP_GRP_/") === 0){
                    $allRoles[$roleId] = RolesService::getOrCreateRole($roleId);
                }
                if (isSet($allRoles[$roleId])) {
                    $this->roles[$roleId] = $allRoles[$roleId];
                    $this->rights["ajxp.roles"][$roleId] = true;
                    $roleObject = $allRoles[$roleId];
                    if($roleObject->alwaysOverrides()){
                        if(!isSet($this->rights["ajxp.roles.sticky"]) || !is_array($this->rights["ajxp.roles.sticky"])) {
                            $this->rights["ajxp.roles.sticky"] = array();
                        }
                        $this->rights["ajxp.roles.sticky"][$roleId] = true;
                    }
                } else if (is_array($this->rights["ajxp.roles"]) && isSet($this->rights["ajxp.roles"][$roleId])) {
                    unset($this->rights["ajxp.roles"][$roleId]);
                }
            }
        }

        if(!isSet($this->rights["ajxp.roles.order"]) && is_array($this->rights["ajxp.roles"])){
            // Create sample order
            $this->rights["ajxp.roles.order"] = array();
            $index = 0;
            foreach($this->rights["ajxp.roles"] as $id => $rBool){
                $this->rights["ajxp.roles.order"][$id] = $index;
                $index++;
            }
        }else{
            $this->rights["ajxp.roles.order"] = unserialize(str_replace('$phpserial$', '', $this->rights["ajxp.roles.order"]));
        }

        // CHECK USER PERSONAL ROLE
        if (isSet($this->roles["AJXP_USR_"."/".$this->id]) && $this->roles["AJXP_USR_"."/".$this->id] instanceof AJXP_Role) {
            $this->personalRole = $this->roles["AJXP_USR_"."/".$this->id];
        } else {
            // MIGRATE NOW !
            $originalRights = $this->rights;
            $changes = $this->migrateRightsToPersonalRole();
            // SAVE RIGHT AND ROLE
            if ($changes > 0) {
                // There was an actual migration, let's save the changes now.
                $removedRights = array_keys(array_diff($originalRights, $this->rights));
                if (count($removedRights)) {
                    // We use (%s) instead of %in to pass everything as string ('1' instead of 1)
                    dibi::query("DELETE FROM [ajxp_user_rights] WHERE [login] = %s AND [repo_uuid] IN (%s)", $this->getId(), $removedRights);
                }
                RolesService::updateRole($this->personalRole);
            } else {
                $this->personalRole = new AJXP_Role("AJXP_USR_"."/".$this->id);
            }
            $this->roles["AJXP_USR_"."/".$this->id] = $this->personalRole;

        }
        $this->recomputeMergedRole();
    }

    /**
     * Save user rights, preferences and bookmarks.
     * @param String $context
     * @see UserInterface#save()
     * @return mixed|void
     */
    protected function _save($context = "superuser")
    {
        if ($context != "superuser") {
            // Nothing specific to do, prefs and bookmarks are saved on-the-fly.
            return;
        }
        $this->log('Saving user...');

        // UPDATE RIGHTS ARRAY
        $this->rights["ajxp.admin"] = ($this->isAdmin() ? "1" : "0");
        if ($this->hasParent()) {
            $this->rights["ajxp.parent_user"] = $this->parentUser;
        }
        if($this->isHidden()){
            $this->rights["ajxp.hidden"] = 'true';
        }

        // UPDATE TABLE
        dibi::query("DELETE FROM [ajxp_user_rights] WHERE [login]=%s", $this->getId());
        foreach ($this->rights as $rightKey => $rightValue) {
            if ($rightKey == "ajxp.roles.sticky") {
                continue;
            }
            if ($rightKey == "ajxp.roles" || $rightKey == "ajxp.roles.order") {
                if (is_array($rightValue) && count($rightValue)) {
                    $rightValue = $this->filterRolesForSaving($rightValue, $rightKey == "ajxp.roles" ? true: false);
                    $rightValue = '$phpserial$'.serialize($rightValue);
                } else {
                    continue;
                }
            }
            dibi::query("INSERT INTO [ajxp_user_rights]", array(
                'login' => $this->getId(),
                'repo_uuid' => $rightKey,
                'rights'	=> $rightValue
            ));
        }

        RolesService::updateRole($this->personalRole);

        if (!empty($this->groupPath)) {
            $this->setGroupPath($this->groupPath);
        }

    }

    /**
     * Get Temporary Data.
     * Implementation uses serialised files because of the overhead incurred with a full db implementation.
     *
     * @param $key String key of data to retrieve
     * @return mixed Requested value
     */
    public function getTemporaryData($key)
    {
        $dirPath = $this->storage->getOption("USERS_DIRPATH");
        if ($dirPath == "") {
            $dirPath = AJXP_INSTALL_PATH."/data/users";
            Logger::info(__CLASS__,"getTemporaryData", array("Warning" => "The conf.sql driver is missing a mandatory option USERS_DIRPATH!"));
        }
        $id = UsersService::ignoreUserCase() ?strtolower($this->getId()):$this->getId();
        return FileHelper::loadSerialFile($dirPath . "/" . $id . "/temp-" . $key . ".ser");
    }

    /**
     * Save Temporary Data.
     * Implementation uses serialised files because of the overhead incurred with a full db implementation.
     *
     * @param string $key String key of data to save.
     * @param mixed $value Value to save
     * @return mixed|void
     */
    public function saveTemporaryData($key, $value)
    {
        $dirPath = $this->storage->getOption("USERS_DIRPATH");
        if ($dirPath == "") {
            $dirPath = AJXP_INSTALL_PATH."/data/users";
            Logger::info(__CLASS__,"setTemporaryData", array("Warning" => "The conf.sql driver is missing a mandatory option USERS_DIRPATH!"));
        }
        $id = UsersService::ignoreUserCase() ?strtolower($this->getId()):$this->getId();
        FileHelper::saveSerialFile($dirPath . "/" . $id . "/temp-" . $key . ".ser", $value);
    }

    /**
     * @param String $groupPath
     * @param bool $update
     * @throws \Pydio\Core\Exception\UserNotFoundException
     */
    public function setGroupPath($groupPath, $update = false)
    {
        if ($update &&  isSet($this->groupPath) && $groupPath != $this->groupPath) {
            // Update Shared Users groups as well
            $res = dibi::query("SELECT [u.login] FROM [ajxp_users] AS u, [ajxp_user_rights] AS p WHERE [u.login] = [p.login] AND [p.repo_uuid] = %s AND [p.rights] = %s AND [u.groupPath] != %s ", "ajxp.parent_user", $this->getId(), $groupPath);
            foreach ($res as $row) {
                $userId = $row->login;
                // UPDATE USER GROUP AND ROLES
                $u = UsersService::getUserById($userId, false);
                $u->setGroupPath($groupPath);
                $r = $u->getRoles();
                // REMOVE OLD GROUP ROLES
                foreach (array_keys($r) as $role) {
                    if(strpos($role, "AJXP_GRP_/") === 0) $u->removeRole($role);
                }
                $u->recomputeMergedRole();
                $u->save("superuser");
            }
        }
        parent::setGroupPath($groupPath);
        dibi::query('UPDATE [ajxp_users] SET ', Array('groupPath'=>$groupPath), 'WHERE [login] = %s', $this->getId());
        $r = $this->getRoles();
        // REMOVE OLD GROUP ROLES
        foreach (array_keys($r) as $role) {
            if(strpos($role, "AJXP_GRP_/") === 0) $this->removeRole($role);
        }
        $this->load();
        $this->recomputeMergedRole();
        $this->log('UPDATE GROUP: [Login]: '.$this->getId().' [Group]:'.$groupPath);
    }

}
