<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
namespace Pydio\OCS\Server\Dav;

defined('AJXP_EXEC') or die('Access not allowed');

use Pydio\Core\Exception\LoginException;
use Pydio\Core\Http\Dav\Collection;
use Pydio\Core\Model\Context;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Share\Store\ShareStore;
use Sabre\DAV;
use Sabre\HTTP;

require_once(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/action.share/vendor/autoload.php");

class AuthSharingBackend extends DAV\Auth\Backend\AbstractBasic
{

    /**
     * @var Collection
     */
    var $rootCollection;
    /**
     * @var array
     */
    var $shareData;

    /**
     * OCS_DavAuthSharingBackend constructor.
     * @param Collection $rootCollection Repository object will be updated once authentication is passed
     */
    public function __construct($rootCollection){
        $this->rootCollection = $rootCollection;
    }

    /**
     * Validates a username and password
     *
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    protected function validateUserPass($username, $password)
    {
        try{
            if(isSet($this->shareData["PRESET_LOGIN"])){
                AuthService::logUser($this->shareData["PRESET_LOGIN"], $password, false, false, -1);
            }else{
                AuthService::logUser($this->shareData["PRELOG_USER"], "", true);
            }
            return true;
        }catch (LoginException $l){
            return false;
        }
    }


    /**
     * Authenticates the user based on the current request.
     *
     * If authentication is successful, true must be returned.
     * If authentication fails, an exception must be thrown.
     *
     * @param DAV\Server $server
     * @param string $realm
     * @throws DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function authenticate(DAV\Server $server, $realm) {

        $auth = new BasicAuthNoPass();
        $auth->setHTTPRequest($server->httpRequest);
        $auth->setHTTPResponse($server->httpResponse);
        $auth->setRealm($realm);
        $userpass = $auth->getUserPass();
        if (!$userpass) {
            $auth->requireLogin();
            throw new DAV\Exception\NotAuthenticated('No basic authentication headers were found');
        }

        // Authenticates the user
        $token = $userpass[0];
        $ctx = new Context($token, null);
        $shareStore = new ShareStore($ctx, ConfService::getGlobalConf("PUBLIC_DOWNLOAD_FOLDER"));
        $shareData = $shareStore->loadShare($token);
        if(is_array($shareData)){
            $this->shareData = $shareData;
        }else{
            $auth->requireLogin();
            throw new DAV\Exception\NotAuthenticated('Username or password does not match');
        }
        if (!$this->validateUserPass($userpass[0],$userpass[1])) {
            $auth->requireLogin();
            throw new DAV\Exception\NotAuthenticated('Username or password does not match');
        }

        $repositoryId = $this->shareData["REPOSITORY"];
        $repository = RepositoryService::getRepositoryById($repositoryId);
        if ($repository == null) {
            $repository = RepositoryService::getRepositoryByAlias($repositoryId);
        }
        if ($repository == null) {
            throw new DAV\Exception\NotAuthenticated('Username cannot access any repository');
        }else{
            $this->rootCollection->updateRepository($repository);
        }

        $this->currentUser = $userpass[0];
        return true;
    }

}