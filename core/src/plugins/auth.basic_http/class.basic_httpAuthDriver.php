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

/**
 * AJXP_Plugin to authenticate users against the Basic-HTTP mechanism
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class basic_httpAuthDriver extends serialAuthDriver
{
    public function usersEditable()
    {
        return false;
    }
    public function passwordsEditable()
    {
        return false;
    }

    public function preLogUser($sessionId)
    {
        $localHttpLogin = $_SERVER["REMOTE_USER"];
        if(!isSet($localHttpLogin)) return ;
        $localHttpPassw = (isset($_SERVER['PHP_AUTH_PW'])) ? $_SERVER['PHP_AUTH_PW'] : md5(microtime(true)) ;
        if ($this->autoCreateUser()) {
            if (!$this->userExists($localHttpLogin)) {
                $this->createUser($localHttpLogin, $localHttpPassw);
            }
            AuthService::logUser($localHttpLogin, $localHttpPassw, true);
        } else {
            // If not auto-create but the user exists, log him.
            if ($this->userExists($localHttpLogin)) {
                AuthService::logUser($localHttpLogin, "", true);
            }
        }


    }
    public function getLogoutRedirect()
    {
        return AJXP_VarsFilter::filter($this->getOption("LOGOUT_URL"));
    }

}
