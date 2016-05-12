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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\WorkspaceNotFoundException;
use Pydio\Core\Http\Middleware\AuthMiddleware;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

defined('AJXP_EXEC') or die('Access not allowed');

class Server
{
    const MODE_REST = 'rest';
    const MODE_SESSION = 'session';
    const MODE_CLI = 'cli';

    public static $mode;
    private $request;
    private $middleWares;

    private static $middleWareInstance;

    public function __construct($serverMode = Server::MODE_SESSION){

        self::$mode = $serverMode;

        $this->middleWares = new \SplStack();
        $this->middleWares->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_KEEP);

        $this->middleWares->push(array("Pydio\\Core\\Controller\\Controller", "registryActionMiddleware"));

        if($serverMode == Server::MODE_CLI){
            $this->middleWares->push(array("Pydio\\Core\\Http\\Cli\\AuthCliMiddleware", "handleRequest"));
        }else{
            $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\AuthMiddleware", "handleRequest"));
        }

        if($serverMode == Server::MODE_SESSION){
            $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\SecureTokenMiddleware", "handleRequest"));
            $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\SessionMiddleware", "handleRequest"));
        }

        if($serverMode == Server::MODE_CLI){
            $this->middleWares->push(array("Pydio\\Core\\Http\\Cli\\CliMiddleware", "handleRequest"));
        }else{
            $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\SapiMiddleware", "handleRequest"));
        }
        self::$middleWareInstance = &$this->middleWares;

    }

    public function getRequest(){
        if(!isSet($this->request)){
            $this->request = $this->initServerRequest();
        }
        return $this->request;
    }

    public function updateRequest(ServerRequestInterface $request){
        $this->request = $request;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function nextCallable(&$request, &$response){
        if($this->middleWares->valid()){
            $callable = $this->middleWares->current();
            $this->middleWares->next();
            $response = call_user_func_array($callable, array(&$request, &$response, function($req, $res){
                return $this->nextCallable($req, $res);
            }));
        }
        return $response;
    }

    

    /**
     * To be used by middlewares
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @param callable|null $next
     * @return ResponseInterface
     */
    public static function callNextMiddleWare(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface, callable $next = null){
        if($next !== null){
            $responseInterface = call_user_func_array($next, array(&$requestInterface, &$responseInterface));
        }
        return $responseInterface;
    }

    /**
     * @param $rewindTo array
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @param callable|null $next
     * @return ResponseInterface
     */
    public static function callNextMiddleWareAndRewind(callable $comparisonFunction, ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface, callable $next = null){
        if($next !== null){
            $responseInterface = call_user_func_array($next, array(&$requestInterface, &$responseInterface));
        }
        self::$middleWareInstance->rewind();
        while(!$comparisonFunction(self::$middleWareInstance->current())){
            self::$middleWareInstance->next();
        }
        self::$middleWareInstance->next();
        return $responseInterface;
    }


    public function addMiddleware(callable $middleWareCallable){
        $this->middleWares->push($middleWareCallable);
        self::$middleWareInstance = $this->middleWares;
    }

    public function listen(){

        $response = new Response();
        $this->middleWares->rewind();
        $this->nextCallable($this->getRequest(), $response);

    }

    /**
     * @param bool $rest
     * @return ServerRequestInterface
     */
    protected function initServerRequest($rest = false){

        return $request = ServerRequestFactory::fromGlobals();

    }


}