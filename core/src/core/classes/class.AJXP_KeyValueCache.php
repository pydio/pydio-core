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
 * Class AJXP_KeyValueCache
 *
 * Simple Key/Value cache stored in memory, independant of the implementation.
 * Currently APC only, we should replace with the Doctrine/cache library (see https://github.com/doctrine/cache )
 * We do implement their Cache Interface for future migration.
 */
class AJXP_KeyValueCache {

    protected function makeId($id){
        if(defined('AJXP_KVCACHE_PREFIX')){
            return AJXP_KVCACHE_PREFIX . " - ".$id;
        }
        return $id;
    }

    /**
     * Fetches an entry from the cache.
     *
     * @param string $id The id of the cache entry to fetch.
     *
     * @return mixed The cached data or FALSE, if no cache entry exists for the given id.
     */
    public function fetch($id){
        if(!function_exists('apc_fetch')) return FALSE;
        if(defined('AJXP_KVCACHE_IGNORE') && AJXP_KVCACHE_IGNORE) return FALSE;
        $result = apc_fetch($this->makeId($id), $success);
        if($success) return $result;
        else return false;
    }
    /**
     * Tests if an entry exists in the cache.
     *
     * @param string $id The cache id of the entry to check for.
     *
     * @return boolean TRUE if a cache entry exists for the given cache id, FALSE otherwise.
     */
    public function contains($id){
        if(!function_exists('apc_fetch')) return FALSE;
        if(defined('AJXP_KVCACHE_IGNORE') && AJXP_KVCACHE_IGNORE) return FALSE;
        apc_fetch($this->makeId($id), $success);
        return $success;
    }
    /**
     * Puts data into the cache.
     *
     * @param string $id       The cache id.
     * @param mixed  $data     The cache entry/data.
     * @param int    $lifeTime The cache lifetime.
     *                         If != 0, sets a specific lifetime for this cache entry (0 => infinite lifeTime).
     *
     * @return boolean TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    public function save($id, $data, $lifeTime = 0){
        if(!function_exists('apc_store')) return false;
        if(defined("AJXP_KVCACHE_IGNORE") && AJXP_KVCACHE_IGNORE) return false;
        $res = apc_store($this->makeId($id), $data, $lifeTime);
        if($res !== false) return true;
        return false;
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $id The cache id.
     *
     * @return boolean TRUE if the cache entry was successfully deleted, FALSE otherwise.
     */
    public function delete($id){
        if(!function_exists('apc_delete')) return true;
        return apc_delete($this->makeId($id));
    }

    /**
     * Flush a whole cache
     */
    public function deleteAll(){
        if(function_exists('apc_clear_cache')){
            apc_clear_cache('user');
        }
    }


}