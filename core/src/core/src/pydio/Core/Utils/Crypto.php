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
namespace Pydio\Core\Utils;

use phpseclib\Crypt\Rijndael;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Crypto\Key;
use Pydio\Core\Utils\Crypto\ZeroPaddingRijndael;
use Pydio\Core\Utils\Vars\StringHelper;


defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Simple encrypt / decrypt utils for small strings
 * Legacy is using mcrypt Rijndael_256, will be replaced by openssl or libsodium with standard cypher
 * @package Pydio\Core\Utils
 */
class Crypto
{
    const HEADER_CBC_128 = 'cbc-128';

    /**
     * @return string
     */
    public static function getApplicationSecret(){
        if (defined('AJXP_SAFE_SECRET_KEY')) {
            return AJXP_SAFE_SECRET_KEY;
        } else {
            return "\1CDAFx¨op#";
        }
    }

    /**
     * @return string
     */
    public static function getCliSecret(){
        $cKey = ConfService::getGlobalConf("AJXP_CLI_SECRET_KEY", "conf");
        if (empty($cKey)) {
            $cKey = "\1CDAFx¨op#";
        }
        return $cKey;
    }

    /**
     * @param bool $base64encode
     * @return string
     */
    public static function getRandomSalt($base64encode = true, $size = 32){
        if(function_exists('openssl_random_pseudo_bytes')){
            $salt = openssl_random_pseudo_bytes($size);
        }else if (function_exists('mcrypt_create_iv')){
            $salt = mcrypt_create_iv(PBKDF2_SALT_BYTE_SIZE, MCRYPT_DEV_URANDOM);
        }else{
            $salt = StringHelper::generateRandomString($size, true);
        }
        return ($base64encode ? base64_encode($salt) : $salt);
    }

    /**
     * @return string
     */
    protected static function getDataHeader(){
        return substr(md5(self::HEADER_CBC_128), 0, 16);
    }

    /**
     * @param $data
     * @return bool
     */
    public static function hasCBCEnctypeHeader($data){
        $h = self::getDataHeader();
        return (strpos($data, $h) === 0);
    }

    /**
     * @param $data
     * @return bool
     */
    private static function removeCBCEnctypeHeader(&$data){
        $h = self::getDataHeader();
        if(strpos($data, $h) === 0){
            $data = substr($data, strlen($h));
            return true;
        }else{
            return false;
        }
    }

    /**
     * Builds a key using various methods depending on legacy status or not
     * @param string $userKey
     * @param string $secret
     * @param null $encodedData
     * @return array|bool|string
     */
    public static function buildKey($userKey, $secret, $encodedData = null){
        if($encodedData === null){
            // new encryption, use new method
            return Key::create($userKey.$secret);
        }else if(self::hasCBCEnctypeHeader($encodedData)){
            // New method detected
            return Key::create($userKey . $secret, Key::STRENGTH_MEDIUM);
        }else{
            // Legacy
            return Key::createLegacy($userKey . $secret);
        }
    }

    /**
     * @param mixed $data
     * @param string $key
     * @param bool $base64encode
     * @return mixed
     */
    public static function encrypt($data, $key, $base64encode = true){
        // Encrypt in new mode, prepending a fixed header to the encoded data.
        $r = new ZeroPaddingRijndael(Rijndael::MODE_CBC);
        $r->setKey($key);
        $r->setBlockLength(128);
        $iv = self::getRandomSalt(false, 16);
        $r->setIV($iv);
        $encoded = $iv . $r->encrypt($data);
        if($base64encode) {
            return self::getDataHeader().base64_encode($encoded);
        } else {
            return self::getDataHeader().$encoded;
        }
    }

    /**
     * @param string $data
     * @param string $key
     * @param bool $base64encoded
     * @return mixed
     */
    public static function decrypt($data, $key, $base64encoded = true){
        $test = self::removeCBCEnctypeHeader($data);
        if($base64encoded){
            $data = base64_decode($data);
        }
        if($test){
            $iv = substr($data, 0, 16);
            $data = substr($data, 16);
            $r = new ZeroPaddingRijndael(Rijndael::MODE_CBC);
            $r->setIV($iv);
            $r->setBlockLength(128);
        }else{
            // Legacy encoding
            $r = new ZeroPaddingRijndael(Rijndael::MODE_ECB);
            $r->setBlockLength(256);
        }
        $r->setKey($key);
        return $r->decrypt($data);
    }

}