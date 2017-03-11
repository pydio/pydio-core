<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Model;


use Pydio\Conf\Core\AJXP_Role;
use Pydio\Core\Exception\UserNotFoundException;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class FilteredUsersList
 * @package Pydio\Core\Model
 */
class FilteredUsersList{

    const FILTER_USERS_INTERNAL = 1;
    const FILTER_USERS_EXTERNAL = 2;
    const FILTER_GROUPS = 4;
    const FILTER_TEAMS = 8;

    const TEAM_PREFIX = '/AJXP_TEAM';

    /**
     * @var ContextInterface
     */
    private $ctx;

    /**
     * FilteredUsersList constructor.
     * @param ContextInterface $ctx
     */
    public function __construct(ContextInterface $ctx){
        $this->ctx = $ctx;
    }

    /**
     * @param $confName
     * @param string $coreType
     * @return mixed
     */
    protected function getConf($confName, $coreType = 'conf'){
        return ConfService::getContextConf($this->ctx, $confName, $coreType);
    }

    /**
     * @param string $groupPathFilter
     * @param string $searchQuery
     * @return string
     */
    protected function computeBaseGroup($groupPathFilter = '', $searchQuery = ''){

        if(strpos($groupPathFilter, self::TEAM_PREFIX) === 0){
            return $groupPathFilter;
        }

        $searchAll      = $this->getConf('CROSSUSERS_ALLGROUPS');
        $displayAll     = $this->getConf('CROSSUSERS_ALLGROUPS_DISPLAY');

        $contextGroupPath   = $this->ctx->getUser()->getGroupPath();
        $baseGroup = '/';
        if( (empty($searchQuery) && !$displayAll) || (!empty($searchQuery) && !$searchAll)){
            $baseGroup = $contextGroupPath;
        }
        if( !empty($groupPathFilter) ){
            $baseGroup = rtrim($baseGroup, '/') . $groupPathFilter;
        }

        return $baseGroup;

    }

    /**
     * @param UserInterface $userObject
     * @param string $rolePrefix get all roles with prefix
     * @param string $includeString get roles in this string
     * @param string $excludeString eliminate roles in this string
     * @param bool $byUserRoles
     * @return array
     */
    protected function searchUserRolesList($userObject, $rolePrefix, $includeString, $excludeString, $byUserRoles = false)
    {
        if (!$userObject){
            return [];
        }
        if ($byUserRoles) {
            $allUserRoles = $userObject->getRoles();
        } else {
            $allUserRoles = RolesService::getRolesList([], true);
        }
        $allRoles = [];
        if (isset($allUserRoles)) {

            // Exclude
            if ($excludeString) {
                if (strpos($excludeString, "preg:") !== false) {
                    $matchFilterExclude = "/" . str_replace("preg:", "", $excludeString) . "/i";
                } else {
                    $valueFiltersExclude = array_map("trim", explode(",", $excludeString));
                    $valueFiltersExclude = array_map("strtolower", $valueFiltersExclude);
                }
            }

            // Include
            if ($includeString) {
                if (strpos($includeString, "preg:") !== false) {
                    $matchFilterInclude = "/" . str_replace("preg:", "", $includeString) . "/i";
                } else {
                    $valueFiltersInclude = array_map("trim", explode(",", $includeString));
                    $valueFiltersInclude = array_map("strtolower", $valueFiltersInclude);
                }
            }

            foreach ($allUserRoles as $roleId => $role) {
                if (!empty($rolePrefix) && strpos($roleId, $rolePrefix) === false) continue;
                if (isSet($matchFilterExclude) && preg_match($matchFilterExclude, substr($roleId, strlen($rolePrefix)))) continue;
                if (isSet($valueFiltersExclude) && in_array(strtolower(substr($roleId, strlen($rolePrefix))), $valueFiltersExclude)) continue;
                if (isSet($matchFilterInclude) && !preg_match($matchFilterInclude, substr($roleId, strlen($rolePrefix)))) continue;
                if (isSet($valueFiltersInclude) && !in_array(strtolower(substr($roleId, strlen($rolePrefix))), $valueFiltersInclude)) continue;
                if($role instanceof AJXP_Role) $roleObject = $role;
                else $roleObject = RolesService::getRole($roleId);
                $label = $roleObject->getLabel();
                $label = !empty($label) ? $label : substr($roleId, strlen($rolePrefix));
                $allRoles[$roleId] = $label;
            }
        }
        return $allRoles;
    }

    /**
     * @param $groupPath string
     * @param $searchTerm string
     * @param $searchLimit int
     * @param $filterArray array
     * @param $recursive bool
     * @return UserInterface[]
     */
    protected function listUsers($groupPath, $searchTerm, $searchLimit, $filterArray, $recursive = false){

        if(strpos($groupPath, self::TEAM_PREFIX) === 0){

            $teamId = str_replace(self::TEAM_PREFIX.'/', '', $groupPath);
            $userIds = ConfService::getConfStorageImpl()->getUsersForRole($teamId);
            $users = [];
            foreach($userIds as $userId) {
                try {
                    $userObject = UsersService::getUserById($userId);
                } catch (UserNotFoundException $e) {
                    continue;
                }
                if(!$filterArray['users_external'] && $userObject->hasParent()) continue;
                if(!$filterArray['users_internal'] && !$userObject->hasParent()) continue;
                $users[] = $userObject;
            }

        } else if($filterArray['users_internal']){
            // only internal or both
            $users = UsersService::listUsers($groupPath, '^'.$searchTerm, 0, $searchLimit, false, $recursive);
            if(!$filterArray['users_external']){
                $users = array_filter($users, function($userObject) {
                    /** @var UserInterface $userObject */
                    return !$userObject->hasParent();
                });
            }
        } else {

            $users = UsersService::getChildrenUsers($this->ctx->getUser()->getId());

        }

        $crossUsers = $this->getConf('ALLOW_CROSSUSERS_SHARING');
        $loggedUser = $this->ctx->getUser();
        $users = array_filter($users, function($userObject) use ($crossUsers, $loggedUser){
            /** @var UserInterface $userObject */
            return $userObject->getId() !== $loggedUser->getId()
                && (!$userObject->hasParent() && $crossUsers ) || $userObject->getParent() === $loggedUser->getId();
        });

        return $users;
    }

    /**
     * @param $baseGroup
     * @return array
     */
    protected function listGroupsOrRoles($baseGroup){

        $allGroups = [];

        $roleOrGroup = $this->getConf("GROUP_OR_ROLE");
        $rolePrefix = $excludeString = $includeString = null;
        if(!is_array($roleOrGroup)){
            $roleOrGroup = ["group_switch_value" => $roleOrGroup];
        }

        $listRoleType = false;
        $loggedUser = $this->ctx->getUser();

        if(isSet($roleOrGroup["PREFIX"])){
            $rolePrefix    = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "PREFIX", null, $roleOrGroup["PREFIX"]);
            $excludeString = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "EXCLUDED", null, $roleOrGroup["EXCLUDED"]);
            $includeString = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "INCLUDED", null, $roleOrGroup["INCLUDED"]);
            $listUserRolesOnly = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "LIST_ROLE_BY", null, $roleOrGroup["LIST_ROLE_BY"]);
            if (is_array($listUserRolesOnly) && isset($listUserRolesOnly["group_switch_value"])) {
                switch ($listUserRolesOnly["group_switch_value"]) {
                    case "userroles":
                        $listRoleType = true;
                        break;
                    case "allroles":
                        $listRoleType = false;
                        break;
                    default;
                        break;
                }
            }
        }

        switch (strtolower($roleOrGroup["group_switch_value"])) {
            case 'user':
                // donothing
                break;
            case 'group':
                $authGroups = UsersService::listChildrenGroups($baseGroup);
                foreach ($authGroups as $gId => $gName) {
                    $allGroups["AJXP_GRP_" . rtrim($baseGroup, "/")."/".ltrim($gId, "/")] = $gName;
                }
                break;
            case 'role':
                $allGroups = $this->searchUserRolesList($loggedUser, $rolePrefix, $includeString, $excludeString, $listRoleType);
                break;
            case 'rolegroup';
                $groups = [];
                $authGroups = UsersService::listChildrenGroups($baseGroup);
                foreach ($authGroups as $gId => $gName) {
                    $groups["AJXP_GRP_" . rtrim($baseGroup, "/")."/".ltrim($gId, "/")] = $gName;
                }
                $roles = $this->searchUserRolesList($loggedUser, $rolePrefix, $includeString, $excludeString, $listRoleType);
                $allGroups = array_merge($groups, $roles);

                break;
            default;
                break;
        }

        return $allGroups;

    }

    /**
     * @param $searchQuery string
     * @return AddressBookItem[]
     */
    protected function listTeams($searchQuery = ''){
        if(!empty($searchQuery)){
            $pregexp = '/^'.preg_quote($searchQuery).'/i';
        }
        $res = [];
        $teams = RolesService::getRolesOwnedBy($this->ctx->getUser()->getId());
        foreach ($teams as $teamObject) {
            if(empty($pregexp) || preg_match($pregexp, $teamObject->getLabel()) || preg_match($pregexp, $teamObject->getId())){
                $res[] = new AddressBookItem('group', self::TEAM_PREFIX.'/'.$teamObject->getId(), $teamObject->getLabel());
            }
        }
        return $res;
    }

    /**
     * @param $value int
     * @return array
     */
    private function parseFilterValue($value){
        return [
            'users_internal' => ($value & self::FILTER_USERS_INTERNAL) > 0,
            'users_external' => ($value & self::FILTER_USERS_EXTERNAL) > 0,
            'groups'         => ($value & self::FILTER_GROUPS) > 0,
            'teams'          => ($value & self::FILTER_TEAMS) > 0
        ];
    }

    /**
     * @param int $filterValue
     * @param bool $allowCreation
     * @param string $searchQuery
     * @param string $groupPathFilter
     * @param string $remoteServerId
     * @return AddressBookItem[]
     */
    public function load($filterValue, $allowCreation = true, $searchQuery = '', $groupPathFilter = '', $remoteServerId = ''){

        $FILTER = $this->parseFilterValue($filterValue);
        if(!empty($groupPathFilter) && strpos($groupPathFilter, self::TEAM_PREFIX) !== 0){
            $FILTER['users_external'] = false;
        }

        // No Regexp and it's mandatory. Just return the current user teams.
        if($this->getConf('USERS_LIST_REGEXP_MANDATORY') && empty($searchQuery) && empty($groupPathFilter)){
            return $this->listTeams();
        }

        $items          = [];
        $mess           = LocaleService::getMessages();
        $allowCreation &= $this->getConf('USER_CREATE_USERS');
        $searchLimit    = $this->getConf('USERS_LIST_COMPLETE_LIMIT');
        $baseGroup      = $this->computeBaseGroup($groupPathFilter, $searchQuery);

        if(!empty($searchQuery)) {
            $regexp = '^'.$searchQuery;
            $pregexp = '/^'.preg_quote($searchQuery).'/i';
        } else {
            $regexp = $pregexp = null;
        }

        $allGroups = [];
        $allUsers = [];
        if( $FILTER['users_internal'] || $FILTER['users_external'] ){
            $allUsers = $this->listUsers($baseGroup, $searchQuery, $searchLimit, $FILTER, empty($groupPathFilter));
        }
        if( $FILTER['groups'] ) {
            $allGroups = $this->listGroupsOrRoles($baseGroup);
        }

        if ( $allowCreation && !empty($searchQuery) && (!count($allUsers) || !array_key_exists(strtolower($searchQuery), $allUsers)) ) {
            $items[] = new AddressBookItem('user', '', $searchQuery, true);
        }
        if ( $FILTER['groups'] && empty($groupPathFilter) && (empty($regexp)  ||  preg_match($pregexp, $mess["447"]))) {
            $items[] = new AddressBookItem('group', 'AJXP_GRP_/', $mess['447']);
        }

        $indexGroup = 0;
        foreach ($allGroups as $groupId => $groupLabel) {
            if ($regexp == null ||  preg_match($pregexp, $groupLabel)) {
                $items[] = new AddressBookItem('group', $groupId, $groupLabel);
                $indexGroup++;
            }
            if($indexGroup == $searchLimit) break;
        }

        if ( $FILTER['teams'] && empty($groupPathFilter) ) {
            $teams = $this->listTeams($searchQuery);
            foreach($teams as $t){
                $items[] = $t;
            }
        }

        $index = 0;
        foreach ($allUsers as $userObject) {

            $userId         = $userObject->getId();
            $userLabel      = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject, "core.conf", $userId);
            $userAvatar     = UsersService::getUserPersonalParameter("avatar", $userObject, "core.conf", "");

            $userDisplay = ($userLabel == $userId ? $userId : $userLabel . " ($userId)");
            if ($this->getConf('USERS_LIST_HIDE_LOGIN') === true && $userLabel !== $userId) {
                $userDisplay = $userLabel;
            }
            $userIsExternal = $userObject->hasParent() ? "true":"false";

            $items[] = new AddressBookItem('user', $userId, $userDisplay, false, $userIsExternal, $userAvatar);
            $index ++;
            if($index == $searchLimit) break;
        }

        return $items;

    }



}
