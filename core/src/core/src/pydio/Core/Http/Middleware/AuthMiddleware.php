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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Core\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Pydio\Auth\Frontend\Core\FrontendsLoader;
use Pydio\Core\Exception\ActionNotFoundException;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Exception\NoActiveWorkspaceException;
use Pydio\Core\Exception\PydioException;

use Pydio\Core\Http\Server;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;

use Pydio\Core\Services\ConfService;
use Zend\Diactoros\Response\EmptyResponse;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class AuthMiddleware
 * PSR7 Middleware that encapsulates the call to pydio authfront plugins
 * @package Pydio\Core\Http\Middleware
 */
class AuthMiddleware
{

    /**
     * @param ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return \Psr\Http\Message\ResponseInterface
     * @param callable|null $next
     * @throws PydioException
     */
    public static function handleRequest(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface $responseInterface, callable $next = null){

        $driverImpl = ConfService::getAuthDriverImpl();
        PluginsService::getInstance(Context::emptyContext())->setPluginUniqueActiveForType("auth", $driverImpl->getName(), $driverImpl);

        $response = FrontendsLoader::frontendsAsAuthMiddlewares($requestInterface, $responseInterface);
        if($response != null){
            return $response;
        }

        try{

            return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

        } catch (NoActiveWorkspaceException $ex){

            throw new AuthRequiredException();

        } catch(ActionNotFoundException $a){

            /** @var ContextInterface $ctx */
            $ctx = $requestInterface->getAttribute("ctx");
            if(!$ctx->hasUser()){
                throw new AuthRequiredException();
            }else{
                return new EmptyResponse();
            }
        }
        
    }

}