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

use phpseclib\Crypt\Rijndael;
use Pydio\Core\Services\ConfService;
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
    public static function getRandomSalt($base64encode = true){
        if(function_exists('openssl_random_pseudo_bytes')){
            $salt = openssl_random_pseudo_bytes(32);
        }else if (function_exists('mcrypt_create_iv')){
            $salt = mcrypt_create_iv(PBKDF2_SALT_BYTE_SIZE, MCRYPT_DEV_URANDOM);
        }else{
            $salt = StringHelper::generateRandomString(32, true);
        }
        return ($base64encode ? base64_encode($salt) : $salt);
    }

    /**
     * @param mixed $data
     * @param string $key
     * @param bool $base64encode
     * @return mixed
     */
    public static function encrypt($data, $key, $base64encode = true){
        $r = new ZeroPaddingRijndael(Rijndael::MODE_ECB);
        $r->setKey($key);
        $r->setBlockLength(256);
        $encoded = $r->encrypt($data);
        if($base64encode) {
            return base64_encode($encoded);
        } else {
            return $encoded;
        }
    }

    /**
     * @param string $data
     * @param string $key
     * @param bool $base64encoded
     * @return mixed
     */
    public static function decrypt($data, $key, $base64encoded = true){
        if($base64encoded){
            $data = base64_decode($data);
        }
        $r = new ZeroPaddingRijndael(Rijndael::MODE_ECB);
        $r->setKey($key);
        $r->setBlockLength(256);
        return $r->decrypt($data);
    }

}