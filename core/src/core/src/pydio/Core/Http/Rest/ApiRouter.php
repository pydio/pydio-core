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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

defined('AJXP_EXEC') or die('Access not allowed');


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
            "cacheFile" => AJXP_DATA_PATH."/cache/plugins_api2routes.php"
        ], $cacheOptions);
    }


    /**
     * @param \FastRoute\RouteCollector $r
     */
    public function configureRoutes(\FastRoute\RouteCollector &$r){
        
        $configObject = json_decode(file_get_contents(AJXP_INSTALL_PATH . "/" . AJXP_DOCS_FOLDER . "/api2.json"), true);
        foreach ($configObject["paths"] as $path => $methods){
            foreach($methods as $method => $apiData){
                $path = str_replace("{path}", "{path:.+}", $path);
                $path = str_replace("{roleId}", "{roleId:.+}", $path);
                $path = str_replace("{groupPath}", "{groupPath:.+}", $path);
                $r->addRoute(strtoupper($method), $this->base . $this->v2Base . $path , $apiData);
            }
        }
        // Legacy V1 API
        $r->addRoute("GET", $this->base . $this->v1Base."/{repository_id}/{action}[{optional:.+}]", ["api-v1" => true]);
        $r->addRoute("POST", $this->base . $this->v1Base."/{repository_id}/{action}[{optional:.+}]", ["api-v1" => true]);

    }

    public function getURIForRequest(ServerRequestInterface $request){

        $uri = $request->getServerParams()['REQUEST_URI'];
        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        return rawurldecode($uri);
    }

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

    protected function findRepositoryInParameters(ServerRequestInterface $request, array $pathVars){
        $params = array_merge($request->getParsedBody(), $pathVars);
        if(isSet($params["workspaceId"])){
            return $params["workspaceId"];
        }else if(isSet($params["path"]) && strpos($params["path"], "/") !== false){
            return array_shift(explode("/", ltrim($params["path"], "/")));
        }else if (preg_match('/^\/admin\//', $request->getAttribute("api_uri"))) {
            return "ajxp_conf";
        }
        // If no repo ID was found, return default repo id "pydio".
        return "pydio";
    }


}