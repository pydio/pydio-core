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
namespace Pydio\Auth\Frontend;

use phpCAS;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Model\Context;
use Pydio\Core\Services\AuthService;
use Pydio\Auth\Frontend\Core\AbstractAuthFrontend;
use Pydio\Core\Services\ConfService;
use Pydio\Conf\Sql\SqlConfDriver;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\DBHelper;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

require_once 'CAS.php';
require_once 'CAS/ProxiedService/Samba.php';

define('PHPCAS_MODE_CLIENT', 'client');
define('PHPCAS_MODE_PROXY', 'proxy');

/**
 * Class CasAuthFrontend
 * @package Pydio\Auth\Frontend
 */
class CasAuthFrontend extends AbstractAuthFrontend
{

    private $cas_server;
    private $cas_port;
    private $cas_uri;
    private $is_AutoCreateUser;
    private $cas_logoutUrl;
    //private $forceRedirect;

    private $cas_mode; // client | proxy
    private $cas_certificate_path;
    private $cas_proxied_service; //
    private $pgt_storage_mode; //file | db

    private $cas_debug_mode;
    private $cas_debug_file;
    private $cas_modify_login_page;
    private $cas_additional_role;
    private $cas_setFixedCallbackURL;


    /**
     * Try to authenticate the user based on various external parameters
     * Return true if user is now logged.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param bool $isLast Whether this is is the last plugin called.
     * @return bool
     */
    function tryToLogUser(ServerRequestInterface &$request, ResponseInterface &$response, $isLast = false)
    {
        $httpVars = $request->getParsedBody();

        if (ApplicationState::hasMinisiteHash()) {
            return false;
        }

        $this->loadConfig();

        if (isset($_SESSION['AUTHENTICATE_BY_CAS'])) {
            $flag = $_SESSION['AUTHENTICATE_BY_CAS'];
        } else {
            $flag = 0;
        }

        $pgtIou = !empty($httpVars['pgtIou']);
        $logged = isset($_SESSION['LOGGED_IN_BY_CAS']);
        $enre = !empty($httpVars['put_action_enable_redirect']);
        $ticket = !empty($httpVars['ticket']);
        $pgt = !empty($_SESSION['phpCAS']['pgt']);
        $clientModeTicketPendding = isset($_SESSION['AUTHENTICATE_BY_CAS_CLIENT_MOD_TICKET_PENDDING']);

        if ($this->cas_modify_login_page) {

            if (($flag == 0) && ($enre) && !$logged && !$pgtIou) {
                $_SESSION['AUTHENTICATE_BY_CAS'] = 1;
            } elseif (($flag == 1) && (!$enre) && !$logged && !$pgtIou && !$ticket && !$pgt) {
                $_SESSION['AUTHENTICATE_BY_CAS'] = 0;
            } elseif (($flag == 1) && ($enre) && !$logged && !$pgtIou) {
                $_SESSION['AUTHENTICATE_BY_CAS'] = 1;
            } elseif ($pgtIou || $pgt) {
                $_SESSION['AUTHENTICATE_BY_CAS'] = 1;
            } elseif ($ticket) {
                $_SESSION['AUTHENTICATE_BY_CAS'] = 1;
                $_SESSION['AUTHENTICATE_BY_CAS_CLIENT_MOD_TICKET_PENDDING'] = 1;
            } elseif ($logged && $pgtIou) {
                $_SESSION['AUTHENTICATE_BY_CAS'] = 2;
            } else {
                $_SESSION['AUTHENTICATE_BY_CAS'] = 0;
            }
            if ($_SESSION['AUTHENTICATE_BY_CAS'] < 1) {
                if ($clientModeTicketPendding) {
                    unset($_SESSION['AUTHENTICATE_BY_CAS_CLIENT_MOD_TICKET_PENDDING']);
                } else {
                    return false;
                }
            }
        }

        /**
         * Depend on phpCAS mode configuration
         */
        switch ($this->cas_mode) {
            case PHPCAS_MODE_CLIENT:
                if ($this->checkConfigurationForClientMode()) {

                    Logger::debug(__FUNCTION__, "Start phpCAS mode Client: ", "sucessfully");

                    phpCAS::client(CAS_VERSION_2_0, $this->cas_server, $this->cas_port, $this->cas_uri, false);

                    if (!empty($this->cas_certificate_path)) {
                        phpCAS::setCasServerCACert($this->cas_certificate_path);
                    } else {
                        phpCAS::setNoCasServerValidation();
                    }

                    /**
                     * Debug
                     */
                    if ($this->cas_debug_mode) {
                        // logfile name by date:
                        $today = getdate();
                        $file_path = AJXP_DATA_PATH . '/logs/phpcas_' . $today['year'] . '-' . $today['month'] . '-' . $today['mday'] . '.txt';
                        $file_path = empty($this->cas_debug_file) ? $file_path : $this->cas_debug_file;
                        phpCAS::setDebug($file_path);
                    }

                    phpCAS::forceAuthentication();

                } else {
                    Logger::error(__FUNCTION__, "Could not start phpCAS mode CLIENT, please verify the configuration", "");
                    return false;
                }
                break;
            case PHPCAS_MODE_PROXY:
                /**
                 * If in login page, user click on login via CAS, the page will be reload with manuallyredirectocas is set.
                 * Or force redirect to cas login page even the force redirect is set in configuration of this module
                 *
                 */

                if ($this->checkConfigurationForProxyMode()) {
                    Logger::info(__FUNCTION__, "Start phpCAS mode Proxy: ", "sucessfully");
                    /**
                     * init phpCAS in mode proxy
                     */

                    phpCAS::proxy(CAS_VERSION_2_0, $this->cas_server, $this->cas_port, $this->cas_uri, false);

                    if (!empty($this->cas_certificate_path)) {
                        phpCAS::setCasServerCACert($this->cas_certificate_path);
                    } else {
                        phpCAS::setNoCasServerValidation();
                    }

                    /**
                     * Debug
                     */
                    if ($this->cas_debug_mode) {
                        // logfile name by date:
                        $today = getdate();
                        $file_path = AJXP_DATA_PATH . '/logs/phpcas_' . $today['year'] . '-' . $today['month'] . '-' . $today['mday'] . '.txt';
                        empty($this->cas_debug_file) ? $file_path : $file_path = $this->cas_debug_file;
                        phpCAS::setDebug($file_path);
                    }

                    if (!empty($this->cas_setFixedCallbackURL)) {
                        phpCAS::setFixedCallbackURL($this->cas_setFixedCallbackURL);
                    }
                    //
                    /**
                     * PTG storage
                     */
                    $this->setPTGStorage();

                    phpCAS::forceAuthentication();
                    /**
                     * Get proxy ticket (PT) for SAMBA to authentication at CAS via pam_cas
                     * In fact, we can use any other service. Of course, it should be enabled in CAS
                     *
                     */
                    $err_code = null;
                    $serviceURL = $this->cas_proxied_service;
                    Logger::debug(__FUNCTION__, "Try to get proxy ticket for service: ", $serviceURL);
                    $res = phpCAS::serviceSMB($serviceURL, $err_code);

                    if (!empty($res)) {
                        $_SESSION['PROXYTICKET'] = $res;
                        Logger::debug(__FUNCTION__, "Get Proxy ticket successfully ", "");
                    } else {
                        Logger::debug(__FUNCTION__, "Could not get Proxy ticket. ", "");
                    }
                    break;
                } else {
                    Logger::error(__FUNCTION__, "Could not start phpCAS mode PROXY, please verify the configuration", "");
                    return false;
                }

            default:
                return false;
                break;
        }

        Logger::debug(__FUNCTION__, "Call phpCAS::getUser() after forceAuthentication ", "");
        $cas_user = phpCAS::getUser();
        if (!UsersService::userExists($cas_user) && $this->is_AutoCreateUser) {
            UsersService::createUser($cas_user, openssl_random_pseudo_bytes(20));
        }
        if (UsersService::userExists($cas_user)) {
            try {
                $userObj = AuthService::logUser($cas_user, "", true);
                AuthService::updateSessionUser($userObj);
                MemorySafe::storeCredentials($cas_user, $_SESSION['PROXYTICKET'], 'authfront.cas');
                $_SESSION['LOGGED_IN_BY_CAS'] = true;

                if (!empty($this->cas_additional_role)) {
                    $userObj = UsersService::getUserById($cas_user, false);
                    $cas_RoleID = $this->cas_additional_role;
                    $userObj->addRole(RolesService::getOrCreateRole($cas_RoleID, $userObj->getGroupPath()));
                    //AuthService::updateUser($userObj);
                }

                // try to log to DEFAULT START REPO
                /*
                 * WILL BE APPLIED LATER
                $userObj->recomputeMergedRole();
                $loggedUser = $userObj;
                $force = $loggedUser->mergedRole->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
                $passId = -1;
                if (isSet($httpVars["tmp_repository_id"])) {
                    $passId = $httpVars["tmp_repository_id"];
                } else if ($force != "" && $loggedUser->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"]) && !isSet($_SESSION["PENDING_REPOSITORY_ID"])) {
                    $passId = $force;
                }
                ConfService::switchUserToActiveRepository($loggedUser, $passId);
                */

                $request = $request->withAttribute("ctx", Context::contextWithObjects($userObj, null));
                return true;
            } catch (\Pydio\Core\Exception\LoginException $l) {

            }
        }

        return false;
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    function logOutCAS(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $action = $requestInterface->getAttribute("action");
        if ($action !== "logout") return;

        require_once "LogoutUrlMessage.php";
        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($x);

        if (isset($_SESSION['LOGGED_IN_BY_CAS'])) {
            AuthService::disconnect();

            $this->loadConfig();
            if (!empty($this->pluginConf["LOGOUT_URL"])) {
                $this->cas_logoutUrl = trim($this->pluginConf["LOGOUT_URL"]);
            } else {
                empty($this->pluginConf["CAS_URI"]) ? $logout_default = 'logout' : $logout_default = '/logout';
                $this->cas_logoutUrl = 'https://' . $this->cas_server . ':' . $this->cas_port . $this->cas_uri . '/logout';
            }

            $x->addChunk(new LogoutUrlMessage($this->cas_logoutUrl));
            session_unset();
            session_destroy();
        } else {
            AuthService::disconnect();
            $x->addChunk(new LogoutUrlMessage("#"));
            session_unset();
            session_destroy();
        }

    }

    function loadConfig()
    {
        if (!empty($this->pluginConf["CAS_SERVER"])) {
            $this->cas_server = trim($this->pluginConf["CAS_SERVER"]);
        }

        if (!empty($this->pluginConf["CAS_PORT"])) {
            $this->cas_port = intval($this->pluginConf["CAS_PORT"]);
        } else {
            $this->cas_port = 443;
        }

        if (!empty($this->pluginConf["CAS_URI"])) {
            $this->cas_uri = trim($this->pluginConf["CAS_URI"]);
        } else {
            $this->cas_uri = "/";
        }

        if (!empty($this->pluginConf["CREATE_USER"])) {
            $this->is_AutoCreateUser = ($this->pluginConf["CREATE_USER"] == "true");
        }

        if (!empty($this->pluginConf["LOGOUT_URL"])) {
            $this->cas_logoutUrl = trim($this->pluginConf["LOGOUT_URL"]);
        } else {
            empty($this->pluginConf["CAS_URI"]) ? $logout_default = 'logout' : $logout_default = '/logout';
            $this->cas_logoutUrl = 'https://' . $this->cas_server . ':' . $this->cas_port . $this->cas_uri . '/logout';
        }

        if (!empty($this->pluginConf["ADDITIONAL_ROLE"])) {
            $this->cas_additional_role = $this->pluginConf["ADDITIONAL_ROLE"];
        }

        if (!empty($this->pluginConf["PHPCAS_MODE"]["casmode"])) {
            $this->cas_mode = $this->pluginConf["PHPCAS_MODE"]["casmode"];
        }
        else{
            $this->cas_mode = PHPCAS_MODE_CLIENT;
        }
        
        if (!empty($this->pluginConf["CERTIFICATE_PATH"])) {
            $this->cas_certificate_path = trim($this->pluginConf["CERTIFICATE_PATH"]);
        }
        if (!empty($this->pluginConf["PHPCAS_MODE"]["PTG_STORE_MODE"])) {
            $this->pgt_storage_mode = $this->pluginConf["PHPCAS_MODE"]["PTG_STORE_MODE"];
        }
        if (!empty($this->pluginConf["PHPCAS_MODE"]["PROXIED_SERVICE_SMB"])) {
            $this->cas_proxied_service = $this->pluginConf["PHPCAS_MODE"]["PROXIED_SERVICE_SMB"];
        }
        if (!empty($this->pluginConf["PHPCAS_MODE"]["FIXED_CALLBACK_URL"])) {
            $this->cas_setFixedCallbackURL = $this->pluginConf["PHPCAS_MODE"]["FIXED_CALLBACK_URL"];
        }
        if (!empty($this->pluginConf["DEBUG_MODE"])) {
            $this->cas_debug_mode = $this->pluginConf["DEBUG_MODE"];
        }
        if (!empty($this->pluginConf["DEBUG_FILE"]) && $this->cas_debug_mode) {
            $this->cas_debug_file = trim($this->pluginConf["DEBUG_FILE"]);
        }

        if (!empty($this->pluginConf["MODIFY_LOGIN_SCREEN"])) {
            $this->cas_modify_login_page = trim($this->pluginConf["MODIFY_LOGIN_SCREEN"]);
        }
    }

    /**
     * @return bool
     */
    function checkConfigurationForClientMode()
    {
        return !empty($this->cas_server) &&
        (strcmp($this->cas_mode, PHPCAS_MODE_CLIENT) === 0);
    }

    /**
     * @return bool
     */
    function checkConfigurationForProxyMode()
    {
        return !empty($this->cas_server) &&
        !empty($this->cas_uri) &&
        (strcmp($this->cas_mode, PHPCAS_MODE_PROXY) === 0) &&
        !empty($this->cas_proxied_service);
    }

    private function setPTGStorage()
    {
        switch (strtolower($this->pgt_storage_mode)) {
            case 'file':
                phpCAS::setPGTStorageFile(session_save_path());
                break;
            case 'db':
                $dbconfig = ConfService::getConfStorageImpl();
                /**
                 * support only for mySQL
                 */
                if ($dbconfig instanceof SqlConfDriver) {
                    if (!empty($dbconfig->sqlDriver["username"])) {
                        $db_username = $dbconfig->sqlDriver["username"];
                        $db_password = $dbconfig->sqlDriver["password"];
                        $db_database = "mysql:" . "dbname=" . $dbconfig->sqlDriver["database"] . ";host=" . $dbconfig->sqlDriver["host"];
                        $db_table = "ajxp_cas_pgt";
                        Logger::debug(__CLASS__, __FUNCTION__, $db_database);
                        phpCAS::setPGTStorageDb($db_database, $db_username, $db_password, $db_table, "");
                    }
                }
                break;
            default:
                break;
        }
    }

    /**
     * @return string
     */
    public function installSQLTables()
    {
        $param = ConfService::getConfStorageImpl();
        $p = $param->sqlDriver;
        return DBHelper::runCreateTablesQuery($p, $this->getBaseDir() . '/createPGTStorage.mysql');
    }
}
