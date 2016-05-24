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
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\WorkspaceNotFoundException;
use Pydio\Core\Http\Rest\RestServer;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;

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

        $response = FrontendsLoader::frontendsAsAuthMiddlewares($requestInterface, $responseInterface);
        if($response != null){
            return $response;
        }

        if(AuthService::getLoggedUser() == null){
            header('HTTP/1.0 401 Unauthorized');
            echo 'You are not authorized to access this API.';
            exit;
        }

        $repoID = $requestInterface->getAttribute("repository_id");
        if($repoID == 'pydio'){
            ConfService::switchRootDir();
            ConfService::getRepository();
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

        ConfService::reloadServicesAndActivePlugins();

        return RestServer::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }


}