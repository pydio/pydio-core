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
use Pydio\Authfront\Core\FrontendsLoader;
use Pydio\Core\Exception\ActionNotFoundException;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Exception\NoActiveWorkspaceException;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\RepositoryLoadException;
use Pydio\Core\Http\Server;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Utils;
use Pydio\Log\Core\AJXP_Logger;
use Zend\Diactoros\Response\EmptyResponse;

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


        try{

            $driverImpl = ConfService::getAuthDriverImpl();
            PluginsService::getInstance()->setPluginUniqueActiveForType("auth", $driverImpl->getName(), $driverImpl);

            $response = FrontendsLoader::frontendsAsAuthMiddlewares($requestInterface, $responseInterface);
            if($response != null){
                return $response;
            }
            self::bootSessionServer($requestInterface);

        } catch (NoActiveWorkspaceException $ex){

            $logged = AuthService::getLoggedUser();
            if($logged !== null) $lock = $logged->getLock();
            if(empty($lock)){
                throw new AuthRequiredException();
            }

        } catch (RepositoryLoadException $r){

            $previous = SessionService::getPreviousRepositoryId();
            if($previous !== null){
                SessionService::saveRepositoryId($previous);
            }
            throw $r;
            
        }

        try{

            return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

        }catch(ActionNotFoundException $a){

            if(AuthService::getLoggedUser() == null){
                throw new AuthRequiredException();
            }else{
                return new EmptyResponse();
            }
        } catch (RepositoryLoadException $r){

            $previous = SessionService::getPreviousRepositoryId();
            if($previous !== null){
                SessionService::saveRepositoryId($previous);
            }
            throw $r;

        }

    }

    protected static function bootSessionServer(ServerRequestInterface &$request){

        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        if(!$ctx->hasUser()){
            SessionMiddleware::updateContext($ctx);
            return;
        }
        $loggedUser = $ctx->getUser();
        $parameters = $request->getParsedBody();

        $restRepositoryId = isSet($parameters["tmp_repository_id"]) ? $parameters["tmp_repository_id"] : null;


        $repoObject = null;

        if($restRepositoryId !== null){

            $repoObject = ConfService::switchRootDir($restRepositoryId);

        }else{

            $repoId = SessionService::getSessionRepositoryId();
            if($repoId !== null){
                try{
                    $repoObject = ConfService::switchRootDir($repoId);
                }catch (\Exception $e){
                    $previous = SessionService::getPreviousRepositoryId();
                    if($previous !== null){
                        $repoObject = ConfService::switchRootDir($previous);
                    }
                }
            }else{
                $userRepositories = UsersService::getRepositoriesForUser($loggedUser);
                if(empty($userRepositories)){
                    throw new NoActiveWorkspaceException();
                }
                $default = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
                $lastVisited = $loggedUser->getArrayPref("history", "last_repository");
                if($default !== -1 && array_key_exists($default, $userRepositories)){
                    $repoObject = $userRepositories[$default];
                }else if($lastVisited !== "" && array_key_exists($lastVisited, $userRepositories)){
                    $repoObject = $userRepositories[$lastVisited];
                }else{
                    $repoObject = array_shift($userRepositories);
                }
            }

            if($repoObject !== null){
                SessionService::saveRepositoryId($repoObject->getId());
                $loggedUser->setArrayPref("history", "last_repository", $repoObject->getId());
            }
        }


/*
        if (UsersService::usersEnabled() && $loggedUser !== null && !empty($repoObject)) {
            $currentRepoId = $repoObject->getId();
            if (isSet($_SESSION["PENDING_REPOSITORY_ID"]) && isSet($_SESSION["PENDING_FOLDER"])) {
                $loggedUser->setArrayPref("history", "last_repository", $_SESSION["PENDING_REPOSITORY_ID"]);
                $loggedUser->setPref("pending_folder", $_SESSION["PENDING_FOLDER"]);
                AuthService::updateUser($loggedUser);
                unset($_SESSION["PENDING_REPOSITORY_ID"]);
                unset($_SESSION["PENDING_FOLDER"]);
            }
            $lastRepoId  = $loggedUser->getArrayPref("history", "last_repository");
            $defaultRepoId = -1;
            // Find default ID from ACLS
            $acls = $loggedUser->getMergedRole()->listAcls(true);
            foreach($acls as $key => $right){
                if (!empty($right) && ConfService::getRepositoryById($key) != null) {
                    $defaultRepoId= $key;
                    break;
                }
            }
            if ($defaultRepoId == -1) {
                throw new NoActiveWorkspaceException();
            } else {
                if ($lastRepoId !== "" && $lastRepoId!== $currentRepoId && $restRepositoryId == -1 && $loggedUser->canSwitchTo($lastRepoId)) {
                    $repoObject = ConfService::switchRootDir($lastRepoId);
                } else if ($restRepositoryId !== -1 && $loggedUser->canSwitchTo($restRepositoryId)) {
                    $repoObject = ConfService::switchRootDir($restRepositoryId);
                } else if (!$loggedUser->canSwitchTo($currentRepoId)) {
                    $repoObject = ConfService::switchRootDir($defaultRepoId);
                }
            }

        }
*/

        if($repoObject !== null){

            $ctx->setRepositoryObject($repoObject);
            $request = $request->withAttribute("ctx", $ctx);

        }

        SessionMiddleware::updateContext($ctx);
        AJXP_Logger::updateContext($ctx);

        //Set language
        if($ctx->hasUser() && $ctx->getUser()->getPref("lang") != "") {
            LocaleService::setLanguage($ctx->getUser()->getPref("lang"));
        } else if(isSet($request->getCookieParams()["AJXP_lang"])) {
            LocaleService::setLanguage($request->getCookieParams()["AJXP_lang"]);
        }

        if(UsersService::usersEnabled() && Utils::detectApplicationFirstRun()){
            try{
                RolesService::bootSequence();
            }catch (PydioException $e){
                if($request->getAttribute("action") == "get_boot_gui"){
                    $request = $request->withAttribute("flash", $e->getMessage());
                }else{
                    throw $e;
                }
            }
        }

    }

}