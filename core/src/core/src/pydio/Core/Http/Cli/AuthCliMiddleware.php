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
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Http\Server;
use Pydio\Core\Model\Context;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Utils;
use Zend\Diactoros\Response;

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
    public static function handleRequest(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface, callable $next = null){

        $driverImpl = ConfService::getAuthDriverImpl();
        PluginsService::getInstance()->setPluginUniqueActiveForType("auth", $driverImpl->getName(), $driverImpl);

        $options = $requestInterface->getAttribute("cli-options");
        $optUser = $options["u"];
        $optPass = $options["p"];
        $optRepoId = $options["r"];

        $repository = ConfService::getRepositoryById($optRepoId);
        if ($repository == null) {
            $repository = ConfService::getRepositoryByAlias($optRepoId);
            if ($repository != null) {
                $optRepoId =($repository->isWriteable()?$repository->getUniqueId():$repository->getId());
            }
        }

        $impersonateUsers = false;
        // TODO 1/ REIMPLEMENT parameter queue: to pass a file with many user names?
        /**
         * if (strpos($optUser, "queue:") === 0) {
        $optUserQueue = substr($optUser, strlen("queue:"));
        $optUser = false;
        //echo("QUEUE : ".$optUserQueue);
        if (is_file($optUserQueue)) {
        $lines = file($optUserQueue);
        if (count($lines) && !empty($lines[0])) {
        $allUsers = explode(",", $lines[0]);
        $optUser = array_shift($allUsers);
        file_put_contents($optUserQueue, implode(",", $allUsers));
        }
        }
        if ($optUser === false) {
        if (is_file($optUserQueue)) {
        unlink($optUserQueue);
        }
        die("No more users inside queue");
        }
         */
        // TODO 2/ REIMPLEMENT DETECT USER PARAMETER BASED ON REPOSITORY PATH OPTION ?
        /*
        if ($optDetectUser != false) {
        $path = $repository->getOption("PATH", true);
        if (strpos($path, "AJXP_USER") !== false) {
        $path = str_replace(
        array("AJXP_INSTALL_PATH", "AJXP_DATA_PATH", "/"),
        array(AJXP_INSTALL_PATH, AJXP_DATA_PATH, DIRECTORY_SEPARATOR),
        $path
        );
        $parts = explode("AJXP_USER", $path);
        if(count($parts) == 1) $parts[1] = "";
        $first = str_replace("\\", "\\\\", $parts[0]);
        $last = str_replace("\\", "\\\\", $parts[1]);
        */
        //if (preg_match("/$first(.*)$last.*/", $optDetectUser, $matches)) {
        // $detectedUser = $matches[1];
        //    }
        //}
        //}



        if(!empty($options["i"])){
            if(!is_array($options["i"])) $options["i"] = [$options["i"]];
            $impersonateUsers = $options["i"];
        }

        if (!empty($options["p"])) {
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


        if (UsersService::usersEnabled() && !empty($optUser)) {
            $seed = AuthService::generateSeed();
            if ($seed != -1) {
                $optPass = md5(md5($optPass).$seed);
            }
            $loggingResult = AuthService::logUser($optUser, $optPass, isSet($optToken), false, $seed);
            if (isset($loggingResult) && $loggingResult != 1) {
                throw new AuthRequiredException();
            }
            $loggedUser = AuthService::getLoggedUser();
            if($loggedUser == null) throw new AuthRequiredException();



        } else {
            throw new AuthRequiredException();
        }

        $requestInterface = $requestInterface->withAttribute("action", $options["a"]);

        if(UsersService::usersEnabled() && Utils::detectApplicationFirstRun()){
            RolesService::bootSequence();
        }

        if ($impersonateUsers !== false && $loggedUser->isAdmin()) {

            foreach ($impersonateUsers as $impersonateUser){
                AuthService::disconnect();
                $responseInterface->getBody()->write("\n--- Impersonating user ".$impersonateUser);
                try{
                    AuthService::logUser($impersonateUser, "empty", true, false, "");
                    $loggedUser = AuthService::getLoggedUser();
                    if($loggedUser == null) continue;
                    ConfService::switchRootDir($optRepoId, true);
                    Controller::registryReset();
                    $subResponse = new Response();
                    $ctx = new Context();
                    $ctx->setUserObject($loggedUser);
                    $ctx->setRepositoryId($optRepoId);
                    $requestInterface = $requestInterface->withAttribute("ctx", $ctx);

                    $subResponse = Server::callNextMiddleWareAndRewind(function($middleware){
                        return (is_array($middleware) && $middleware["0"] == "Pydio\\Core\\Http\\Cli\\AuthCliMiddleware" && $middleware[1] == "handleRequest");
                    },
                        $requestInterface,
                        $subResponse,
                        $next
                    );
                    $responseInterface->getBody()->write("\n". $subResponse->getBody());

                }catch (\Exception $e){

                    $responseInterface->getBody()->write("\nERROR: ".$e->getMessage());

                }
            }
            return $responseInterface;

        }else{

            ConfService::switchRootDir($optRepoId, true);

            $ctx = new Context();
            $ctx->setUserObject($loggedUser);
            $ctx->setRepositoryId($optRepoId);
            $requestInterface = $requestInterface->withAttribute("ctx", $ctx);

            return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

        }


    }

}