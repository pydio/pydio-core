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

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Rest\ApiRouter;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Http\Server;
use Pydio\Core\Utils\Utils;

defined('AJXP_EXEC') or die('Access not allowed');


class SapiMiddleware implements ITopLevelMiddleware
{

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable|null $next
     * @return ResponseInterface
     * @throws PydioException
     */
    public function handleRequest(ServerRequestInterface $request, ResponseInterface $response, callable $next = null){

        $params = $request->getQueryParams();
        $postParams = $request->getParsedBody();
        if(is_array($postParams)){
            $params = array_merge($params, $postParams);
        }
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
            ->withAttribute("action", Utils::sanitize($action, AJXP_SANITIZE_EMAILCHARS))
            ->withAttribute("api", "session")
        ;

    }

    public function emitResponse(ServerRequestInterface $request, ResponseInterface $response){
        if($response !== false && $response->getBody() && $response->getBody() instanceof SerializableResponseStream){
            /**
             * @var SerializableResponseStream $body;
             */
            $body = &$response->getBody();
            if($request->hasHeader("Accept") && $request->getHeader("Accept")[0] == "application/json"){
                $body->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_JSON);
                $response = $response->withHeader("Content-type", "application/json; charset=UTF-8");
            }else{
                $body->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_XML);
                $response = $response->withHeader("Content-type", "text/xml; charset=UTF-8");
            }
        }

        if($response !== false && ($response->getBody()->getSize() || $response instanceof \Zend\Diactoros\Response\EmptyResponse) || $response->getStatusCode() != 200) {
            $emitter = new \Zend\Diactoros\Response\SapiEmitter();
            $emitter->emit($response);
        }
    }

}