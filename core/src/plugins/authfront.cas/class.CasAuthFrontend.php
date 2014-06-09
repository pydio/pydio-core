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
defined('AJXP_EXEC') or die('Access not allowed');

require_once 'CAS.php';

class CasAuthFrontend extends AbstractAuthFrontend
{

    private $cas_server;
    private $cas_port;
    private $cas_uri;
    private $is_AutoCreateUser;
    private $cas_logoutUrl;
    private $forceRedirect;


    function tryToLogUser($httpVars, $isLast = false)
    {
        if (isset($this->pluginConf["CAS_SERVER"])) {
            $this->cas_server = $this->pluginConf["CAS_SERVER"];
        }

        if (isset($this->pluginConf["CAS_PORT"])) {
            $this->cas_port = intval($this->pluginConf["CAS_PORT"]);
        }

        if (isset($this->pluginConf["CAS_URI"])) {
            $this->cas_uri = $this->pluginConf["CAS_URI"];
        }

        if (isset($this->pluginConf["CREATE_USER"])) {
            $this->is_AutoCreateUser = ($this->pluginConf["CREATE_USER"] == "true");
        }

        if (isset($this->pluginConf["LOGOUT_URL"])) {
            $this->cas_logoutUrl = $this->pluginConf["LOGOUT_URL"];
        }

        if (isset($this->pluginConf["FORCE_REDIRECT"])) {
            $this->forceRedirect = $this->pluginConf["FORCE_REDIRECT"];
        }

        phpCAS::setDebug(AJXP_DATA_PATH . "/logs/debug.log");
        if ($GLOBALS['PHPCAS_CLIENT'] == null) {
            phpCAS::client(CAS_VERSION_2_0, $this->cas_server, $this->cas_port, $this->cas_uri, false);
        }
        phpCAS::setNoCasServerValidation();
        AJXP_Logger::debug(__FUNCTION__, "Call forceAuthentication ", "");

        if($this->forceRedirect) {
            // if forceRedirect is enable, redirect webpage to CAS web to do the authentication.
            // After login successfully, CAS will go back to pydio webpage.
            phpCAS::forceAuthentication();
        }else{
            // Otherwise, verify user has already logged by using CAS or not?
            if(!phpCAS::isAuthenticated()){
                // In case of NO, return false to bypass the authentication by CAS and continue to use another method
                // in authfront list.
                return false;
            }
        }

        AJXP_Logger::debug(__FUNCTION__, "Call phpCAS::getUser() after forceAuthentication ", "");
        $cas_user = phpCAS::getUser();
        if (!AuthService::userExists($cas_user) && $this->is_AutoCreateUser) {
            AuthService::createUser($cas_user, openssl_random_pseudo_bytes(20));
        }
        if (AuthService::userExists($cas_user)) {
            $res = AuthService::logUser($cas_user, "", true);
            if ($res > 0) {
                return true;
            }
        }

        return false;
    }

    function logOutCAS($action, $httpVars, $fileVars)
    {
        if (!isSet($this->actions[$action])) return;

        switch ($action) {
            case "logoutCAS":
                AuthService::disconnect();
                AJXP_XMLWriter::header("url");
                echo $this->pluginConf["LOGOUT_URL"];
                AJXP_XMLWriter::close("url");
                session_unset();
                session_destroy();
                break;
            default:
                break;
        }
    }
} 