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
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Bridge with the phpBB users system.
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class phpbbAuthDriver extends serialAuthDriver
{
    public $usersSerFile;
    public $phpbb_root_path;
    public $driverName = "phpbb";

    public function init($options)
    {
        parent::init($options);
        $options = $this->options;

        $this->usersSerFile = $options["USERS_FILEPATH"];
        $this->slaveMode = ($options["SLAVE_MODE"]) ? true : false;
        $this->urls = array($options["LOGIN_URL"], $options["LOGOUT_URL"]);

        global $phpbb_root_path, $phpEx, $user, $db, $config, $cache, $template;
        define('IN_PHPBB', true);
        $phpbb_root_path =  $options["PHPBB_PATH"];
        $phpEx = substr(strrchr(__FILE__, '.'), 1);
        require($phpbb_root_path . 'common.' . $phpEx);
        $user->session_begin();

        if(!$user->data['is_registered'])
            $this->disconnect();

    }

    public function disconnect()
    {
        if (!empty($_SESSION["AJXP_USER"])) {
            unset($_SESSION["AJXP_USER"]);
            session_destroy();
        }
    }

    public function usersEditable() { return false; }

    public function passwordsEditable() { return false; }

    public function preLogUser($sessionId)
    {
        global $user;

        $username = $user->data['username_clean'];
        $password = md5($user->data['user_password']);

        if(!$user->data['is_registered'])
            return false;

        if (!$this->userExists($username)) {
            if ($this->autoCreateUser()) {
                $this->createUser($username, $password);
            } else {
                return false;
            }
        }

        AuthService::logUser($username, '', true);
        return true;
    }

    public function getLoginRedirect()
    {
        if ($this->slaveMode) {
            if (!empty($_SESSION["AJXP_USER"]))
                return false;

            return $this->urls[0];
        }
        return false;
    }

    public function getLogoutRedirect()
    {
        if ($this->slaveMode) {
            return $this->urls[1];
        }
        return false;
    }

}
