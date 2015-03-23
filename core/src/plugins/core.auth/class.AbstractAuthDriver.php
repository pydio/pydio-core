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

define('AJXP_FILTER_EMPTY', 'AJXP_FILTER_EMPTY');
define('AJXP_FILTER_NOT_EMPTY', 'AJXP_FILTER_NOT_EMPTY');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @class AbstractAuthDriver
 * Abstract representation of an authentication driver. Must be implemented by the auth plugin
 */
class AbstractAuthDriver extends AJXP_Plugin
{
    public $options;
    public $driverName = "abstract";
    public $driverType = "auth";

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;

        switch ($action) {

            case "get_secure_token" :
                HTMLWriter::charsetHeader("text/plain");
                print AuthService::generateSecureToken();
                //exit(0);
                break;

            //------------------------------------
            //	CHANGE USER PASSWORD
            //------------------------------------
            case "pass_change":

                $userObject = AuthService::getLoggedUser();
                if ($userObject == null || $userObject->getId() == "guest") {
                    header("Content-Type:text/plain");
                    print "SUCCESS";
                    break;
                }
                $oldPass = $httpVars["old_pass"];
                $newPass = $httpVars["new_pass"];
                $passSeed = $httpVars["pass_seed"];
                if (strlen($newPass) < ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth")) {
                    header("Content-Type:text/plain");
                    print "PASS_ERROR";
                    break;
                }
                if (AuthService::checkPassword($userObject->getId(), $oldPass, false, $passSeed)) {
                    AuthService::updatePassword($userObject->getId(), $newPass);
                    if ($userObject->getLock() == "pass_change") {
                        $userObject->removeLock();
                        $userObject->save("superuser");
                    }
                } else {
                    header("Content-Type:text/plain");
                    print "PASS_ERROR";
                    break;
                }
                header("Content-Type:text/plain");
                print "SUCCESS";

                break;



            default;
            break;
        }
        return "";
    }


    public function getRegistryContributions( $extendedVersion = true )
    {
        $this->loadRegistryContributions();
        if(!$extendedVersion) return $this->registryContributions;

        $logged = AuthService::getLoggedUser();
        if (AuthService::usersEnabled()) {
            if ($logged == null) {
                return $this->registryContributions;
            } else {
                $xmlString = AJXP_XMLWriter::getUserXml($logged);
            }
        } else {
            $xmlString = AJXP_XMLWriter::getUserXml(null);
        }
        $dom = new DOMDocument();
        $dom->loadXML($xmlString);
        $this->registryContributions[]=$dom->documentElement;
        return $this->registryContributions;
    }

    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return ;

        if(AuthService::usersEnabled() && $this->passwordsEditable()) return ;
        // Disable password change action
        if(!isSet($actionXpath)) $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $passChangeNodeList = $actionXpath->query('action[@name="pass_change"]', $contribNode);
        if(!$passChangeNodeList->length) return ;
        unset($this->actions["pass_change"]);
        $passChangeNode = $passChangeNodeList->item(0);
        $contribNode->removeChild($passChangeNode);
    }

    /**
     * Old way of prelogging user, replaced by authentication frontends
     * @param String $sessionId
     */
    public function preLogUser($sessionId){}

    /**
     * Wether users can be listed using offset and limit
     * @return bool
     */
    public function supportsUsersPagination()
    {
        return false;
    }

    /**
     * Applicable if supportsUsersPagination(), try to detect at what page the user is
     * @param string $baseGroup
     * @param string $userLogin
     * @param int $usersPerPage
     * @param int $offset
     * @return int
     */
    public function findUserPage($baseGroup, $userLogin, $usersPerPage, $offset = 0){
        return -1;
    }

    /**
     * List users using offsets
     * @param string $baseGroup
     * @param string $regexp
     * @param int $offset
     * @param int $limit
     * @param bool $recursive
     * @return AbstractAjxpUser[]
     */
    public function listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive = true)
    {
        return $this->listUsers($baseGroup, $recursive);
    }

    /**
     * @param string $baseGroup
     * @param string $regexp
     * @param null|string $filterProperty Can be "admin" or "parent"
     * @param null|string $filterValue Can be a user Id, or AJXP_FILTER_EMPTY or AJXP_FILTER_NOT_EMPTY
     * @param bool $recursive
     * @return int
     */
    public function getUsersCount($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null, $recursive = true)
    {
        return -1;
    }

    /**
     *
     * @param string $baseGroup
     * @param bool $recursive
     * @return AbstractAjxpUser[]
     */
    public function listUsers($baseGroup = "/", $recursive = true){}

    /**
     * @param $login
     * @return boolean
     */
    public function userExists($login){}

    /**
     * Alternative method to be used when checking if user exists
     * before creating a new user.
     * @param $login
     * @return bool
     */
    public function userExistsWrite($login)
    {
        return $this->userExists($login);
    }

    /**
     * @param string $login
     * @param string $pass
     * @param string $seed
     * @return bool
     */
    public function checkPassword($login, $pass, $seed){}

    /**
     * @param string $login
     * @return string
     */
    public function createCookieString($login){}



    /**
     * @return bool
     */
    public function usersEditable(){}

    /**
     * @return bool
     */
    public function passwordsEditable(){}

    public function createUser($login, $passwd){}
    public function changePassword($login, $newPass){}
    public function deleteUser($login){}

    public function supportsAuthSchemes()
    {
        return false;
    }
    /**
     * @param $login
     * @return String
     */
    public function getAuthScheme($login)
    {
        return null;
    }

    public function getLoginRedirect()
    {
        if (isSet($this->options["LOGIN_REDIRECT"])) {
            return $this->options["LOGIN_REDIRECT"];
        } else {
            return false;
        }
    }

    public function getLogoutRedirect()
    {
        return false;
    }

    public function getOption($optionName)
    {
        return (isSet($this->options[$optionName])?$this->options[$optionName]:"");
    }

    /**
     * @param $optionName
     * @return bool
     */
    public function getOptionAsBool($optionName)
    {
        return (isSet($this->options[$optionName]) &&
            ($this->options[$optionName] === true || $this->options[$optionName] === 1
                || $this->options[$optionName] === "true" || $this->options[$optionName] === "1")
        );
    }

    public function isAjxpAdmin($login)
    {
        return ($this->getOption("AJXP_ADMIN_LOGIN") === $login);
    }

    public function autoCreateUser()
    {
        $opt = $this->getOption("AUTOCREATE_AJXPUSER");
        if($opt === true) return true;
        return false;
    }

    public function getSeed($new=true)
    {
        if($this->getOptionAsBool("TRANSMIT_CLEAR_PASS")) return -1;
        if ($new) {
            $seed = md5(time());
            $_SESSION["AJXP_CURRENT_SEED"] = $seed;
            return $seed;
        } else {
            return (isSet($_SESSION["AJXP_CURRENT_SEED"])?$_SESSION["AJXP_CURRENT_SEED"]:0);
        }
    }

    public function filterCredentials($userId, $pwd)
    {
        return array($userId, $pwd);
    }

    /**
     * List children groups of a given group. By default will report this on the CONF driver,
     * but can be overriden to grab info directly from auth driver (ldap, etc).
     * @param string $baseGroup
     * @return string[]
     */
    public function listChildrenGroups($baseGroup = "/")
    {
        return ConfService::getConfStorageImpl()->getChildrenGroups($baseGroup);
    }

    /**
     * @param AbstractAjxpUser $userObject
     */
    public function updateUserObject(&$userObject)
    {
        $applyRole = $this->getOption("AUTO_APPLY_ROLE");
        if(!empty($applyRole) && !(is_array($userObject->getRoles()) && array_key_exists($applyRole, $userObject->getRoles())) ){
            $rObject = AuthService::getRole($applyRole, true);
            $userObject->addRole($rObject);
            $userObject->save("superuser");
        }
    }

}
