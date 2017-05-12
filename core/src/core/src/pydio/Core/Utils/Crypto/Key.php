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
namespace Pydio\Core\Utils\Crypto;

use Pydio\Core\Utils\Crypto;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class Key
 * @package Pydio\Core\Utils\Crypto
 */
class Key
{
    const STRENGTH_LOW = 0;
    const STRENGTH_MEDIUM = 1;
    const STRENGTH_HIGH = 2;

    const SIZE_128 = 16;
    const SIZE_256 = 32;

    /**
     * @param $password
     * @param int $strength
     * @param null $options
     * @return array|bool|string
     */
    public static function create($password, $strength = Key::STRENGTH_MEDIUM, $options = null){

        if(!$options){
            $options = array(
                "strength" => self::STRENGTH_MEDIUM,
                "size" => self::SIZE_256,
                "iterations" => 20000,
                "salt" => md5(Crypto::getApplicationSecret()),
                "hash_function" => "SHA512"
            );
        }

        if($strength == self::STRENGTH_HIGH && function_exists('openssl_random_pseudo_bytes')){

            $aes_key = self::create($password);
            $method = "aes-" . strlen($options["size"]) . "-cbc";
            
            $key = openssl_random_pseudo_bytes($options["size"]);
            $rsa = openssl_pkey_new(array(
                "digest_algo" => "sha512",
                "private_key_bits" => "4096",
                "private_key_type" => OPENSSL_KEYTYPE_RSA
            ));
            openssl_pkey_export($rsa, $private);

            $iv = openssl_random_pseudo_bytes(16);
            $private = openssl_encrypt($private, $method, $aes_key, OPENSSL_RAW_DATA, $iv);
            $public = openssl_pkey_get_details($rsa)["key"];

            $options["public"] = $public;
            $options["private"] = $private;
            $options["iv"] = $iv;
            openssl_public_encrypt($key, $options["key"], $public);

            return array(
                $key,
                $options
            );

        } else if($strength == self::STRENGTH_LOW){
            return substr(hash($options["hash_function"], $password), 0, $options["size"]);
        } else {
            return openssl_pbkdf2($password, $options["salt"], $options["size"], $options["iterations"], $options["hash_function"]);
        }
    }

    /**
     * @param $password
     * @return string
     */
    public static function createLegacy($password){
        return md5($password);
    }

}