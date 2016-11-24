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
namespace Pydio\Core\Http\Wopi;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Controller\ShutdownScheduler;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\RouteNotFoundException;
use Pydio\Core\Http\Message\Message;
use Pydio\Core\Http\Middleware\SapiMiddleware;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\SapiEmitter;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class RestWopiMiddleware
 * Specific middleware to handle Wopi actions
 * @package Pydio\Core\Http\Wopi
 */
class Middleware extends SapiMiddleware
{
    protected $base;

    /**
     * RestWopiMiddleware constructor.
     * @param $base
     */
    public function __construct($base)
    {
        $this->base = $base;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws PydioException
     */
    protected function parseRequestRouteAndParams(ServerRequestInterface &$request, ResponseInterface &$response){

        $router = new Router($this->base);
        if(!$router->route($request, $response)) {
            throw new RouteNotFoundException();
        }
    }

    /**
     * Output the response to the browser, if no headers were already sent.
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return void
     */
    public function emitResponse(ServerRequestInterface $request, ResponseInterface $response) {

        if($response !== false && $response->getBody() && $response->getBody() instanceof SerializableResponseStream){

            /**
             * @var SerializableResponseStream $body;
             */
            $status = &$response->getStatusCode();

            // Modifying the results
            if ($status == 200) {
                $body = &$response->getBody();

                /** @var NodesList $originalData */
                $originalData = $body->getChunks()[0];

                /** @var AJXP_Node $node */
                $node = $originalData->getChildren()[0];

                // We modify the result to have the correct format required by the api
                $x = new SerializableResponseStream();
                $meta = $node->getNodeInfoMeta();
                $user = $node->getUser();
                $userId = $user->getId();
                $repo = $node->getRepository();
                $repoId = $repo->getId();
                $data = [
                    "BaseFileName" => $node->getLabel(),
                    "OwnerId" => $userId,
                    "Size" => $meta["bytesize"],
                    "UserId" => $userId,
                    "Version" => "" . $meta["ajxp_modiftime"],
                    "UserFriendlyName" => $userId,
                    "UserCanWrite" => $user->canWrite($repoId) && is_writeable($node->getUrl())
                ];

                $x->addChunk(new Message($data));
                $response = $response->withBody($x);

            }

            $body = &$response->getBody();

            $params = $request->getParsedBody();
            $forceXML = false;

            if(isSet($params["format"]) && $params["format"] == "xml"){
                $forceXML = true;
            }

            if(($request->hasHeader("Accept") && $request->getHeader("Accept")[0] == "text/xml" ) || $forceXML){
                $body->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_XML);
                $response = $response->withHeader("Content-type", "text/xml; charset=UTF-8");
            } else {
                $body->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_JSON);
                $response = $response->withHeader("Content-type", "application/json; charset=UTF-8");
            }
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
