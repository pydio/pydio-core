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
 * Authenticates user against an SMB server
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class smbAuthDriver extends AbstractAuthDriver
{
    public $driverName = "smb";

    public function listUsers()
    {
        $adminUser = $this->options["ADMIN_USER"];
        return array($adminUser => $adminUser);
    }

    public function userExists($login)
    {
        return true;
    }

    public function logoutCallback($actionName, $httpVars, $fileVars)
    {
        AJXP_Safe::clearCredentials();
        $adminUser = $this->options["ADMIN_USER"];
        $subUsers = array();
        unset($_SESSION["COUNT"]);
        unset($_SESSION["disk"]);
        AuthService::disconnect();
        session_write_close();
        AJXP_XMLWriter::header();
        AJXP_XMLWriter::loggingResult(2);
        AJXP_XMLWriter::close();
    }

    public function checkPassword($login, $pass, $seed)
    {
       require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/access.smb/smb.php");

        $_SESSION["AJXP_SESSION_REMOTE_PASS"] = $pass;
        $repoId = $this->options["REPOSITORY_ID"];
        $repoObject = ConfService::getRepositoryById($repoId);
        if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);
        $path = "";
        $basePath = $repoObject->getOption("PATH", true);
        $basePath = str_replace("AJXP_USER", $login, $basePath);
        $host = $repoObject->getOption("HOST");
        $domain = $repoObject->getOption("DOMAIN", true);
        $smbPath = $repoObject->getOption("PATH", true);

        if(!empty($domain)){
            $login = $domain.$login;
        }
        $strTmp = "$login:$pass@".$host."/".$basePath."/";
        $strTmp = str_replace("//", "/",$strTmp);
        $url = "smb://".$strTmp;
        try {
            if (!is_dir($url)) {
                $this->logDebug("SMB Login failure");
                $_SESSION["AJXP_SESSION_REMOTE_PASS"] = '';
                unset($_SESSION["COUNT"]);
                unset($_SESSION["disk"]);
                return false;
            }
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
