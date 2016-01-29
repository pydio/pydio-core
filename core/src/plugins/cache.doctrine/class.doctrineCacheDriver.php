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

use \Doctrine\Common\Cache;

/**
 * Standard Memcache driver
 * @package AjaXplorer_Plugins
 * @subpackage Log
 */
class doctrineCacheDriver extends AbstractCacheDriver
{
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


        switch ($this->options['driver']) {
            case "apc":
                $this->_apc_init();
                break;
            case "memcache":
                $this->_memcache_init();
                break;
            case "memcached":
                $this->_memcached_init();
                break;
            case "redis":
                $this->_redis_init();
                break;
            case "xcache":
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
        $this->memcache = new Memcache();
        @$running = $this->memcache->connect($this->options['MEMCACHE_HOSTNAME'], $this->options['MEMCACHE_PORT']);

        if (! $running) return;

        $this->cacheDriver = new Cache\MemcacheCache();
        $this->cacheDriver->setMemcache($this->memcache);
    }

    public function _memcached_init() {
        $this->memcached = new Memcache();
        @$running = $this->memcached->connect($this->options['MEMCACHED_HOSTNAME'], $this->options['MEMCACHED_PORT']);

        if (! $running) return;

        $this->cacheDriver = new Cache\MemcachedCache();
        $this->cacheDriver->setMemcached($this->memcached);
    }

    public function _redis_init() {
        $this->redis = new Redis();
        @$running = $this->redis->connect($this->options['REDIS_HOSTNAME'], $this->options['REDIS_PORT']);

        if (! $running) return;

        $this->cacheDriver = new \Doctrine\Common\Cache\RedisCache();
        $this->cacheDriver->setRedis($this->redis);
    }

    public function _xcache_init() {
        $this->cacheDriver = new Cache\XcacheCache();
    }

    public function getCacheDriver() {
        return $this->cacheDriver;
    }


}
