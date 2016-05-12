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
namespace Pydio\Core\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Pydio\Authfront\Core\AbstractAuthFrontend;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Exception\NoActiveWorkspaceException;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\WorkspaceNotFoundException;
use Pydio\Core\Http\Server;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Log\Core\AJXP_Logger;

defined('AJXP_EXEC') or die('Access not allowed');


class AuthMiddleware
{

    /**
     * @param ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return \Psr\Http\Message\ResponseInterface
     * @param callable|null $next
     * @throws PydioException
     */
    public static function handleRequest(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface, callable $next = null){

        if(AuthService::usersEnabled()){

            PluginsService::getInstance()->initActivePlugins();
            $frontends = PluginsService::getInstance()->getActivePluginsForType("authfront");
            $index = 0;
            /**
             * @var AbstractAuthFrontend $frontendPlugin
             */
            foreach($frontends as $frontendPlugin){
                if(!$frontendPlugin->isEnabled()) continue;
                if(!method_exists($frontendPlugin, "tryToLogUser")){
                    AJXP_Logger::error(__CLASS__, __FUNCTION__, "Trying to use an authfront plugin without tryToLogUser method. Wrongly initialized?");
                    continue;
                }
                //$res = $frontendPlugin->tryToLogUser($httpVars, ($index == count($frontends)-1));
                $isLast = ($index == count($frontends)-1);
                $res = $frontendPlugin->tryToLogUser($requestInterface, $responseInterface, $isLast);
                $index ++;
                if($res) {
                    if($responseInterface->getBody()->getSize() > 0 || $responseInterface->getStatusCode() != 200){
                        // Do not go to the other middleware, return directly.
                        return $responseInterface;
                    }
                    break;
                }
            }

        }

        if(Server::$mode == Server::MODE_SESSION){
            self::bootSessionServer($requestInterface);
        }else{
            self::bootRestServer($requestInterface);
        }

        try{
            ConfService::reloadServicesAndActivePlugins();
        }catch (NoActiveWorkspaceException $ex){
            if(Server::$mode != Server::MODE_SESSION) throw $ex;
            $logged = AuthService::getLoggedUser();
            if($logged !== null) $lock = $logged->getLock();
            if(empty($lock)){
                throw new AuthRequiredException();
            }
        }

        return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }

    protected static function bootSessionServer(ServerRequestInterface $request){

        $parameters = $request->getParsedBody();
        if (AuthService::usersEnabled()) {

            $loggedUser = AuthService::getLoggedUser();
            if ($loggedUser != null) {
                $res = ConfService::switchUserToActiveRepository($loggedUser, (isSet($parameters["tmp_repository_id"])?$parameters["tmp_repository_id"]:"-1"));
                if (!$res) {
                    AuthService::disconnect();
                }
            }

        }else{

            if (isSet($parameters["tmp_repository_id"])) {
                try{
                    ConfService::switchRootDir($parameters["tmp_repository_id"], true);
                }catch(PydioException $e){}
            } else if (isSet($_SESSION["SWITCH_BACK_REPO_ID"])) {
                ConfService::switchRootDir($_SESSION["SWITCH_BACK_REPO_ID"]);
                unset($_SESSION["SWITCH_BACK_REPO_ID"]);
            }

        }

        //Set language
        $loggedUser = AuthService::getLoggedUser();
        if($loggedUser != null && $loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
        else if(isSet($request->getCookieParams()["AJXP_lang"])) ConfService::setLanguage($request->getCookieParams()["AJXP_lang"]);

    }

    protected static function bootRestServer(ServerRequestInterface $request){

        if(AuthService::getLoggedUser() == null){
            header('HTTP/1.0 401 Unauthorized');
            echo 'You are not authorized to access this API.';
            exit;
        }

        $repoID = $request->getAttribute("repository_id");
        if($repoID == 'pydio'){
            ConfService::switchRootDir();
            $repo = ConfService::getRepository();
        }else{
            $repo = ConfService::findRepositoryByIdOrAlias($repoID);
            if ($repo == null) {
                throw new WorkspaceNotFoundException($repoID);
            }
            if(!ConfService::repositoryIsAccessible($repo->getId(), $repo, AuthService::getLoggedUser(), false, true)){
                header('HTTP/1.0 401 Unauthorized');
                echo 'You are not authorized to access this workspace.';
                exit;
            }
            ConfService::switchRootDir($repo->getId());
        }

    }


}