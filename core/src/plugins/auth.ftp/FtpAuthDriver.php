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
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;

use Pydio\Core\PluginFramework\PluginsService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Authenticate users against an FTP server
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class FtpAuthDriver extends AbstractAuthDriver
{
    public $driverName = "ftp";

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        if (!isset($this->options["FTP_LOGIN_SCREEN"]) || $this->options["FTP_LOGIN_SCREEN"] != "TRUE" || $this->options["FTP_LOGIN_SCREEN"] === false) {
            return;
        }
        // ENABLE WEBFTP LOGIN SCREEN
        $this->logDebug(__FUNCTION__, "Enabling authfront.webftp");
        PluginsService::getInstance($ctx)->getPluginById("authfront.webftp")->enabled = true;
    }

    public function listUsers($baseGroup = "/", $recursive = true)
    {
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        if (isSet($this->options["ADMIN_USER"])) {
            $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        }
        return array($adminUser => $adminUser);
    }

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
        $safeCredentials = MemorySafe::loadCredentials();
        $crtUser = $safeCredentials["user"];
        if (isSet($_SESSION["AJXP_DYNAMIC_FTP_DATA"])) {
            unset($_SESSION["AJXP_DYNAMIC_FTP_DATA"]);
        }
        MemorySafe::clearCredentials();
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        if (isSet($this->options["ADMIN_USER"])) {
            $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        }
        $subUsers = array();
        if ($crtUser != $adminUser && $crtUser != "") {
            ConfService::getConfStorageImpl()->deleteUser($crtUser, $subUsers);
        }
        AuthService::disconnect();
        session_destroy();
        session_write_close();

        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $x->addChunk(new \Pydio\Core\Http\Message\LoggingResult(2));
        $responseInterface = $responseInterface->withBody($x);
    }

    public function setFtpDataCallback(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $options = array("CHARSET", "FTP_DIRECT", "FTP_HOST", "FTP_PORT", "FTP_SECURE", "PATH");
        $ftpOptions = array();
        $httpVars = $requestInterface->getParsedBody();
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
        require_once($this->getBaseDir() . "/FtpSonWrapper.php");
        $wrapper = new \Pydio\Access\Driver\StreamProvider\FTP\FtpSonWrapper();
        try {
            $wrapper->initUrl("ajxp.ftp://fake:fake@$repositoryId/");
        } catch (Exception $e) {
            if ($e->getMessage() == "Cannot login to FTP server with user fake") {
                return "SUCCESS: FTP server successfully contacted.";
            } else {
                return "ERROR: " . $e->getMessage();
            }
        }
        return "SUCCESS: Could succesfully connect to the FTP server!";
    }

    public function checkPassword($login, $pass)
    {
        require_once($this->getBaseDir() . "/FtpSonWrapper.php");
        $wrapper = new \Pydio\Access\Driver\StreamProvider\FTP\FtpSonWrapper();
        $repoId = $this->options["REPOSITORY_ID"];
        try {
            $wrapper->initUrl("ajxp.ftp://" . rawurlencode($login) . ":" . rawurlencode($pass) . "@$repoId/");
            MemorySafe::storeCredentials($login, $pass);
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
