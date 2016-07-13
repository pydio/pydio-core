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
use Pydio\Core\Model\Context;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Utils\Vars\InputFilter;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class RepositoriesManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class RepositoriesManager extends AbstractManager
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
        $fullBasePath       = "/" . $rootPath . "/" . $relativePath;
        $REPOS_PER_PAGE     = 50;
        $paginationHash     = $paginationHash === null ? 1 : $paginationHash;
        $offset             = ($paginationHash - 1) * $REPOS_PER_PAGE;
        $count              = null;
        $ctxUser            = $this->context->getUser();
        $nodesList          = new NodesList($fullBasePath);

        // Load all repositories = normal, templates, and templates children
        $criteria = array(
            "ORDERBY"       => array("KEY" => "display", "DIR"=>"ASC"),
            "CURSOR"        => array("OFFSET" => $offset, "LIMIT" => $REPOS_PER_PAGE)
        );
        if($this->currentUserIsGroupAdmin()){
            $criteria = array_merge($criteria, array(
                "owner_user_id" => AJXP_FILTER_EMPTY,
                "groupPath"     => "regexp:/^".str_replace("/", "\/", $ctxUser->getGroupPath()).'/',
            ));
        }else{
            $criteria["parent_uuid"] = AJXP_FILTER_EMPTY;
        }
        if(isSet($httpVars) && is_array($httpVars) && isSet($httpVars["template_children_id"])){
            $criteria["parent_uuid"] = InputFilter::sanitize($httpVars["template_children_id"], InputFilter::SANITIZE_ALPHANUM);
        }

        $repos = RepositoryService::listRepositoriesWithCriteria($criteria, $count);
        $nodesList->initColumnsData("filelist", "list", "ajxp_conf.repositories");
        $nodesList->setPaginationData($count, $paginationHash, ceil($count / $REPOS_PER_PAGE));
        $nodesList->appendColumn("ajxp_conf.8", "ajxp_label");
        $nodesList->appendColumn("ajxp_conf.9", "accessType");
        $nodesList->appendColumn("ajxp_conf.125", "slug");

        $driverLabels = array();

        foreach ($repos as $repoIndex => $repoObject) {

            if($repoObject->getAccessType() == "ajxp_conf" || $repoObject->getAccessType() == "ajxp_shared") continue;
            if (!empty($ctxUser) && !$ctxUser->canAdministrate($repoObject))continue;
            if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;

            $icon           = "hdd_external_unmount.png";
            $accessType     = $repoObject->getAccessType();
            $accessLabel    = $this->getDriverLabel($accessType, $driverLabels);
            $label          = $repoObject->getDisplay();
            $editable       = $repoObject->isWriteable();
            if ($repoObject->isTemplate) {
                $icon = "hdd_external_mount.png";
                if ($ctxUser != null && $ctxUser->getGroupPath() != "/") {
                    $editable = false;
                }
            }

            $meta = [
                "text"          => $label,
                "repository_id" => $repoIndex,
                "accessType"	=> ($repoObject->isTemplate?"Template for ":"").$repoObject->getAccessType(),
                "accessLabel"	=> $accessLabel,
                "icon"			=> $icon,
                "owner"			=> ($repoObject->hasOwner()?$repoObject->getOwner():""),
                "openicon"		=> $icon,
                "slug"          => $repoObject->getSlug(),
                "parentname"	=> "/repositories",
                "ajxp_mime" 	=> "repository".($editable?"_editable":""),
                "is_template"   => ($repoObject->isTemplate?"true":"false")
            ];

            $nodeKey = "/data/repositories/$repoIndex";
            $this->appendBookmarkMeta($nodeKey, $meta);
            $repoNode = new AJXP_Node($nodeKey, $meta);
            $nodesList->addBranch($repoNode);

            if ($repoObject->isTemplate) {
                // Now Load children for template repositories
                $children = RepositoryService::listRepositoriesWithCriteria(array("parent_uuid" => $repoIndex . ""), $count);
                foreach($children as $childId => $childObject){
                    if (!empty($ctxUser) && !$ctxUser->canAdministrate($childObject))continue;
                    if(is_numeric($childId)) $childId = "".$childId;
                    $meta = array(
                        "text"          => $childObject->getDisplay(),
                        "repository_id" => $childId,
                        "accessType"	=> $childObject->getAccessType(),
                        "accessLabel"	=> $this->getDriverLabel($childObject->getAccessType(), $driverLabels),
                        "icon"			=> "repo_child.png",
                        "slug"          => $childObject->getSlug(),
                        "owner"			=> ($childObject->hasOwner()?$childObject->getOwner():""),
                        "openicon"		=> "repo_child.png",
                        "parentname"	=> "/repositories",
                        "ajxp_mime" 	=> "repository_editable",
                        "template_name" => $label
                    );
                    $cNodeKey = "/data/repositories/$childId";
                    $this->appendBookmarkMeta($cNodeKey, $meta);
                    $repoNode = new AJXP_Node($cNodeKey, $meta);
                    $nodesList->addBranch($repoNode);
                }
            }
        }

        return $nodesList;
    }

    /**
     * Get label for an access.* plugin
     * @param $pluginId
     * @param $labels
     * @return mixed|string
     */
    protected function getDriverLabel($pluginId, &$labels){
        if(isSet($labels[$pluginId])){
            return $labels[$pluginId];
        }
        $plugin = PluginsService::getInstance(Context::emptyContext())->getPluginById("access.".$pluginId);
        if(!is_object($plugin)) {
            $label = "access.$plugin (plugin disabled!)";
        }else{
            $label = $plugin->getManifestLabel();
        }
        $labels[$pluginId] = $label;
        return $label;
    }

}