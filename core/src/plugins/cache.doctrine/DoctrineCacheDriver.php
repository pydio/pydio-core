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
namespace Pydio\Cache\Implementation;

defined('AJXP_EXEC') or die( 'Access not allowed');

require __DIR__ . "/vendor/autoload.php";

define('APC_EXTENSION_LOADED', extension_loaded('apc') || extension_loaded('apcu'));
define('MEMCACHE_EXTENSION_LOADED', extension_loaded('memcache'));
define('MEMCACHED_EXTENSION_LOADED', extension_loaded('memcached'));
define('REDIS_EXTENSION_LOADED', extension_loaded('redis'));
define('XCACHE_EXTENSION_LOADED', extension_loaded('xcache'));

use \Doctrine\Common\Cache;
use Pydio\Cache\Core\AbstractCacheDriver;
use Pydio\Cache\Doctrine\Ext\PydioChainCache;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Log\Core\Logger;
use Pydio\Cache\Doctrine\Ext;

/**
 * Standard Memcache driver
 * @package AjaXplorer_Plugins
 * @subpackage Log
 */
class doctrineCacheDriver extends AbstractCacheDriver
{

    /**
     * @param string $namespace
     * @return Cache\CacheProvider
     */
    public function getCacheDriver($namespace = AJXP_CACHE_SERVICE_NS_SHARED){

        if(!isSet($this->namespacedCaches[$namespace])){
            $this->namespacedCaches[$namespace] = $this->initCacheWithNamespace($namespace);
        }
        return $this->namespacedCaches[$namespace];

    }

    /**
     * Initialise the cache driver based on config
     * @param ContextInterface $ctx
     * @param array $options array of options specific to the cache driver.
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);

        $driverOptions = $this->getContextualOption($ctx, "DRIVER");
        if(!is_array($driverOptions) || !isset($driverOptions['driver'])){
            return;
        }

        switch ($driverOptions['driver']) {
            case "apc":
                if (!APC_EXTENSION_LOADED) {
                    Logger::error(__CLASS__, "init", "The APC extension package must be installed!");
                    return;
                }
                break;
            case "memcache":
                if (!MEMCACHE_EXTENSION_LOADED) {
                    Logger::error(__CLASS__, "init", "The Memcache extension package must be installed!");
                    return;
                }
                break;
            case "memcached":
                if (!MEMCACHED_EXTENSION_LOADED) {
                    Logger::error(__CLASS__, "init", "The Memcached extension package must be installed!");
                    return;
                }
                break;
            case "redis":
                if (!REDIS_EXTENSION_LOADED) {
                    Logger::error(__CLASS__, "init", "The Redis extension package must be installed!");
                    return;
                }
                break;
            case "xcache":
                if (!XCACHE_EXTENSION_LOADED) {
                    Logger::error(__CLASS__, "init", "The XCache extension package must be installed!");
                    return;
                }
                break;
            default:
                break;
        }
    }

    /**
     * @param $namespace
     * @return Cache\ApcCache|Cache\MemcacheCache|Cache\MemcachedCache|Cache\XcacheCache|null|Ext\PydioApcuCache|Ext\PydioRedisCache
     */
    private function initCacheWithNamespace($namespace){
        $cacheDriver    = null;
        $emptyContext   = Context::emptyContext();
        $driverOptions  = $this->getContextualOption($emptyContext, "DRIVER");
        $cachePrefix    = $this->getContextualOption($emptyContext, "CACHE_PREFIX");

        if(!is_array($driverOptions) || (!isset($driverOptions["driver"]) && !isSet($driverOptions["group_switch_value"]))){
            return null;
        }else{
            if(!isSet($driverOptions["driver"]) && isSet($driverOptions["group_switch_value"])){
                $driverOptions["driver"] = $driverOptions["group_switch_value"];
            }
        }

        switch ($driverOptions['driver']) {
            case "apc":
                $cacheDriver = $this->_apc_init($driverOptions);
                break;
            case "memcache":
                $cacheDriver = $this->_memcache_init($driverOptions);
                break;
            case "memcached":
                $cacheDriver = $this->_memcached_init($driverOptions);
                break;
            case "redis":
                $cacheDriver = $this->_redis_init($driverOptions);
                break;
            case "xcache":
                $cacheDriver = $this->_xcache_init($driverOptions);
                break;
            default:
                break;
        }

        if(empty($cacheDriver)){
            return null;
        }

        if(empty($cachePrefix)){
            $cachePrefix = StringHelper::slugify(ApplicationState::detectServerURL(true));
        }

        $cachePrefix .= "_".$namespace."_";

        // Using chained array for nodes
        if ($namespace == AJXP_CACHE_SERVICE_NS_NODES) {
            $arrayCache = new Ext\PydioArrayCache();

            $cacheDriver = new PydioChainCache([
                $arrayCache,
                $cacheDriver
            ]);
        }

        $cacheDriver->setNamespace($cachePrefix);
        return $cacheDriver;

    }

    /**
     * @param $options
     * @return Cache\ApcCache|Ext\PydioApcuCache
     */
    public function _apc_init($options) {
        if (extension_loaded('apcu')) {
            $cacheDriver = new Ext\PydioApcuCache();
        } else {
            $cacheDriver = new Cache\ApcCache();
        }
        return $cacheDriver;
    }

    /**
     * @param $options
     * @return Cache\MemcacheCache|null
     */
    public function _memcache_init($options) {
        $memcache = new \Memcache();
        @$running = $memcache->connect($options['MEMCACHE_HOSTNAME'], $options['MEMCACHE_PORT']);

        if (! $running) return null;

        $cacheDriver = new Cache\MemcacheCache();
        $cacheDriver->setMemcache($memcache);
        return $cacheDriver;
    }

    /**
     * @param $options
     * @return Cache\MemcachedCache|null
     */
    public function _memcached_init($options) {
        $memcached = new \Memcached();
        @$running = $memcached->addServer($options['MEMCACHED_HOSTNAME'], $options['MEMCACHED_PORT']);

        if (! $running) return null;

        $cacheDriver = new Cache\MemcachedCache();
        $cacheDriver->setMemcached($memcached);
        return $cacheDriver;
    }

    /**
     * @param $options
     * @return null|Ext\PydioRedisCache
     */
    public function _redis_init($options) {
        $redis = new \Redis();
        @$running = $redis->connect($options['REDIS_HOSTNAME'], $options['REDIS_PORT']);

        if (! $running) return null;

        $cacheDriver = new Ext\PydioRedisCache();
        $cacheDriver->setRedis($redis);
        return $cacheDriver;
    }

    /**
     * @param $options
     * @return Cache\XcacheCache
     */
    public function _xcache_init($options) {
        $cacheDriver = new Cache\XcacheCache();
        return $cacheDriver;
    }

}
