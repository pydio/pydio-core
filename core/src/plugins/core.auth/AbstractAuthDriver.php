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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Auth\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Conf\Core\AbstractUser;
use Pydio\Core\Http\Middleware\SecureTokenMiddleware;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Serializer\UserXML;
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Zend\Diactoros\Response\TextResponse;
use Pydio\Core\Utils\Vars\InputFilter;

defined('AJXP_EXEC') or die( 'Access not allowed');

define('AJXP_FILTER_EMPTY', 'AJXP_FILTER_EMPTY');
define('AJXP_FILTER_NOT_EMPTY', 'AJXP_FILTER_NOT_EMPTY');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @class AbstractAuthDriver
 * Abstract representation of an authentication driver. Must be implemented by the auth plugin
 */
class AbstractAuthDriver extends Plugin
{
    public $options;
    public $driverName = "abstract";
    public $driverType = "auth";

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return string
     * @throws \Exception
     */
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $action     = $requestInterface->getAttribute("action");
        $httpVars   = $requestInterface->getParsedBody();
        /** @var ContextInterface $ctx */
        $ctx        = $requestInterface->getAttribute("ctx");
        
        switch ($action) {

            case "get_secure_token" :
                $responseInterface = new TextResponse(SecureTokenMiddleware::generateSecureToken());
                break;

            //------------------------------------
            //	CHANGE USER PASSWORD
            //------------------------------------
            case "pass_change":

                $userObject = $ctx->getUser();
                if ($userObject == null || $userObject->getId() == "guest") {
                    $responseInterface = new TextResponse("SUCCESS");
                    break;
                }
                $oldPass = $httpVars["old_pass"];
                $newPass = $httpVars["new_pass"];
                if (strlen($newPass) < ConfService::getContextConf($ctx, "PASSWORD_MINLENGTH", "auth")) {
                    $responseInterface = new TextResponse("PASS_ERROR");
                    break;
                }
                if (UsersService::checkPassword($userObject->getId(), $oldPass, false)) {
                    UsersService::updatePassword($userObject->getId(), $newPass);
                    if ($userObject->hasLockByName("pass_change")) {
                        $userObject->removeLock("pass_change");
                        $userObject->save("superuser");
                    }
                } else {
                    $responseInterface = new TextResponse("PASS_ERROR");
                    break;
                }
                $responseInterface = new TextResponse("SUCCESS");
                break;



            default;
            break;
        }
        return "";
    }


    /**
     * @inheritdoc
     */
    public function getRegistryContributions(ContextInterface $ctx, $extendedVersion = true )
    {
        $this->loadRegistryContributions($ctx);
        if(!$extendedVersion) return $this->registryContributions;
        if(UsersService::usersEnabled() && !$ctx->hasUser()){
            return $this->registryContributions;
        }

        $userSerializer = new UserXML();
        $xmlString = $userSerializer->serialize($ctx);

        $dom = new \DOMDocument();
        $dom->loadXML($xmlString);
        $this->registryContributions[]=$dom->documentElement;
        return $this->registryContributions;
    }

    /**
     * @inheritdoc
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if($contribNode->nodeName != "actions") return ;

        if(UsersService::usersEnabled() && $this->passwordsEditable()) return ;
        // Disable password change action
        if(!isSet($actionXpath)) $actionXpath=new \DOMXPath($contribNode->ownerDocument);
        $passChangeNodeList = $actionXpath->query('action[@name="pass_change"]', $contribNode);
        if(!$passChangeNodeList->length) return ;
        $passChangeNode = $passChangeNodeList->item(0);
        $contribNode->removeChild($passChangeNode);
    }
    
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
     * @return AbstractUser[]
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
     * @return AbstractUser[]
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
     * @return bool
     */
    public function checkPassword($login, $pass){}

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

    /**
     * @param $login
     * @param $passwd
     */
    public function createUser($login, $passwd){}

    /**
     * @param $login
     * @param $newPass
     */
    public function changePassword($login, $newPass){}

    /**
     * @param $login
     */
    public function deleteUser($login){}

    /**
     * @return bool
     */
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

    /**
     * @return bool
     */
    public function getLoginRedirect()
    {
        if (isSet($this->options["LOGIN_REDIRECT"])) {
            return $this->options["LOGIN_REDIRECT"];
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function getLogoutRedirect()
    {
        return false;
    }

    /**
     * @param $optionName
     * @return string
     */
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

    /**
     * @param $login
     * @return bool
     */
    public function isAjxpAdmin($login)
    {
        return ($this->getOption("AJXP_ADMIN_LOGIN") === $login);
    }

    /**
     * @return bool
     */
    public function autoCreateUser()
    {
        $opt = $this->getOption("AUTOCREATE_AJXPUSER");
        if($opt === true) return true;
        return false;
    }

    /**
     * @param $userId
     * @param $pwd
     * @return array
     */
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
     * @return string
     */
    public function getStats(){
        return $this->getId();
    }

    /**
     * @param UserInterface $userObject
     */
    public function updateUserObject(&$userObject)
    {
        $applyRole = $this->getOption("AUTO_APPLY_ROLE");
        if(!empty($applyRole) && !(is_array($userObject->getRoles()) && array_key_exists($applyRole, $userObject->getRoles())) ){
            $rObject = RolesService::getOrCreateRole($applyRole, "/");
            $userObject->addRole($rObject);
            $userObject->save("superuser");
        }
    }

    /**
     * Sanitize user_id and password. Should be implemented by children (auth ldap) to
     * be able to use login_id with special characters (utf8) such as : ä, é ...
     * @param $s
     * @param int $level
     * @return mixed|string
     * @throws \Pydio\Core\Exception\ForbiddenCharacterException
     */
    public function sanitize($s, $level = InputFilter::SANITIZE_HTML){
        return InputFilter::sanitize($s, $level);
    }
}
