<?php
/*
 * Copyright 2007-2013 Charles du Jeu <contact (at) cdujeu.me>
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


class AJXP_Sabre_AuthBackendBasic extends Sabre\DAV\Auth\Backend\AbstractBasic
{
    protected $currentUser;
    private $repositoryId;

    /**
     * Utilitary method to detect basic header.
     * @return bool
     */
    public static function detectBasicHeader()
    {
        if(isSet($_SERVER["PHP_AUTH_USER"])) return true;
        if(isSet($_SERVER["HTTP_AUTHORIZATION"])) $value = $_SERVER["HTTP_AUTHORIZATION"];
        if(!isSet($value) && isSet($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) $value = $_SERVER["HTTP_AUTHORIZATION"];
        if(!isSet($value)) return false;
        return  (strpos(strtolower($value),'basic') ===0) ;
    }

    public function __construct($repositoryId)
    {
        $this->repositoryId = $repositoryId;
    }


    protected function validateUserPass($username, $password)
    {
        // Warning, this can only work if TRANSMIT_CLEAR_PASS is true;
        return AuthService::checkPassword($username, $password, false, -1);
    }

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

        $confDriver = ConfService::getConfStorageImpl();
        $userObject = $confDriver->createUserObject($userpass[0]);
        $webdavData = $userObject->getPref("AJXP_WEBDAV_DATA");
        if (empty($webdavData) || !isset($webdavData["ACTIVE"]) || $webdavData["ACTIVE"] !== true) {
            AJXP_Logger::warning(__CLASS__, "Login failed", array("user" => $userpass[0], "error" => "WebDAV user not found or disabled"));
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
            AJXP_Logger::warning(__CLASS__, "Login failed", array("user" => $userpass[0], "error" => "Invalid WebDAV user or password"));
            $auth->requireLogin();
            throw new Sabre\DAV\Exception\NotAuthenticated('Username or password does not match');
        }
        $this->currentUser = $userpass[0];

        $res = AuthService::logUser($this->currentUser, $userpass[1], true);
        if ($res < 1) {
          throw new Sabre\DAV\Exception\NotAuthenticated();
        }
        $this->updateCurrentUserRights(AuthService::getLoggedUser());
        if (ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")) {
            AJXP_Safe::storeCredentials($this->currentUser, $userpass[1]);
        }
        if(isSet($this->repositoryId) && ConfService::getRepositoryById($this->repositoryId)->getOption("AJXP_WEBDAV_DISABLED") === true){
            throw new Sabre\DAV\Exception\NotAuthenticated('You are not allowed to access this workspace');
        }
        ConfService::switchRootDir($this->repositoryId);
        // the method used here will invalidate the cached password every minute on the minute
        if (!$cachedPasswordValid) {
            $webdavData["TMP_PASS"] = $encryptedPass;
            $userObject->setPref("AJXP_WEBDAV_DATA", $webdavData);
            $userObject->save("user");
            AuthService::updateUser($userObject);
        }

        return true;
    }


    /**
     * @param AbstractAjxpUser $user
     * @return bool
     */
    protected function updateCurrentUserRights($user)
    {
        if ($this->repositoryId == null) {
            return true;
        }
        if (!$user->canSwitchTo($this->repositoryId)) {
            throw new Sabre\DAV\Exception\NotAuthenticated();
        }
    }


}
