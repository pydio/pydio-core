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
namespace Pydio\Auth\Frontend;

use Duo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Auth\Frontend\Core\AbstractAuthFrontend;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\BruteForceHelper;
use Pydio\Core\Utils\CookiesHelper;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Utils\CaptchaProvider;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class DuoSecurityFrontend
 * @package Pydio\Auth\Frontend
 */
class DuoSecurityFrontend extends AbstractAuthFrontend
{

    /**
     * Try to authenticate the user based on various external parameters
     * Return true if user is now logged.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param bool $isLast Whether this is is the last plugin called.
     * @return bool
     */
    function tryToLogUser(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response, $isLast = false)
    {

        $httpVars = $request->getParsedBody();
        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        // CATCH THE STANDARD LOGIN OPERATION
        if ($request->getAttribute("action") != "login") {
            return false;
        }
        if (Utils::userAgentIsNativePydioApp()) {
            return false;
        }

        $userId = (isSet($httpVars["userid"]) ? Utils::sanitize($httpVars["userid"], AJXP_SANITIZE_EMAILCHARS) : null);
        $duoActive = false;
        if (!empty($userId) && \Pydio\Core\Services\UsersService::userExists($userId)) {
            $uObject = \Pydio\Core\Services\UsersService::getUserById($userId);
            if ($uObject != null) {
                $duoActive = $uObject->getMergedRole()->filterParameterValue("authfront.duosecurity", "DUO_AUTH_ACTIVE", AJXP_REPO_SCOPE_ALL, false);
            }
        }
        if (!$duoActive) {
            return false;
        }

        $rememberLogin = "";
        $rememberPass = "";
        $secureToken = "";
        $loggedUser = null;
        if (BruteForceHelper::suspectBruteForceLogin() && (!isSet($httpVars["captcha_code"]) || !CaptchaProvider::checkCaptchaResult($httpVars["captcha_code"]))) {
            $loggingResult = -4;
        } else {
            $userId = (isSet($httpVars["userid"]) ? trim($httpVars["userid"]) : null);
            $userPass = (isSet($httpVars["password"]) ? trim($httpVars["password"]) : null);
            $rememberMe = ((isSet($httpVars["remember_me"]) && $httpVars["remember_me"] == "true") ? true : false);
            $cookieLogin = (isSet($httpVars["cookie_login"]) ? true : false);
            $loggingResult = AuthService::logUser($userId, $userPass, false, $cookieLogin, $httpVars["login_seed"]);
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
        $loggedUser = $ctx->getUser();
        if ($loggedUser != null) {
            $force = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
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

        if ($loggedUser != null && (CookiesHelper::hasRememberCookie() || (isSet($rememberMe) && $rememberMe == true))) {
            CookiesHelper::refreshRememberCookie($loggedUser);
        }

        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $response = $response->withBody($x);
        $x->addChunk(new \Pydio\Core\Http\Message\LoggingResult($loggingResult, $rememberLogin, $rememberPass, $secureToken));

        if ($loggingResult > 0 && $loggedUser != null) {

            // Create an updated context
            $ctx = \Pydio\Core\Model\Context::fromGlobalServices();
            require_once($this->getBaseDir() . "/duo_php/duo_web.php");
            $appUnique = $this->getContextualOption($ctx, "DUO_AUTH_AKEY");
            $iKey = $this->getContextualOption($ctx, "DUO_AUTH_IKEY");
            $sKey = $this->getContextualOption($ctx, "DUO_AUTH_SKEY");

            $res = Duo::signRequest($iKey, $sKey, $appUnique, $loggedUser->getId());

            $loggedUser->getPersonalRole()->setParameterValue("authfront.duosecurity", "DUO_AUTH_LAST_SIGNATURE", $res);
            $loggedUser->setLock("duo_show_iframe");
            $loggedUser->save("superuser");
        }

        return true;

    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws \Exception
     * @throws \Pydio\Core\Exception\AuthRequiredException
     */
    public function postVerificationCode(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {

        if ($requestInterface->getAttribute("action") !== "duo_post_verification_code") {
            return;
        }

        $httpVars = $requestInterface->getParsedBody();
        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $u = $ctx->getUser();
        if ($u == null) return;
        $sigResponse = $httpVars["sig_response"];

        require_once($this->getBaseDir() . "/duo_php/duo_web.php");
        $appUnique = $this->getContextualOption($ctx, "DUO_AUTH_AKEY");
        $iKey = $this->getContextualOption($ctx, "DUO_AUTH_IKEY");
        $sKey = $this->getContextualOption($ctx, "DUO_AUTH_SKEY");

        $verif = Duo::verifyResponse($iKey, $sKey, $appUnique, $sigResponse);

        if ($verif != null && $verif == $u->getId()) {
            $u->removeLock();
            $u->save("superuser");
            $u->recomputeMergedRole();
            AuthService::updateUser($u);
            ConfService::switchUserToActiveRepository($u);
            $force = $u->getMergedRole()->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
            $passId = -1;
            if ($force != "" && $u->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"]) && !isSet($_SESSION["PENDING_REPOSITORY_ID"])) {
                $passId = $force;
            }
            $res = ConfService::switchUserToActiveRepository($u, $passId);
            if (!$res) {
                AuthService::disconnect();
                throw new \Pydio\Core\Exception\AuthRequiredException();
            }
            ConfService::getInstance()->invalidateLoadedRepositories();
        } else {
            AuthService::disconnect();
            throw new \Pydio\Core\Exception\AuthRequiredException();
        }

    }


}