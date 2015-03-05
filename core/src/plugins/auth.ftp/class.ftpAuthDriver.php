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

require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/access.ftp/class.ftpAccessWrapper.php");

class ftpSonWrapper extends ftpAccessWrapper
{
    public function initUrl($url)
    {
        $this->parseUrl($url, true);
    }
}

/**
 * Authenticate users against an FTP server
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class ftpAuthDriver extends AbstractAuthDriver
{
    public $driverName = "ftp";

    public function init($options){
        parent::init($options);
        if (!isset($this->options["FTP_LOGIN_SCREEN"]) || $this->options["FTP_LOGIN_SCREEN"] != "TRUE" || $this->options["FTP_LOGIN_SCREEN"] === false){
            return;
        }
        // ENABLE WEBFTP LOGIN SCREEN
        $this->logDebug(__FUNCTION__, "Enabling authfront.webftp");
        AJXP_PluginsService::findPluginById("authfront.webftp")->enabled = true;
    }

    public function listUsers()
    {
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        if (isSet($this->options["ADMIN_USER"])) {
            $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        }
        return array($adminUser => $adminUser);
    }

    public function userExists($login)
    {
        return true ;
    }

    public function logoutCallback($actionName, $httpVars, $fileVars)
    {
        $safeCredentials = AJXP_Safe::loadCredentials();
        $crtUser = $safeCredentials["user"];
        if (isSet($_SESSION["AJXP_DYNAMIC_FTP_DATA"])) {
            unset($_SESSION["AJXP_DYNAMIC_FTP_DATA"]);
        }
        AJXP_Safe::clearCredentials();
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        if (isSet($this->options["ADMIN_USER"])) {
              $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
          }
        $subUsers = array();
        if ($crtUser != $adminUser && $crtUser!="") {
            ConfService::getConfStorageImpl()->deleteUser($crtUser, $subUsers);
        }
        AuthService::disconnect();
        session_destroy();
        session_write_close();
        AJXP_XMLWriter::header();
        AJXP_XMLWriter::loggingResult(2);
        AJXP_XMLWriter::close();
    }

    public function setFtpDataCallback($actionName, $httpVars, $fileVars)
    {
        $options = array("CHARSET", "FTP_DIRECT", "FTP_HOST", "FTP_PORT", "FTP_SECURE", "PATH");
        $ftpOptions = array();
        foreach ($options as $option) {
            if (isSet($httpVars[$option])) {
                $ftpOptions[$option] = $httpVars[$option];
            }
        }
        $_SESSION["AJXP_DYNAMIC_FTP_DATA"] = $ftpOptions;
    }

    public function testParameters($params)
    {
        $this->logDebug("TESTING", $params);
        $repositoryId = $params["REPOSITORY_ID"];
        $wrapper = new ftpSonWrapper();
        try {
            $wrapper->initUrl("ajxp.ftp://fake:fake@$repositoryId/");
        } catch (Exception $e) {
            if ($e->getMessage() == "Cannot login to FTP server with user fake") {
                return "SUCCESS: FTP server successfully contacted.";
            } else {
                return "ERROR: ".$e->getMessage();
            }
        }
        return "SUCCESS: Could succesfully connect to the FTP server!";
    }

    public function checkPassword($login, $pass, $seed)
    {
        $wrapper = new ftpSonWrapper();
        $repoId = $this->options["REPOSITORY_ID"];
        try {
            $wrapper->initUrl("ajxp.ftp://".rawurlencode($login).":".rawurlencode($pass)."@$repoId/");
            AJXP_Safe::storeCredentials($login, $pass);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function usersEditable()
    {
        return false;
    }
    public function passwordsEditable()
    {
        return false;
    }

    public function createUser($login, $passwd)
    {
    }
    public function changePassword($login, $newPass)
    {
    }
    public function deleteUser($login)
    {
    }

    public function getUserPass($login)
    {
        return "";
    }

}
