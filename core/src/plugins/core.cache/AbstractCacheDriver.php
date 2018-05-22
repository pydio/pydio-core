<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, mosen
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

defined('AJXP_EXEC') or die( 'Access not allowed');

define('AJXP_CACHE_SERVICE_NS_SHARED', 'shared');
define('AJXP_CACHE_SERVICE_NS_NODES', 'nodes');

use Doctrine\Common\Cache;
use Pydio\Access\Core\Model\AJXP_Node;


use Pydio\Cache\Doctrine\Ext\PydioApcuCache;
use Pydio\Cache\Doctrine\Ext\PydioChainCache;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Cache\Doctrine\Ext\PatternClearableCache;
use GuzzleHttp\Client;
use Pydio\Core\Services\ApplicationState;
use Pydio\Log\Core\Logger;

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @class AbstractCacheDriver
 * @author ghecquet
 * @abstract
 * Abstraction of the caching system
 * The cache will be implemented by the plugin which extends this class.
 */
abstract class AbstractCacheDriver extends Plugin
{
    /**
     * Driver type
     *
     * @var String type of driver
     */
    public $driverType = "cache";

    /**
     * @var Cache\CacheProvider[]
     */
    protected $namespacedCaches = array();

    /**
     * Keep a pointer to an http client if necessary
     * @var Client
     */
    protected $httpClient;

    /**
     * @var array
     */
    private $httpDeletion = [];
    
    /**
     * @param string $namespace
     * @return Cache\CacheProvider
     */
    abstract public function getCacheDriver($namespace=AJXP_CACHE_SERVICE_NS_SHARED);

    /**
     * Compute a cache ID for a given node. Will be in the form
     * cacheType://repositoryId/userSepcificKey|shared/nodePath[##detail]
     *
     * @param AJXP_Node $node
     * @param string $cacheType
     * @param string $details
     * @return array
     * @throws \Exception
     */
    public static function getOptionsForNode($node, $cacheType, $details = ''){
        $ctx = $node->getContext();
        $repo = $node->getRepository();
        if($repo == null) return ["id" => "failed-id"];
        $scope = $repo->securityScope();
        $userId = $node->getUserId();
        // Compute second segment
        if($userId === "*"){
            $subPath = "";
        }else if($scope === "USER"){
            $subPath = "/".$userId;
        }else if($scope == "GROUP"){
            $gPath = "/";
            $user = $ctx->getUser();
            if($user !== null) $gPath = $user->getGroupPath();
            $subPath =  "/".ltrim(str_replace("/", "__", $gPath), "__");
        }else{
            $subPath = "/shared";
        }

        $options = [
            "id"        => $cacheType."://".$repo->getId().$subPath.$node->getPath().($details?"##$details":""),
            "timelimit" => $repo->getContextOption($ctx, "CACHE_TIMELIMIT", 0)
        ];
        return $options;
    }

    /**
     * Same as computeIdForNode but maybe used if a repository has been deleted
     * @param $repositoryId
     * @param $cacheType
     * @return string
     */
    public static function computeFullRepositoryId($repositoryId, $cacheType){
        return $ID = $cacheType."://".$repositoryId."/";
    }

    /**
     * Fetches an entry from the cache.
     *
     * @param $namespace
     * @param string $id The id of the cache entry to fetch.
     * @return mixed The cached data or FALSE, if no cache entry exists for the given id.
     */
    public function fetch($namespace, $id){
        $cacheDriver = $this->getCacheDriver($namespace);

        if (isset($cacheDriver) && $cacheDriver->contains($id)) {
            $result = $cacheDriver->fetch($id);
            return $result;
        }

        return false;
    }

    /**
     * Fetches many entries from the cache.
     *
     * @param $namespace
     * @param string[] $ids The ids of the cache entry to fetch.
     * @return mixed The cached data or FALSE, if no cache entry exists for the given id.
     */
    public function fetchMultiple($namespace, $ids){
        $cacheDriver = $this->getCacheDriver($namespace);

        if (isset($cacheDriver)) {
            return $cacheDriver->fetchMultiple($ids);
        }

        return false;
    }

    /**
     * Tests if an entry exists in the cache.
     *
     * @param $namespace
     * @param string $id The cache id of the entry to check for.
     * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise.
     */
    public function contains($namespace, $id){
        $cacheDriver = $this->getCacheDriver($namespace);

        if (! isset($cacheDriver)) {
            return false;
        }

        $result = $cacheDriver->contains($id);
        return $result;
    }

    /**
     * Puts data into the cache.
     *
     * @param $namespace
     * @param string $id The cache id.
     * @param mixed $data The cache entry/data.
     * @param int $lifeTime The cache lifetime.
     *                         If != 0, sets a specific lifetime for this cache entry (0 => infinite lifeTime).
     * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    public function save($namespace, $id, $data, $lifeTime = 0){
        $cacheDriver = $this->getCacheDriver($namespace);

        if (! isset($cacheDriver)) {
          return false;
        }

        $result = $cacheDriver->save($id, $data, $lifeTime);

        return $result;
    }

    /**
     * Puts data into the cache, and store an associated timestamp of current time
     * @param $namespace
     * @param $id
     * @param $data
     * @param int $lifeTime
     * @return bool
     */
    public function saveWithTimestamp($namespace, $id, $data, $lifeTime = 0){

        $cacheDriver = $this->getCacheDriver($namespace);
        if(!isSet($cacheDriver)) return false;

        $res = $cacheDriver->save($id, $data, $lifeTime);
        if(!$res){
            return false;
        }
        $timestamp = time();
        $cacheDriver->save($this->getTimestampKey($id), $timestamp, $lifeTime);
        return $res;
    }


    /**
     * @param $namespace
     * @param $id
     * @param $idsToCheck
     * @return mixed|false
     */
    public function fetchWithTimestamps($namespace, $id, $idsToCheck){

        $cacheDriver = $this->getCacheDriver($namespace);
        if(!isSet($cacheDriver)) return false;
        if(!count($idsToCheck)){
            return $cacheDriver->fetch($id);
        }
        $idStamp = $cacheDriver->fetch($this->getTimestampKey($id));
        if(!$idStamp){
            return false;
        }
        $keys = array_map(array($this, "getTimestampKey"), $idsToCheck);
        $stamps = $cacheDriver->fetchMultiple($keys);

        foreach($stamps as $stampKey => $stampTime){
            if($stampTime > $idStamp){
                // One of the object has a more recent timestamp, needs reload
                $this->delete($namespace, $id);
                return false;
            }
        }
        return $cacheDriver->fetch($id);

    }


    /**
     * Deletes an entry from the cache
     *
     * @param $namespace
     * @param string $id The id of the cache entry to fetch.
     * @return bool TRUE if the entry was successfully deleted, FALSE otherwise.
     */
    public function delete($namespace, $id){

        $cacheDriver = $this->getCacheDriver($namespace);
        if($this->requiresHttpForwarding($cacheDriver)){
            $this->httpDeletion[$namespace.$id.'key'] = ["namespace"=>$namespace, "key" => $id];
            return true;
        }

        if (isset($cacheDriver) && $cacheDriver->contains($id)) {
            Logger::debug("CacheDriver::Http", "Clear Key ".$id, ["namespace" => $namespace]);
            $result = $cacheDriver->delete($id);
            if($cacheDriver->contains($this->getTimestampKey($id))){
                $cacheDriver->delete($this->getTimestampKey($id));
            }
            return $result;
        }

        return false;
    }

    /**
     * Deletes ALL entries from the cache
     *
     * @param $namespace
     * @return bool TRUE if the entries were successfully deleted, FALSE otherwise.
     *
     */
    public function deleteAll($namespace){

        $cacheDriver = $this->getCacheDriver($namespace);
        if($this->requiresHttpForwarding($cacheDriver)){
            $this->httpDeletion[$namespace.'all'] = ["namespace"=>$namespace, "all" => "true"];
            return true;
        }

        if (isset($cacheDriver)) {
            Logger::debug("CacheDriver::Http", "Clear All", ["namespace" => $namespace]);
            $result = $cacheDriver->deleteAll();
            return $result;
        }

        return false;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function supportsPatternDelete($namespace)
    {
        $cacheDriver = $this->getCacheDriver($namespace);
        if($cacheDriver instanceof PydioChainCache){
            return $cacheDriver->allProvidersSupportPatternDeletion();
        }else{
            return $cacheDriver instanceof PatternClearableCache;
        }
    }

    /**
     * @param string $namespace
     * @param string $id
     * @return bool
     */
    public function deleteKeyStartingWith($namespace, $id){

        /** @var PatternClearableCache $cacheDriver */
        $cacheDriver = $this->getCacheDriver($namespace);
        if($this->requiresHttpForwarding($cacheDriver)){
            $this->httpDeletion[$namespace.$id.'pattern'] = ["namespace"=>$namespace, "pattern" => $id];
            return true;
        }
        if(!$this->supportsPatternDelete($namespace)){
            return false;
        }
        Logger::debug("CacheDriver::Http", "Clear Pattern ".$id, ["namespace" => $namespace]);
        return $cacheDriver->deleteKeysStartingWith($id);
    }
    
    /**
     * @param $namespace
     * @return array|null
     */
    public function getStats($namespace){
        $cacheDriver = $this->getCacheDriver($namespace);
        if(empty($cacheDriver) || !method_exists($cacheDriver, "getStats")){
            return null;
        }
        return $cacheDriver->getStats();
    }

    /**
     * @param string $id
     * @return string
     */
    protected function getTimestampKey($id){
        return $id . "-PYDIO_CACHE_TIME";
    }

    /**
     * @param Cache\CacheProvider
     * @return bool
     */
    protected function requiresHttpForwarding($cacheDriver){
        if(empty($cacheDriver) || !ApplicationState::sapiIsCli()){
            return false;
        }
        if(($cacheDriver instanceof PydioChainCache && $cacheDriver->oneProviderRequiresHttpDeletion()) || $cacheDriver instanceof PydioApcuCache){
            return true;
        }
        return false;
    }

    /**
     * @return Client
     */
    protected function getHttpClient(){
        if(!isSet($this->httpClient)){
            $baseUrl = ApplicationState::detectServerURL(true);
            if(empty($baseUrl)){
                $this->logError("Http Deletion", "Server URL does not seem to be configured, CLI cannot trigger cache deletion commands!");
                return null;
            }
            $this->httpClient = new Client([
                'base_url' => $baseUrl
            ]);
        }
        return $this->httpClient;
    }

    public function __destruct()
    {
        if(count($this->httpDeletion)){
            $baseUrl = ApplicationState::detectServerURL(true);
            $client = $this->getHttpClient();
            if(empty($client)) return;
            $client->post($baseUrl.'/?get_action=clear_cache_key', [
                'body' => [
                    'data' => json_encode($this->httpDeletion)
                ]
            ]);

        }
    }

}
