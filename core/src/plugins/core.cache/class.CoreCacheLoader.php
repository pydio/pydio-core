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
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @static
 * Provides access to the cache via the Doctrine interface
 */
class CoreCacheLoader extends AJXP_Plugin
{
    /**
     * @var AbstractCacheDriver
     */
    protected static $cacheInstance;

    public function getCacheImpl()
    {

        $pluginInstance = null;

        if (!isSet(self::$cacheInstance) || (isset($this->pluginConf["UNIQUE_INSTANCE_CONFIG"]["instance_name"]) && self::$cacheInstance->getId() != $this->pluginConf["UNIQUE_INSTANCE_CONFIG"]["instance_name"])) {
            if (isset($this->pluginConf["UNIQUE_INSTANCE_CONFIG"])) {
                $pluginInstance = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["UNIQUE_INSTANCE_CONFIG"], "AbstractCacheDriver");

                if ($pluginInstance != false) {
                    AJXP_PluginsService::getInstance()->setPluginUniqueActiveForType("cache", $pluginInstance->getName(), $pluginInstance);
                }
            }
            self::$cacheInstance = $pluginInstance;
            if($pluginInstance !== null){
                AJXP_MetaStreamWrapper::appendMetaWrapper("pydio.cache", "CacheStreamLayer");
            }
        }

        return self::$cacheInstance;
    }
    /**
     * @param AJXP_Node $node
     * @param AJXP_Node $contextNode
     * @param bool $details
     */
    public function cacheNodeInfo(&$node, $contextNode, $details){
        $id = $this->computeId($node, $details);
        CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $id, $node->getNodeInfoMeta());
    }

    /**
     * @param AJXP_Node $node
     * @param AJXP_Node $contextNode
     * @param bool $details
     */
    public function loadNodeInfoFromCache(&$node, $contextNode, $details){
        $id = $this->computeId($node, $details);
        if(CacheService::contains(AJXP_CACHE_SERVICE_NS_NODES, $id)){
            $metadata = CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $id);
            if(is_array($metadata)){
                $node->mergeMetadata($metadata);
                $node->setInfoLoaded($details);
            }
        }
    }

    /**
     * @param AJXP_Node|null $from
     * @param AJXP_Node|null $to
     * @param bool $copy
     */
    public function clearNodeInfoCache($from=null, $to=null, $copy = false){
        if($from != null){
            $this->clearCacheForNode($from);
        }
        if($to != null){
            $this->clearCacheForNode($to);
        }
    }

    /**
     * @param AJXP_Node $node
     */
    protected function clearCacheForNode($node){
        // Clear meta
        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $this->computeId($node, true));
        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $this->computeId($node, false));
        // Clear stat
        CacheStreamLayer::clearStatCache($node->getUrl());
        // Clear parent listing
        if($node->getParent() !== null){
            CacheStreamLayer::clearDirCache($node->getParent()->getUrl());
        }
    }

    /**
     * @param AJXP_Node $node
     * @param bool $details
     * @return string
     */
    protected function computeId($node, $details){
        $repo = $node->getRepository();
        if($repo == null) return "failed-id";
        $scope = $repo->securityScope();
        $additional = "";
        if($scope === "USER"){
            $additional = AuthService::getLoggedUser()->getId()."@";
        }else if($scope == "GROUP"){
            $additional =  ltrim(str_replace("/", "__", AuthService::getLoggedUser()->getGroupPath()), "__")."@";
        }
        $scheme = parse_url($node->getUrl(), PHP_URL_SCHEME);
        return str_replace($scheme . "://", "node.info://".$additional, $node->getUrl())."##".($details?"all":"short");

    }

    public function exposeCacheStats($actionName, $httpVars, $fileVars){

        $cImpl = $this->getCacheImpl();
        $result = [];
        if($cImpl != null){
            $nspaces = $cImpl->listNamespaces();
            foreach ($nspaces as $nspace){
                $data = $cImpl->getStats($nspace);
                $data["namespace"] = $nspace;
                $result[] = $data;
            }
        }
        HTMLWriter::charsetHeader("application/json");
        echo json_encode($result);

    }

    public function clearCacheByNS($actionName, $httpVars, $fileVars){

        $ns = AJXP_Utils::sanitize($httpVars["namespace"], AJXP_SANITIZE_ALPHANUM);
        if($ns == AJXP_CACHE_SERVICE_NS_SHARED){
            ConfService::clearAllCaches();
        }else{
            CacheService::deleteAll($ns);
        }
        HTMLWriter::charsetHeader("text/json");
        echo json_encode(["result"=>"ok"]);

    }


}
