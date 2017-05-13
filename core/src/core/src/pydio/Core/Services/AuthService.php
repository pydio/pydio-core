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
namespace Pydio\Core\Services;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\LoginException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Utils\Http\BruteForceHelper;
use Pydio\Core\Utils\Http\CookiesHelper;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Static access to the authentication mechanism. Encapsulates the authDriver implementation
 * @package Pydio
 * @subpackage Core
 */
class AuthService
{

    public static $bufferedMessage = null;

    /**
     * Log the user from its credentials
     * @static
     * @param string $user_id The user id
     * @param string $pwd The password
     * @param bool $bypass_pwd Ignore password or not
     * @param bool $cookieLogin Is it a logging from the remember me cookie?
     * @return UserInterface
     * @throws LoginException
     * @throws \Exception
     * @throws \Pydio\Core\Exception\UserNotFoundException
     */
    public static function logUser($user_id, $pwd, $bypass_pwd = false, $cookieLogin = false)
    {
        $user_id = UsersService::filterUserSensitivity($user_id);
        $authDriver = ConfService::getAuthDriverImpl();
        // CHECK USER PASSWORD HERE!
        $loginAttempt = BruteForceHelper::getBruteForceLoginArray();
        $bruteForceLogin = BruteForceHelper::checkBruteForceLogin($loginAttempt);
        BruteForceHelper::setBruteForceLoginArray($loginAttempt);

        if (!$authDriver->userExists($user_id)) {
            Logger::warning(__CLASS__, "Login failed", array("user" => InputFilter::sanitize($user_id, InputFilter::SANITIZE_EMAILCHARS), "error" => "Invalid user"));
            if ($bruteForceLogin === FALSE) {
                throw new LoginException(-4);
            } else {
                throw new LoginException(-1);
            }
        }
        if (!$bypass_pwd) {
            if (!UsersService::checkPassword($user_id, $pwd, $cookieLogin)) {
                Logger::warning(__CLASS__, "Login failed", array("user" => InputFilter::sanitize($user_id, InputFilter::SANITIZE_EMAILCHARS), "error" => "Invalid password"));
                if ($bruteForceLogin === FALSE) {
                    throw new LoginException(-4);
                } else {
                    if($cookieLogin) throw new LoginException(-5);
                    throw new LoginException(-1);
                }
            }
        }
        // Successful login attempt
        BruteForceHelper::setBruteForceLoginArray($loginAttempt, true);

        $user = UsersService::getUserById($user_id, false);

        $tempContext = Context::contextWithObjects($user, null);
        Controller::applyHook("user.before_login", [$tempContext, &$user]);

        // Setting session credentials if asked in config
        if (ConfService::getContextConf($tempContext, "SESSION_SET_CREDENTIALS", "auth") && !empty($pwd)) {
            list($authId, $authPwd) = $authDriver->filterCredentials($user_id, $pwd);
            MemorySafe::storeCredentials($authId, $authPwd);
        }


        if ($user->hasLockByName("logout")) {
            Logger::warning(__CLASS__, "Login failed", array("user" => InputFilter::sanitize($user_id, InputFilter::SANITIZE_EMAILCHARS), "error" => "Locked user"));
            throw new LoginException(-1);
        }

        if ($authDriver->isAjxpAdmin($user_id)) {
            $user->setAdmin(true);
        }

        if ($user->isAdmin()) {
            $user = RolesService::updateAdminRights($user);
        }

        if ($authDriver->autoCreateUser() && !$user->storageExists()) {
            $user->save("superuser"); // make sure update rights now
        }

        //self::updateSessionUser($user);
        Controller::applyHook("user.after_login", [$tempContext, $user]);

        Logger::info(__CLASS__, "Log In", array("context"=> ApplicationState::sapiUsesSession() ?"WebUI":"API"));
        return $user;
    }

    /**
     * Store the object in the session
     * @static
     * @param $userObject
     * @return void
     */
    public static function updateSessionUser($userObject) {
        SessionService::save(SessionService::USER_KEY, $userObject);
    }

    /**
     * Force an acl change for the current session user
     * @param $repositoryId
     * @param $acl
     */
    public static function updateSessionUserAcl($repositoryId, $acl){
        /**
         * @var $u UserInterface
         */
        $u = SessionService::fetch(SessionService::USER_KEY);
        if($u instanceof UserInterface){
            $u->getPersonalRole()->setAcl($repositoryId, $acl);
            $u->recomputeMergedRole();
            self::updateSessionUser($u);
        }
    }


    /**
     * Clear the session
     * @static
     * @return void
     */
    public static function disconnect()
    {
        $user = SessionService::fetch(SessionService::USER_KEY);
        if(empty($user) || !$user instanceof UserInterface){
            return;
        }
        $userId = $user->getId();
        Controller::applyHook("user.before_disconnect", array(Context::emptyContext(), $user));
        CookiesHelper::clearRememberCookie($user);
        Logger::info(__CLASS__, "Log Out", "");
        SessionService::delete(SessionService::USER_KEY);
        SessionService::invalidateLoadedRepositories();
        if (ConfService::getContextConf(Context::contextWithObjects($user, null), "SESSION_SET_CREDENTIALS", "auth")) {
            MemorySafe::clearCredentials();
        }
        Controller::applyHook("user.after_disconnect", array(Context::emptyContext(), $userId));
    }

}