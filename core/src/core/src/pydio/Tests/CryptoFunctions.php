<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Tests;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Check whether mcrypt is enabled
 * @package Pydio\Tests
 */
class CryptoFunctions extends AbstractTest
{

    /**
     * @inheritdoc
     */
    public function __construct() { parent::__construct("Crypto Extensions", "Either OpenSSL or MCrypt are required by internal security functions."); }

    /**
     * @inheritdoc
     */
    public function doTest()
    {
        $this->failedLevel = "error";
        if (!function_exists("openssl_encrypt")  && !function_exists("mcrypt_create_iv")) {
            $this->testedParams["OpenSSL or MCrypt Detected"] = "No";
            return FALSE;
        }
        $this->testedParams["Crypto Extension Detected"] = function_exists("openssl_encrypt") ? "OpenSSL" : "MCrypt";
        return TRUE;
    }
}