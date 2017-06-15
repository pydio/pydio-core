<?php
/*
 * Copyright 2007-2017 Charles du Jeu <contact (at) cdujeu.me>
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

namespace Pydio\Core\Http\Dav;

use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Exception\LoginException;
use Pydio\Core\Exception\RepositoryLoadException;
use Pydio\Core\Exception\UserNotFoundException;
use Pydio\Core\Exception\WorkspaceForbiddenException;
use Pydio\Core\Exception\WorkspaceNotFoundException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;

use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;

use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Log\Core\Logger;
use \Sabre;

defined('AJXP_EXEC') or die( 'Access not allowed');


/**
 * Class AuthBackendBasic
 * @package Pydio\Core\Http\Dav
 */
class AuthBackendBasic extends Sabre\DAV\Auth\Backend\AbstractBasic
{
    /**
     * @var ContextInterface
     */
    private $context;

    /**
     * Utilitary method to detect basic header.
     * @return bool
     */
    public static function detectBasicHeader()
    {
        if(isSet($_SERVER["PHP_AUTH_USER"])) return true;
        if(isSet($_SERVER["HTTP_AUTHORIZATION"])) $value = $_SERVER["HTTP_AUTHORIZATION"];
        if(!isSet($value) && isSet($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) $value = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
        if(!isSet($value)) return false;
        return  (strpos(strtolower($value),'basic') ===0) ;
    }

    /**
     * AuthBackendBasic constructor.
     * @param ContextInterface $ctx
     */
    public function __construct(ContextInterface $ctx)
    {
        $this->context = $ctx;
    }


    /**
     * @param string $username
     * @param string $password
     * @return bool|void
     */
    protected function validateUserPass($username, $password)
    {
        return UsersService::checkPassword($username, $password, false);
    }

    /**
     * @param Sabre\DAV\Server $server
     * @param string $realm
     * @return bool
     * @throws Sabre\DAV\Exception\NotAuthenticated
     */
    public function authenticate(Sabre\DAV\Server $server, $realm)
    {
        $auth = new Sabre\HTTP\BasicAuth();
        $auth->setHTTPRequest($server->httpRequest);
        $auth->setHTTPResponse($server->httpResponse);
        $auth->setRealm($realm);
        $userpass = $auth->getUserPass();
        if (!$userpass) {
            $auth->requireLogin();
            throw new Sabre\DAV\Exception\NotAuthenticated('No basic authentication headers were found');
        }

        // Authenticates the user
        //AJXP_Logger::info(__CLASS__,"authenticate",$userpass[0]);

        try{
            $userObject = UsersService::getUserById($userpass[0]);
        }catch (UserNotFoundException $e){
            throw new Sabre\DAV\Exception\NotAuthenticated();
        }
        
        $webdavData = $userObject->getPref("AJXP_WEBDAV_DATA");
        $globalActive = ConfService::getGlobalConf("WEBDAV_ACTIVE_ALL");
        if(!empty($webdavData) && isSet($webdavData["ACTIVE"])){
            $active = $webdavData["ACTIVE"];
        } else {
            $active = $globalActive;
        }
        if (!$active) {
            Logger::warning(__CLASS__, "Login failed", array("user" => $userpass[0], "error" => "WebDAV user not found or disabled"));
            throw new Sabre\DAV\Exception\NotAuthenticated();
        }
        // check if there are cached credentials. prevents excessive authentication calls to external
        // auth mechanism.
        $cachedPasswordValid = 0;
        $secret = (defined("AJXP_SECRET_KEY")? AJXP_SECRET_KEY:"\1CDAFx¨op#");
        $encryptedPass = md5($userpass[1].$secret.date('YmdHi'));
        if (isSet($webdavData["TMP_PASS"]) && ($encryptedPass == $webdavData["TMP_PASS"])) {
            $cachedPasswordValid = true;
            //AJXP_Logger::debug("Using Cached Password");
        }

        if (!$cachedPasswordValid && (!$this->validateUserPass($userpass[0],$userpass[1]))) {
            Logger::warning(__CLASS__, "Login failed", array("user" => $userpass[0], "error" => "Invalid WebDAV user or password"));
            $auth->requireLogin();
            throw new Sabre\DAV\Exception\NotAuthenticated('Username or password does not match');
        }
        $this->currentUser = $userpass[0];

        try{
            $loggedUser = AuthService::logUser($this->currentUser, $userpass[1], true);
        }catch (LoginException $l){
            throw new Sabre\DAV\Exception\NotAuthenticated();
        }
        $this->updateCurrentUserRights($loggedUser);
        if($this->context->hasRepository()){
            $repoId = $this->context->getRepositoryId();
            try{
                $repoObject = UsersService::getRepositoryWithPermission($loggedUser, $repoId);
            }catch (WorkspaceForbiddenException $e){
                throw new Sabre\DAV\Exception\NotAuthenticated('You are not allowed to access this workspace');
            }catch (WorkspaceNotFoundException $e){
                throw new Sabre\DAV\Exception\NotAuthenticated('Could not find workspace!');
            }catch (RepositoryLoadException $e){
                throw new Sabre\DAV\Exception\NotAuthenticated('Error while loading workspace');
            }catch (\Exception $e){
                throw new Sabre\DAV\Exception\NotAuthenticated('Error while loading workspace');
            }
            if($repoObject->getContextOption($this->context, "AJXP_WEBDAV_DISABLED", false)){
                throw new Sabre\DAV\Exception\NotAuthenticated('WebDAV access is disabled for this workspace');
            }
            $this->context->setRepositoryObject($repoObject);
        }

        // NOW UPDATE CONTEXT
        $this->context->setUserId($this->currentUser);
        if (ConfService::getContextConf($this->context, "SESSION_SET_CREDENTIALS", "auth")) {
            MemorySafe::storeCredentials($this->currentUser, $userpass[1]);
        }
        //PluginsService::getInstance($this->context);
        Logger::updateContext($this->context);
        TextEncoder::updateContext($this->context);

        // the method used here will invalidate the cached password every minute on the minute
        if (!$cachedPasswordValid) {
            $webdavData["TMP_PASS"] = $encryptedPass;
            $userObject->setPref("AJXP_WEBDAV_DATA", $webdavData);
            $userObject->save("user");
            AuthService::updateSessionUser($userObject);
        }

        return true;
    }


    /**
     * @param UserInterface $user
     * @return bool
     * @throws Sabre\DAV\Exception\NotAuthenticated
     */
    protected function updateCurrentUserRights($user)
    {
        if (!$this->context->hasRepository()) {
            return true;
        }
        if (!$user->canSwitchTo($this->context->getRepositoryId())) {
            throw new Sabre\DAV\Exception\NotAuthenticated();
        }
        return true;
    }


}
