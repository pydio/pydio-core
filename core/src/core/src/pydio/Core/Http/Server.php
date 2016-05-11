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
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

defined('AJXP_EXEC') or die('Access not allowed');

define('PYDIO_SERVER_MODE_REST', 'rest');
define('PYDIO_SERVER_MODE_SESSION', 'session');

class Server
{
    private $mode;
    private $request;
    private $requireAuth = false;
    private $middleWares;

    public function __construct($serverMode = PYDIO_SERVER_MODE_SESSION){

        $this->mode = $serverMode;
        if($this->mode == PYDIO_SERVER_MODE_REST){
            $this->requireAuth = true;
        }
        $this->middleWares = new \SplStack();
        $this->middleWares->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_KEEP);

        $this->middleWares->push(array("Pydio\\Core\\Controller\\Controller", "registryActionMiddleware"));
        $this->middleWares->push(array($this, "formatDetectionMiddleware"));
        $this->middleWares->push(array($this, "simpleEmitterMiddleware"));

    }

    public function getRequest(){
        if(!isSet($this->request)){
            $this->request = $this->initServerRequest();
        }
        return $this->request;
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
            $response = call_user_func($callable, $request, $response, function($req, $res){
                return $this->nextCallable($req, $res);
            });
        }
        return $response;
    }

    public function formatDetectionMiddleware(ServerRequestInterface $request, ResponseInterface $response, $next = null){
        if($next !== null){
            $response = call_user_func($next, $request, $response);
        }
        if($response !== false && $response->getBody() && $response->getBody() instanceof SerializableResponseStream){
            // For the moment, use XML by default
            if($request->hasHeader("Accept") && $request->getHeader("Accept")[0] == "application/json"){
                $response->getBody()->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_JSON);
                $response = $response->withHeader("Content-type", "application/json; charset=UTF-8");
            }else{
                $response->getBody()->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_XML);
                $response = $response->withHeader("Content-type", "application/xml; charset=UTF-8");
            }
        }
        return $response;
    }

    /**
     * @param $request
     * @param $response
     * @param callable|null $next
     * @throws \Pydio\Core\Exception\AuthRequiredException
     */
    public function simpleEmitterMiddleware($request, $response, $next = null){
        try{
            if($next !== null){
                $response = call_user_func($next, $request, $response);
            }
            if($response !== false && ($response->getBody()->getSize() || $response instanceof \Zend\Diactoros\Response\EmptyResponse)) {
                $emitter = new \Zend\Diactoros\Response\SapiEmitter();
                $emitter->emit($response);
            }
        }catch (\Pydio\Core\Exception\AuthRequiredException $authExc){
            if($this->requireAuth){
                throw $authExc;
            }
        }
    }

    public function addMiddleware(callable $middleWareCallable){
        $this->middleWares->push($middleWareCallable);
    }

    public function listen(){

        $response = new Response();
        $this->middleWares->rewind();
        $this->nextCallable($this->request, $response);

    }

    /**
     * @param bool $rest
     * @return ServerRequestInterface
     */
    protected function initServerRequest($rest = false){

        $request = ServerRequestFactory::fromGlobals();
        $httpVars = $request->getQueryParams();
        $postParams = $request->getParsedBody();
        if(is_array($postParams)){
            $httpVars = array_merge($httpVars, $postParams);
        }
        $request = $request->withParsedBody($httpVars);

        if($this->mode == PYDIO_SERVER_MODE_REST){

            $restBase = ConfService::currentContextIsRestAPI();
            $serverData = $request->getServerParams();
            $uri = $serverData["REQUEST_URI"];
            $scriptUri = ltrim(Utils::safeDirname($serverData["SCRIPT_NAME"]),'/').$restBase."/";
            $uri = substr($uri, strlen($scriptUri));
            $uri = explode("/", trim($uri, "/"));
            $repoID = array_shift($uri);
            $action = array_shift($uri);
            $path = "/".implode("/", $uri);
            return $request->withAttribute("action", $action)
                ->withAttribute("rest_path", $path)
                ->withAttribute("repository_id", $repoID);

        }else{

            $this->requestHandlerDetectAction($request);
            $this->requestHandlerSecureToken($request);
            return $request;

        }

    }

    public function bootSessionServer(ServerRequestInterface $request){

        $parameters = $request->getParsedBody();
        if (AuthService::usersEnabled()) {

            AuthService::logUser(null, null);
            // Check that current user can access current repository, try to switch otherwise.
            $loggedUser = AuthService::getLoggedUser();
            if ($loggedUser == null || $loggedUser->getId() == "guest") {
                // Now try to log the user with the various credentials that could be detected in the request
                PluginsService::getInstance()->initActivePlugins();
                AuthService::preLogUser($parameters);
                $loggedUser = AuthService::getLoggedUser();
                if($loggedUser == null) $this->requireAuth = true;
            }
            if ($loggedUser != null) {
                $res = ConfService::switchUserToActiveRepository($loggedUser, (isSet($parameters["tmp_repository_id"])?$parameters["tmp_repository_id"]:"-1"));
                if (!$res) {
                    AuthService::disconnect();
                    $this->requireAuth = true;
                }
            }

        }else{

            if (isSet($parameters["tmp_repository_id"])) {
                try{
                    ConfService::switchRootDir($parameters["tmp_repository_id"], true);
                }catch(PydioException $e){}
            } else if (isSet($_SESSION["SWITCH_BACK_REPO_ID"])) {
                ConfService::switchRootDir($_SESSION["SWITCH_BACK_REPO_ID"]);
                unset($_SESSION["SWITCH_BACK_REPO_ID"]);
            }

        }

        //Set language
        $loggedUser = AuthService::getLoggedUser();
        if($loggedUser != null && $loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
        else if(isSet($request->getCookieParams()["AJXP_lang"])) ConfService::setLanguage($request->getCookieParams()["AJXP_lang"]);

        //------------------------------------------------------------
        // SPECIAL HANDLING FOR FLEX UPLOADER RIGHTS FOR THIS ACTION
        //------------------------------------------------------------
        if (AuthService::usersEnabled()) {
            $loggedUser = AuthService::getLoggedUser();
            if ($request->getAttribute("action") == "upload" &&
                ($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRepositoryId().""))
                && isSet($request->getUploadedFiles()['Filedata'])) {
                header('HTTP/1.0 ' . '410 Not authorized');
                die('Error 410 Not authorized!');
            }
        }

    }

    public function bootRestServer(ServerRequestInterface $request){

        PluginsService::getInstance()->initActivePlugins();
        AuthService::preLogUser(array_merge($_GET, $_POST));
        if(AuthService::getLoggedUser() == null){
            header('HTTP/1.0 401 Unauthorized');
            echo 'You are not authorized to access this API.';
            exit;
        }

        $repoID = $request->getAttribute("repository_id");
        if($repoID == 'pydio'){
            ConfService::switchRootDir();
            $repo = ConfService::getRepository();
        }else{
            $repo = ConfService::findRepositoryByIdOrAlias($repoID);
            if ($repo == null) {
                die("Cannot find repository with ID ".$repoID);
            }
            if(!ConfService::repositoryIsAccessible($repo->getId(), $repo, AuthService::getLoggedUser(), false, true)){
                header('HTTP/1.0 401 Unauthorized');
                echo 'You are not authorized to access this workspace.';
                exit;
            }
            ConfService::switchRootDir($repo->getId());
        }
        
    }

    /**
     * @param ServerRequestInterface $request
     * @return static
     */
    private function requestHandlerDetectAction(ServerRequestInterface &$request){
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
        $request = $request->withAttribute("action", Utils::sanitize($action, AJXP_SANITIZE_EMAILCHARS));
    }

    /**
     * @param ServerRequestInterface $request
     * @throws PydioException
     */
    private function requestHandlerSecureToken(ServerRequestInterface $request){

        $pluginsUnSecureActions = ConfService::getDeclaredUnsecureActions();
        $unSecureActions = array_merge($pluginsUnSecureActions, array("get_secure_token"));
        if (!in_array($request->getAttribute("action"), $unSecureActions) && AuthService::getSecureToken()) {
            $params = $request->getParsedBody();
            if(array_key_exists("secure_token", $params)){
                $token = $params["secure_token"];
            }
            if ( !isSet($token) || !AuthService::checkSecureToken($token)) {
                throw new PydioException("You are not allowed to access this resource.");
            }
        }
    }

}