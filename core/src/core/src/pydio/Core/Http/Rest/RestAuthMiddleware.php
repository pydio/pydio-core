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
namespace Pydio\Core\Http\Rest;

use Psr\Http\Message\ServerRequestInterface;
use Pydio\Authfront\Core\FrontendsLoader;
use Pydio\Core\Exception\NoActiveWorkspaceException;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\WorkspaceForbiddenException;
use Pydio\Core\Exception\WorkspaceNotFoundException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Utils;
use Pydio\Log\Core\AJXP_Logger;

defined('AJXP_EXEC') or die('Access not allowed');


class RestAuthMiddleware
{

    /**
     * @param ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return \Psr\Http\Message\ResponseInterface
     * @param callable|null $next
     * @throws PydioException
     */
    public static function handleRequest(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface, callable $next = null){

        $driverImpl = ConfService::getAuthDriverImpl();
        PluginsService::getInstance(Context::emptyContext())->setPluginUniqueActiveForType("auth", $driverImpl->getName(), $driverImpl);

        $response = FrontendsLoader::frontendsAsAuthMiddlewares($requestInterface, $responseInterface);
        if($response != null){
            return $response;
        }
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");

        if(!$ctx->hasUser()){
            $responseInterface = $responseInterface->withStatus(401);
            $responseInterface->getBody()->write('You are not authorized to access this API.');
            return $responseInterface;
        }

        $repoID = $requestInterface->getAttribute("repository_id");
        if($repoID == 'pydio'){
            $userRepositories = UsersService::getRepositoriesForUser($ctx->getUser());
            if(empty($userRepositories)){
                throw new NoActiveWorkspaceException();
            }
            $repo = array_shift($userRepositories);
        }else{
            try{
                $repo = UsersService::getRepositoryWithPermission($ctx->getUser(), $repoID);
            }catch (WorkspaceForbiddenException $w){
                $responseInterface = $responseInterface->withStatus(401);
                $responseInterface->getBody()->write('You are not authorized to access this API.');
                return $responseInterface;
            }
        }

        $ctx->setRepositoryObject($repo);
        $requestInterface = $requestInterface->withAttribute("ctx", $ctx);

        AJXP_Logger::updateContext($ctx);

        if(UsersService::usersEnabled() && Utils::detectApplicationFirstRun()){
            RolesService::bootSequence();
        }

        return RestServer::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }


}