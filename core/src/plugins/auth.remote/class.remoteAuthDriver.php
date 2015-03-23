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
 * AJXP_Plugin to bridge authentication between Ajxp and external CMS
 *  This class works in 2 modes (master / slave)
    It requires the following arguments:
       - SLAVE_MODE
       - LOGIN_URL
       - LOGOUT_URL
       - SECRET
       - USERS_FILEPATH (the users.ser filepath)

    In master mode, the login dialog is still displayed in AJXP.
       When the user attempt a login, the given credential are sent back to the given remote URL.
       The LOGIN_URL is called as GET LOGIN_URL?name=<entered_user_name>&pass=<entered_password>&key=MD5(name.password.SECRET)
       The method must return a valid PHP serialized object for us to continue (see below)

    In slave mode, the login dialog is not displayed in AJXP.
    If the user directly go to the main page, (s)he's redirected to the LOGIN_URL.
    The logout button isn't displayed either, a back button linking to LOGOUT_URL is used instead.
    The user will log in on the remote site, and the remote script will call us, as GET ajxpPath/plugins/auth.remote/login.php?object=<serialized object>&key=MD5(object.SECRET)

    The serialized object contains the same data as the serialAuthDriver.
 *
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class remoteAuthDriver extends AbstractAuthDriver
{
    public $usersSerFile;
    /** The current authentication mode */
    public $slaveMode;
    /** The current secret */
    public $secret;
    /** The current url array */
    public $urls;

    public function init($options)
    {
        // Migrate new version of the options
        if (isSet($options["CMS_TYPE"])) {
            // Transform MASTER_URL + LOGIN_URI to MASTER_HOST, MASTER_URI, LOGIN_URL, LOGOUT_URI
            $options["SLAVE_MODE"] = "false";
            $cmsOpts = $options["CMS_TYPE"];
            if ($cmsOpts["cms"] != "custom") {
                $loginURI = $cmsOpts["LOGIN_URI"];
                if (strpos($cmsOpts["MASTER_URL"], "http") === 0) {
                    $parse = parse_url($cmsOpts["MASTER_URL"]);
                    $rootHost = $parse["host"];
                    $rootURI = $parse["path"];
                } else {
                    $rootHost = "";
                    $rootURI = $cmsOpts["MASTER_URL"];
                }
                $cmsOpts["MASTER_HOST"] = $rootHost;
                $cmsOpts["LOGIN_URL"] = $cmsOpts["MASTER_URI"] = AJXP_Utils::securePath("/".$rootURI."/".$loginURI);
                $logoutAction = $cmsOpts["LOGOUT_ACTION"];
                switch ($cmsOpts["cms"]) {
                    case "wp":
                        $cmsOpts["LOGOUT_URL"] = ($logoutAction == "back" ? $cmsOpts["MASTER_URL"] : $cmsOpts["MASTER_URL"]."/wp-login.php?action=logout");
                        break;
                    case "joomla":
                        $cmsOpts["LOGOUT_URL"] = $cmsOpts["LOGIN_URL"];
                        break;
                    case "drupal":
                        $cmsOpts["LOGOUT_URL"] = ($logoutAction == "back" ? $cmsOpts["LOGIN_URL"] : $cmsOpts["MASTER_URL"]."/user/logout");
                        break;
                    default:
                        break;
                }
            }
            $options = array_merge($options, $cmsOpts);
        }

        $this->slaveMode = $options["SLAVE_MODE"] == "true";
        if ($this->slaveMode && ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")) {
            $contribs = $this->xPath->query("registry_contributions/external_file");
            foreach ($contribs as $contribNode) {
                if ($contribNode->getAttribute('filename') == 'plugins/core.auth/standard_auth_actions.xml') {
                    $contribNode->parentNode->removeChild($contribNode);
                }
            }
        }
        parent::init($options);
        $options = $this->options;

        $this->usersSerFile = $options["USERS_FILEPATH"];
        $this->secret = $options["SECRET"];
        $this->urls = array($options["LOGIN_URL"], $options["LOGOUT_URL"]);
    }

    public function supportsUsersPagination()
    {
        return true;
    }

    public function listUsers()
    {
        $users = AJXP_Utils::loadSerialFile($this->usersSerFile);
        if (AuthService::ignoreUserCase()) {
            $users = array_combine(array_map("strtolower", array_keys($users)), array_values($users));
        }
        ksort($users);
        return $users;
    }

    public function listUsersPaginated($baseGroup, $regexp, $offset = -1 , $limit = -1, $recursive = true)
    {
        $users = $this->listUsers();
        $result = array();
        $index = 0;
        foreach ($users as $usr => $pass) {
            if (!empty($regexp) && !preg_match("/$regexp/i", $usr)) {
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
    public function getUsersCount($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null)
    {
        return count($this->listUsersPaginated($baseGroup, $regexp));
    }


    public function userExists($login)
    {
        $users = $this->listUsers();
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        if(!is_array($users) || !array_key_exists($login, $users)) return false;
        return true;
    }

    public function checkPassword($login, $pass, $seed)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        global $AJXP_GLUE_GLOBALS;
        if (isSet($AJXP_GLUE_GLOBALS) || (!empty($this->options["LOCAL_PREFIX"]) && strpos($login, $this->options["LOCAL_PREFIX"]) === 0) ) {
            $userStoredPass = $this->getUserPass($login);
            if(!$userStoredPass) return false;
            if ($seed == "-1") { // Seed = -1 means that password is not encoded.
                return  AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass);// ($userStoredPass == md5($pass));
            } else {
                return (md5($userStoredPass.$seed) == $pass);
            }
        } else {
            $crtSessionId = session_id();
            session_write_close();
            $host = "";
            if (isSet($this->options["MASTER_HOST"])) {
                $host = $this->options["MASTER_HOST"];
            } else {
                $host = parse_url($_SERVER["SERVER_ADDR"], PHP_URL_HOST);
            }
            $formId = "";
            if (isSet($this->options["MASTER_AUTH_FORM_ID"])) {
                $formId = $this->options["MASTER_AUTH_FORM_ID"];
            }
            $uri = $this->options["MASTER_URI"];
            $funcName = $this->options["MASTER_AUTH_FUNCTION"];
            require_once 'cms_auth_functions.php';
            if (function_exists($funcName)) {
                $sessCookies = call_user_func($funcName, $host, $uri, $login, $pass, $formId);
                if ($sessCookies != "") {
                    if (is_array($sessCookies)) {
                        $sessid = $sessCookies["AjaXplorer"];
                        session_id($sessid);
                        session_start();
                        if (!$this->slaveMode) {
                            foreach ($sessCookies as $k => $v) {
                                if($k == "AjaXplorer") continue;
                                setcookie($k, urldecode($v), 0, $uri);
                            }
                        }
                    } else if (is_string($sessCookies)) {
                        session_id($sessCookies);
                        session_start();
                    }
                    return true;
                }

                $sessid = call_user_func($funcName, $host, $uri, $login, $pass, $formId);
                if ($sessid != "") {
                    session_id($sessid);
                    session_start();
                    return true;
                }
            }
            // NOW CHECK IN LOCAL USERS LIST
            $userStoredPass = $this->getUserPass($login);
            if(!$userStoredPass) return false;
            if ($seed == "-1") { // Seed = -1 means that password is not encoded.
                $res = AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass); //($userStoredPass == md5($pass));
            } else {
                $res = (md5($userStoredPass.$seed) == $pass);
            }
            if ($res) {
                session_id($crtSessionId);
                session_start();
                return true;
            }
            return false;
        }
    }

    public function createCookieString($login)
    {
        $userPass = $this->getUserPass($login);
        return md5($login.":".$userPass.":ajxp");
    }

    public function usersEditable()
    {
        return true;
    }
    public function passwordsEditable()
    {
        return false;
    }

    public function createUser($login, $passwd)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->listUsers();
        if(!is_array($users)) $users = array();
        if(array_key_exists($login, $users)) return "exists";
        if ($this->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $users[$login] = AJXP_Utils::pbkdf2_create_hash($passwd);
        } else {
            $users[$login] = $passwd;
        }
        AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
    }
    public function changePassword($login, $newPass)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->listUsers();
        if(!is_array($users) || !array_key_exists($login, $users)) return ;
        if ($this->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $users[$login] = AJXP_Utils::pbkdf2_create_hash($newPass);
        } else {
            $users[$login] = $newPass;
        }
        AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
    }
    public function deleteUser($login)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->listUsers();
        if (is_array($users) && array_key_exists($login, $users)) {
            unset($users[$login]);
            AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
        }
    }

    public function getUserPass($login)
    {
        if(!$this->userExists($login)) return false;
        $users = $this->listUsers();
        return $users[$login];
    }

    public function getLoginRedirect()
    {
        return parent::getLoginRedirect();
    }

    public function getLogoutRedirect()
    {
        return $this->urls[1];
    }

}
