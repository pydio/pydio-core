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
use Pydio\Core\Services\AuthService;
use Pydio\Authfront\Core\AbstractAuthFrontend;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Utils\CaptchaProvider;
use Pydio\Core\Controller\HTMLWriter;

defined('AJXP_EXEC') or die( 'Access not allowed');


class SessionLoginFrontend extends AbstractAuthFrontend {

    function isEnabled(){
        if(Utils::detectApplicationFirstRun()) return false;
        return parent::isEnabled();
    }

    protected function logUserFromSession(&$httpVars){

        if(isSet($_SESSION["AJXP_USER"]) && is_object($_SESSION["AJXP_USER"])) {
            /**
             * @var \Pydio\Conf\Core\AbstractAjxpUser $u
             */
            $u = $_SESSION["AJXP_USER"];
            if($u->reloadRolesIfRequired()){
                ConfService::getInstance()->invalidateLoadedRepositories();
                AuthService::$bufferedMessage = XMLWriter::reloadRepositoryList(false);
                AuthService::updateUser($u);
            }
            return true;
        }
        if (ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && !isSet($_SESSION["CURRENT_MINISITE"])) {
            $authDriver = ConfService::getAuthDriverImpl();
            if (!$authDriver->userExists("guest")) {
                AuthService::createUser("guest", "");
                $guest = ConfService::getConfStorageImpl()->createUserObject("guest");
                $guest->save("superuser");
            }
            AuthService::logUser("guest", null);
            return true;
        }
        return false;

    }

    protected function logUserFromLoginAction(&$httpVars){


    }

    function tryToLogUser(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response, $isLast = false){

        $httpVars = $request->getParsedBody();
        if($request->getAttribute("action") != "login"){
            return $this->logUserFromSession($httpVars);
        }
        $rememberLogin = "";
        $rememberPass = "";
        $secureToken = "";
        $loggedUser = null;
        if (AuthService::suspectBruteForceLogin() && (!isSet($httpVars["captcha_code"]) || !CaptchaProvider::checkCaptchaResult($httpVars["captcha_code"]))) {
            $loggingResult = -4;
        } else {
            $userId = (isSet($httpVars["userid"])?Utils::sanitize($httpVars["userid"], AJXP_SANITIZE_EMAILCHARS):null);
            $userPass = (isSet($httpVars["password"])?trim($httpVars["password"]):null);
            $rememberMe = ((isSet($httpVars["remember_me"]) && $httpVars["remember_me"] == "true")?true:false);
            $cookieLogin = (isSet($httpVars["cookie_login"])?true:false);
            $loggingResult = AuthService::logUser($userId, $userPass, false, $cookieLogin, $httpVars["login_seed"]);
            if ($rememberMe && $loggingResult == 1) {
                $rememberLogin = "notify";
                $rememberPass = "notify";
            }
            if ($loggingResult == 1) {
                session_regenerate_id(true);
                $secureToken = \Pydio\Core\Http\Middleware\SecureTokenMiddleware::generateSecureToken();
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

        $stream = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $stream->addChunk(new \Pydio\Core\Http\Message\LoggingResult($loggingResult, $rememberLogin, $rememberPass, $secureToken));
        $response = $response->withBody($stream);
        return true;

    }

    public function switchAction(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        switch ($requestInterface->getAttribute("action")) {

            case "login":

                break;

            case "logout" :

                AuthService::disconnect();
                $loggingResult = 2;
                session_destroy();
                $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
                $x->addChunk(new \Pydio\Core\Http\Message\LoggingResult($loggingResult));
                $responseInterface = $responseInterface->withBody($x);

                break;

            case "get_seed" :
                $seed = AuthService::generateSeed();
                if (AuthService::suspectBruteForceLogin()) {
                    $responseInterface = new \Zend\Diactoros\Response\JsonResponse(["seed" => $seed, "captcha" => true]);
                } else {
                    $responseInterface = $responseInterface->withHeader("Content-Type", "text/plain");
                    $responseInterface->getBody()->write($seed);
                }
                break;

            case "get_captcha":
                $x = new \Pydio\Core\Http\Response\AsyncResponseStream(function(){
                    CaptchaProvider::sendCaptcha();
                });
                $responseInterface = $responseInterface->withBody($x);
                break;

            case "back":
                $responseInterface = $responseInterface->withHeader("Content-Type", "text/xml");
                $responseInterface->getBody()->write("<url>".AuthService::getLogoutAddress(false)."</url>");
                break;

            default;
                break;
        }
        return "";
    }



} 