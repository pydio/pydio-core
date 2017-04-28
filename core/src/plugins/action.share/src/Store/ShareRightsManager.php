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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Share\Store;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Filter\AJXP_PermissionMask;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Meta\Watch\WatchRegister;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;

use Pydio\Conf\Core\AJXP_Role;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\OCS\Model\TargettedLink;
use Pydio\Share\Model\ShareLink;

defined('AJXP_EXEC') or die('Access not allowed');

define('PARAM_USER_LOGIN_PREFIX', "user_");
define('PARAM_USER_PASS_PREFIX', "user_pass_");
define('PARAM_USER_RIGHT_WATCH_PREFIX', "right_watch_");
define('PARAM_USER_RIGHT_READ_PREFIX', "right_read_");
define('PARAM_USER_RIGHT_WRITE_PREFIX', "right_write_");
define('PARAM_USER_ENTRY_TYPE', "entry_type_");

/**
 * Class ShareRightsManager
 * @package Pydio\Share\Store
 */
class ShareRightsManager
{
    /**
     * @var WatchRegister|bool
     */
    var $watcher;
    /**
     * @var ShareStore $store
     */
    var $store;

    /**
     * @var array $options
     */
    var $options;

    /** @var  ContextInterface */
    var $context;

    /**
     * ShareRightsManager constructor.
     * @param ContextInterface $context
     * @param array $options
     * @param ShareStore $store
     * @param WatchRegister|bool $watcher
     */
    public function __construct(ContextInterface $context, $options, $store, $watcher = false)
    {
        $this->context = $context;
        $this->options = $options;
        $this->watcher = $watcher;
        $this->store = $store;
    }

    /**
     * @param array $httpVars
     * @param ShareLink $shareObject
     * @param bool $update
     * @param null $guestUserPass
     * @return array
     * @throws \Exception
     */
    public function prepareSharedUserEntry($httpVars, &$shareObject, $update, $guestUserPass = null){
        $userPass = null;

        $forcePassword = $this->options["SHARE_FORCE_PASSWORD"];
        if($forcePassword && (
                (isSet($httpVars["create_guest_user"]) && $httpVars["create_guest_user"] == "true" && empty($guestUserPass))
                || (isSet($httpVars["guest_user_id"]) && isSet($guestUserPass) && strlen($guestUserPass) == 0)
            )){
            $mess = LocaleService::getMessages();
            throw new \Exception($mess["share_center.175"]);
        }

        if($update){

            // THIS IS AN EXISTING SHARE
            // FIND SHARE AND EXISTING HIDDEN USER ID
            if($shareObject->isAttachedToRepository()){
                $existingRepo = $shareObject->getRepository();
                $this->store->testUserCanEditShare($existingRepo->getOwner(), $existingRepo->options);
            }
            $uniqueUser = $shareObject->getUniqueUser();
            if($guestUserPass !== null && strlen($guestUserPass)) {
                $userPass = $guestUserPass;
                $shareObject->setUniqueUser($uniqueUser, true);
            }else if(!$shareObject->shouldRequirePassword() || ($guestUserPass !== null && $guestUserPass == "")){
                $shareObject->setUniqueUser($uniqueUser, false);
            }
            if($update && $forcePassword && !($shareObject instanceof TargettedLink) && !$shareObject->shouldRequirePassword() && empty($guestUserPass)){
                $mess = LocaleService::getMessages();
                throw new \Exception($mess["share_center.175"]);
            }

        } else {

            $update = false;
            $shareObject->createHiddenUserId(
                $this->options["SHARED_USERS_TMP_PREFIX"],
                !empty($guestUserPass)
            );
            if(!empty($guestUserPass)){
                $userPass = $guestUserPass;
            }else{
                $userPass = $shareObject->createHiddenUserPassword();
            }
            $uniqueUser = $shareObject->getUniqueUser();
        }

        $hiddenUserEntry = $this->createHiddenUserEntry($httpVars, $uniqueUser, $userPass, $update);
        if(empty($hiddenUserEntry["RIGHT"])){
            $mess = LocaleService::getMessages();
            throw new \Exception($mess["share_center.58"]);
        }
        $hiddenUserEntry["DISABLE_DOWNLOAD"] = $shareObject->disableDownload();
        if($shareObject instanceof TargettedLink){
            $hiddenUserEntry["REMOTE"] = true;
        }
        return $hiddenUserEntry;
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
     * @throws \Exception
     */
    public function createUsersFromParameters($httpVars, &$users = array(), &$groups = array()){

        $index = 0;
        $allowCrossUserSharing = ConfService::getContextConf($this->context, "ALLOW_CROSSUSERS_SHARING", "conf");
        $allowSharedUsersCreation = ConfService::getContextConf($this->context, "USER_CREATE_USERS", "conf");
        $loggedUser = $this->context->getUser();
        $confDriver = ConfService::getConfStorageImpl();
        $mess = LocaleService::getMessages();

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

                $u = InputFilter::decodeSecureMagic($httpVars[PARAM_USER_LOGIN_PREFIX . $index], InputFilter::SANITIZE_EMAILCHARS);
                $userExistsRead = UsersService::userExists($u);
                if (!$userExistsRead && !isSet($httpVars[PARAM_USER_PASS_PREFIX.$index])) {
                    $index++;
                    continue;
                } else if (UsersService::userExists($u, "w") && isSet($httpVars[PARAM_USER_PASS_PREFIX.$index])) {
                    throw new \Exception( str_replace("%s", $u, $mess["share_center.222"]));
                }
                if($userExistsRead){
                    $userObject = UsersService::getUserById($u, false);
                    if ( $allowCrossUserSharing != true && ( !$userObject->hasParent() || $userObject->getParent() != $loggedUser->getId() ) ) {
                        throw new \Exception($mess["share_center.221"]);
                    }
                }else{
                    if(!$allowSharedUsersCreation || UsersService::isReservedUserId($u)){
                        throw new \Exception($mess["share_center.220"]);
                    }
                    if(!empty($this->options["SHARED_USERS_TMP_PREFIX"]) && strpos($u, $this->options["SHARED_USERS_TMP_PREFIX"])!==0 ){
                        $u = $this->options["SHARED_USERS_TMP_PREFIX"] . $u;
                    }
                }
                $entry = array("ID" => $u, "TYPE" => "user");

            } else {

                $u = InputFilter::decodeSecureMagic($httpVars[PARAM_USER_LOGIN_PREFIX . $index]);

                if (strpos($u, "/AJXP_TEAM/") === 0) {

                    $roleId = str_replace("/AJXP_TEAM/", "", $u);
                    $roleObject = RolesService::getOwnedRole($roleId, $this->context->getUser()->getId());
                    if(empty($roleObject)){
                        $index++;
                        continue;
                    }else{
                        // Replace now with roleId
                        $u = $roleId;
                    }

                    $entry = array("ID" => $u, "TYPE" => "group", "USER_TEAM" => true);

                }else{

                    $entry = array("ID" => $u, "TYPE" => "group");

                }

            }
            $entry["RIGHT"] = $rightString;
            $entry["PASSWORD"] = isSet($httpVars[PARAM_USER_PASS_PREFIX.$index])?$httpVars[PARAM_USER_PASS_PREFIX.$index]:"";
            if ($this->watcher !== false) {
                $entry["WATCH"] = $uWatch;
            }
            if($entry["TYPE"] == "user") {
                $users[$entry["ID"]] = $entry;
            }else{
                if($entry["ID"] == "AJXP_GRP_/"){
                    $entry["ID"] = "AJXP_GRP_".($this->context->hasUser() ? $this->context->getUser()->getRealGroupPath("/") : "/");
                }
                $groups[$entry["ID"]] = $entry;

            }
            $index ++;

        }

    }

    /**
     * @param array $ocsData
     * @param \Pydio\OCS\Model\ShareInvitation[] $existingInvitations
     * @param array $newOcsUsers
     * @param array $unshareInvitations
     * @return int
     */
    public function remoteUsersFromParameters($ocsData, $existingInvitations, &$newOcsUsers, &$unshareInvitations){
        $totalInvitations = count($ocsData["invitations"]);
        $newOcsUsers = array();
        $unshareInvitations = array();

        $resentIds = array();
        foreach($ocsData["invitations"] as $invitationData){
            if(isSet($invitationData["INVITATION_ID"])){
                $resentIds[] = $invitationData["INVITATION_ID"];
            }else{
                $newOcsUsers[] = $invitationData;
            }
        }
        foreach($existingInvitations as $invitation){
            if(!in_array($invitation->getId(), $resentIds)){
                $unshareInvitations[] = $invitation;
            }
        }
        return $totalInvitations;
    }

    /**
     * @param String $repoId
     * @param bool $mixUsersAndGroups
     * @param \Pydio\Access\Core\Model\AJXP_Node|null $watcherNode
     * @return array
     */
    public function computeSharedRepositoryAccessRights($repoId, $mixUsersAndGroups, $watcherNode = null)
    {
        $roles = RolesService::getRolesForRepository($repoId);
        $sharedEntries = $sharedGroups = array();
        $mess = LocaleService::getMessages();
        foreach($roles as $rId){
            $role = RolesService::getRole($rId);
            if ($role == null) continue;

            $RIGHT = $role->getAcl($repoId);
            if (empty($RIGHT)) continue;
            $ID = $rId;
            $WATCH = false;
            $HIDDEN = false;
            $AVATAR = false;
            if(strpos($rId, "AJXP_USR_/") === 0){
                $userId = substr($rId, strlen('AJXP_USR_/'));
                $role = RolesService::getRole($rId);
                if(!UsersService::userExists($userId)) continue;
                $userObject = UsersService::getUserById($userId);
                $LABEL = $role->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
                $AVATAR = $role->filterParameterValue("core.conf", "avatar", AJXP_REPO_SCOPE_ALL, "");
                if(empty($LABEL)) $LABEL = $userId;
                $TYPE = $userObject->hasParent()?"tmp_user":"user";
                $HIDDEN = $userObject->isHidden();
                if ($this->watcher !== false && $watcherNode != null) {
                    $WATCH = $this->watcher->hasWatchOnNode(
                        $watcherNode,
                        $userId,
                        WatchRegister::$META_WATCH_USERS_NAMESPACE
                    );
                }
                $ID = $userId;
            }else if($rId == "AJXP_GRP_".($this->context->hasUser() ? $this->context->getUser()->getRealGroupPath("/") : "/")){
                $TYPE = "group";
                $LABEL = $mess["447"];
            }else if(strpos($rId, "AJXP_GRP_/") === 0){
                $currentUserGroup = ($this->context->hasUser() ? $this->context->getUser()->getGroupPath() : "/");
                $rootGroup = "/";
                if(empty($loadedGroups)){
                    $displayAll = ConfService::getContextConf($this->context, "CROSSUSERS_ALLGROUPS_DISPLAY", "conf");
                    $loadedGroups = UsersService::listChildrenGroups($displayAll ? $rootGroup : $currentUserGroup);
                    if(!$displayAll){
                        foreach($loadedGroups as $loadedG => $loadedLabel){
                            unset($loadedGroups[$loadedG]);
                            $loadedGroups[rtrim($currentUserGroup, "/")."/".ltrim($loadedG, "/")] = $loadedLabel;
                        }
                    }
                }
                $groupId = substr($rId, strlen('AJXP_GRP_'));
                if(isSet($loadedGroups[$groupId])) {
                    $LABEL = $loadedGroups[$groupId];
                }
                /*
                if($groupId == AuthService::filterBaseGroup("/")){
                    $LABEL = $mess["447"];
                }
                */
                if(empty($LABEL)) $LABEL = $groupId;
                $TYPE = "group";
            }else{
                $role = RolesService::getRole($rId);
                if(empty($role)) continue;
                $LABEL = $role->getLabel();
                if($role->hasOwner()) $TYPE = "team";
                else $TYPE = 'group';
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
            if($TYPE === "group" || $TYPE === "team"){
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
     * @param \Pydio\Core\Model\RepositoryInterface $parentRepository
     * @param Repository $childRepository
     * @param bool $isUpdate
     * @param array $users
     * @param array $groups
     * @param \Pydio\Access\Core\Model\UserSelection $selection
     * @param AJXP_Node $originalNode
     * @throws \Exception
     */
    public function assignSharedRepositoryPermissions($parentRepository, $childRepository, $isUpdate, $users, $groups, $selection, $originalNode = null){

        $childRepoId = $childRepository->getId();
        if($isUpdate){
            $this->unregisterRemovedUsers($childRepoId, $users, $groups, $selection->getUniqueNode());
        }

        $loggedUser = $this->context->getUser();
        foreach ($users as $userName => $userEntry) {

            if (UsersService::userExists($userName, "r")) {
                $userObject = UsersService::getUserById($userName);
                if(isSet($userEntry["HIDDEN"]) && isSet($userEntry["UPDATE_PASSWORD"])){
                    UsersService::updatePassword($userName, $userEntry["UPDATE_PASSWORD"]);
                }
            } else {
                $mess = LocaleService::getMessages();
                $hiddenUserLabel = "[".$mess["share_center.109"]."] ". InputFilter::sanitize($childRepository->getDisplay(), InputFilter::SANITIZE_EMAILCHARS);
                $userObject = $this->createNewUser($loggedUser, $userName, $userEntry["PASSWORD"], isset($userEntry["HIDDEN"]), $hiddenUserLabel);
            }

            // ASSIGN NEW REPO RIGHTS
            $userObject->getPersonalRole()->setAcl($childRepoId, $userEntry["RIGHT"]);

            // FORK MASK IF THERE IS ANY
            $childMask = $this->forkMaskIfAny($loggedUser, $parentRepository->getId(), $selection->getUniqueNode());
            if($childMask != null){
                $userObject->getPersonalRole()->setMask($childRepoId, $childMask);
            }

            // CREATE A MINISITE-LIKE ROLE FOR THIS REPOSITORY
            if (isSet($userEntry["HIDDEN"]) && !isSet($userEntry["REMOTE"])) {
                $minisiteRole = $this->createRoleForMinisite($childRepoId, $userEntry["DISABLE_DOWNLOAD"], $isUpdate);
                if($minisiteRole != null){
                    $userObject->addRole($minisiteRole);
                }
            }
            // ADD "my shared files" REPO OTHERWISE SOME USER CANNOT ACCESS
            if( !isSet($userEntry["HIDDEN"]) && $childRepository->hasContentFilter()){
                $inboxRepo = RepositoryService::getRepositoryById("inbox");
                $currentAcl = $userObject->getMergedRole()->getAcl("inbox");
                if($inboxRepo !== null && empty($currentAcl)){
                    $userObject->getPersonalRole()->setAcl("inbox", "rw");
                }
            }

            $userObject->save("superuser");
            if(!empty($originalNode)){
                Controller::applyHook("node.share.assign_right", array($this->context, $userObject, $childRepository, $originalNode));
            }
        }

        foreach ($groups as $group => $groupEntry) {
            $r = $groupEntry["RIGHT"];
            if($groupEntry["USER_TEAM"]){
                $grRole = RolesService::getOwnedRole($group, $this->context->getUser()->getId());
            }else{
                $grRole = RolesService::getOrCreateRole($group, $this->context->hasUser() ? $this->context->getUser()->getGroupPath() : "/");
            }
            $grRole->setAcl($childRepoId, $r);
            RolesService::updateRole($grRole);
            if(!empty($originalNode)) {
                Controller::applyHook("node.share.assign_right", array($this->context, $group, $childRepository, $originalNode));
            }
        }

    }

    /**
     * @param string $repoId
     * @param array $newUsers
     * @param array $newGroups
     * @param AJXP_Node|null $watcherNode
     */
    public function unregisterRemovedUsers($repoId, $newUsers, $newGroups, $watcherNode = null){

        $currentRights = $this->computeSharedRepositoryAccessRights(
            $repoId,
            false,
            $watcherNode
        );

        $originalUsers = array_keys($currentRights["USERS"]);
        $removeUsers = array_diff($originalUsers, array_keys($newUsers));
        if (count($removeUsers)) {
            foreach ($removeUsers as $user) {
                if (UsersService::userExists($user)) {
                    $userObject = UsersService::getUserById($user, false);
                    $userObject->getPersonalRole()->setAcl($repoId, "");
                    $userObject->save("superuser");
                    Controller::applyHook("node.share.remove_right", array($this->context, $userObject, $repoId));
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
                $role = RolesService::getRole($groupId);
                if ($role !== false) {
                    $role->setAcl($repoId, "");
                    RolesService::updateRole($role);
                    Controller::applyHook("node.share.remove_right", array($this->context, $groupId, $repoId));
                }
            }
        }

    }

    /**
     * @param UserInterface $parentUser
     * @param string $userName
     * @param string $password
     * @param bool $isHidden
     * @param string $display
     * @return UserInterface
     * @throws \Exception
     */
    public function createNewUser($parentUser, $userName, $password, $isHidden, $display){

        $confDriver = ConfService::getConfStorageImpl();
        if(!$isHidden){
            // This is an explicit user creation - check possible limits
            Controller::applyHook("user.before_create", array($this->context, $userName, null, false, false));
            $limit = $parentUser->getMergedRole()->filterParameterValue("core.conf", "USER_SHARED_USERS_LIMIT", AJXP_REPO_SCOPE_ALL, "");
            if (!empty($limit) && intval($limit) > 0) {
                $count = count($confDriver->getUserChildren($parentUser->getId()));
                if ($count >= $limit) {
                    $mess = LocaleService::getMessages();
                    throw new \Exception($mess['483']);
                }
            }
        }

        $userObject = UsersService::createUser($userName, $password, false, $isHidden);
        $userObject->getPersonalRole()->clearAcls();
        $userObject->setParent($parentUser->getId());
        $userObject->setGroupPath($parentUser->getGroupPath());
        $userObject->setProfile("shared");
        if($isHidden){
            $userObject->setHidden(true);
            $userObject->getPersonalRole()->setParameterValue("core.conf", "USER_DISPLAY_NAME", $display);
        }
        Controller::applyHook("user.after_create", array($this->context, $userObject));

        return $userObject;

    }


    /**
     * @param UserInterface $parentUser
     * @param string $parentRepoId
     * @param AJXP_Node $ajxpNode
     * @return AJXP_PermissionMask|null
     */
    public function forkMaskIfAny($parentUser, $parentRepoId, $ajxpNode){

        $file = $ajxpNode->getPath();
        if($file != "/" && $parentUser->getMergedRole()->hasMask($parentRepoId)){
            $parentTree = $parentUser->getMergedRole()->getMask($parentRepoId)->getTree();
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
                RolesService::deleteRole("AJXP_SHARED-" . $repositoryId);
            }catch (\Exception $e){}
        }
        $newRole = new AJXP_Role("AJXP_SHARED-".$repositoryId);
        $r = RolesService::getRole("MINISITE");
        if ($r instanceof AJXP_Role) {
            if ($disableDownload) {
                $f = RolesService::getRole("MINISITE_NODOWNLOAD");
                if ($f instanceof AJXP_Role) {
                    $r = $f->override($r);
                }
            }
            $allData = $r->getDataArray();
            $newData = $newRole->getDataArray();
            if(isSet($allData["ACTIONS"][AJXP_REPO_SCOPE_SHARED])) $newData["ACTIONS"][$repositoryId] = $allData["ACTIONS"][AJXP_REPO_SCOPE_SHARED];
            if(isSet($allData["PARAMETERS"][AJXP_REPO_SCOPE_SHARED])) $newData["PARAMETERS"][$repositoryId] = $allData["PARAMETERS"][AJXP_REPO_SCOPE_SHARED];
            $newRole->bunchUpdate($newData);
            RolesService::updateRole($newRole);
            return $newRole;
        }
        return null;
    }

}