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

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\SapiEmitter;

use Pydio\Core\Http\Server;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Controller\ShutdownScheduler;
use Pydio\Core\Utils\Vars\InputFilter;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class SapiMiddleware: main middleware for http requests
 * Pydio core is organized following the PSR-7 pattern,
 * that defines HTTP message interfaces and concentric middlewares
 * that carry around these interfaces.
 *
 * @package Pydio\Core\Http\Middleware
 */
class SapiMiddleware implements ITopLevelMiddleware
{

    /**
     * Standard interface for PSR-7 Middleware
     *
     * @param ServerRequestInterface $request Interface that encapsulate http request parameters
     * @param ResponseInterface $response Interface encapsulating the response
     * @param callable|null $next Next middleware to call
     * @return ResponseInterface Returns the modified response interface.
     * @throws PydioException
     */
    public function handleRequest(ServerRequestInterface $request, ResponseInterface $response, callable $next = null){

        $params = $request->getQueryParams();
        $postParams = $request->getParsedBody();
        if(is_array($postParams)){
            $params = array_merge($params, $postParams);
        }
        /** @var ServerRequestInterface $request */
        $request = $request->withParsedBody($params);

        if(in_array("application/json", $request->getHeader("Content-Type"))){
            $body = "".$request->getBody();
            $body = json_decode($body, true);
            if(is_array($body)){
                $request = $request->withParsedBody(array_merge($request->getParsedBody(), ["request_body" => $body]));
            }
        }

        $this->parseRequestRouteAndParams($request, $response);

        $response = Server::callNextMiddleWare($request, $response, $next);

        if(headers_sent()){
            return;
        }
        
        $this->emitResponse($request, $response);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $responseInterface
     */
    protected function parseRequestRouteAndParams(ServerRequestInterface &$request, ResponseInterface &$responseInterface){

        $serverData = $request->getServerParams();
        $params = $request->getParsedBody();
        if(isSet($params["get_action"])){
            $action = $params["get_action"];
        }else if(isSet($params["action"])){
            $action = $params["action"];
        }else if (preg_match('/MSIE 7/',$serverData['HTTP_USER_AGENT']) || preg_match('/MSIE 8/',$serverData['HTTP_USER_AGENT'])) {
            $action = "get_boot_gui";
        } else {
            $action = (strpos($serverData["HTTP_ACCEPT"], "text/html") !== false ? "get_boot_gui" : "ping");
        }
        $request = $request
            ->withAttribute("action", InputFilter::sanitize($action, InputFilter::SANITIZE_EMAILCHARS))
            ->withAttribute("api", "session")
        ;

    }

    /**
     * Output the response to the browser, if no headers were already sent.
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return void
     */
    public function emitResponse(ServerRequestInterface $request, ResponseInterface $response){
        if($response !== false && $response->getBody() && $response->getBody() instanceof SerializableResponseStream){
            /**
             * @var SerializableResponseStream $body;
             */
            $body = &$response->getBody();
            $params = $request->getParsedBody();
            $forceJson = false;
            if(isSet($params["format"]) && $params["format"] == "json"){
                $forceJson = true;
            }
            if(($request->hasHeader("Accept") && $request->getHeader("Accept")[0] == "application/json") || $forceJson){
                $body->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_JSON);
                $response = $response->withHeader("Content-type", "application/json; charset=UTF-8");
            }else{
                $body->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_XML);
                $response = $response->withHeader("Content-type", "text/xml; charset=UTF-8");
            }
        }
        if($response === false){
            return;
        }

        if( $response->getBody()->getSize() === null
            || $response->getBody()->getSize() > 0
            || $response instanceof \Zend\Diactoros\Response\EmptyResponse
            || $response->getStatusCode() != 200) {
            $emitter = new SapiEmitter();
            ShutdownScheduler::setCloseHeaders($response);
            $emitter->emit($response);
            ShutdownScheduler::getInstance()->callRegisteredShutdown();
        }
    }

}