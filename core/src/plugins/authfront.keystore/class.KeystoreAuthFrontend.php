<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');


class KeystoreAuthFrontend extends AbstractAuthFrontend {

    /**
     * @var sqlConfDriver $storage
     */
    var $storage;

    function init($options){
        parent::init($options);
    }

    function detectVar($varName){
        if(isSet($_GET[$varName])) return $_GET[$varName];
        if(isSet($_POST[$varName])) return $_POST[$varName];
        if(isSet($_SERVER["HTTP_PYDIO_".strtoupper($varName)])) return $_SERVER["HTTP_".strtoupper($varName)];
    }

    function tryToLogUser($isLast = false){

        $token = $this->detectVar("auth_token");
        if(empty($token)){
            return false;
        }
        $secret = $this->detectVar("auth_hash");
        $this->storage = ConfService::getConfStorageImpl();
        if(!is_a($this->storage, "sqlConfDriver")) return false;

        $data = null;
        $this->storage->simpleStoreGet("keystore", $token, "serial", $data);
        if(empty($data)){
            return false;
        }
        $userId = $data["USER_ID"];
        $private = $data["PRIVATE"];
        if(md5($userId.$private) == $secret){
            AuthService::logUser($userId, "", true);
            return true;
        }
        return false;

    }

} 