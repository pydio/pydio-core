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

define('APC_EXTENSION_LOADED', extension_loaded('apc'));
define('MEMCACHE_EXTENSION_LOADED', extension_loaded('memcache'));
define('MEMCACHED_EXTENSION_LOADED', extension_loaded('memcached'));
define('REDIS_EXTENSION_LOADED', extension_loaded('redis'));
define('XCACHE_EXTENSION_LOADED', extension_loaded('xcache'));

use \Doctrine\Common\Cache;

/**
 * Standard Memcache driver
 * @package AjaXplorer_Plugins
 * @subpackage Log
 */
class doctrineCacheDriver extends AbstractCacheDriver
{

    private $cacheDriver;

    /**
     * Close file handle on objects destructor.
     */
    public function __destruct()
    {
    }

    /**
     * If the plugin is cloned, make sure to renew the $fileHandle
     */
    public function __clone() {
    }

    /**
     * Initialise the cache driver based on config
     *
     * @param Array $options array of options specific to the cache driver.
     * @access public
     * @return null
     */
    public function init($options)
    {
        parent::init($options);

        $this->cacheDriver = null;
        $this->options = $this->getFilteredOption("DRIVER");
        if(!is_array($this->options)){
            return;
        }

        switch ($this->options['driver']) {
            case "apc":
                if (!APC_EXTENSION_LOADED) {
                   AJXP_Logger::error(__CLASS__, "init", "The APC extension package must be installed!");
                   return;
                }
                $this->_apc_init();
                break;
            case "memcache":
                if (!MEMCACHE_EXTENSION_LOADED) {
                    AJXP_Logger::error(__CLASS__, "init", "The Memcache extension package must be installed!");
                    return;
                }
                $this->_memcache_init();
                break;
            case "memcached":
                if (!MEMCACHED_EXTENSION_LOADED) {
                    AJXP_Logger::error(__CLASS__, "init", "The Memcached extension package must be installed!");
                    return;
                }
                $this->_memcached_init();
                break;
            case "redis":
                if (!REDIS_EXTENSION_LOADED) {
                    AJXP_Logger::error(__CLASS__, "init", "The Redis extension package must be installed!");
                    return;
                }
                $this->_redis_init();
                break;
            case "xcache":
                if (!XCACHE_EXTENSION_LOADED) {
                    AJXP_Logger::error(__CLASS__, "init", "The XCache extension package must be installed!");
                    return;
                }
                $this->_xcache_init();
                break;
            default:
                break;
        }
    }

    public function _apc_init() {
        $this->cacheDriver = new Cache\ApcCache();
    }

    public function _memcache_init() {
        $memcache = new Memcache();
        @$running = $memcache->connect($this->options['MEMCACHE_HOSTNAME'], $this->options['MEMCACHE_PORT']);

        if (! $running) return;

        $this->cacheDriver = new Cache\MemcacheCache();
        $this->cacheDriver->setMemcache($memcache);
    }

    public function _memcached_init() {
        $memcached = new Memcached();
        @$running = $memcached->addServer($this->options['MEMCACHED_HOSTNAME'], $this->options['MEMCACHED_PORT']);

        if (! $running) return;

        $this->cacheDriver = new Cache\MemcachedCache();
        $this->cacheDriver->setMemcached($memcached);
    }

    public function _redis_init() {
        $redis = new Redis();
        @$running = $redis->connect($this->options['REDIS_HOSTNAME'], $this->options['REDIS_PORT']);

        if (! $running) return;

        $this->cacheDriver = new \Doctrine\Common\Cache\RedisCache();
        $this->cacheDriver->setRedis($redis);
    }

    public function _xcache_init() {
        $this->cacheDriver = new Cache\XcacheCache();
    }

    public function getCacheDriver() {
        return $this->cacheDriver;
    }


}
