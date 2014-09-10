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


class ServerHttpAuthFrontend extends AbstractAuthFrontend {

    function tryToLogUser(&$httpVars, $isLast = false){

        $localHttpLogin = $_SERVER["REMOTE_USER"];
        $localHttpPassw = isSet($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : "";
        if(!isSet($localHttpLogin)) return false;

        if(!AuthService::userExists($localHttpLogin) && $this->pluginConf["CREATE_USER"] === true){
            AuthService::createUser($localHttpLogin, $localHttpPassw, (isset($this->pluginConf["AJXP_ADMIN"]) && $this->pluginConf["AJXP_ADMIN"] == $localHttpLogin));
        }
        $res = AuthService::logUser($localHttpLogin, $localHttpPassw, true);
        if($res > 0) return true;

        return false;

    }

} 