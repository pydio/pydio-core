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
namespace Pydio\Cache\Core;
use Pydio\Access\Core\AJXP_MetaStreamWrapper;
use Pydio\Access\Core\Model\Repository;
use Pydio\Core\PluginFramework\CoreInstanceProvider;
use Pydio\Core\Services\CacheService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Access\Core\Model\AJXP_Node;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @static
 * Provides access to the cache via the Doctrine interface
 */
class CoreCacheLoader extends Plugin implements CoreInstanceProvider
{
    /**
     * @var AbstractCacheDriver
     */
    protected static $cacheInstance;

    /**
     * @param PluginsService|null $pluginsService
     * @return null|AbstractCacheDriver|Plugin
     */
    public function getImplementation($pluginsService = null)
    {

        $pluginInstance = null;

        if (!isSet(self::$cacheInstance) || (isset($this->pluginConf["UNIQUE_INSTANCE_CONFIG"]["instance_name"]) && self::$cacheInstance->getId() != $this->pluginConf["UNIQUE_INSTANCE_CONFIG"]["instance_name"])) {
            if (isset($this->pluginConf["UNIQUE_INSTANCE_CONFIG"])) {
                if($pluginsService === null){
                    $pluginsService = PluginsService::getInstance();
                }
                $pluginInstance = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["UNIQUE_INSTANCE_CONFIG"], "Pydio\\Cache\\Core\\AbstractCacheDriver", $pluginsService);
            }
            self::$cacheInstance = $pluginInstance;
            if($pluginInstance !== null && $pluginInstance instanceof AbstractCacheDriver && $pluginInstance->supportsPatternDelete(AJXP_CACHE_SERVICE_NS_NODES)){
                AJXP_MetaStreamWrapper::appendMetaWrapper("pydio.cache", "\\Pydio\\Cache\\Core\\CacheStreamLayer");
            }
        }

        return self::$cacheInstance;
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param \Pydio\Access\Core\Model\AJXP_Node $contextNode
     * @param bool $details
     */
    public function cacheNodeInfo(&$node, $contextNode, $details){
        $cDriver = ConfService::getCacheDriverImpl();
        if(empty($cDriver) || !($cDriver->supportsPatternDelete(AJXP_CACHE_SERVICE_NS_NODES))){
            return;
        }
        $id = $this->computeId($node, $details);
        CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $id, $node->getNodeInfoMeta());
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param AJXP_Node $contextNode
     * @param bool $details
     * @param bool $forceRefresh
     */
    public function loadNodeInfoFromCache(&$node, $contextNode, $details, $forceRefresh = false){
        if($forceRefresh) {
            $this->clearNodeInfoCache($node);
            return;
        }
        $cDriver = ConfService::getCacheDriverImpl();
        if(empty($cDriver) || !($cDriver->supportsPatternDelete(AJXP_CACHE_SERVICE_NS_NODES))){
            return;
        }
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
     * @param \Pydio\Access\Core\Model\AJXP_Node|null $from
     * @param \Pydio\Access\Core\Model\AJXP_Node|null $to
     * @param bool $copy
     */
    public function clearNodeInfoCache($from=null, $to=null, $copy = false){
        $cDriver = ConfService::getCacheDriverImpl();
        if(empty($cDriver) || !($cDriver->supportsPatternDelete(AJXP_CACHE_SERVICE_NS_NODES))){
            return;
        }
        if($from != null){
            $this->clearCacheForNode($from);
        }
        if($to != null){
            $this->clearCacheForNode($to);
        }
    }

    /**
     * @param Repository $repositoryObject
     */
    public function clearWorkspaceNodeInfos($repositoryObject){
        $cDriver = ConfService::getCacheDriverImpl();
        if(empty($cDriver) || !($cDriver->supportsPatternDelete(AJXP_CACHE_SERVICE_NS_NODES))){
            return;
        }
        $node = new AJXP_Node("pydio://".$repositoryObject->getId()."/");
        $node->setLeaf(false);
        $this->clearCacheForNode($node);
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     */
    protected function clearCacheForNode($node){
        if($node->isLeaf()){
            // Clear meta
            CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $this->computeId($node, true));
            CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $this->computeId($node, false));
            // Clear stat
            CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::computeIdForNode($node, "stat"));
            // Clear parent listing
            if($node->getParent() !== null){
                CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::computeIdForNode($node->getParent(), "list"));
            }
        }else {
            $cacheDriver = ConfService::getCacheDriverImpl();
            $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, $this->computeId($node, true));
            $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, $this->computeId($node, false));
            $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::computeIdForNode($node, "stat"));
            if($node->getParent() !== null){
                $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::computeIdForNode($node->getParent(), "list"));
            }else{
                $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::computeIdForNode($node, "list"));
            }
        }
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param bool $details
     * @return string
     */
    protected function computeId($node, $details){
        return AbstractCacheDriver::computeIdForNode($node, "node.info", $details?"all":"short");
    }

    public function exposeCacheStats($actionName, $httpVars, $fileVars){

        $cImpl = $this->getImplementation();
        $result = [];
        if($cImpl != null){
            $nspaces = $cImpl->listNamespaces();
            foreach ($nspaces as $nspace){
                $data = $cImpl->getStats($nspace);
                $data["namespace"] = $nspace;
                $result[] = $data;
            }
        }
        \Pydio\Core\Controller\HTMLWriter::charsetHeader("application/json");
        echo json_encode($result);

    }

    public function clearCacheByNS($actionName, $httpVars, $fileVars){

        $ns = \Pydio\Core\Utils\Utils::sanitize($httpVars["namespace"], AJXP_SANITIZE_ALPHANUM);
        if($ns == AJXP_CACHE_SERVICE_NS_SHARED){
            ConfService::clearAllCaches();
        }else{
            CacheService::deleteAll($ns);
        }
        HTMLWriter::charsetHeader("text/json");
        echo json_encode(["result"=>"ok"]);

    }


}
