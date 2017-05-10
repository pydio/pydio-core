<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Utils\Vars;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Various Helpers for URL and Path parsing
 * @package Pydio\Core\Utils
 */
class UrlUtils
{
    /**
     * UTF8 support for parseUrl
     * @param string $url
     * @param int $part one of PHP_URL_** variable
     * @return array|string
     */
    public static function mbParseUrl($url, $part = -1){
        $enc_url = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ($matches)
            {
                return urlencode($matches[0]);
            },
            $url
        );
        if($enc_url === null) {
            return parse_url($url, $part);
        }

        $parts = parse_url($enc_url, $part);

        if($parts === false)
        {
            throw new \InvalidArgumentException('Malformed URL: ' . $url);
        }
        if($part !== -1){
            return urldecode($parts);
        }

        foreach($parts as $name => $value)
        {
            $parts[$name] = urldecode($value);
        }

        return $parts;
    }

    /**
     * Parse URL ignoring # and ?
     * @param $path
     * @return array
     */
    public static function safeParseUrl($path)
    {
        $parts = self::mbParseUrl(str_replace(array("#", "?"), array("__AJXP_FRAGMENT__", "__AJXP_MARK__"), $path));
        $parts["path"] = str_replace(array("__AJXP_FRAGMENT__", "__AJXP_MARK__"), array("#", "?"), $parts["path"]);
        return $parts;
    }
}