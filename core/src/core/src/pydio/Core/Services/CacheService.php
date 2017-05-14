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
namespace Pydio\Core\Services;


defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Static access to the caching mechanism. Encapsulates the cacheDriver implementation
 * @package Pydio
 * @subpackage Core
 */
class CacheService
{
    /**
     * @param $namespace
     * @param $id
     * @return bool
     */
    public static function contains($namespace, $id) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            return $cacheDriver->contains($namespace, $id);
        }

        return false;
    }

    /**
     * @param $namespace
     * @param $id
     * @param $object
     * @param int $timelimit
     * @return bool
     */
    public static function save($namespace, $id, $object, $timelimit = 0) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            //if($namespace === AJXP_CACHE_SERVICE_NS_SHARED) error_log("Saving data for $id");
            return $cacheDriver->save($namespace, $id, $object, $timelimit);
        }

        return false;
    }

    /**
     * @param $namespace
     * @param $id
     * @param $object
     * @param int $timelimit
     * @return bool
     */
    public static function saveWithTimestamp($namespace, $id, $object, $timelimit = 0){
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            //if($namespace === AJXP_CACHE_SERVICE_NS_SHARED) error_log("Saving data for $id with timestamp");
            return $cacheDriver->saveWithTimestamp($namespace, $id, $object, $timelimit);
        }

        return false;
        
    }
    
    /**
     * @param $namespace
     * @param $id
     * @return bool|mixed
     */
    public static function fetch($namespace, $id) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            $data = $cacheDriver->fetch($namespace, $id);
            if($data !== false && $namespace === AJXP_CACHE_SERVICE_NS_SHARED){
                //error_log("Found data for $id");
            }
            return $data;
        }

        return false;
    }

    /**
     * @param $namespace
     * @param array $ids
     * @return bool|mixed
     */
    public static function fetchMultiple($namespace, $ids) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            return $cacheDriver->fetchMultiple($namespace, $ids);
        }

        return false;
    }

    /**
     * @param $namespace
     * @param $id
     * @param array $idsToCheck
     * @return bool|false|mixed
     */
    public static function fetchWithTimestamps($namespace, $id, $idsToCheck){
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            $data = $cacheDriver->fetchWithTimestamps($namespace, $id, $idsToCheck);
            if($data !== false && $namespace === AJXP_CACHE_SERVICE_NS_SHARED){
                //error_log("Found data for $id after checking timestamps for ".implode(",", $idsToCheck));
            }
            return $data;
        }

        return false;

    }
    
    /**
     * @param $namespace
     * @param $id
     * @return bool
     */
    public static function delete($namespace, $id) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            return $cacheDriver->delete($namespace, $id);
        }

        return false;
    }

    /**
     * @param $namespace
     * @return bool
     */
    public static function deleteAll($namespace) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            return $cacheDriver->deleteAll($namespace);
        }

        return false;
    }
}