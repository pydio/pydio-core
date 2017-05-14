<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Core\Utils\Http;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Helpers to detect current User-Agent values
 * @package Pydio\Core\Utils
 */
class UserAgent
{

    /**
     * Detect mobile browsers
     * @static
     * @return bool
     */
    public static function userAgentIsMobile()
    {
        $op = strtolower($_SERVER['HTTP_X_OPERAMINI_PHONE'] OR "");
        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        $ac = strtolower($_SERVER['HTTP_ACCEPT']);
        $isMobile = strpos($ac, 'application/vnd.wap.xhtml+xml') !== false
            || $op != ''
            || strpos($ua, 'sony') !== false
            || strpos($ua, 'symbian') !== false
            || strpos($ua, 'nokia') !== false
            || strpos($ua, 'samsung') !== false
            || strpos($ua, 'mobile') !== false
            || strpos($ua, 'android') !== false
            || strpos($ua, 'windows ce') !== false
            || strpos($ua, 'epoc') !== false
            || strpos($ua, 'opera mini') !== false
            || strpos($ua, 'nitro') !== false
            || strpos($ua, 'j2me') !== false
            || strpos($ua, 'midp-') !== false
            || strpos($ua, 'cldc-') !== false
            || strpos($ua, 'netfront') !== false
            || strpos($ua, 'mot') !== false
            || strpos($ua, 'up.browser') !== false
            || strpos($ua, 'up.link') !== false
            || strpos($ua, 'audiovox') !== false
            || strpos($ua, 'blackberry') !== false
            || strpos($ua, 'ericsson,') !== false
            || strpos($ua, 'panasonic') !== false
            || strpos($ua, 'philips') !== false
            || strpos($ua, 'sanyo') !== false
            || strpos($ua, 'sharp') !== false
            || strpos($ua, 'sie-') !== false
            || strpos($ua, 'portalmmm') !== false
            || strpos($ua, 'blazer') !== false
            || strpos($ua, 'avantgo') !== false
            || strpos($ua, 'danger') !== false
            || strpos($ua, 'palm') !== false
            || strpos($ua, 'series60') !== false
            || strpos($ua, 'palmsource') !== false
            || strpos($ua, 'pocketpc') !== false
            || strpos($ua, 'smartphone') !== false
            || strpos($ua, 'rover') !== false
            || strpos($ua, 'ipaq') !== false
            || strpos($ua, 'au-mic,') !== false
            || strpos($ua, 'alcatel') !== false
            || strpos($ua, 'ericy') !== false
            || strpos($ua, 'up.link') !== false
            || strpos($ua, 'vodafone/') !== false
            || strpos($ua, 'wap1.') !== false
            || strpos($ua, 'wap2.') !== false;
        return $isMobile;
    }

    /**
     * Detect iOS browser
     * @static
     * @return bool
     */
    public static function userAgentIsIOS()
    {
        if (stripos($_SERVER["HTTP_USER_AGENT"], "iphone") !== false) return true;
        if (stripos($_SERVER["HTTP_USER_AGENT"], "ipad") !== false) return true;
        if (stripos($_SERVER["HTTP_USER_AGENT"], "ipod") !== false) return true;
        return false;
    }

    /**
     * Detect Windows Phone
     * @static
     * @return bool
     */
    public static function userAgentIsWindowsPhone()
    {
        if (stripos($_SERVER["HTTP_USER_AGENT"], "IEMobile") !== false) return true;
        return false;
    }

    /**
     * Detect Android UA
     * @static
     * @return bool
     */
    public static function userAgentIsAndroid()
    {
        return (stripos($_SERVER["HTTP_USER_AGENT"], "android") !== false);
    }

    /**
     * Detect native apps user agent values (io, android, python)
     * @return bool
     */
    public static function userAgentIsNativePydioApp()
    {

        return (
            stripos($_SERVER["HTTP_USER_AGENT"], "ajaxplorer-ios-client") !== false
            || stripos($_SERVER["HTTP_USER_AGENT"], "Apache-HttpClient") !== false
            || stripos($_SERVER["HTTP_USER_AGENT"], "python-requests") !== false
            || stripos($_SERVER["HTTP_USER_AGENT"], "Pydio-Native") !== false
        );
    }

    /**
     *
     * @param null $useragent
     * @return int|string
     */
    public static function osFromUserAgent($useragent = null)
    {

        $osList = array
        (
            'Windows 10' => 'windows nt 10.0',
            'Windows 8.1' => 'windows nt 6.3',
            'Windows 8' => 'windows nt 6.2',
            'Windows 7' => 'windows nt 6.1',
            'Windows Vista' => 'windows nt 6.0',
            'Windows Server 2003' => 'windows nt 5.2',
            'Windows XP' => 'windows nt 5.1',
            'Windows 2000 sp1' => 'windows nt 5.01',
            'Windows 2000' => 'windows nt 5.0',
            'Windows NT 4.0' => 'windows nt 4.0',
            'Windows Me' => 'win 9x 4.9',
            'Windows 98' => 'windows 98',
            'Windows 95' => 'windows 95',
            'Windows CE' => 'windows ce',
            'Windows (version unknown)' => 'windows',
            'OpenBSD' => 'openbsd',
            'SunOS' => 'sunos',
            'Ubuntu' => 'ubuntu',
            'Linux' => '(linux)|(x11)',
            'Mac OSX Beta (Kodiak)' => 'mac os x beta',
            'Mac OSX Cheetah' => 'mac os x 10.0',
            'Mac OSX Jaguar' => 'mac os x 10.2',
            'Mac OSX Panther' => 'mac os x 10.3',
            'Mac OSX Tiger' => 'mac os x 10.4',
            'Mac OSX Leopard' => 'mac os x 10.5',
            'Mac OSX Snow Leopard' => 'mac os x 10.6',
            'Mac OSX Lion' => 'mac os x 10.7',
            'Mac OSX Mountain Lion' => 'mac os x 10.8',
            'Mac OSX Mavericks' => 'mac os x 10.9',
            'Mac OSX Yosemite' => 'mac os x 10.10',
            'Mac OSX El Capitan' => 'mac os x 10.11',
            'Mac OSX Puma' => 'mac os x 10.1',
            'Mac OS (classic)' => '(mac_powerpc)|(macintosh)',
            'QNX' => 'QNX',
            'BeOS' => 'beos',
            'Apple iPad' => 'iPad',
            'Apple iPhone' => 'iPhone',
            'OS2' => 'os\/2',
            'SearchBot' => '(nuhk)|(googlebot)|(yammybot)|(openbot)|(slurp)|(msnbot)|(ask jeeves\/teoma)|(ia_archiver)',
            'Pydio iOS Native Application' => 'ajaxplorer-ios',
            'PydioPro iOS Native Application' => 'Pydio-Native-iOS',
            'Pydio Android Native Application' => 'Apache-HttpClient',
            'PydioPro Android Native Application' => 'Pydio-Native-Android',
            'Pydio Sync Client' => 'python-requests',
            'Pydio Booster' => "Go-http-client"
        );

        if ($useragent == null) {
            $useragent = $_SERVER['HTTP_USER_AGENT'];
            $useragent = strtolower($useragent);
        }

        $found = "Not automatically detected.$useragent";
        foreach ($osList as $os => $match) {
            if (preg_match('/' . $match . '/i', $useragent)) {
                $found = $os;
                break;
            }
        }

        return $found;


    }
}