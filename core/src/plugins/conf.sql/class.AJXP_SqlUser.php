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
 * @package AjaXplorer_Plugins
 * @subpackage Conf
 */
class AJXP_SqlUser extends AbstractAjxpUser
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
     * @var Array
     */
    public $rights;

    /**
     * User preferences array in the format Array( "preference_key" => "preference_value" )
     * @var Array
     */
    public $prefs;

    /**
     * User bookmarks array in the format Array( "repoid" => Array( Array( "path"=>"/path/to/bookmark", "title"=>"bookmark" )))
     * @var Array
     */
    public $bookmarks;

    /**
     * User version(?) possibly deprecated.
     *
     * @var unknown_type
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
     * @return AJXP_SqlUser
     */
    public function AJXP_SqlUser($id, $storage=null, $debugEnabled = false)
    {
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
    public function storageExists()
    {
        $this->log('Checking for existence of AJXP_SqlUser storage...');

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
     * @param $textMessage String text of the message to log
     * @param $severityLevel Integer constant of the logging severity
     * @return null
     */
    public function log($textMessage, $severityLevel = LOG_LEVEL_DEBUG)
    {
        if ($this->debugEnabled) {
            $logger = AJXP_Logger::getInstance();
            $logger->write($textMessage, $severityLevel);
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
            // Update an existing preference
            if (array_key_exists($prefName, $this->prefs)) {

                // Delete an existing preferences row, because the value has been unset.
                if ('' == $prefValue) {

                    dibi::query('DELETE FROM [ajxp_user_prefs] WHERE [login] = %s AND [name] = %s', $this->getId(), $prefName);

                    $this->log('DELETE PREFERENCE: [Login]: '.$this->getId().' [Preference]:'.$prefName.' [Value]:'.$prefValue);
                    unset($this->prefs[$prefName]); // Update the internal array only if successful.

                // Update an existing rights row, because only some of the rights have changed.
                } else {

                    dibi::query('UPDATE [ajxp_user_prefs] SET [val] = %bin WHERE [login] = %s AND [name] = %s', $prefValue, $this->getId(), $prefName);

                    $this->log('UPDATE PREFERENCE: [Login]: '.$this->getId().' [Preference]:'.$prefName.' [Value]:'.$prefValue);
                    $this->prefs[$prefName] = $prefValue;
                }

            // The repository supplied does not exist, so insert the right.
            } else {

                dibi::query('INSERT INTO [ajxp_user_prefs] ([login],[name],[val]) VALUES (%s, %s, %bin)', $this->getId(),$prefName,$prefValue);

                $this->log('INSERT PREFERENCE: [Login]: '.$this->getId().' [Preference]:'.$prefName.' [Value]:'.$prefValue);
                $this->prefs[$prefName] = $prefValue;
            }

        } catch (DibiException $e) {
            $this->log('MODIFY PREFERENCE FAILED: Reason: '.$e->getMessage());
        }

    }

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
     *
     * @param $path String Relative path to bookmarked location.
     * @param $title String Title of the bookmark
     * @param $repId String Repository Unique ID
     * @return null or -1 on error.
     * @see AbstractAjxpUser#addBookmark($path, $title, $repId)
     */
    public function addBookmark($path, $title="", $repId = -1)
    {
        if(!isSet($this->bookmarks)) $this->bookmarks = array();
        if($repId == -1) $repId = ConfService::getCurrentRepositoryId();
        if($title == "") $title = basename($path);
        if(!isSet($this->bookmarks[$repId])) $this->bookmarks[$repId] = array();
        foreach ($this->bookmarks[$repId] as $v) {
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
    public function removeBookmark($path)
    {
        $repId = ConfService::getCurrentRepositoryId();
        if(isSet($this->bookmarks)
            && isSet($this->bookmarks[$repId])
            && is_array($this->bookmarks[$repId]))
            {
                foreach ($this->bookmarks[$repId] as $k => $v) {
                    $toCompare = "";
                    if(is_string($v)) $toCompare = $v;
                    else if(is_array($v)) $toCompare = $v["PATH"];
                    if ($toCompare == trim($path)) {
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
    public function renameBookmark($path, $title)
    {
        $repId = ConfService::getCurrentRepositoryId();
        if(isSet($this->bookmarks)
            && isSet($this->bookmarks[$repId])
            && is_array($this->bookmarks[$repId]))
            {
                foreach ($this->bookmarks[$repId] as $k => $v) {
                    $toCompare = "";
                    if(is_string($v)) $toCompare = $v;
                    else if(is_array($v)) $toCompare = $v["PATH"];
                    if ($toCompare == trim($path)) {
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
    public function load()
    {
        $this->log('Loading all user data..');
        // update group
        $res = dibi::query('SELECT [groupPath] FROM [ajxp_users] WHERE [login] = %s', $this->getId());
        $this->groupPath = $res->fetchSingle();
        if (empty($this->groupPath)) {
            // Auto migrate from old version
            $this->setGroupPath("/");
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
            $allRoles = AuthService::getRolesList($rolesToLoad);
            foreach ($rolesToLoad as $roleId) {
                if (isSet($allRoles[$roleId])) {
                    $this->roles[$roleId] = $allRoles[$roleId];
                    $this->rights["ajxp.roles"][$roleId] = true;
                } else if (is_array($this->rights["ajxp.roles"]) && isSet($this->rights["ajxp.roles"][$roleId])) {
                    unset($this->rights["ajxp.roles"][$roleId]);
                }
            }
        }

        // CHECK USER PERSONAL ROLE
        if (isSet($this->roles["AJXP_USR_"."/".$this->id]) && is_a($this->roles["AJXP_USR_"."/".$this->id], "AJXP_Role")) {
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
                AuthService::updateRole($this->personalRole);
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
     * @see AbstractAjxpUser#save()
     */
    public function save($context = "superuser")
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
            if ($rightKey == "ajxp.roles") {
                if (is_array($rightValue) && count($rightValue)) {
                    $rightValue = $this->filterRolesForSaving($rightValue);
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

        AuthService::updateRole($this->personalRole);

        if (!empty($this->groupPath)) {
            $this->setGroupPath($this->groupPath);
        }

    }

    /**
     * Get Temporary Data.
     * Implementation uses serialised files because of the overhead incurred with a full db implementation.
     *
     * @param $key String key of data to retrieve
     * @return Requested value
     */
    public function getTemporaryData($key)
    {
        $dirPath = $this->storage->getOption("USERS_DIRPATH");
        if ($dirPath == "") {
            $dirPath = AJXP_INSTALL_PATH."/data/users";
            AJXP_Logger::info(__CLASS__,"getTemporaryData", array("Warning" => "The conf.sql driver is missing a mandatory option USERS_DIRPATH!"));
        }
        $id = AuthService::ignoreUserCase()?strtolower($this->getId()):$this->getId();
        return AJXP_Utils::loadSerialFile($dirPath."/".$id."/temp-".$key.".ser");
    }

    /**
     * Save Temporary Data.
     * Implementation uses serialised files because of the overhead incurred with a full db implementation.
     *
     * @param $key String key of data to save.
     * @param $value Value to save
     */
    public function saveTemporaryData($key, $value)
    {
        $dirPath = $this->storage->getOption("USERS_DIRPATH");
        if ($dirPath == "") {
            $dirPath = AJXP_INSTALL_PATH."/data/users";
            AJXP_Logger::info(__CLASS__,"setTemporaryData", array("Warning" => "The conf.sql driver is missing a mandatory option USERS_DIRPATH!"));
        }
        $id = AuthService::ignoreUserCase()?strtolower($this->getId()):$this->getId();
        AJXP_Utils::saveSerialFile($dirPath."/".$id."/temp-".$key.".ser", $value);
    }

    public function setGroupPath($groupPath, $update = false)
    {
        if ($update &&  isSet($this->groupPath) && $groupPath != $this->groupPath) {
            // Update Shared Users groups as well
            $res = dibi::query("SELECT [u.login] FROM [ajxp_users] AS u, [ajxp_user_rights] AS p WHERE [u.login] = [p.login] AND [p.repo_uuid] = %s AND [p.rights] = %s AND [u.groupPath] != %s ", "ajxp.parent_user", $this->getId(), $groupPath);
            foreach ($res as $row) {
                $userId = $row->login;
                // UPDATE USER GROUP AND ROLES
                $u = ConfService::getConfStorageImpl()->createUserObject($userId);
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
        $this->log('UPDATE GROUP: [Login]: '.$this->getId().' [Group]:'.$groupPath);
    }

}
