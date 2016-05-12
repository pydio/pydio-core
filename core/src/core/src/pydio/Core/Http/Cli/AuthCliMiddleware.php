<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Core\Http\Cli;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Auth\Core\AJXP_Safe;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Http\Server;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;

defined('AJXP_EXEC') or die('Access not allowed');


class AuthCliMiddleware
{
    /**
     * @param ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @param callable|null $next
     * @return ResponseInterface
     * @throws AuthRequiredException
     */
    public static function handleRequest(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface, callable $next = null){


        $options = $requestInterface->getAttribute("cli-options");
        $optUser = $options["u"];
        $optPass = $options["p"];
        $optRepoId = $options["r"];

        $impersonateUser = false;

        if (isSet($options["p"])) {
            $optPass = $options["p"];
        } else {
            // Consider "u" is a crypted version of u:p
            $optToken = $options["t"];
            $cKey = ConfService::getCoreConf("AJXP_CLI_SECRET_KEY", "conf");
            if(empty($cKey)) $cKey = "\1CDAFx¨op#";
            $optUser = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($optToken.$cKey), base64_decode($optUser), MCRYPT_MODE_ECB), "\0");
            $env = getenv("AJXP_SAFE_CREDENTIALS");
            if(!empty($env)){
                $array = AJXP_Safe::getCredentialsFromEncodedString($env);
                if(isSet($array["user"]) && $array["user"] == $optUser){
                    unset($optToken);
                    $optPass = $array["password"];
                }
            }
        }


        if (AuthService::usersEnabled() && !empty($optUser)) {
            $seed = AuthService::generateSeed();
            if ($seed != -1) {
                $optPass = md5(md5($optPass).$seed);
            }
            $loggingResult = AuthService::logUser($optUser, $optPass, isSet($optToken), false, $seed);
            // Check that current user can access current repository, try to switch otherwise.
            $loggedUser = AuthService::getLoggedUser();
            if ($loggedUser != null && $impersonateUser !== false && $loggedUser->isAdmin()) {
                AuthService::disconnect();
                AuthService::logUser($impersonateUser, "empty", true, false, "");
                $loggedUser = AuthService::getLoggedUser();
            }
            if ($loggedUser != null) {
                ConfService::switchRootDir($optRepoId, true);
            }
            if (isset($loggingResult) && $loggingResult != 1) {
                throw new AuthRequiredException();
            }
        } else {
            throw new AuthRequiredException();
        }

        ConfService::reloadServicesAndActivePlugins();

        $requestInterface = $requestInterface->withAttribute("action", $options["a"]);

        return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }

}