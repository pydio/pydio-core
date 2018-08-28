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
namespace Pydio\Core\Http\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class ApiRouter
 * Router used for REST API v1 and REST API v2.
 * Based on fast-route, creates route for api/v1 using XML declaration, and routes for api/v2 using
 * swagger file.
 * @package Pydio\Core\Http\Rest
 */
class ApiRouter
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

    private $v2Base = "/v2";
    private $v1Base = "";

    /**
     * ApiRouter constructor.
     * @param string $base
     * @param array $cacheOptions
     */
    public function __construct($base, $cacheOptions = []){
        $this->base = $base;
        $this->cacheOptions = array_merge([
            "cacheDisabled" => AJXP_SKIP_CACHE,
            "cacheFile" => AJXP_CACHE_DIR."/plugins_api2routes.php"
        ], $cacheOptions);
    }


    /**
     * @param \FastRoute\RouteCollector $r
     */
    public function configureRoutes(\FastRoute\RouteCollector &$r){

        $configObject = json_decode(file_get_contents(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/core.ajaxplorer/routes/api2.json"), true);
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
                $r->addRoute(strtoupper($method), $this->base . $this->v2Base . $path , $apiData);
            }

            // Adding OPTIONS to allow CORS Request
            var_dump($path);
            $r->addRoute("OPTIONS", $this->base . $this->v2Base . $path, []);
        }

        // Legacy V1 API
        $r->addRoute("GET", $this->base . $this->v1Base."/{repository_id}/{action}[{optional:.+}]", ["api-v1" => true]);
        $r->addRoute("POST", $this->base . $this->v1Base."/{repository_id}/{action}[{optional:.+}]", ["api-v1" => true]);

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

        $dispatcher = \FastRoute\cachedDispatcher(function(\FastRoute\RouteCollector $r) {

            $this->configureRoutes($r);

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
                $apiData = $routeInfo[1];
                $vars = $routeInfo[2];
                if(isSet($apiData["api-v1"])){
                    $apiUri = preg_replace('/^'.preg_quote($this->base.$this->v1Base, '/').'/', '', $uri);
                    $request = $request
                        ->withAttribute("action", $vars["action"])
                        ->withAttribute("repository_id", $vars["repository_id"])
                        ->withAttribute("rest_base", $this->base.$this->v1Base)
                        ->withAttribute("rest_path", $vars["optional"])
                        ->withAttribute("api", "v1")
                        ->withAttribute("api_uri", $apiUri)
                    ;
                }else{
                    $apiUri = preg_replace('/^'.preg_quote($this->base.$this->v2Base, '/').'/', '', $uri);
                    $request = $request->withAttribute("api_uri", $apiUri);
                    $repoId = $this->findRepositoryInParameters($request, $vars);
                    $request = $request
                        ->withAttribute("action", $apiData["x-pydio-action"])
                        ->withAttribute("repository_id", $repoId)
                        ->withAttribute("rest_base", $this->base.$this->v2Base)
                        ->withAttribute("api", "v2")
                        ->withParsedBody(array_merge($request->getParsedBody(), $vars));
                }
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
