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
 * Standard auth implementation, stores the data in serialized files
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class serialAuthDriver extends AbstractAuthDriver
{
    public $usersSerFile;
    public $driverName = "serial";

    public function init($options)
    {
        parent::init($options);
        $this->usersSerFile = AJXP_VarsFilter::filter($this->getOption("USERS_FILEPATH"));
    }

    public function performChecks()
    {
        if(!isset($this->options)) return;
        if (isset($this->options["FAST_CHECKS"]) && $this->options["FAST_CHECKS"] === true) {
            return;
        }
        $usersDir = dirname($this->usersSerFile);
        if (!is_dir($usersDir) || !is_writable($usersDir)) {
            throw new Exception("Parent folder for users file is either inexistent or not writeable.");
        }
        if (is_file($this->usersSerFile) && !is_writable($this->usersSerFile)) {
            throw new Exception("Users file exists but is not writeable!");
        }
    }

    protected function _listAllUsers()
    {
        $users = AJXP_Utils::loadSerialFile($this->usersSerFile);
        if (AuthService::ignoreUserCase()) {
            $users = array_combine(array_map("strtolower", array_keys($users)), array_values($users));
        }
        ConfService::getConfStorageImpl()->filterUsersByGroup($users, "/", true);
        return $users;
    }

    public function listUsers($baseGroup = "/", $recursive = true)
    {
        $users = AJXP_Utils::loadSerialFile($this->usersSerFile);
        if (AuthService::ignoreUserCase()) {
            $users = array_combine(array_map("strtolower", array_keys($users)), array_values($users));
        }
        ConfService::getConfStorageImpl()->filterUsersByGroup($users, $baseGroup, !$recursive);
        ksort($users);
        return $users;
    }

    public function supportsUsersPagination()
    {
        return true;
    }

    // $baseGroup = "/"
    public function listUsersPaginated($baseGroup, $regexp, $offset = -1 , $limit = -1, $recursive = true)
    {
        $users = $this->listUsers($baseGroup);
        $result = array();
        $index = 0;
        foreach ($users as $usr => $pass) {
            if (!empty($regexp) && !preg_match("/".preg_quote($regexp)."/i", $usr)) {
                continue;
            }
            if ($offset != -1 && $index < $offset) {
                $index ++;
                continue;
            }
            $result[$usr] = $pass;
            $index ++;
            if($limit != -1 && count($result) >= $limit) break;
        }
        return $result;
    }
    public function getUsersCount($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null, $recursive = true)
    {
        return count($this->listUsersPaginated($baseGroup, $regexp, -1, -1, $recursive));
    }


    public function userExists($login)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->_listAllUsers();
        if(!is_array($users) || !array_key_exists($login, $users)) return false;
        return true;
    }

    public function checkPassword($login, $pass, $seed)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $userStoredPass = $this->getUserPass($login);
        if(!$userStoredPass) return false;
        if ($seed == "-1") { // Seed = -1 means that password is not encoded.
            return AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass);//($userStoredPass == md5($pass));
        } else {
            return (md5($userStoredPass.$seed) == $pass);
        }
    }

    public function usersEditable()
    {
        return true;
    }
    public function passwordsEditable()
    {
        return true;
    }

    public function createUser($login, $passwd)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->_listAllUsers();
        if(!is_array($users)) $users = array();
        if(array_key_exists($login, $users)) return "exists";
        if ($this->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $users[$login] = AJXP_Utils::pbkdf2_create_hash($passwd);//md5($passwd);
        } else {
            $users[$login] = $passwd;
        }
        AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
    }
    public function changePassword($login, $newPass)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->_listAllUsers();
        if(!is_array($users) || !array_key_exists($login, $users)) return ;
        if ($this->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $users[$login] = AJXP_Utils::pbkdf2_create_hash($newPass);//md5($newPass);
        } else {
            $users[$login] = $newPass;
        }
        AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
    }
    public function deleteUser($login)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->_listAllUsers();
        if (is_array($users) && array_key_exists($login, $users)) {
            unset($users[$login]);
            AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
        }
    }

    public function getUserPass($login)
    {
        if(!$this->userExists($login)) return false;
        $users = $this->_listAllUsers();
        return $users[$login];
    }

}
