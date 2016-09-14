<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Share\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Http\Server;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class MinisiteAuthMiddleware
 * @package Pydio\Share\Http
 */
class MinisiteAuthMiddleware
{
    /**
     * Parse request parameters
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     */
    public static function handleRequest(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface, callable $next = null){

        $rest = $requestInterface->getAttribute("rest");
        $hash = $requestInterface->getAttribute("hash");
        $shareData = $requestInterface->getAttribute("data");

        if($rest){
            AuthService::$useSession = false;
        }else{
            session_name("AjaXplorer_Shared".str_replace(".","_",$hash));
            session_start();
            AuthService::disconnect();
        }

        if (!empty($shareData["PRELOG_USER"])) {

            $loggedUser = AuthService::logUser($shareData["PRELOG_USER"], "", true);
            $requestInterface = $requestInterface->withAttribute("ctx", Context::contextWithObjects($loggedUser, null));

        } else if(isSet($shareData["PRESET_LOGIN"])) {

            if($rest){
                $responseInterface = self::basicHttp($shareData["PRESET_LOGIN"], $requestInterface, $responseInterface);
                if($responseInterface->getStatusCode() === 401){
                    return $responseInterface;
                }
            }else{
                $_SESSION["PENDING_REPOSITORY_ID"] = $shareData["REPOSITORY"];
                $_SESSION["PENDING_FOLDER"] = "/";
            }

        }

        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");

        if (isSet($_GET["lang"])) {
            if ($ctx->hasUser()) {
                $ctx->getUser()->setPref("lang", $_GET["lang"]);
            } else {
                setcookie("AJXP_lang", $_GET["lang"]);
            }
        }

        if(!$rest){
            $_SESSION["CURRENT_MINISITE"] = $hash;
        }
        if(!empty($ctx) && $ctx->hasUser() && isSet($shareData["REPOSITORY"])){
            $repoObject = UsersService::getRepositoryWithPermission($ctx->getUser(), $shareData["REPOSITORY"]);
            $ctx->setRepositoryObject($repoObject);
            $requestInterface = $requestInterface->withAttribute("ctx", $ctx);
        }

        return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }

    /**
     * Perform Basic HTTP Auth
     * @param string $presetLogin
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface|static
     * @throws \Pydio\Core\Exception\LoginException
     */
    public static function basicHttp($presetLogin, ServerRequestInterface &$requestInterface, ResponseInterface $responseInterface){

        $serverData = $requestInterface->getServerParams();
        $localHttpLogin = $serverData["PHP_AUTH_USER"];
        $localHttpPassw = $serverData['PHP_AUTH_PW'];

        // mod_php
        if (isset($serverData['PHP_AUTH_USER'])) {
            $localHttpLogin = $serverData['PHP_AUTH_USER'];
            $localHttpPassw = $serverData['PHP_AUTH_PW'];

            // most other servers
        } elseif (isset($serverData['HTTP_AUTHORIZATION'])) {
            if (strpos(strtolower($serverData['HTTP_AUTHORIZATION']), 'basic') === 0) {
                list($localHttpLogin, $localHttpPassw) = explode(':', base64_decode(substr($serverData['HTTP_AUTHORIZATION'], 6)));
            }
            // Sometimes prepend a REDIRECT
        } elseif (isset($serverData['REDIRECT_HTTP_AUTHORIZATION'])) {

            if (strpos(strtolower($serverData['REDIRECT_HTTP_AUTHORIZATION']), 'basic') === 0) {
                list($localHttpLogin, $localHttpPassw) = explode(':', base64_decode(substr($serverData['REDIRECT_HTTP_AUTHORIZATION'], 6)));
            }

        }

        // Check that localHttpLogin == Hash ?

        if (empty($localHttpPassw)) {
            return $responseInterface->withHeader("WWW-Authenticate", "Basic realm=\"Please provide password\"")->withStatus(401);
        }

        try {

            $loggedUser = AuthService::logUser($presetLogin, $localHttpPassw, false, false);
            $requestInterface = $requestInterface->withAttribute("ctx", Context::contextWithObjects($loggedUser, null));
            return $responseInterface;

        } catch (\Pydio\Core\Exception\LoginException $l) {
            if ($l->getLoginError() !== -4) {
                return $responseInterface->withHeader("WWW-Authenticate", "Basic realm=\"Please provide a valid password\"")->withStatus(401);
            }else{
                throw $l;
            }
        }

    }

}