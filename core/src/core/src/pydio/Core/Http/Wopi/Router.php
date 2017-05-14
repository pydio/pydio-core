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
namespace Pydio\Core\Http\Wopi;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class WopiRouter
 * Simple router for /wopi/ endpoints
 * @package Pydio\Core\Http\Wopi
 */
class Router
{
    /**
     * @var array
     */
    private $base;

    /**
     * @var array
     *       "cacheOptions"  => ["cacheFile" => "path", "cacheDisabled" => true],
     */
    private $cacheOptions;

    /**
     * ApiRouter constructor.
     * @param string $base
     * @param array $cacheOptions
     */
    public function __construct($base, $cacheOptions = []){
        $this->base = $base;
        $this->cacheOptions = array_merge([
            "cacheDisabled" => AJXP_SKIP_CACHE,
            "cacheFile" => AJXP_CACHE_DIR."/plugins_wopiroutes.php"
        ], $cacheOptions);
    }


    /**
     * @param RouteCollector $r
     */
    public function configureRoutes(RouteCollector &$r){
        
        $configObject = json_decode(file_get_contents(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/core.ajaxplorer/routes/wopi.json"), true);

        foreach ($configObject["paths"] as $path => $methods){
            foreach($methods as $method => $apiData){
                if(preg_match('/\{path\}/', $path)){
                    if(isset($apiData["parameters"]["0"]['$ref']) &&  preg_match('/Optional$/', $apiData["parameters"]["0"]['$ref'])){
                        $path = str_replace("{path}", "{path:.*}", $path);
                    }else{
                        $path = str_replace("{path}", "{path:.+}", $path);
                    }
                }
                $path = str_replace("{roleId}", "{roleId:.+}", $path);
                $r->addRoute(strtoupper($method), $this->base . $path , $apiData);
            }
        }
    }

    /**
     * Get Path component of the URI, without query parameters
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
     * Find a route in api definitions
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     */
    public function route(ServerRequestInterface &$request, ResponseInterface &$response){

        $dispatcher = \FastRoute\cachedDispatcher(function(RouteCollector $r) {

            $this->configureRoutes($r);

        }, $this->cacheOptions);

        $httpMethod = $request->getServerParams()['REQUEST_METHOD'];
        $uri = $this->getURIForRequest($request);
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response = $response->withStatus(404);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                //$allowedMethods = $routeInfo[1];
                //$response = $response->withStatus(405);
                break;
            case Dispatcher::FOUND:
                $apiData = $routeInfo[1];
                $vars = $routeInfo[2];

                $apiUri = preg_replace('/^'.preg_quote($this->base, '/').'/', '', $uri);

                $request = $request->withAttribute("api_uri", $apiUri);
                $repoId = $this->findRepositoryInParameters($request, $vars);

                $request = $request
                    ->withAttribute("action", $apiData["x-pydio-action"])
                    ->withAttribute("repository_id", $repoId)
                    ->withAttribute("rest_base", $this->base)
                    ->withAttribute("api", "v2") // We want the same behaviour as for the v2 api
                    ->withParsedBody(array_merge($request->getParsedBody(), $vars));

                return true;
            default:
                break;
        }

        return false;
    }

    /**
     * Analyze URI and parameters to guess the current workspace
     *
     * @param ServerRequestInterface $request
     * @param array $pathVars
     * @return mixed|string
     */
    protected function findRepositoryInParameters(ServerRequestInterface $request, array $pathVars){
        $params = array_merge($request->getParsedBody(), $pathVars);
        if (preg_match('/^\/admin\//', $request->getAttribute("api_uri"))) {
            return "ajxp_conf";
        }else if(isSet($params["workspaceId"])){
            return $params["workspaceId"];
        }else if(isSet($params["path"]) && strpos($params["path"], "/") !== false){
            return array_shift(explode("/", ltrim($params["path"], "/")));
        }
        // If no repo ID was found, return default repo id "pydio".
        return "pydio";
    }


}