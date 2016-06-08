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
namespace Pydio\Core\Http\Dav;

use Pydio\Core\Services\UsersService;
use \Sabre;
use Pydio\Auth\Core\AJXP_Safe;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Log\Core\AJXP_Logger;

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

    public function __construct($context)
    {
        $this->context = $context;
        if (defined('AJXP_SAFE_SECRET_KEY')) {
            $this->secretKey = AJXP_SAFE_SECRET_KEY;
        } else {
            $this->secretKey = "\1CDAFx¨op#";
        }
    }

    public function getDigestHash($realm, $username)
    {
        if (!UsersService::userExists($username)) {
            return false;
        }
        $confDriver = ConfService::getConfStorageImpl();
        $user = $confDriver->createUserObject($username);
        $webdavData = $user->getPref("AJXP_WEBDAV_DATA");
        if (empty($webdavData) || !isset($webdavData["ACTIVE"]) || $webdavData["ACTIVE"] !== true || (!isSet($webdavData["PASS"]) && !isset($webdavData["HA1"]) ) ) {
            return false;
        }
        if (isSet($webdavData["HA1"])) {
            return $webdavData["HA1"];
        } else {
            $pass = $this->_decodePassword($webdavData["PASS"], $username);
            return md5("{$username}:{$realm}:{$pass}");
        }

    }

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
            $res = AuthService::logUser($this->currentUser, null, true);
            if ($res < 1) {
                throw new Sabre\DAV\Exception\NotAuthenticated();
            }
            $this->updateCurrentUserRights(AuthService::getLoggedUser());
            if (ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")) {
                $webdavData = AuthService::getLoggedUser()->getPref("AJXP_WEBDAV_DATA");
                AJXP_Safe::storeCredentials($this->currentUser, $this->_decodePassword($webdavData["PASS"], $this->currentUser));
            }
        } else {
          if ($success === false) {
            AJXP_Logger::warning(__CLASS__, "Login failed", array("user" => $this->currentUser, "error" => "Invalid WebDAV user or password"));
          }
          throw new Sabre\DAV\Exception\NotAuthenticated($errmsg);
        }
        ConfService::switchRootDir($this->repositoryId);

        // NOW UPDATE CONTEXT
        $this->context->setUserId($this->currentUser);
        $this->context->setRepositoryId(ConfService::getCurrentRepositoryId());

        return true;
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

    private function _decodePassword($encoded, $user)
    {
        if (function_exists('mcrypt_decrypt')) {
            $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
            $encoded = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($user.$this->secretKey), base64_decode($encoded), MCRYPT_MODE_ECB, $iv), "\0");
        }
        return $encoded;
    }



}
