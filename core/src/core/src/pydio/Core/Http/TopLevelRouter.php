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
namespace Pydio\Core\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\PydioException;

use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Vars\PathUtils;
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

    const ROUTE_CACHE_FILENAME = "plugins_toplevel_routes.php";

    /**
     * TopLevelRouter constructor.
     * @param array $cacheOptions
     */
    public function __construct($cacheOptions = []){
        $this->cacheOptions = array_merge([
            "cacheDisabled" => AJXP_SKIP_CACHE,
            "cacheFile" => AJXP_CACHE_DIR."/".TopLevelRouter::ROUTE_CACHE_FILENAME
        ], $cacheOptions);
    }


    /**
     * @param string $base Base URI (empty string if "/").
     * @param \FastRoute\RouteCollector $r
     */
    public function configureRoutes($base, RouteCollector &$r){
        
        $allMethods = ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'TRACE', 'OPTIONS', 'CONNECT', 'PATCH', 'PROPFIND', 'PROPPATCH', 'MKCOL', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'];
        $file = AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.ajaxplorer/routes/main.json";
        $textContent = file_get_contents($file);
        $textContent = str_replace("%PUBLIC_BASEURI%", ConfService::getGlobalConf("PUBLIC_BASEURI"), $textContent);
        $textContent = str_replace("%WEBDAV_BASEURI%", ConfService::getGlobalConf("WEBDAV_BASEURI"), $textContent);
        $routes = json_decode($textContent, true);
        $adminURI = ConfService::getGlobalConf("ADMIN_URI");
        if(!empty($adminURI)){
            // Remove /settings from "*" route
            $routes["/"]["routes"] = array_filter($routes["/"]["routes"], function($entry){
                return strpos($entry, "/settings") !== 0;
            });
            $lastSlash = array_pop($routes);
            $routes[$adminURI] = [
                "methods" => "*",
                "routes"  => [$adminURI."[{optional:.+}]"],
                "class"   => "Pydio\\Core\\Http\\Base",
                "method"  => "handleRoute"
            ];
            $routes["/"] = $lastSlash;
        }
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
        $this->base = rtrim(PathUtils::forwardSlashDirname($request->getServerParams()["SCRIPT_NAME"]), "/");

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
                header("HTTP/1.0 404 Not Found");
                echo file_get_contents(AJXP_INSTALL_PATH . "/plugins/gui.ajax/res/html/404.html");
                die();
        }

    }

}