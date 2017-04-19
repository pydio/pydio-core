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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\LoggingException;
use Pydio\Core\Exception\PydioException;

use Pydio\Core\Exception\ResponseEmissionException;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Middleware\ITopLevelMiddleware;
use Pydio\Core\Http\Middleware\SapiMiddleware;
use Pydio\Core\Http\Response\SerializableResponseChunk;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\Context;
use Pydio\Log\Core\Logger;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Pydio HTTP Server
 * @package Pydio\Core\Http
 */
class Server
{
    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var \SplStack
     */
    protected $middleWares;

    /**
     * @var ITopLevelMiddleware
     */
    protected $topMiddleware;

    /**
     * @var string
     */
    protected $base;

    /**
     * @var \SplStack
     */
    protected static $middleWareInstance;

    /**
     * @var array Additional attributes will be added to the initial Request object
     */
    private $requestAttributes;

    /**
     * Server constructor.
     * @param $base
     * @param $requestAttributes
     */
    public function __construct($base, $requestAttributes = []){

        $this->middleWares = new \SplStack();
        $this->middleWares->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_KEEP);

        $this->base = $base;

        $this->stackMiddleWares();

        self::$middleWareInstance = &$this->middleWares;

        $this->requestAttributes = $requestAttributes;
    }

    protected function stackMiddleWares(){

        $this->middleWares->push(array("Pydio\\Core\\Controller\\Controller", "registryActionMiddleware"));
        $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\SessionRepositoryMiddleware", "handleRequest"));
        $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\WorkspaceAuthMiddleware", "handleRequest"));
        $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\AuthMiddleware", "handleRequest"));
        $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\SecureTokenMiddleware", "handleRequest"));
        $this->middleWares->push(array("Pydio\\Core\\Http\\Middleware\\SessionMiddleware", "handleRequest"));

        $topMiddleware = new SapiMiddleware();
        $this->topMiddleware = $topMiddleware;
        $this->middleWares->push(array($topMiddleware, "handleRequest"));

    }

    public function registerCatchAll(){
        if (is_file(TESTS_RESULT_FILE) || is_file(TESTS_RESULT_FILE_LEGACY)) {
            set_error_handler(array($this, "catchError"), E_ALL & ~E_NOTICE & ~E_STRICT );
            set_exception_handler(array($this, "catchException"));
        }
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest(){
        if(!isSet($this->request)){
            $this->request = $this->initServerRequest();
        }
        return $this->request;
    }

    /**
     * @param ServerRequestInterface $request
     */
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
            $response = call_user_func_array($callable, array($request, $response, function($req, $res){
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
    public static function callNextMiddleWare(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface, callable $next = null){
        if($next !== null){
            $responseInterface = call_user_func_array($next, array($requestInterface, $responseInterface));
        }
        return $responseInterface;
    }

    /**
     * @param callable $comparisonFunction
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @param callable|null $next
     * @return ResponseInterface
     */
    public static function callNextMiddleWareAndRewind(callable $comparisonFunction, ServerRequestInterface $requestInterface, ResponseInterface $responseInterface, callable $next = null){
        if($next !== null){
            $responseInterface = call_user_func_array($next, array($requestInterface, $responseInterface));
        }
        self::$middleWareInstance->rewind();
        while(!$comparisonFunction(self::$middleWareInstance->current())){
            self::$middleWareInstance->next();
        }
        self::$middleWareInstance->next();
        return $responseInterface;
    }

    /**
     * @param callable $middleWareCallable
     */
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

        $request = ServerRequestFactory::fromGlobals();
        $request = $request->withAttribute("ctx", Context::emptyContext());
        if(!empty($this->requestAttributes) && count($this->requestAttributes)){
            foreach($this->requestAttributes as $attName => $attValue){
                $request = $request->withAttribute($attName, $attValue);
            }
        }
        return $request;

    }

    /**
     * Error Catcher for PHP errors. Depending on the SERVER_DEBUG config
     * shows the file/line info or not.
     * @static
     * @param $code
     * @param $message
     * @param $fichier
     * @param $ligne
     * @param $context
     */
    public function catchError($code, $message, $fichier, $ligne, $context)
    {
        if(error_reporting() == 0) {
            return ;
        }
        try{
            Logger::error(basename($fichier), "error l.$ligne", array("message" => $message));
        }catch(\Exception $e){
            throw new LoggingException($e);
        }
        if(AJXP_SERVER_DEBUG){
            if($context instanceof  \Exception){
                $message .= $context->getTraceAsString();
            }else{
                $message .= PydioException::buildDebugBackTrace();
            }
        }
        $req = $this->getRequest();
        $resp = new Response();
        $x = new SerializableResponseStream();
        if($code > 100 && $code < 599){
            $resp = $resp->withStatus($code);
        }
        $resp = $resp->withBody($x);
        $x->addChunk(new UserMessage($message, LOG_LEVEL_ERROR));
        try{
            $this->topMiddleware->emitResponse($req, $resp);
        }catch(\Exception $e1){
            throw new ResponseEmissionException();
        }

    }

    /**
     * Catch exceptions, @see catchError
     * @param \Exception $exception
     */
    public function catchException($exception)
    {
        if($exception instanceof SerializableResponseChunk){

            $req = $this->getRequest();
            $resp = new Response();
            $x = new SerializableResponseStream();
            $resp = $resp->withBody($x);
            $x->addChunk($exception);
            try{
                $this->topMiddleware->emitResponse($req, $resp);
            }catch(\Exception $innerEx){
                error_log("Exception thrown while trying to emit a SerizaliableResponseChunk exception!");
            }
            return;
        }

        try {
            $this->catchError($exception->getCode(), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception);
        } catch (ResponseEmissionException $responseEx){
            // Could not send the response, probably because response was already sent. Just log the error, do not try to append content!
            error_log("Exception was caught but could not be sent: ".$exception->getMessage());
            error_log(" ===> Exception details : ".$exception->getFile()." on line ".$exception->getLine(). " ".$exception->getTraceAsString());

        } catch (LoggingException $innerEx) {
            error_log($innerEx->getMessage());
            error_log("Exception was caught but could not be logged properly: ".$exception->getMessage()." in ".$exception->getFile()." on line ".$exception->getLine());
            error_log(" ===> Exception details : ".$exception->getFile()." on line ".$exception->getLine(). " ".$exception->getTraceAsString());
            print("Blocking error encountered, please check the server logs: '" . $exception->getMessage()."'");
        }
    }


}