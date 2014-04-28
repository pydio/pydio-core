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
        $mess = ConfService::getMessages();

        switch ($action) {

            case "login" :

                if(!AuthService::usersEnabled()) return;
                $rememberLogin = "";
                $rememberPass = "";
                $secureToken = "";
                $loggedUser = null;
                include_once(AJXP_BIN_FOLDER."/class.CaptchaProvider.php");
                if (AuthService::suspectBruteForceLogin() && (!isSet($httpVars["captcha_code"]) || !CaptchaProvider::checkCaptchaResult($httpVars["captcha_code"]))) {
                    $loggingResult = -4;
                } else {
                    $userId = (isSet($httpVars["userid"])?trim($httpVars["userid"]):null);
                    $userPass = (isSet($httpVars["password"])?trim($httpVars["password"]):null);
                    $rememberMe = ((isSet($httpVars["remember_me"]) && $httpVars["remember_me"] == "true")?true:false);
                    $cookieLogin = (isSet($httpVars["cookie_login"])?true:false);
                    $loggingResult = AuthService::logUser($userId, $userPass, false, $cookieLogin, $httpVars["login_seed"]);
                    if ($rememberMe && $loggingResult == 1) {
                        $rememberLogin = "notify";
                        $rememberPass = "notify";
                        $loggedUser = AuthService::getLoggedUser();
                    }
                    if ($loggingResult == 1) {
                        session_regenerate_id(true);
                        $secureToken = AuthService::generateSecureToken();
                    }
                    if ($loggingResult < 1 && AuthService::suspectBruteForceLogin()) {
                        $loggingResult = -4; // Force captcha reload
                    }
                }
                $loggedUser = AuthService::getLoggedUser();
                if ($loggedUser != null) {
                       $force = $loggedUser->mergedRole->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
                       $passId = -1;
                       if (isSet($httpVars["tmp_repository_id"])) {
                           $passId = $httpVars["tmp_repository_id"];
                       } else if ($force != "" && $loggedUser->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"]) && !isSet($_SESSION["PENDING_REPOSITORY_ID"])) {
                           $passId = $force;
                       }
                       $res = ConfService::switchUserToActiveRepository($loggedUser, $passId);
                       if (!$res) {
                           AuthService::disconnect();
                           $loggingResult = -3;
                       }
                   }

                if ($loggedUser != null && (AuthService::hasRememberCookie() || (isSet($rememberMe) && $rememberMe ==true))) {
                    AuthService::refreshRememberCookie($loggedUser);
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::loggingResult($loggingResult, $rememberLogin, $rememberPass, $secureToken);
                AJXP_XMLWriter::close();


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

            case "logout" :

                AuthService::disconnect();
                $loggingResult = 2;
                session_destroy();
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::loggingResult($loggingResult, null, null, null);
                AJXP_XMLWriter::close();


            break;

            case "get_seed" :
                $seed = AuthService::generateSeed();
                if (AuthService::suspectBruteForceLogin()) {
                    HTMLWriter::charsetHeader('application/json');
                    print json_encode(array("seed" => $seed, "captcha" => true));
                } else {
                    HTMLWriter::charsetHeader("text/plain");
                    print $seed;
                }
                //exit(0);
            break;

            case "get_secure_token" :
                HTMLWriter::charsetHeader("text/plain");
                print AuthService::generateSecureToken();
                //exit(0);
            break;

            case "get_captcha":
                include_once(AJXP_BIN_FOLDER."/class.CaptchaProvider.php");
                CaptchaProvider::sendCaptcha();
                //exit(0) ;
            break;

            case "back":
                AJXP_XMLWriter::header("url");
                  echo AuthService::getLogoutAddress(false);
                  AJXP_XMLWriter::close("url");
                //exit(1);

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
                $xmlString = AJXP_XMLWriter::getUserXml($logged, false);
            }
        } else {
            $xmlString = AJXP_XMLWriter::getUserXml(null, false);
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
        if (AJXP_Utils::detectApplicationFirstRun()) {
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $passChangeNodeList = $actionXpath->query('action[@name="login"]', $contribNode);
            if(!$passChangeNodeList->length) return ;
            unset($this->actions["login"]);
            $passChangeNode = $passChangeNodeList->item(0);
            $contribNode->removeChild($passChangeNode);
        }

        if(AuthService::usersEnabled() && $this->passwordsEditable()) return ;
        // Disable password change action
        if(!isSet($actionXpath)) $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $passChangeNodeList = $actionXpath->query('action[@name="pass_change"]', $contribNode);
        if(!$passChangeNodeList->length) return ;
        unset($this->actions["pass_change"]);
        $passChangeNode = $passChangeNodeList->item(0);
        $contribNode->removeChild($passChangeNode);
    }

    public function preLogUser($sessionId){}

    public function supportsUsersPagination()
    {
        return false;
    }
    public function listUsersPaginated($baseGroup, $regexp, $offset, $limit)
    {
        return $this->listUsers($baseGroup);
    }
    public function getUsersCount($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null)
    {
        return -1;
    }

    /**
     * @return Array
     */
    public function listUsers($baseGroup = "/"){}
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
        if($this->getOption("TRANSMIT_CLEAR_PASS") === true) return -1;
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
    }

}
