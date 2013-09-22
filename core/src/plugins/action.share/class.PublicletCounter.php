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
 * Download counter for publiclets
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class PublicletCounter
{
    private static $counters;

    public static function getCount($publiclet)
    {
        $counters = self::loadCounters();
        if(isSet($counters[$publiclet])) return $counters[$publiclet];
        return 0;
    }

    public static function increment($publiclet)
    {
        if(!self::isActive()) return -1 ;
        $counters = self::loadCounters();
        if (!isSet($counters[$publiclet])) {
            $counters[$publiclet]  = 0;
        }
        $counters[$publiclet] ++;
        self::saveCounters($counters);
        return $counters[$publiclet];
    }

    public static function reset($publiclet)
    {
        if(!self::isActive()) return -1 ;
        $counters = self::loadCounters();
        $counters[$publiclet]  = 0;
        self::saveCounters($counters);
    }

    public static function delete($publiclet)
    {
        if(!self::isActive()) return -1 ;
        $counters = self::loadCounters();
        if (isSet($counters[$publiclet])) {
            unset($counters[$publiclet]);
            self::saveCounters($counters);
        }
    }

    private static function isActive()
    {
        return (is_dir(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")) && is_writable(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")));
    }

    private static function loadCounters()
    {
        if (!isSet(self::$counters)) {
            self::$counters = AJXP_Utils::loadSerialFile(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/.ajxp_publiclet_counters.ser");
        }
        return self::$counters;
    }

    private static function saveCounters($counters)
    {
        self::$counters = $counters;
        AJXP_Utils::saveSerialFile(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/.ajxp_publiclet_counters.ser", $counters, false);
    }

}
