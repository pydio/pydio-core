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
namespace Pydio\Core\Services;

use Pydio\Conf\Sql\SqlConfDriver;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Utils\Http\UserAgent;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class ApiKeysService
 * @package Pydio\Core\Services
 */
class ApiKeysService
{
    /**
     * @return SqlConfDriver
     * @throws PydioException
     */
    private static function getStore(){
        $store = ConfService::getConfStorageImpl();
        if (!($store instanceof SqlConfDriver)){
            throw new PydioException("Wrong configuration driver, please use Sql");
        }
        return $store;
    }

    /**
     * @param $userId
     * @param string $deviceId
     * @param string $deviceUA
     * @param string $deviceIP
     * @return array
     * @throws PydioException
     * @throws \Exception
     */
    public static function generatePairForAuthfront($userId, $deviceId = "", $deviceUA = "", $deviceIP = ""){

        $store = self::getStore();
        $token = StringHelper::generateRandomString();
        $private = StringHelper::generateRandomString();
        $data = array("USER_ID" => $userId, "PRIVATE" => $private);
        if (!empty($deviceId)) {
            // Revoke previous tokens for this device
            $cursor = null;
            $keys = $store->simpleStoreList("keystore", $cursor, "", "serial", '%"DEVICE_ID";s:' . strlen($deviceId) . ':"' . $deviceId . '"%');
            foreach ($keys as $keyId => $keyData) {
                if ($keyData["USER_ID"] != $userId) continue;
                $store->simpleStoreClear("keystore", $keyId);
            }
            $data["DEVICE_ID"] = $deviceId;
        }
        $data["DEVICE_UA"] = $deviceUA;
        $data["DEVICE_IP"] = $deviceIP;
        $store->simpleStoreSet("keystore", $token, $data, "serial");
        return ["t" => $token, "p" => $private];

    }

    /**
     * @param $userId
     * @param $adminTaskId
     * @param string $restrictToIP
     * @return array
     * @throws PydioException
     * @throws \Exception
     */
    public static function generatePairForAdminTask($adminTaskId, $userId = "", $restrictToIP = ""){

        $store = self::getStore();
        $token = StringHelper::generateRandomString();
        $private = StringHelper::generateRandomString();
        $data = [
            "PRIVATE"       => $private,
            "ADMIN_TASK_ID" => $adminTaskId
        ];
        if(!empty($userId)){
            $data["USER_ID"] = $userId;
        }
        if(!empty($restrictToIP)){
            $data["RESTRICT_TO_IP"] = $restrictToIP;
        }
        $store->simpleStoreSet("keystore", $token, $data, "serial");
        return ["t" => $token, "p" => $private];

    }

    /**
     * @param $adminTaskId
     * @param $userId
     * @return array|null
     * @throws PydioException
     */
    public static function findPairForAdminTask($adminTaskId, $userId = ""){

        $keys = self::getStore()->simpleStoreList("keystore", $cursor, "", "serial", '%"ADMIN_TASK_ID";s:' . strlen($adminTaskId) . ':"' . $adminTaskId . '"%');
        foreach($keys as $kId => $kData){
            if(empty($userId) || $kData["USER_ID"] === $userId){
                return ["t" => $kId, "p" => $kData["PRIVATE"]];
            }
        }
        return null;

    }

    /**
     * @param string $adminTaskId
     * @param string $userId
     * @return integer number of deleted keys
     * @throws PydioException
     */
    public static function revokePairForAdminTask($adminTaskId, $userId = ""){

        $keys = self::getStore()->simpleStoreList("keystore", $cursor, "", "serial", '%"ADMIN_TASK_ID";s:' . strlen($adminTaskId) . ':"' . $adminTaskId . '"%');
        $c = 0;
        foreach($keys as $kId => $kData){
            if(empty($userId) || $kData["USER_ID"] === $userId){
                self::getStore()->simpleStoreClear("keystore", $kId);
                $c++;
            }
        }
        return $c;

    }

    /**
     * @param $serverData
     * @param $adminTaskId
     * @param $userId
     * @return bool
     */
    public static function requestHasValidHeadersForAdminTask($serverData, $adminTaskId, $userId = ""){
        if(!isSet($serverData['HTTP_X_PYDIO_ADMIN_AUTH'])){
            Logger::error(__CLASS__, __FUNCTION__,"Invalid tokens for admin task $adminTaskId");
            return false;
        }
        list($t, $p) = explode(":", trim($serverData['HTTP_X_PYDIO_ADMIN_AUTH']));
        $existingKey = self::findPairForAdminTask(PYDIO_BOOSTER_TASK_IDENTIFIER);
        if($existingKey === null || $existingKey['p'] !== $p || $existingKey['t'] !== $t){
            Logger::error(__CLASS__, __FUNCTION__, "Invalid tokens for admin task $adminTaskId");
            return false;
        }
        Logger::debug(__CLASS__, "Valid tokens for admin task $adminTaskId");
        return true;
    }

    /**
     * @param $token
     * @param string $checkPrivate
     * @return bool
     * @throws PydioException
     */
    public static function loadDataForPair($token, $checkPrivate = ""){
        $data = null;
        self::getStore()->simpleStoreGet("keystore", $token, "serial", $data);
        if (empty($data)) {
            return false;
        }
        if(!empty($checkPrivate)){
            $p = $data["PRIVATE"];
            unset($data["PRIVATE"]);
            return $checkPrivate === $p ? $data : false;
        }
        return $data;
    }

    /**
     * @param $userId
     * @param $token
     * @return int
     * @throws PydioException
     */
    public static function revokeTokens($userId, $token = ""){
        $keys = self::getStore()->simpleStoreList("keystore", $cursor, $token, "serial", '%"USER_ID";s:' . strlen($userId) . ':"' . $userId . '"%');
        $c = 0;
        foreach ($keys as $keyId => $keyData) {
            $c ++;
            self::getStore()->simpleStoreClear("keystore", $keyId);
        }
        return $c;
    }

    /**
     * @param $userId
     * @return array
     * @throws PydioException
     */
    public static function listPairsForUser($userId){
        $cursor = null;
        $keys = self::getStore()->simpleStoreList("keystore", $cursor, "", "serial", '%"USER_ID";s:' . strlen($userId) . ':"' . $userId . '"%');
        foreach ($keys as $keyId => &$keyData) {
            unset($keyData["PRIVATE"]);
            unset($keyData["USER_ID"]);
            $deviceDesc = "Web Browser";
            $deviceOS = "Unkown";
            if (isSet($keyData["DEVICE_UA"])) {
                $agent = $keyData["DEVICE_UA"];
                if (strpos($agent, "python-requests") !== false) {
                    $deviceDesc = "PydioSync";
                    if (strpos($agent, "Darwin") !== false) $deviceOS = "Mac OS X";
                    else if (strpos($agent, "Windows/7") !== false) $deviceOS = "Windows 7";
                    else if (strpos($agent, "Windows/8") !== false) $deviceOS = "Windows 8";
                    else if (strpos($agent, "Linux") !== false) $deviceOS = "Linux";
                } else {
                    $deviceDesc = $deviceOS = UserAgent::osFromUserAgent($agent);
                }
            }
            $keyData["DEVICE_DESC"] = $deviceDesc;
            $keyData["DEVICE_OS"] = $deviceOS;
        }
        return $keys;
    }

}