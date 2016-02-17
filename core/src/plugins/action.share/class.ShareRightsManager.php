<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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

defined('AJXP_EXEC') or die('Access not allowed');

define('PARAM_USER_LOGIN_PREFIX', "user_");
define('PARAM_USER_PASS_PREFIX', "user_pass_");
define('PARAM_USER_RIGHT_WATCH_PREFIX', "right_watch_");
define('PARAM_USER_RIGHT_READ_PREFIX', "right_read_");
define('PARAM_USER_RIGHT_WRITE_PREFIX', "right_write_");
define('PARAM_USER_ENTRY_TYPE', "entry_type_");

class ShareRightsManager
{
    /**
     * @var string
     */
    var $tmpUsersPrefix;
    /**
     * @var MetaWatchRegister|bool
     */
    var $watcher;

    /**
     * ShareRightsManager constructor.
     * @param string $tmpUsersPrefix
     * @param MetaWatchRegister|bool $watcher
     */
    public function __construct($tmpUsersPrefix = "", $watcher = false)
    {
        $this->tmpUsersPrefix = $tmpUsersPrefix;
        $this->watcher = $watcher;
    }

    /**
     * @param array $httpVars
     * @param string $userId
     * @param string|null $userPass
     * @param bool|false $update
     * @return array
     */
    public function createHiddenUserEntry($httpVars, $userId, $userPass = null, $update = false){

        $entry = array("ID" => $userId, "TYPE" => "user", "HIDDEN" => true);
        $read = isSet($httpVars["simple_right_read"]) ;
        $write = isSet($httpVars["simple_right_write"]);
        $disableDownload = !isSet($httpVars["simple_right_download"]);
        if (!$read && !$disableDownload) {
            $read = true;
        }
        $entry["RIGHT"] = ($read?"r":"").($write?"w":"");
        $entry["WATCH"] = false;
        if(isSet($userPass)){
            if($update){
                $entry["UPDATE_PASSWORD"] = $userPass;
            }else{
                $entry["PASSWORD"] = $userPass;
            }
        }
        return $entry;

    }

    /**
     * @param $httpVars
     * @param array $users
     * @param array $groups
     * @throws Exception
     */
    public function createUsersFromParameters($httpVars, &$users = array(), &$groups = array()){

        $index = 0;
        $allowCrossUserSharing = ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING", "conf");
        $allowSharedUsersCreation = ConfService::getCoreConf("USER_CREATE_USERS", "conf");
        $loggedUser = AuthService::getLoggedUser();
        $confDriver = ConfService::getConfStorageImpl();

        while (isSet($httpVars[PARAM_USER_LOGIN_PREFIX.$index])) {

            $eType = $httpVars[PARAM_USER_ENTRY_TYPE.$index];
            $rightString = ($httpVars[PARAM_USER_RIGHT_READ_PREFIX.$index]=="true"?"r":"").($httpVars[PARAM_USER_RIGHT_WRITE_PREFIX.$index]=="true"?"w":"");
            $uWatch = false;
            if($this->watcher !== false) {
                $uWatch = $httpVars[PARAM_USER_RIGHT_WATCH_PREFIX.$index] == "true" ? true : false;
            }
            if (empty($rightString)) {
                $index++;
                continue;
            }

            if ($eType == "user") {

                $u = AJXP_Utils::decodeSecureMagic($httpVars[PARAM_USER_LOGIN_PREFIX.$index], AJXP_SANITIZE_EMAILCHARS);
                $userExistsRead = AuthService::userExists($u);
                if (!$userExistsRead && !isSet($httpVars[PARAM_USER_PASS_PREFIX.$index])) {
                    $index++;
                    continue;
                } else if (AuthService::userExists($u, "w") && isSet($httpVars[PARAM_USER_PASS_PREFIX.$index])) {
                    throw new Exception("User $u already exists, please choose another name.");
                }
                if($userExistsRead){
                    $userObject = $confDriver->createUserObject($u);
                    if ( $allowCrossUserSharing != true && ( !$userObject->hasParent() || $userObject->getParent() != $loggedUser->getId() ) ) {
                        throw new Exception("You are not allowed to share with other users, except your internal users.");
                    }
                }else{
                    if(!$allowSharedUsersCreation || AuthService::isReservedUserId($u)){
                        throw new Exception("You are not allowed to create users.");
                    }
                    if(!empty($this->tmpUsersPrefix) && strpos($u, $this->tmpUsersPrefix)!==0 ){
                        $u = $this->tmpUsersPrefix . $u;
                    }
                }
                $entry = array("ID" => $u, "TYPE" => "user");

            } else {

                $u = AJXP_Utils::decodeSecureMagic($httpVars[PARAM_USER_LOGIN_PREFIX.$index]);

                if (strpos($u, "/AJXP_TEAM/") === 0) {

                    if (method_exists($confDriver, "teamIdToUsers")) {
                        $teamUsers = $confDriver->teamIdToUsers(str_replace("/AJXP_TEAM/", "", $u));
                        foreach ($teamUsers as $userId) {
                            $users[$userId] = array("ID" => $userId, "TYPE" => "user", "RIGHT" => $rightString);
                            if ($this->watcher !== false) {
                                $users[$userId]["WATCH"] = $uWatch;
                            }
                        }
                    }
                    $index++;
                    continue;

                }

                $entry = array("ID" => $u, "TYPE" => "group");

            }
            $entry["RIGHT"] = $rightString;
            $entry["PASSWORD"] = isSet($httpVars[PARAM_USER_PASS_PREFIX.$index])?$httpVars[PARAM_USER_PASS_PREFIX.$index]:"";
            if ($this->watcher !== false) {
                $entry["WATCH"] = $uWatch;
            }
            if($entry["TYPE"] == "user") {
                $users[$entry["ID"]] = $entry;
            }else{
                $groups[$entry["ID"]] = $entry;
            }
            $index ++;

        }

    }

    /**
     * @param String $repoId
     * @param bool $mixUsersAndGroups
     * @param AJXP_Node|null $watcherNode
     * @return array
     */
    public function computeSharedRepositoryAccessRights($repoId, $mixUsersAndGroups, $watcherNode = null)
    {
        $roles = AuthService::getRolesForRepository($repoId);
        $sharedEntries = $sharedGroups = array();
        $mess = ConfService::getMessages();
        foreach($roles as $rId){
            $role = AuthService::getRole($rId);
            if ($role == null) continue;

            $RIGHT = $role->getAcl($repoId);
            if (empty($RIGHT)) continue;
            $ID = $rId;
            $WATCH = false;
            $HIDDEN = false;
            $AVATAR = false;
            if(strpos($rId, "AJXP_USR_/") === 0){
                $userId = substr($rId, strlen('AJXP_USR_/'));
                $role = AuthService::getRole($rId);
                $userObject = ConfService::getConfStorageImpl()->createUserObject($userId);
                $LABEL = $role->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
                $AVATAR = $role->filterParameterValue("core.conf", "avatar", AJXP_REPO_SCOPE_ALL, "");
                if(empty($LABEL)) $LABEL = $userId;
                $TYPE = $userObject->hasParent()?"tmp_user":"user";
                $HIDDEN = $userObject->isHidden();
                if ($this->watcher !== false && $watcherNode != null) {
                    $WATCH = $this->watcher->hasWatchOnNode(
                        $watcherNode,
                        $userId,
                        MetaWatchRegister::$META_WATCH_USERS_NAMESPACE
                    );
                }
                $ID = $userId;
            }else if($rId == "AJXP_GRP_/"){
                $rId = "AJXP_GRP_/";
                $TYPE = "group";
                $LABEL = $mess["447"];
            }else if(strpos($rId, "AJXP_GRP_/") === 0){
                if(empty($loadedGroups)){
                    $displayAll = ConfService::getCoreConf("CROSSUSERS_ALLGROUPS_DISPLAY", "conf");
                    if($displayAll){
                        AuthService::setGroupFiltering(false);
                    }
                    $loadedGroups = AuthService::listChildrenGroups();
                    if($displayAll){
                        AuthService::setGroupFiltering(true);
                    }else{
                        $baseGroup = AuthService::filterBaseGroup("/");
                        foreach($loadedGroups as $loadedG => $loadedLabel){
                            unset($loadedGroups[$loadedG]);
                            $loadedGroups[rtrim($baseGroup, "/")."/".ltrim($loadedG, "/")] = $loadedLabel;
                        }
                    }
                }
                $groupId = substr($rId, strlen('AJXP_GRP_'));
                if(isSet($loadedGroups[$groupId])) {
                    $LABEL = $loadedGroups[$groupId];
                }
                if($groupId == "/"){
                    $LABEL = $mess["447"];
                }
                if(empty($LABEL)) $LABEL = $groupId;
                $TYPE = "group";
            }else{
                $role = AuthService::getRole($rId);
                $LABEL = $role->getLabel();
                $TYPE = 'group';
            }

            if(empty($LABEL)) $LABEL = $rId;
            $entry = array(
                "ID"    => $ID,
                "TYPE"  => $TYPE,
                "LABEL" => $LABEL,
                "RIGHT" => $RIGHT
            );
            if($WATCH) $entry["WATCH"] = $WATCH;
            if($HIDDEN) $entry["HIDDEN"] = true;
            if($AVATAR !== false) $entry["AVATAR"] = $AVATAR;
            if($TYPE == "group"){
                $sharedGroups[$entry["ID"]] = $entry;
            } else {
                $sharedEntries[$entry["ID"]] = $entry;
            }
        }

        if (!$mixUsersAndGroups) {
            return array("USERS" => $sharedEntries, "GROUPS" => $sharedGroups);
        }else{
            return array_merge(array_values($sharedGroups), array_values($sharedEntries));

        }
    }

    /**
     * @param Repository $parentRepository
     * @param Repository $childRepository
     * @param bool $isUpdate
     * @param array $users
     * @param array $groups
     * @param UserSelection $selection
     * @param bool|false $disableDownload
     * @throws Exception
     */
    public function assignSharedRepositoryPermissions($parentRepository, $childRepository, $isUpdate, $users, $groups, $selection, $disableDownload = false){

        $childRepoId = $childRepository->getId();
        if($isUpdate){
            $this->unregisterRemovedUsers($childRepoId, $users, $groups, $selection->getUniqueNode());
        }
        $confDriver = ConfService::getConfStorageImpl();
        $loggedUser = AuthService::getLoggedUser();
        foreach ($users as $userName => $userEntry) {

            if (AuthService::userExists($userName, "r")) {
                $userObject = $confDriver->createUserObject($userName);
                if(isSet($userEntry["HIDDEN"]) && isSet($userEntry["UPDATE_PASSWORD"])){
                    AuthService::updatePassword($userName, $userEntry["UPDATE_PASSWORD"]);
                }
            } else {
                $mess = ConfService::getMessages();
                $hiddenUserLabel = "[".$mess["share_center.109"]."] ". AJXP_Utils::sanitize($childRepository->getDisplay(), AJXP_SANITIZE_EMAILCHARS);
                $userObject = $this->createNewUser($loggedUser, $userName, $userEntry["PASSWORD"], isset($userEntry["HIDDEN"]), $hiddenUserLabel);
            }

            // ASSIGN NEW REPO RIGHTS
            $userObject->personalRole->setAcl($childRepoId, $userEntry["RIGHT"]);

            // FORK MASK IF THERE IS ANY
            $childMask = $this->forkMaskIfAny($loggedUser, $parentRepository->getId(), $selection->getUniqueNode());
            if($childMask != null){
                $userObject->personalRole->setMask($childRepoId, $childMask);
            }

            // CREATE A MINISITE-LIKE ROLE FOR THIS REPOSITORY
            if (isSet($userEntry["HIDDEN"])) {
                $minisiteRole = $this->createRoleForMinisite($childRepoId, $disableDownload, $isUpdate);
                if($minisiteRole != null){
                    $userObject->addRole($minisiteRole);
                }
            }

            $userObject->save("superuser");
        }

        foreach ($groups as $group => $groupEntry) {
            $r = $groupEntry["RIGHT"];
            $grRole = AuthService::getRole($group, true);
            $grRole->setAcl($childRepoId, $r);
            AuthService::updateRole($grRole);
        }

    }

    /**
     * @param string $repoId
     * @param array $newUsers
     * @param array $newGroups
     * @param AJXP_Node|null $watcherNode
     */
    public function unregisterRemovedUsers($repoId, $newUsers, $newGroups, $watcherNode = null){

        $confDriver = ConfService::getConfStorageImpl();

        $currentRights = $this->computeSharedRepositoryAccessRights(
            $repoId,
            false,
            $watcherNode
        );

        $originalUsers = array_keys($currentRights["USERS"]);
        $removeUsers = array_diff($originalUsers, array_keys($newUsers));
        if (count($removeUsers)) {
            foreach ($removeUsers as $user) {
                if (AuthService::userExists($user)) {
                    $userObject = $confDriver->createUserObject($user);
                    $userObject->personalRole->setAcl($repoId, "");
                    $userObject->save("superuser");
                }
                if($this->watcher !== false && $watcherNode !== null){
                    $this->watcher->removeWatchFromFolder(
                        $watcherNode,
                        $user,
                        true
                    );
                }
            }
        }
        $originalGroups = array_keys($currentRights["GROUPS"]);
        $removeGroups = array_diff($originalGroups, array_keys($newGroups));
        if (count($removeGroups)) {
            foreach ($removeGroups as $groupId) {
                $role = AuthService::getRole($groupId);
                if ($role !== false) {
                    $role->setAcl($repoId, "");
                    AuthService::updateRole($role);
                }
            }
        }

    }

    /**
     * @param AbstractAjxpUser $parentUser
     * @param string $userName
     * @param string $password
     * @param bool $isHidden
     * @param string $display
     * @return AbstractAjxpUser
     * @throws Exception
     */
    public function createNewUser($parentUser, $userName, $password, $isHidden, $display){

        $confDriver = ConfService::getConfStorageImpl();
        if (ConfService::getAuthDriverImpl()->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $pass = $password;
        } else {
            $pass = md5($password);
        }
        if(!$isHidden){
            // This is an explicit user creation - check possible limits
            AJXP_Controller::applyHook("user.before_create", array($userName, null, false, false));
            $limit = $parentUser->mergedRole->filterParameterValue("core.conf", "USER_SHARED_USERS_LIMIT", AJXP_REPO_SCOPE_ALL, "");
            if (!empty($limit) && intval($limit) > 0) {
                $count = count($confDriver->getUserChildren($parentUser->getId()));
                if ($count >= $limit) {
                    $mess = ConfService::getMessages();
                    throw new Exception($mess['483']);
                }
            }
        }
        AuthService::createUser($userName, $pass, false, $isHidden);
        $userObject = $confDriver->createUserObject($userName);
        $userObject->personalRole->clearAcls();
        $userObject->setParent($parentUser->getId());
        $userObject->setGroupPath($parentUser->getGroupPath());
        $userObject->setProfile("shared");
        if($isHidden){
            $userObject->setHidden(true);
            $userObject->personalRole->setParameterValue("core.conf", "USER_DISPLAY_NAME", $display);
        }
        AJXP_Controller::applyHook("user.after_create", array($userObject));

        return $userObject;

    }


    /**
     * @param AbstractAjxpUser $parentUser
     * @param string $parentRepoId
     * @param AJXP_Node $ajxpNode
     * @return AJXP_PermissionMask|null
     */
    public function forkMaskIfAny($parentUser, $parentRepoId, $ajxpNode){

        $file = $ajxpNode->getPath();
        if($file != "/" && $parentUser->mergedRole->hasMask($parentRepoId)){
            $parentTree = $parentUser->mergedRole->getMask($parentRepoId)->getTree();
            // Try to find a branch on the current selection
            $parts = explode("/", trim($file, "/"));
            while( ($next = array_shift($parts))  !== null){
                if(is_array($parentTree) && isSet($parentTree[$next])) {
                    $parentTree = $parentTree[$next];
                }else{
                    $parentTree = null;
                    break;
                }
            }
            if($parentTree != null){
                $newMask = new AJXP_PermissionMask();
                $newMask->updateTree($parentTree);
            }
            if(isset($newMask)){
                return $newMask;//$childUser->personalRole->setMask($childRepoId, $newMask);
            }
        }
        return null;

    }

    /**
     * @param string $repositoryId
     * @param bool $disableDownload
     * @param bool $replace
     * @return AJXP_Role|null
     */
    public function createRoleForMinisite($repositoryId, $disableDownload, $replace){
        if($replace){
            try{
                AuthService::deleteRole("AJXP_SHARED-".$repositoryId);
            }catch (Exception $e){}
        }
        $newRole = new AJXP_Role("AJXP_SHARED-".$repositoryId);
        $r = AuthService::getRole("MINISITE");
        if (is_a($r, "AJXP_Role")) {
            if ($disableDownload) {
                $f = AuthService::getRole("MINISITE_NODOWNLOAD");
                if (is_a($f, "AJXP_Role")) {
                    $r = $f->override($r);
                }
            }
            $allData = $r->getDataArray();
            $newData = $newRole->getDataArray();
            if(isSet($allData["ACTIONS"][AJXP_REPO_SCOPE_SHARED])) $newData["ACTIONS"][$repositoryId] = $allData["ACTIONS"][AJXP_REPO_SCOPE_SHARED];
            if(isSet($allData["PARAMETERS"][AJXP_REPO_SCOPE_SHARED])) $newData["PARAMETERS"][$repositoryId] = $allData["PARAMETERS"][AJXP_REPO_SCOPE_SHARED];
            $newRole->bunchUpdate($newData);
            AuthService::updateRole($newRole);
            return $newRole;
        }
        return null;
    }

}