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
namespace Pydio\Access\Driver\DataProvider\Provisioning;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class UsersManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class UsersManager extends AbstractManager
{

    /**
     * @param array $httpVars Full set of query parameters
     * @param string $rootPath Path to prepend to the resulting nodes
     * @param string $relativePath Specific path part for this function
     * @param string $paginationHash Number added to url#2 for pagination purpose.
     * @param string $findNodePosition Path to a given node to try to find it
     * @param string $aliasedDir Aliased path used for alternative url
     *
     * @return NodesList A populated NodesList object, eventually recursive.
     */
    public function listNodes($httpVars, $rootPath, $relativePath, $paginationHash = null, $findNodePosition = null, $aliasedDir = null)
    {
        $fullBasePath   = "/" . $rootPath . "/" . $relativePath;
        $USER_PER_PAGE  = 50;
        $messages       = LocaleService::getMessages();
        $nodesList      = new NodesList();
        $parentNode     = new AJXP_Node($fullBasePath, [
            "remote_indexation" => "admin_search",
            "is_file" => false,
            "text" => ""
        ]);
        $nodesList->setParentNode($parentNode);

        $baseGroup      = ($relativePath === "users" ? "/" : substr($relativePath, strlen("users")));
        if($this->context->hasUser()){
            $baseGroup = $this->context->getUser()->getRealGroupPath($baseGroup);
        }

        if ($findNodePosition != null && $paginationHash == null) {

            $findNodePositionPath = $fullBasePath."/".$findNodePosition;
            $position = UsersService::findUserPage($baseGroup, $findNodePosition, $USER_PER_PAGE);

            if($position != -1){
                $nodesList->addBranch(new AJXP_Node($findNodePositionPath, [
                    "text" => $findNodePosition,
                    "page_position" => $position
                ]));
            }else{
                // Loop on each page to find the correct page.
                $count = UsersService::authCountUsers($baseGroup);
                $pages = ceil($count / $USER_PER_PAGE);
                for ($i = 0; $i < $pages ; $i ++) {

                    $newList = $this->listNodes($httpVars, $rootPath, $relativePath, $i+1, true, $findNodePosition);
                    $foundNode = $newList->findChildByPath($findNodePositionPath);
                    if ($foundNode !== null) {
                        $foundNode->mergeMetadata(["page_position" => $i+1]);
                        $nodesList->addBranch($foundNode);
                        break;
                    }
                }
            }
            return $nodesList;

        }

        $nodesList->initColumnsData("filelist", "list", "ajxp_conf.users");
        $nodesList->appendColumn("ajxp_conf.6", "ajxp_label", "String", "40%");
        $nodesList->appendColumn("ajxp_conf.102", "object_id", "String", "10%");
        if(UsersService::driverSupportsAuthSchemes()){
            $nodesList->appendColumn("ajxp_conf.115", "auth_scheme", "String", "5%");
            $nodesList->appendColumn("ajxp_conf.7", "isAdmin", "String", "5%");
        }else{
            $nodesList->appendColumn("ajxp_conf.7", "isAdmin", "String", "10%");
        }
        $nodesList->appendColumn("ajxp_conf.70", "ajxp_roles", "String", "15%");
        $nodesList->appendColumn("ajxp_conf.62", "rights_summary", "String", "15%");

        if(!UsersService::usersEnabled()) return $nodesList;

        if(empty($paginationHash)) $paginationHash = 1;
        $count = UsersService::authCountUsers($baseGroup, "", null, null, false);
        if (UsersService::authSupportsPagination() && $count >= $USER_PER_PAGE) {

            $offset = ($paginationHash - 1) * $USER_PER_PAGE;
            $nodesList->setPaginationData($count, $paginationHash, ceil($count / $USER_PER_PAGE));
            $users = UsersService::listUsers($baseGroup, "", $offset, $USER_PER_PAGE, true, false);
            if ($paginationHash == 1) {
                $groups = UsersService::listChildrenGroups($baseGroup);
            } else {
                $groups = array();
            }

        } else {

            $users = UsersService::listUsers($baseGroup, "", -1, -1, true, false);
            $groups = UsersService::listChildrenGroups($baseGroup);

        }

        // Append Root Group
        if($this->pluginName === "ajxp_admin" && $baseGroup == "/" && $paginationHash == 1 && !$this->currentUserIsGroupAdmin()){

            $rootGroupNode = new AJXP_Node($fullBasePath ."/", [
                "icon" => "users-folder.png",
                "icon_class" => "icon-home",
                "ajxp_mime" => "group",
                "object_id" => "/",
                "is_file"   => false,
                "text"      => $messages["ajxp_conf.151"]
            ]);
            $nodesList->addBranch($rootGroupNode);

        }

        // LIST GROUPS
        foreach ($groups as $groupId => $groupLabel) {

            $nodeKey = $fullBasePath ."/".ltrim($groupId,"/");
            $meta = array(
                "icon" => "users-folder.png",
                "icon_class" => "icon-folder-close",
                "ajxp_mime" => "group",
                "object_id" => $groupId,
                "text"      => $groupLabel,
                "is_file"   => false
            );
            $this->appendBookmarkMeta($nodeKey, $meta);
            $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
        }

        // LIST USERS
        $userArray  = array();
        $logger     = Logger::getInstance();
        if(method_exists($logger, "usersLastConnection")){
            $allUserIds = array();
        }
        foreach ($users as $userObject) {
            $label = $userObject->getId();
            if(isSet($allUserIds)) $allUserIds[] = $label;
            if ($userObject->hasParent()) {
                $label = $userObject->getParent()."000".$label;
            }else{
                $children = ConfService::getConfStorageImpl()->getUserChildren($label);
                foreach($children as $addChild){
                    $userArray[$label."000".$addChild->getId()] = $addChild;
                }
            }
            $userArray[$label] = $userObject;
        }
        if(isSet($allUserIds) && count($allUserIds)){
            $connections = $logger->usersLastConnection($allUserIds);
        }
        ksort($userArray);

        foreach ($userArray as $userObject) {
            $repos = ConfService::getConfStorageImpl()->listRepositories($userObject);
            $isAdmin = $userObject->isAdmin();
            $userId = $userObject->getId();
            $icon = "user".($userId=="guest"?"_guest":($isAdmin?"_admin":""));
            $iconClass = "icon-user";
            if ($userObject->hasParent()) {
                $icon = "user_child";
                $iconClass = "icon-angle-right";
            }
            if ($isAdmin) {
                $rightsString = $messages["ajxp_conf.63"];
            } else {
                $r = array();
                foreach ($repos as $repoId => $repository) {
                    if($repository->getAccessType() == "ajxp_shared") continue;
                    if(!$userObject->canRead($repoId) && !$userObject->canWrite($repoId)) continue;
                    $rs = ($userObject->canRead($repoId) ? "r" : "");
                    $rs .= ($userObject->canWrite($repoId) ? "w" : "");
                    $r[] = $repository->getDisplay()." (".$rs.")";
                }
                $rightsString = implode(", ", $r);
            }
            $nodeLabel = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject, "core.conf", $userId);
            $scheme = UsersService::getAuthScheme($userId);
            $nodeKey = $fullBasePath. "/" .$userId;
            $roles = array_filter(array_keys($userObject->getRoles()), array($this, "filterReservedRoles"));
            $mergedRole = $userObject->mergedRole->getDataArray(true);
            if(!isSet($httpVars["format"]) || $httpVars["format"] !== "json"){
                $mergedRole = json_encode($mergedRole);
            }
            $meta = [
                "text" => $nodeLabel,
                "is_file" => true,
                "isAdmin" => $messages[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")],
                "icon" => $icon.".png",
                "icon_class" => $iconClass,
                "object_id" => $userId,
                "auth_scheme" => ($scheme != null? $scheme : ""),
                "rights_summary" => $rightsString,
                "ajxp_roles" => implode(", ", $roles),
                "ajxp_mime" => "user".(($userId!="guest"&&$userId!=$this->context->getUser()->getId())?"_editable":""),
                "json_merged_role" => $mergedRole
            ];
            if($userObject->hasParent()) {
                $meta["shared_user"] = "true";
            }
            if(isSet($connections) && isSet($connections[$userObject->getId()]) && !empty($connections[$userObject->getId()])) {
                $meta["last_connection"] = strtotime($connections[$userObject->getId()]);
                $meta["last_connection_readable"] = StatHelper::relativeDate($meta["last_connection"], $messages);
            }
            $this->appendBookmarkMeta($nodeKey, $meta);
            $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
        }
        return $nodesList;
    }

    /**
     * Do not display AJXP_GRP_/ and AJXP_USR_/ roles if not in server debug mode
     * @param $key
     * @return bool
     */
    protected function filterReservedRoles($key){
        return (strpos($key, "AJXP_GRP_/") === FALSE && strpos($key, "AJXP_USR_/") === FALSE);
    }


}