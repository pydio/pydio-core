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


use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class BruteForceHelper
 * @package Pydio\Core\Utils
 */
class BruteForceHelper
{

    /**
     * The array is located in the AjxpTmpDir/failedAJXP.log
     * @static
     * @return array
     */
    public static function getBruteForceLoginArray()
    {
        $failedLog = ApplicationState::getTemporaryFolder() . "/failedAJXP.log";
        $loginAttempt = @file_get_contents($failedLog);
        $loginArray = unserialize($loginAttempt);
        $ret = array();
        $curTime = time();
        if (is_array($loginArray)) {
            // Filter the array (all old time are cleaned)
            foreach ($loginArray as $key => $login) {
                if (($curTime - $login["time"]) <= 60 * 60 * 24) $ret[$key] = $login;
            }
        }
        return $ret;
    }

    /**
     * Store the array
     * @static
     * @param $loginArray
     * @return void
     */
    public static function setBruteForceLoginArray($loginArray, $validCurrent = false)
    {
        if($validCurrent && isSet($loginArray[$_SERVER["REMOTE_ADDR"]])){
            unset($loginArray[$_SERVER["REMOTE_ADDR"]]);
        }
        $failedLog = ApplicationState::getTemporaryFolder() . "/failedAJXP.log";
        @file_put_contents($failedLog, serialize($loginArray));
    }

    /**
     * Determines whether the user is try to make many attemps
     * @static
     * @param $loginArray
     * @return bool
     */
    public static function checkBruteForceLogin(&$loginArray)
    {
        if (isSet($_SERVER['REMOTE_ADDR'])) {
            $serverAddress = $_SERVER['REMOTE_ADDR'];
        } else if (isSet($_SERVER['SERVER_ADDR'])) {
            $serverAddress = $_SERVER['SERVER_ADDR'];
        } else {
            return TRUE;
        }
        $login = null;
        if (isSet($loginArray[$serverAddress])) {
            $login = $loginArray[$serverAddress];
        }
        if (is_array($login)) {
            $login["count"]++;
        } else $login = array("count" => 1, "time" => time());
        $loginArray[$serverAddress] = $login;
        if ($login["count"] > 3) {
            if (AJXP_SERVER_DEBUG || ConfService::getGlobalConf("DISABLE_BRUTE_FORCE_CHECK", "auth") === true) {
                Logger::debug("Warning: failed login 3 time from address $serverAddress, but ignored because captcha is disabled.");
                return true;
            }
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Is there a brute force login attempt?
     * @static
     * @return bool
     */
    public static function suspectBruteForceLogin()
    {
        $loginAttempt = self::getBruteForceLoginArray();
        return !self::checkBruteForceLogin($loginAttempt);
    }
}