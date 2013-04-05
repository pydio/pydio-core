<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');


class AJXP_Sabre_AuthBackendBasic extends Sabre_DAV_Auth_Backend_AbstractBasic{

    protected $currentUser;
    private $repositoryId;

    function __construct($repositoryId){
        $this->repositoryId = $repositoryId;
    }


	protected function validateUserPass($username, $password) {
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->checkPassword($username, $password, "");
	}
	
    public function authenticate(Sabre_DAV_Server $server, $realm){
        $auth = new Sabre_HTTP_BasicAuth();
        $auth->setHTTPRequest($server->httpRequest);
        $auth->setHTTPResponse($server->httpResponse);
        $auth->setRealm($realm);
        $userpass = $auth->getUserPass();
        if (!$userpass) {
            $auth->requireLogin();
            throw new Sabre_DAV_Exception_NotAuthenticated('No basic authentication headers were found');
        }

        // Authenticates the user
		//AJXP_Logger::logAction("authenticate: " . $userpass[0]);

		$confDriver = ConfService::getConfStorageImpl();
		$userObject = $confDriver->createUserObject($userpass[0]);
		$webdavData = $userObject->getPref("AJXP_WEBDAV_DATA");
		$repository = ConfService::getRepositoryById($repositoryId);
		if (empty($webdavData) || !isset($webdavData["ACTIVE"]) || $webdavData["ACTIVE"] !== true) {
			return false;
		}
        //  check if there are cached credentials. prevents excessive ldap auths.
		$encryptedPass = md5($userpass[1]);
        $cachedPasswordValid = 0;
		//AJXP_Logger::logAction("Checking " . $encryptedPass . " against cache.");
		foreach ($webdavData as $cacheEncryptedPass => $cacheExpiry) {
			//AJXP_Logger::logAction("  Checking: ". $cacheEncryptedPass . "/" . $cacheExpiry);
			if ($cacheExpiry < time()) {
				unset($webdavData[$cacheEncryptedPass]);
			} else {
				if ($cacheEncryptedPass == $encryptedPass) {
					$cachedPasswordValid = true;
					//AJXP_Logger::logAction("Found valid cached password.");
				}
			}
		}

        if (!$cachedPasswordValid && (!$this->validateUserPass($userpass[0],$userpass[1]))) {
            $auth->requireLogin();
            throw new Sabre_DAV_Exception_NotAuthenticated('Username or password does not match');
        }
        $this->currentUser = $userpass[0];

		AuthService::logUser($this->currentUser, null, true);
		$res = $this->updateCurrentUserRights(AuthService::getLoggedUser());
		if($res === false){
			return false;
		}

		// cache the credentials for 15 mins (900 seconds, adjusted to 90 during testing)
		if (!$cachedPasswordValid) {
			$webdavData[$encryptedPass] = time() + 90;
			$userObject->setPref("AJXP_WEBDAV_DATA", $webdavData);
			$userObject->save("user");
			AuthService::updateUser($userObject);
		}

        return true;
    }


    protected function updateCurrentUserRights($user){
        if(!$user->canSwitchTo($this->repositoryId)){
            return false;
        }
        return true;
    }


}
