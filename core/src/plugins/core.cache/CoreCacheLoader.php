<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Cache\Core;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\Repository;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\CoreInstanceProvider;
use Pydio\Core\Services\CacheService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\Log\Core\Logger;
use Zend\Diactoros\Response\JsonResponse;

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
                    $pluginsService = PluginsService::getInstance(Context::emptyContext());
                }
                $pluginInstance = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["UNIQUE_INSTANCE_CONFIG"], "Pydio\\Cache\\Core\\AbstractCacheDriver", $pluginsService);
            }
            self::$cacheInstance = $pluginInstance;
            if(!ApplicationState::sapiIsCli() && !$this->pluginConf["CORE_CACHE_DISABLE_NODES"]
                && $pluginInstance !== null && $pluginInstance instanceof AbstractCacheDriver && $pluginInstance->supportsPatternDelete(AJXP_CACHE_SERVICE_NS_NODES)){
                MetaStreamWrapper::appendMetaWrapper("pydio.cache", "\\Pydio\\Cache\\Core\\CacheStreamLayer");
            }
        }

        return self::$cacheInstance;
    }

    /**
     * Check if nodes caching is enabled
     * @param $node AJXP_Node
     * @return bool
     */
    public function enableNodesCaching($node = null){
        if($this->getConfigs()["CORE_CACHE_DISABLE_NODES"]) {
            return false;
        }
        if($node !== null && $node->getRepositoryId() === 'inbox'){
            return false;
        }
        $cDriver = ConfService::getCacheDriverImpl();
        return !(empty($cDriver) || !($cDriver->supportsPatternDelete(AJXP_CACHE_SERVICE_NS_NODES)));
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param \Pydio\Access\Core\Model\AJXP_Node $contextNode
     * @param bool $details
     */
    public function cacheNodeInfo(&$node, $contextNode, $details){
        if(!$this->enableNodesCaching($node)) return;
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
        if(!$this->enableNodesCaching($node)) return;
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
        if(!$this->enableNodesCaching(!empty($from) ? $from : $to)) return;
        if($from !== null){
            $this->clearCacheForNode($from);
        }
        if($to !== null && ($from === null || $from->getUrl() !== $to->getUrl())){
            $this->clearCacheForNode($to);
        }
    }

    /**
     * Hooked to workspace.after_update event
     * @param ContextInterface $ctx
     * @param Repository $repositoryObject
     */
    public function onWorkspaceUpdate(ContextInterface $ctx, $repositoryObject){
        $this->clearWorkspaceCache($repositoryObject->getId());
    }

    /**
     * Hooked to workspace.after_delete event
     * @param ContextInterface $ctx
     * @param string $repositoryId
     */
    public function onWorkspaceDelete(ContextInterface $ctx, $repositoryId){
        $this->clearWorkspaceCache($repositoryId);
    }

    /**
     * Util to clear all caches (node.info, stat, list) for a workspace (any users).
     * @param $repositoryId
     */
    private function clearWorkspaceCache($repositoryId){
        $cDriver = ConfService::getCacheDriverImpl();
        if(empty($cDriver) || !($cDriver->supportsPatternDelete(AJXP_CACHE_SERVICE_NS_NODES))){
            return;
        }
        $cDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::computeFullRepositoryId($repositoryId, "node.info"));
        $cDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::computeFullRepositoryId($repositoryId, "stat"));
        $cDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::computeFullRepositoryId($repositoryId, "list"));
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     */
    protected function clearCacheForNode($node){
        $cacheDriver = ConfService::getCacheDriverImpl();
        if($cacheDriver === null) {
            return;
        }
        // Clear meta for this node
        $cacheDriver->delete(AJXP_CACHE_SERVICE_NS_NODES, $this->computeId($node, true));
        $cacheDriver->delete(AJXP_CACHE_SERVICE_NS_NODES, $this->computeId($node, false));
        
        if($node->isLeaf()){
            // Clear stat
            $cacheDriver->delete(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::getOptionsForNode($node, "stat")["id"]);
            // Clear parent listing
            if($node->getParent() !== null){
                $cacheDriver->delete(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::getOptionsForNode($node->getParent(), "list")["id"]);
            }
        }else {
            // Delete node data and all its children
            $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::getOptionsForNode($node, "stat")["id"]);
            $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::getOptionsForNode($node, "node.info")["id"]);
            if($node->getParent() !== null){
                $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::getOptionsForNode($node->getParent(), "list")["id"]);
            }else{
                $cacheDriver->deleteKeyStartingWith(AJXP_CACHE_SERVICE_NS_NODES, AbstractCacheDriver::getOptionsForNode($node, "list")["id"]);
            }
        }
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param bool $details
     * @return string
     */
    protected function computeId($node, $details){
        return AbstractCacheDriver::getOptionsForNode($node, "node.info", $details?"all":"short")["id"];
    }

    /**
     * @return array
     */
    public function listNamespaces(){
        if($this->enableNodesCaching()){
            return [AJXP_CACHE_SERVICE_NS_SHARED, AJXP_CACHE_SERVICE_NS_NODES];
        }else{
            return [AJXP_CACHE_SERVICE_NS_SHARED];
        }
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function exposeCacheStats(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){

        $cImpl = $this->getImplementation();
        $result = [];
        if($cImpl != null){
            $nspaces = $this->listNamespaces();
            foreach ($nspaces as $nspace){
                $data = $cImpl->getStats($nspace);
                $data["namespace"] = $nspace;
                $result[] = $data;
            }
        }
        $responseInterface = new JsonResponse($result);

    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function clearCacheByNS(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){

        $ns = InputFilter::sanitize($requestInterface->getParsedBody()["namespace"], InputFilter::SANITIZE_ALPHANUM);
        if($ns == AJXP_CACHE_SERVICE_NS_SHARED){
            ConfService::clearAllCaches();
        }else{
            CacheService::deleteAll($ns);
        }
        $responseInterface = new JsonResponse(["result"=>"ok"]);

    }

    /**
     * Service to clear a cache key or a pattern
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function clearCacheKey(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){
        $cacheDriver = ConfService::getCacheDriverImpl();
        if($cacheDriver === null) {
            $responseInterface = new JsonResponse(["result" => "NOCACHEFOUND"]);
            return;
        }
        $params = $requestInterface->getParsedBody();
        $data = json_decode($params["data"], true);
        foreach($data as $key => $params){
            $ns = $params['namespace'];
            if(isSet($params["key"])){
                Logger::info("CoreCacheLoader", "Clear Key ".$params["key"], $params);
                $cacheDriver->delete($ns, $params["key"]);
            }else if(isSet($params["pattern"])){
                Logger::info("CoreCacheLoader", "Clear Pattern ".$params["pattern"], $params);
                $cacheDriver->deleteKeyStartingWith($ns, $params["pattern"]);
            }else if(isSet($params["all"])) {
                Logger::info("CoreCacheLoader", "Clear All ", $params);
                $cacheDriver->deleteAll($ns);
            }
        }
        $responseInterface = new JsonResponse(["result" => "SUCCESS"]);
    }

}
