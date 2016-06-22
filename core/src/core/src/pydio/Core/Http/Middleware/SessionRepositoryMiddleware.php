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
namespace Pydio\Core\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\NoActiveWorkspaceException;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\RepositoryLoadException;
use Pydio\Core\Http\Server;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Utils;
use Pydio\Log\Core\AJXP_Logger;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class SessionRepositoryMiddleware
 * @package Pydio\Core\Http\Middleware
 */
class SessionRepositoryMiddleware
{
    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     * @param callable|null $next
     * @throws PydioException
     */
    public static function handleRequest(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface, callable $next = null) {

        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $repository = null;
        if($ctx->hasUser()){
            $loggedUser = $ctx->getUser();
            try{

                $repository = self::switchUserToRepository($loggedUser, $requestInterface);

            } catch (RepositoryLoadException $r){

                $previous = SessionService::getPreviousRepositoryId();
                if($previous !== null){
                    SessionService::saveRepositoryId($previous);
                }
                throw $r;

            }
        }

        if($repository !== null){
            $ctx->setRepositoryObject($repository);
            $requestInterface = $requestInterface->withAttribute("ctx", $ctx);
        }

        SessionMiddleware::updateContext($ctx);
        AJXP_Logger::updateContext($ctx);

        //Set language
        if($ctx->hasUser() && $ctx->getUser()->getPref("lang") != "") {
            LocaleService::setLanguage($ctx->getUser()->getPref("lang"));
        } else if(isSet($requestInterface->getCookieParams()["AJXP_lang"])) {
            LocaleService::setLanguage($requestInterface->getCookieParams()["AJXP_lang"]);
        }

        if(UsersService::usersEnabled() && Utils::detectApplicationFirstRun()){
            try{
                RolesService::bootSequence();
            }catch (PydioException $e){
                if($requestInterface->getAttribute("action") == "get_boot_gui"){
                    $requestInterface = $requestInterface->withAttribute("flash", $e->getMessage());
                }else{
                    throw $e;
                }
            }
        }

        return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }


    /**
     * @param UserInterface $user
     * @param ServerRequestInterface $requestInterface
     * @return RepositoryInterface
     * @throws NoActiveWorkspaceException
     * @throws PydioException
     * @throws \Pydio\Core\Exception\WorkspaceNotFoundException
     */
    public static function switchUserToRepository(UserInterface $user, ServerRequestInterface $requestInterface) {

        $parameters         = $requestInterface->getParsedBody();
        $restRepositoryId   = isSet($parameters["tmp_repository_id"]) ? $parameters["tmp_repository_id"] : null;
        $repoObject         = null;

        if($restRepositoryId !== null){

            $repoObject = UsersService::getRepositoryWithPermission($user, $restRepositoryId);

        }else{

            $repoId = SessionService::getSessionRepositoryId();
            if($repoId !== null){
                try{
                    $repoObject = UsersService::getRepositoryWithPermission($user, $repoId);
                }catch (\Exception $e){
                    $previous = SessionService::getPreviousRepositoryId();
                    if($previous !== null){
                        $repoObject = UsersService::getRepositoryWithPermission($user, $previous);
                    }
                }
            }else{
                $userRepositories = UsersService::getRepositoriesForUser($user);
                if(empty($userRepositories)){
                    throw new NoActiveWorkspaceException();
                }
                $default = $user->getMergedRole()->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
                $lastVisited = $user->getArrayPref("history", "last_repository");
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
                $user->setArrayPref("history", "last_repository", $repoObject->getId());
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

        return $repoObject;

    }
}