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
use Pydio\Core\Model\Context;
use Pydio\Core\Services\AuthService;
use Pydio\Authfront\Core\AbstractAuthFrontend;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\BruteForceHelper;
use Pydio\Core\Utils\CookiesHelper;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Utils\CaptchaProvider;

defined('AJXP_EXEC') or die( 'Access not allowed');


class SessionLoginFrontend extends AbstractAuthFrontend {

    function isEnabled(){
        if(Utils::detectApplicationFirstRun()) return false;
        return parent::isEnabled();
    }

    protected function logUserFromSession(\Psr\Http\Message\ServerRequestInterface &$request){

        if(isSet($_SESSION["AJXP_USER"]) && !$_SESSION["AJXP_USER"] instanceof __PHP_Incomplete_Class && $_SESSION["AJXP_USER"] instanceof \Pydio\Core\Model\UserInterface) {
            /**
             * @var \Pydio\Conf\Core\AbstractAjxpUser $u
             */
            $u = $_SESSION["AJXP_USER"];
            if($u->reloadRolesIfRequired()){
                ConfService::getInstance()->invalidateLoadedRepositories();
                AuthService::$bufferedMessage = XMLWriter::reloadRepositoryList(false);
                //AuthService::updateUser($u);
            }
            $request = $request->withAttribute("ctx", Context::contextWithObjects($u, null));
            //ConfService::switchRootDir();
            return true;
        }
        if (ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") && !isSet($_SESSION["CURRENT_MINISITE"])) {
            $authDriver = ConfService::getAuthDriverImpl();
            if (!$authDriver->userExists("guest")) {
                UsersService::createUser("guest", "");
                $guest = ConfService::getConfStorageImpl()->createUserObject("guest");
                $guest->save("superuser");
            }
            $logged = AuthService::logUser("guest", null);
            $request = $request->withAttribute("ctx", Context::contextWithObjects($logged, null));
            //ConfService::switchRootDir();
            return true;
        }
        return false;

    }
    
    function tryToLogUser(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response, $isLast = false){

        $httpVars = $request->getParsedBody();
        if($request->getAttribute("action") != "login"){
            return $this->logUserFromSession($request);
        }
        $rememberLogin = "";
        $rememberPass = "";
        $secureToken = "";
        $loggedUser = null;
        $cookieLogin = (isSet($httpVars["cookie_login"])?true:false);
        if (BruteForceHelper::suspectBruteForceLogin() && (!isSet($httpVars["captcha_code"]) || !CaptchaProvider::checkCaptchaResult($httpVars["captcha_code"]))) {
            $loggingResult = -4;
        } else if($cookieLogin && !CookiesHelper::hasRememberCookie()){
            $loggingResult = -5;
        } else {
            if($cookieLogin){
                list($userId, $userPass) = CookiesHelper::getRememberCookieData();
            }else{
                $userId = (isSet($httpVars["userid"])?Utils::sanitize($httpVars["userid"], AJXP_SANITIZE_EMAILCHARS):null);
                $userPass = (isSet($httpVars["password"])?trim($httpVars["password"]):null);
            }
            $rememberMe = ((isSet($httpVars["remember_me"]) && $httpVars["remember_me"] == "true")?true:false);
            $loggingResult = 1;

            try{
                $loggedUser = AuthService::logUser($userId, $userPass, false, $cookieLogin, $httpVars["login_seed"]);
                $request = $request->withAttribute("ctx", Context::contextWithObjects($loggedUser, null));
            }catch (\Pydio\Core\Exception\LoginException $l){
                $loggingResult = $l->getLoginError();
            }

            if ($rememberMe && $loggingResult == 1) {
                $rememberLogin = "notify";
                $rememberPass = "notify";
            }
            if ($loggingResult == 1) {
                session_regenerate_id(true);
                $secureToken = \Pydio\Core\Http\Middleware\SecureTokenMiddleware::generateSecureToken();
            }
            if ($loggingResult < 1 && BruteForceHelper::suspectBruteForceLogin()) {
                $loggingResult = -4; // Force captcha reload
            }
        }

        if ($loggedUser != null) {

            if(ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")){
                ConfService::getInstance()->invalidateLoadedRepositories();
            }

            $force = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
            $passId = -1;
            if (isSet($httpVars["tmp_repository_id"])) {
                $passId = $httpVars["tmp_repository_id"];
            } else if ($force != "" && $loggedUser->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"]) && !isSet($_SESSION["PENDING_REPOSITORY_ID"])) {
                $passId = $force;
            }
            //$res = ConfService::switchUserToActiveRepository($loggedUser, $passId);
            //if (!$res) {
            //    AuthService::disconnect();
            //    $loggingResult = -3;
            //}
        }

        if ($loggedUser != null && (CookiesHelper::hasRememberCookie() || (isSet($rememberMe) && $rememberMe ==true))) {
            CookiesHelper::refreshRememberCookie($loggedUser);
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
                if (BruteForceHelper::suspectBruteForceLogin()) {
                    $responseInterface = new \Zend\Diactoros\Response\JsonResponse(["seed" => $seed, "captcha" => true]);
                } else {
                    $responseInterface = $responseInterface->withHeader("Content-Type", "text/plain");
                    $responseInterface->getBody()->write($seed);
                }
                break;

            case "get_captcha":
                $x = new \Pydio\Core\Http\Response\AsyncResponseStream(function(){
                    restore_error_handler();
                    restore_exception_handler();
                    set_error_handler(function ($code, $message, $script) {
                        if(error_reporting() == 0) return;
                        \Pydio\Log\Core\AJXP_Logger::error("Captcha", "Error while loading captcha : ".$message, []);
                    });
                    CaptchaProvider::sendCaptcha();
                    return "";
                });
                $responseInterface = $responseInterface->withBody($x);
                break;

            case "back":
                $responseInterface = $responseInterface->withHeader("Content-Type", "text/xml");
                $responseInterface->getBody()->write("<url>". UsersService::getLogoutAddress(false) ."</url>");
                break;

            default;
                break;
        }
        return "";
    }



} 