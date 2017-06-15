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
namespace Pydio\Core\Http\Dav;

use Pydio\Core\Exception\LoginException;
use Pydio\Core\Exception\RepositoryLoadException;
use Pydio\Core\Exception\UserNotFoundException;
use Pydio\Core\Exception\WorkspaceForbiddenException;
use Pydio\Core\Exception\WorkspaceNotFoundException;


use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Crypto;
use Pydio\Core\Utils\TextEncoder;
use \Sabre;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package Pydio
 * @subpackage SabreDav
 */
class AuthBackendDigest extends Sabre\DAV\Auth\Backend\AbstractDigest
{
    private $secretKey;
    private $repositoryId;
    /**
     * @var ContextInterface
     */
    private $context;

    /**
     * AuthBackendDigest constructor.
     * @param $context
     */
    public function __construct($context)
    {
        $this->context = $context;
        $this->secretKey = Crypto::getApplicationSecret();
    }

    /**
     * @param string $realm
     * @param string $username
     * @return bool|string
     * @throws Sabre\DAV\Exception\NotAuthenticated
     */
    public function getDigestHash($realm, $username)
    {
        try {
            $user = UsersService::getUserById($username);
        } catch (UserNotFoundException $e) {
            throw new Sabre\DAV\Exception\NotAuthenticated();
        }
        $webdavData = $user->getPref("AJXP_WEBDAV_DATA");
        $globalActive = ConfService::getGlobalConf("WEBDAV_ACTIVE_ALL");
        // If the user has no WebDAV Data, no WebDAV can be used
        if (empty($webdavData)) {
            return false;
        }
        // If WebDAV is not globally active and also inactive in user prefs
        if (!$globalActive && (isSet($webdavData["ACTIVE"]) && $webdavData["ACTIVE"] === false)){
            return false;
        }
        if (!isSet($webdavData["PASS"]) && !isset($webdavData["HA1"])) {
            return false;
        }
        if (isSet($webdavData["HA1"])) {
            return $webdavData["HA1"];
        } else {
            $pass = $this->_decodePassword($webdavData["PASS"], $username);
            return md5("{$username}:{$realm}:{$pass}");
        }
    }

    /**
     * @param Sabre\DAV\Server $server
     * @param string $realm
     * @return bool
     * @throws Sabre\DAV\Exception\NotAuthenticated
     */
    public function authenticate(Sabre\DAV\Server $server, $realm)
    {
        //AJXP_Logger::debug("Try authentication on $realm", $server);
        $errmsg = "";
        try {

          $success = parent::authenticate($server, $realm);

        } catch(\Exception $e) {
          $success = 0;
          $errmsg = $e->getMessage();
          if ($errmsg != "No digest authentication headers were found")
            $success = false;
        }

        if ($success) {

            try{
                $loggedUser = AuthService::logUser($this->currentUser, null, true);
            }catch (LoginException $l){
                $this->breakNotAuthenticatedAndRequireLogin($server, $realm, $errmsg);
            }
            $this->updateCurrentUserRights($loggedUser);
        } else {
            if ($success === false) {
                Logger::warning(__CLASS__, "Login failed", array("user" => $this->currentUser, "error" => "Invalid WebDAV user or password"));
            }
            $this->breakNotAuthenticatedAndRequireLogin($server, $realm, $errmsg);
        }

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
        if (ConfService::getContextConf($this->context, "SESSION_SET_CREDENTIALS", "auth")) {
            $webdavData = $loggedUser->getPref("AJXP_WEBDAV_DATA");
            MemorySafe::storeCredentials($this->currentUser, $this->_decodePassword($webdavData["PASS"], $this->currentUser));
        }

        // NOW UPDATE CONTEXT
        $this->context->setUserObject($loggedUser);
        Logger::updateContext($this->context);
        TextEncoder::updateContext($this->context);

        return true;
    }

    /**
     * @param Sabre\DAV\Server $server
     * @param $errmsg
     */
    function breakNotAuthenticatedAndRequireLogin(Sabre\DAV\Server $server, $realm, $errmsg){
        $digest = new Sabre\HTTP\DigestAuth();

        // Hooking up request and response objects
        $digest->setHTTPRequest($server->httpRequest);
        $digest->setHTTPResponse($server->httpResponse);

        $digest->setRealm($realm);
        $digest->init();
        $digest->requireLogin();
        throw new Sabre\DAV\Exception\NotAuthenticated($errmsg);

    }

    /**
     * @param \Pydio\Core\Model\UserInterface $user
     * @return bool
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     */
    protected function updateCurrentUserRights($user)
    {
        if ($this->repositoryId == null) {
            return true;
        }
        if (!$user->canSwitchTo($this->repositoryId)) {
            throw new Sabre\DAV\Exception\NotAuthenticated();
        }
        return true;
    }

    /**
     * @param $encoded
     * @param $user
     * @return string
     */
    private function _decodePassword($encoded, $user)
    {
        $key = Crypto::buildKey($user, Crypto::getApplicationSecret(), $encoded);
        return Crypto::decrypt($encoded, $key);
    }



}
