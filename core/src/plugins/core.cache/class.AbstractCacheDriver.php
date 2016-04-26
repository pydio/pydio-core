<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, mosen
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

define('AJXP_CACHE_SERVICE_NS_SHARED', 'shared');
define('AJXP_CACHE_SERVICE_NS_NODES', 'nodes');

use Doctrine\Common\Cache;

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @class AbstractCacheDriver
 * @author ghecquet
 * @abstract
 * Abstraction of the caching system
 * The cache will be implemented by the plugin which extends this class.
 */
abstract class AbstractCacheDriver extends AJXP_Plugin
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


    public function init($options)
    {
      parent::init($options);
    }

    /**
     * @param string $namespace
     * @return Cache\CacheProvider
     */
    abstract public function getCacheDriver($namespace=AJXP_CACHE_SERVICE_NS_SHARED);

    /**
     * @param AJXP_Node $node
     * @param string $cacheType
     * @param string $details
     * @return string
     */
    public static function computeIdForNode($node, $cacheType, $details = ''){
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
        return str_replace($scheme . "://", $cacheType."://".$additional, $node->getUrl()).($details?"##".$details:"");
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function supportsPatternDelete($namespace)
    {
        $cacheDriver = $this->getCacheDriver($namespace);
        return $cacheDriver instanceof Pydio\Plugins\Cache\Doctrine\Ext\PatternClearableCache;
        //return is_a($cacheDriver, "PatternClearableCache");
    }

    /**
     * @param string $namespace
     * @param string $id
     * @return bool
     */
    public function deleteKeyStartingWith($namespace, $id){
        $cacheDriver = $this->getCacheDriver($namespace);
        if(!($cacheDriver instanceof Pydio\Plugins\Cache\Doctrine\Ext\PatternClearableCache)){
            return false;
        }
        return $cacheDriver->deleteKeysStartingWith($id);
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
     * Deletes an entry from the cache
     *
     * @param $namespace
     * @param string $id The id of the cache entry to fetch.
     * @return bool TRUE if the entry was successfully deleted, FALSE otherwise.
     */
    public function delete($namespace, $id){
        $cacheDriver = $this->getCacheDriver($namespace);

        if (isset($cacheDriver) && $cacheDriver->contains($id)) {
           $result = $cacheDriver->delete($id);
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

        if (isset($cacheDriver)) {
           $result = $cacheDriver->deleteAll();
           return $result;
        }

        return false;
    }

    public function listNamespaces(){
        return [AJXP_CACHE_SERVICE_NS_SHARED, AJXP_CACHE_SERVICE_NS_NODES];
    }

    public function getStats($namespace){
        $cacheDriver = $this->getCacheDriver($namespace);
        return $cacheDriver->getStats();
    }

}