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
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class RolesManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class RolesManager extends AbstractManager
{

    protected $listSpecialRoles = AJXP_SERVER_DEBUG;

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
        $nodesList = new NodesList("/$rootPath/$relativePath");
        $nodesList->initColumnsData("filelist", "list", "ajxp_conf.roles");
        $nodesList->appendColumn("ajxp_conf.76", "ajxp_label");
        $nodesList->appendColumn("ajxp_conf.114", "is_default");
        $nodesList->appendColumn("ajxp_conf.62", "rights_summary");

        if(!UsersService::usersEnabled()) {
            return $nodesList;
        }

        $mess       = LocaleService::getMessages();
        $ctxUser    = $this->context->getUser();
        $roles      = RolesService::getRolesList(array(), !$this->listSpecialRoles);
        ksort($roles);

        if(!$this->listSpecialRoles && $this->pluginName != "ajxp_admin" && !$this->currentUserIsGroupAdmin()){
            $rootGroupRole = RolesService::getOrCreateRole("AJXP_GRP_/", empty($ctxUser) ? "/" : $ctxUser->getGroupPath());
            if($rootGroupRole->getLabel() == "AJXP_GRP_/"){
                $rootGroupRole->setLabel($mess["ajxp_conf.151"]);
                RolesService::updateRole($rootGroupRole);
            }
            array_unshift($roles, $rootGroupRole);
        }

        foreach ($roles as $roleObject) {

            $r = array();
            if(!empty($ctxUser) && !$ctxUser->canAdministrate($roleObject)) {
                continue;
            }
            $count = 0;
            $repos = RepositoryService::listRepositoriesWithCriteria(array("role" => $roleObject), $count);
            foreach ($repos as $repoId => $repository) {
                if($repository->getAccessType() == "ajxp_shared") continue;
                if(!$roleObject->canRead($repoId) && !$roleObject->canWrite($repoId)) continue;
                $rs = ($roleObject->canRead($repoId) ? "r" : "");
                $rs .= ($roleObject->canWrite($repoId) ? "w" : "");
                $r[] = $repository->getDisplay()." (".$rs.")";
            }
            $rightsString = implode(", ", $r);
            $nodeKey = "/".$rootPath."/".$relativePath."/".$roleObject->getId();
            $appliesToDefault = implode(",", $roleObject->listAutoApplies());
            if($roleObject->getId() == "AJXP_GRP_/"){
                $appliesToDefault = $mess["ajxp_conf.153"];
            }
            $meta = array(
                "icon"              => "user-acl.png",
                "rights_summary"    => $rightsString,
                "is_default"        => $appliesToDefault,
                "ajxp_mime"         => "role",
                "role_id"           => $roleObject->getId(),
                "text"              => $roleObject->getLabel()
            );
            $this->appendBookmarkMeta($nodeKey, $meta);
            $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
        }
        return $nodesList;

    }
}