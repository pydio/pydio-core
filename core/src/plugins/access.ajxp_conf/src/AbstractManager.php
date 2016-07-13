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

use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\OptionsHelper;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Parent Class for CRUD operation of application objects.
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
abstract class AbstractManager
{
    /** @var  ContextInterface */
    protected $context;

    /** @var  array */
    protected $bookmarks;

    /** @var  string */
    protected $pluginName;

    /** @var bool */
    protected $listSpecialRoles = AJXP_SERVER_DEBUG;

    /**
     * Manager constructor.
     * @param ContextInterface $ctx
     * @param string $pluginName
     */
    public function __construct(ContextInterface $ctx, $pluginName){
        $this->context = $ctx;
        $this->pluginName = $pluginName;
    }

    /**
     * @return bool
     */
    protected function currentUserIsGroupAdmin(){
        if(ConfService::getAuthDriverImpl()->isAjxpAdmin($this->context->getUser()->getId())){
            return false;
        }
        return (UsersService::usersEnabled() && $this->context->getUser()->getGroupPath() !== "/");
    }

    /**
     * @return array
     */
    protected function getBookmarks(){
        if(!isSet($this->bookmarks)){
            $this->bookmarks = [];
            if(UsersService::usersEnabled()) {
                $bookmarks = $this->context->getUser()->getBookmarks($this->context->getRepositoryId());
                foreach ($bookmarks as $bm) {
                    $this->bookmarks[] = $bm["PATH"];
                }
            }
        }
        return $this->bookmarks;
    }

    /**
     * @param string $nodePath
     * @param array $meta
     */
    protected function appendBookmarkMeta($nodePath, &$meta){
        if(in_array($nodePath, $this->getBookmarks())) {
            $meta = array_merge($meta, array(
                "ajxp_bookmarked" => "true",
                "overlay_icon" => "bookmark.png"
            ));
        }
    }


    /**
     * @param ContextInterface $ctx
     * @param $repDef
     * @param $options
     * @param bool $globalBinaries
     * @param array $existingValues
     */
    protected function parseParameters(ContextInterface $ctx, &$repDef, &$options, $globalBinaries = false, $existingValues = array())
    {
        OptionsHelper::parseStandardFormParameters($ctx, $repDef, $options, "DRIVER_OPTION_", ($globalBinaries ? array() : null));
        if(!count($existingValues)){
            return;
        }
        $this->mergeExistingParameters($options, $existingValues);
    }

    /**
     * @param array $parsed
     * @param array $existing
     */
    protected function mergeExistingParameters(&$parsed, $existing){
        foreach($parsed as $k => &$v){
            if($v === "__AJXP_VALUE_SET__" && isSet($existing[$k])){
                $parsed[$k] = $existing[$k];
            }else if(is_array($v) && is_array($existing[$k])){
                $this->mergeExistingParameters($v, $existing[$k]);
            }
        }
    }


    /**
     * @param ContextInterface $ctx
     * @param $currentUserIsGroupAdmin
     * @param bool $withLabel
     * @return array
     */
    protected function getEditableParameters($ctx, $currentUserIsGroupAdmin, $withLabel = false){

        $query = "//param|//global_param";
        if($currentUserIsGroupAdmin){
            $query = "//param[@scope]|//global_param[@scope]";
        }

        $nodes = PluginsService::getInstance($ctx)->searchAllManifests($query, "node", false, true, true);
        $actions = array();
        foreach ($nodes as $node) {
            if($node->parentNode->nodeName != "server_settings") continue;
            $parentPlugin = $node->parentNode->parentNode;
            $pId = $parentPlugin->attributes->getNamedItem("id")->nodeValue;
            if (empty($pId)) {
                $pId = $parentPlugin->nodeName .".";
                if($pId == "ajxpdriver.") $pId = "access.";
                $pId .= $parentPlugin->attributes->getNamedItem("name")->nodeValue;
            }
            if(!is_array($actions[$pId])) $actions[$pId] = array();
            $actionName = $node->attributes->getNamedItem("name")->nodeValue;
            $attributes = array();
            for( $i = 0; $i < $node->attributes->length; $i ++){
                $att = $node->attributes->item($i);
                $value = $att->nodeValue;
                if(in_array($att->nodeName, array("choices", "description", "group", "label"))) {
                    $value = XMLWriter::replaceAjxpXmlKeywords($value);
                }
                $attributes[$att->nodeName] = $value;
            }
            if($withLabel){
                $actions[$pId][$actionName] = array(
                    "parameter" => $actionName ,
                    "label" => $attributes["label"],
                    "attributes" => $attributes
                );
            }else{
                $actions[$pId][] = $actionName;
            }

        }
        foreach ($actions as $actPid => $actionGroup) {
            ksort($actionGroup, SORT_STRING);
            $actions[$actPid] = array();
            foreach ($actionGroup as $v) {
                $actions[$actPid][] = $v;
            }
        }
        return $actions;
    }



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
    public abstract function listNodes($httpVars, $rootPath, $relativePath, $paginationHash = null, $findNodePosition=null, $aliasedDir=null);

}