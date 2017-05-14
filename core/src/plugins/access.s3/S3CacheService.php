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
 *
 */
namespace Pydio\Access\Driver\StreamProvider\S3;

use Pydio\Core\Services\CacheService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class S3CacheService
 * Pydio Connector between AWS Cache and pydio CacheService
 * @package Pydio\Access\Driver\StreamProvider\S3
 */
class S3CacheService implements \Aws\CacheInterface
{
    /**
     * Get a cache item by key.
     *
     * @param string $key Key to retrieve.
     *
     * @return mixed|null Returns the value or null if not found.
     */
    public function get($key) {
        return  CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $key);
    }

    /**
     * Set a cache key value.
     *
     * @param string $key   Key to set
     * @param mixed  $value Value to set.
     * @param int    $ttl   Number of seconds the item is allowed to live. Set
     *                      to 0 to allow an unlimited lifetime.
     */
    public function set($key, $value, $ttl = 0) {
        CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $key, $value, $ttl);
    }

    /**
     * Remove a cache key.
     *
     * @param string $key Key to remove.
     */
    public function remove($key) {
        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $key);
    }
}