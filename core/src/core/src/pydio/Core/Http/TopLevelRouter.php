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
namespace Pydio\Core\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\PydioException;

use Zend\Diactoros\ServerRequestFactory;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class TopLevelRouter
 * Creates a simple router for top level segments. Will replace the RewriteRules and others.
 *
 * @package Pydio\Core\Http
 */
class TopLevelRouter
{
    /**
     * @var array
     *       "cacheOptions"  => ["cacheFile" => "path", "cacheDisabled" => true],
     */
    private $cacheOptions;

    private $base = "";

    /**
     * TopLevelRouter constructor.
     * @param array $cacheOptions
     */
    public function __construct($cacheOptions = []){
        $this->cacheOptions = array_merge([
            "cacheDisabled" => AJXP_SKIP_CACHE,
            "cacheFile" => AJXP_DATA_PATH."/cache/plugins_toplevel_routes.php"
        ], $cacheOptions);
    }


    /**
     * @param string $base Base URI (empty string if "/").
     * @param \FastRoute\RouteCollector $r
     */
    public function configureRoutes($base, RouteCollector &$r){
        
        $allMethods = ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'TRACE', 'OPTIONS', 'CONNECT', 'PATCH', 'PROPFIND', 'PROPPATCH', 'MKCOL', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'];
        $file = AJXP_DATA_PATH."/".AJXP_PLUGINS_FOLDER."/boot.conf/routes.json";
        if(!file_exists($file)){
            $file = AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.ajaxplorer/routes.json";
        }
        $routes = json_decode(file_get_contents($file), true);
        foreach ($routes as $short => $data){
            $methods = $data["methods"] == "*" ? $allMethods : $data["methods"];
            foreach($data["routes"] as $route){
                $data["short"] = $short;
                $r->addRoute($methods, $this->base.$route, $data);
            }
        }

    }

    /**
     * Simple parser to get URI
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
     * @throws PydioException
     */
    public function route(){

        $request = ServerRequestFactory::fromGlobals();
        $this->base = rtrim(dirname($request->getServerParams()["SCRIPT_NAME"]), "/");

        $dispatcher = \FastRoute\cachedDispatcher(function(RouteCollector $r) {
            $this->configureRoutes($this->base, $r);
        }, $this->cacheOptions);

        $httpMethod = $request->getServerParams()['REQUEST_METHOD'];
        $uri = $this->getURIForRequest($request);
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                $data = $routeInfo[1];
                if(isSet($data["path"])){
                    require_once (AJXP_INSTALL_PATH."/".$data["path"]);
                }
                call_user_func(array($data["class"], $data["method"]), $this->base, $data["short"], $routeInfo[2]);
                break;
            case Dispatcher::NOT_FOUND:
            default:
                throw new PydioException("Oups, could not find any valid route for ".$uri.", method was was ".$httpMethod);
                break;
        }

    }

}