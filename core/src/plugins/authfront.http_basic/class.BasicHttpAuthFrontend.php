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


class BasicHttpAuthFrontend extends AbstractAuthFrontend {

    function tryToLogUser(&$httpVars, $isLast = false){

        $localHttpLogin = $_SERVER["PHP_AUTH_USER"];
        $localHttpPassw = $_SERVER['PHP_AUTH_PW'];

        // mod_php
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $localHttpLogin = $_SERVER['PHP_AUTH_USER'];
            $localHttpPassw = $_SERVER['PHP_AUTH_PW'];

        // most other servers
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']),'basic')===0){
                list($localHttpLogin,$localHttpPassw) = explode(':',base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            }
        // Sometimes prepend a REDIRECT
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {

            if (strpos(strtolower($_SERVER['REDIRECT_HTTP_AUTHORIZATION']),'basic')===0){
                list($localHttpLogin,$localHttpPassw) = explode(':',base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)));
            }

        }

        if($isLast && empty($localHttpLogin)){
            header('WWW-Authenticate: Basic realm="Pydio API"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'You are not authorized to access this API.';
            exit();
        }
        if(!isSet($localHttpLogin)) return false;

        $res = AuthService::logUser($localHttpLogin, $localHttpPassw, false, false, "-1");
        if($res > 0) return true;
        if($isLast && $res != -4){
            header('WWW-Authenticate: Basic realm="Pydio API"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'You are not authorized to access this API.';
            exit();
        }


        return false;

    }

} 