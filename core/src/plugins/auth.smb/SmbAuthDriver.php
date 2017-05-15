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
namespace Pydio\Auth\Driver;

use Exception;
use Pydio\Auth\Core\AbstractAuthDriver;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\AuthService;

use Pydio\Core\Services\RepositoryService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Authenticates user against an SMB server
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class SmbAuthDriver extends AbstractAuthDriver
{
    public $driverName = "smb";

    /**
     *
     * @param string $baseGroup
     * @param bool $recursive
     * @return UserInterface[]
     */
    public function listUsers($baseGroup = "/", $recursive = true)
    {
        $adminUser = $this->options["ADMIN_USER"];
        return array($adminUser => $adminUser);
    }

    /**
     * @param $login
     * @return boolean
     */
    public function userExists($login)
    {
        return true;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     */
    public function logoutCallback(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        MemorySafe::clearCredentials();
        foreach ($_SESSION as $key => $val) {
            if ((substr($key, -4) === "disk") && (substr($key, 0, 4) == "smb_")) {
                unset($_SESSION[$key]);
            }
        }
        AuthService::disconnect();
        session_write_close();
        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $x->addChunk(new \Pydio\Core\Http\Message\LoggingResult(2));
        $responseInterface = $responseInterface->withBody($x);
    }

    /**
     * @param string $login
     * @param string $pass
     * @return bool
     * @throws Exception
     */
    public function checkPassword($login, $pass)
    {
        if (!defined('SMB4PHP_SMBCLIENT')) {
            define('SMB4PHP_SMBCLIENT', $this->options["SMBCLIENT"]);
        }

        require_once(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/access.smb/smb.php");

        $_SESSION["AJXP_SESSION_REMOTE_PASS"] = $pass;
        $repoId = $this->options["REPOSITORY_ID"];
        $repoObject = RepositoryService::getRepositoryById($repoId);
        if (!isSet($repoObject)) throw new Exception("Cannot find repository with id " . $repoId);

        $basePath = $repoObject->getSafeOption("PATH");
        $basePath = str_replace("AJXP_USER", $login, $basePath);
        $host = $repoObject->getSafeOption("HOST");
        $domain = $repoObject->getSafeOption("DOMAIN");

        if (!empty($domain)) {
            $login = $domain . $login;
        }
        $strTmp = "$login:$pass@" . $host . "/" . $basePath . "/";
        $strTmp = str_replace("//", "/", $strTmp);
        $url = "smbclient://" . $strTmp;
        try {
            if (!is_dir($url)) {
                $this->logDebug("SMB Login failure");
                $_SESSION["AJXP_SESSION_REMOTE_PASS"] = '';
                foreach ($_SESSION as $key => $val) {
                    if ((substr($key, -4) === "disk") && (substr($key, 0, 4) == "smb_")) {
                        unset($_SESSION[$key]);
                    }
                }
                return false;
            }
            MemorySafe::storeCredentials($login, $pass);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function usersEditable()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function passwordsEditable()
    {
        return false;
    }

    /**
     * @param $login
     * @param $passwd
     */
    public function createUser($login, $passwd)
    {
    }

    /**
     * @param $login
     * @param $newPass
     */
    public function changePassword($login, $newPass)
    {
    }

    /**
     * @param $login
     */
    public function deleteUser($login)
    {
    }

    /**
     * @param $login
     * @return string
     */
    public function getUserPass($login)
    {
        return "";
    }

}
