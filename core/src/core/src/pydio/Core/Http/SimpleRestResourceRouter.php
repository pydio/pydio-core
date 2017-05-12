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
namespace Pydio\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class SimpleRestResourceRouter
 * FastRoute encapsuler that can be used to make a simple CRUD Rest API on an existing 
 * route of the application.
 * @package Pydio\Core\Http
 */
class SimpleRestResourceRouter
{
    /**
     * @var array
     *      ["resourceName" => "name",
     *       "parameterName" => "object_id"
     *       "crudCallbacks" => ["CREATE" => methodName,
     *                            "RETRIEVE_MANY"=> methodName,
     *                            "RETRIEVE_ONE"=> methodName,
     *                            "UPDATE" => methodName,
     *                            "DELETE" => methodName],
     *       "linkedResources" => [NESTED CONFIG ARRAY]
     *       "additionalRoutes" => [[method=>"GET", uri=>"/path/to/route", callable]]
     *      ]
     */
    private $config;
    /**
     * @var array
     *       "cacheOptions"  => ["cacheFile" => "path", "cacheDisabled" => true],
     */
    private $cacheOptions;
    /**
     * @var object Will be used as '$this' for triggering the callbacks
     */
    private $callbacksContext;

    private $base = "/api/{repository_id}";

    /**
     * SimpleRestResourceRouter constructor.
     * @param object $callbacksContext
     * @param array $config
     * @param array $cacheOptions
     */
    public function __construct($callbacksContext, $config, $cacheOptions){
        $this->config = $config;
        $this->cacheOptions = $cacheOptions;
        $this->callbacksContext = $callbacksContext;
    }

    /**
     * @param $method
     * @param $uri
     * @param $callable
     */
    public function addRoute($method, $uri, $callable){
        $this->config["additional_routes"][] = ["method" => $method, "uri" => $uri, "callable" => $callable];
    }

    /**
     * @param array $configObject
     * @param \FastRoute\RouteCollector $r
     */
    public function configureRoutes($base, $configObject, \FastRoute\RouteCollector &$r){

        $parameterName = $configObject["parameterName"];
        $resName = $configObject["resourceName"];
        $callbacks = $configObject["crudCallbacks"];
        if(isSet($callbacks["RETRIEVE_MANY"])){
            $r->addRoute('GET', $base."/".$resName, ['simpleHandler', $callbacks["RETRIEVE_MANY"]]);
        }
        if(isset($callbacks["RETRIEVE_ONE"])){
            $r->addRoute('GET', $base."/".$resName.'/{'.$parameterName.'}', ['simpleHandler', $callbacks["RETRIEVE_ONE"]]);
        }
        if(isSet($callbacks["CREATE"])){
            $r->addRoute('POST', $base."/".$resName, ['bodyHandler', $callbacks["CREATE"]]);
        }
        if(isSet($callbacks["UPDATE"])){
            $r->addRoute('PUT', $base."/".$resName.'/{'.$parameterName.'}', ['bodyHandler', $callbacks["UPDATE"]]);
            $r->addRoute('PATCH', $base."/".$resName.'/{'.$parameterName.'}', ['bodyHandler', $callbacks["UPDATE"]]);
        }
        if(isSet($callbacks["DELETE"])){
            $r->addRoute('DELETE', $base."/".$resName.'/{'.$parameterName.'}', ['simpleHandler', $callbacks["DELETE"]]);
        }

        if(is_array($configObject["additionalRoutes"])){
            foreach ($configObject["additionalRoutes"] as $additional_route){
                if(in_array($additional_route["method"], ["GET", "DELETE", "HEAD"])){
                    $r->addRoute($additional_route["method"], $base.$additional_route["route"], ['simpleHandler', $additional_route["callback"]]);
                }else if(in_array($additional_route["method"], ["POST", "PUT", "PATCH"])){
                    $r->addRoute($additional_route["method"], $base.$additional_route["route"], ['bodyHandler', $additional_route["callback"]]);
                }
            }
        }

        if(is_array($configObject["linkedResources"])){
            foreach ($configObject["linkedResources"] as $linkedResource){
                $this->configureRoutes($base."/".$resName."/{".$parameterName."}", $linkedResource, $r);
            }
        }

    }

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    public function getURIForRequest(ServerRequestInterface $request){

        $uri = $request->getServerParams()['REQUEST_URI'];
        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        return rawurldecode($uri);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     */
    public function route(ServerRequestInterface &$request, ResponseInterface &$response){

        $dispatcher = \FastRoute\cachedDispatcher(function(\FastRoute\RouteCollector $r) {

            $this->configureRoutes($this->base, $this->config, $r);

        }, $this->cacheOptions);

        $httpMethod = $request->getServerParams()['REQUEST_METHOD'];
        $uri = $this->getURIForRequest($request);
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                //$response = $response->withStatus(404);
                break;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                //$allowedMethods = $routeInfo[1];
                //$response = $response->withStatus(405);
                break;
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1][0];
                $callback = $routeInfo[1][1];
                $vars = $routeInfo[2];

                $request = $request->withParsedBody(array_merge($request->getParsedBody(), $vars));
                $this->$handler($callback, $request, $response);
                return true;
            default:
                break;
        }

        return false;
    }

    /**
     * @param callable $callback
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    protected function simpleHandler($callback, ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface){

        $data = $this->callbacksContext->$callback($requestInterface, $responseInterface);

        $responseInterface = $responseInterface->withHeader("Content-type", "application/json");
        $responseInterface->getBody()->write(json_encode($data));
    }

    /**
     * @param callable $callback
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    protected function bodyHandler($callback, ServerRequestInterface &$request, ResponseInterface &$response){

        $postedObject = json_decode($request->getBody()->getContents());
        $request = $request->withParsedBody(array_merge($request->getParsedBody(), ["postedObject" => $postedObject]));
        $data = $this->callbacksContext->$callback($request, $response);

        $response = $response->withHeader("Content-Type", "application/json");
        $response->getBody()->write(json_encode($data));

    }


    /**
     * Class casting
     *
     * @param string|object $destination
     * @param object $sourceObject
     * @return mixed
     */
    public static function cast($destination, $sourceObject)
    {
        if (is_string($destination)) {
            $destination = new $destination();
        }
        $destinationReflection = new \ReflectionObject($destination);
        if(is_object($sourceObject)){
            $sourceReflection = new \ReflectionObject($sourceObject);
            $sourceProperties = $sourceReflection->getProperties();
            foreach ($sourceProperties as $sourceProperty) {
                $sourceProperty->setAccessible(true);
                $name = $sourceProperty->getName();
                $value = $sourceProperty->getValue($sourceObject);
                if ($destinationReflection->hasProperty($name)) {
                    $propDest = $destinationReflection->getProperty($name);
                    $propDest->setAccessible(true);
                    $propDest->setValue($destination,$value);
                } else {
                    $destination->$name = $value;
                }
            }
        }else{
            foreach($sourceObject as $name => $value){
                if ($destinationReflection->hasProperty($name)) {
                    $propDest = $destinationReflection->getProperty($name);
                    $propDest->setAccessible(true);
                    $propDest->setValue($destination,$value);
                } else {
                    $destination->$name = $value;
                }
            }
        }
        return $destination;
    }


}