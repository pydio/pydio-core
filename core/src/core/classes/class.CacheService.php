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
 * Static access to the caching mechanism. Encapsulates the cacheDriver implementation
 * @package Pydio
 * @subpackage Core
 */
class CacheService
{
    /**
     * @param $id
     * @return bool
     */
    public static function contains($id) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            return $cacheDriver->contains($id);
        }

        return false;
    }

    /**
     * @param $id
     * @param $object
     * @param int $timelimit
     * @return bool
     */
    public static function save($id, $object, $timelimit = 0 ) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            return $cacheDriver->save($id, $object, $timelimit);
        }

        return false;
    }

    /**
     * @param $id
     * @return bool|mixed
     */
    public static function fetch($id) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            $data = $cacheDriver->fetch($id);
            return $data;
        }

        return false;
    }

    /**
     * @param $id
     * @return bool
     */
    public static function delete($id) {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            return $cacheDriver->delete($id);
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function deleteAll() {
        $cacheDriver = ConfService::getCacheDriverImpl();

        if ($cacheDriver) {
            return $cacheDriver->deleteAll();
        }

        return false;
    }
}