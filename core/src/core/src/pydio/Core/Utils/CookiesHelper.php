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
namespace Pydio\Core\Utils;

use Pydio\Core\Model\UserInterface;

defined('AJXP_EXEC') or die('Access not allowed');


class CookiesHelper
{
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
        $newHash = md5($user->getId().":".Utils::generateRandomString());
        array_push($hashes, $newHash);
        $user->setPref("cookie_hash", implode(",",$hashes));
        $user->save("user");
        return $newHash;
    }

}