<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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

use Pydio\Core\Http\Server;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Services\ApplicationState;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * SessionMiddleware launches a working session
 * @package Pydio\Core\Http\Middleware
 */
class SessionMiddleware
{
    /**
     * @var \Pydio\Enterprise\Session\PydioSessionHandler $sessionHandler
     */ 
    private static $sessionHandler;

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @param callable|null $next
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function handleRequest(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface $responseInterface, callable $next = null){

        if(ApplicationState::isAdminMode()){
            SessionService::setSessionName(PYDIO_SESSION_NAME_SETTINGS);
        }
        $getParams = $requestInterface->getQueryParams();
        $sessionName = SessionService::getSessionName();

        if (isSet($getParams[PYDIO_SESSION_QUERY_PARAM])) {
            $cookies = $requestInterface->getCookieParams();
            if (!isSet($cookies[$sessionName])) {
                $cookies[$sessionName] = $getParams[PYDIO_SESSION_QUERY_PARAM];
                $_COOKIE[$sessionName] = $getParams[PYDIO_SESSION_QUERY_PARAM];
                $requestInterface = $requestInterface->withCookieParams($cookies);
            }
        }

        if(defined("AJXP_SESSION_HANDLER_PATH") && defined("AJXP_SESSION_HANDLER_CLASSNAME") && file_exists(AJXP_SESSION_HANDLER_PATH)){
            require_once(AJXP_SESSION_HANDLER_PATH);
            if(class_exists(AJXP_SESSION_HANDLER_CLASSNAME, false)){
                $sessionHandlerClass = AJXP_SESSION_HANDLER_CLASSNAME;
                /** @var \Pydio\Enterprise\Session\PydioSessionHandler $sessionHandler */
                $sessionHandler = new $sessionHandlerClass();
                self::$sessionHandler = $sessionHandler;
                $sessionHandler->updateContext($requestInterface->getAttribute("ctx"));
                session_set_save_handler($sessionHandler, false);
            }
        }
        session_name($sessionName);
        session_start();

        register_shutdown_function(function(){
            SessionService::close();
        });

        if(SessionService::has(SessionService::CTX_MINISITE_HASH)){
            ApplicationState::setStateMinisite(SessionService::fetch(SessionService::CTX_MINISITE_HASH));
        }

        return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }

    /**
     * @param ContextInterface $ctx
     */
    public static function updateContext($ctx){
        if(self::$sessionHandler){
            self::$sessionHandler->updateContext($ctx);
        }
    }
}