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

use Pydio\Core\Model\UserInterface;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class CookiesHelper - Helper for http cookies
 * @package Pydio\Core\Utils
 */
class CookiesHelper
{

    /**
     * @static
     * @param UserInterface $user
     */
    public static function refreshRememberCookie($user)
    {
        $current = $_COOKIE["AjaXplorer-remember"];
        if (!empty($current)) {
            CookiesHelper::invalidateCookieString($user, substr($current, strpos($current, ":")+1));
        }
        $rememberPass = CookiesHelper::getCookieString($user);
        setcookie("AjaXplorer-remember", $user->getId().":".$rememberPass, time()+3600*24*10, null, null, (isSet($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) == "on"), true);
    }

    /**
     * @static
     * @return bool
     */
    public static function hasRememberCookie()
    {
        return (isSet($_COOKIE["AjaXplorer-remember"]) && !empty($_COOKIE["AjaXplorer-remember"]));
    }

    /**
     * 
     * @return array [fakeuser,fakepass]
     */
    public static function getRememberCookieData(){
        return explode(":", $_COOKIE["AjaXplorer-remember"]);
    }

    /**
     * @static
     * Warning, must be called before sending other headers!
     * @param $user
     */
    public static function clearRememberCookie($user)
    {
        $current = $_COOKIE["AjaXplorer-remember"];
        if (!empty($current) && $user != null) {
            CookiesHelper::invalidateCookieString($user, substr($current, strpos($current, ":")+1));
        }
        setcookie("AjaXplorer-remember", "", time()-3600, null, null, (isSet($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) == "on"), true);
    }

    /**
     * @param UserInterface $user
     * @param string $cookieString
     * @return bool
     */
    public static function checkCookieString(UserInterface $user, $cookieString)
    {
        if($user->getPref("cookie_hash") == "") return false;
        $hashes = explode(",", $user->getPref("cookie_hash"));
        return in_array($cookieString, $hashes);
    }

    /**
     * @param UserInterface $user
     * @param string $cookieString
     */
    public static function invalidateCookieString(UserInterface $user, $cookieString = "")
    {
        if($user->getPref("cookie_hash") == "") return;
        $hashes = explode(",", $user->getPref("cookie_hash"));
        if(in_array($cookieString, $hashes)) $hashes = array_diff($hashes, array($cookieString));
        $user->setPref("cookie_hash", implode(",", $hashes));
        $user->save("user");
    }

    /**
     * @param UserInterface $user
     * @return string
     */
    public static function getCookieString(UserInterface $user)
    {
        $hashes = $user->getPref("cookie_hash");
        if ($hashes == "") {
            $hashes = array();
        } else {
            $hashes = explode(",", $hashes);
        }
        $newHash = md5($user->getId().":". StringHelper::generateRandomString());
        array_push($hashes, $newHash);
        $user->setPref("cookie_hash", implode(",",$hashes));
        $user->save("user");
        return $newHash;
    }

}