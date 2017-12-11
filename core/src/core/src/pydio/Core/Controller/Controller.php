<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Core\Controller;

use Pydio\Core\Utils\XMLHelper;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\ContextProviderInterface;

use Pydio\Core\Services;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\UsersService;
use Pydio\Log\Core\Logger;

use Pydio\Core\Exception\ActionNotFoundException;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Exception\PydioException;

defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * Core controller for dispatching the actions.
 * It uses the XML Registry (simple version, not extended) to search all the <action> tags and find the action.
 * @package Pydio
 * @subpackage Core
 */
class Controller
{
    /**
     * @var \DOMXPath[]
     */
    private static $xPathes = [];
    /**
     * @var array
     */
    private static $includeHooks = [];
    /**
     * @var array
     */
    private static $hooksCaches = [];

    /**
     * Initialize the queryable xPath object, pointing to the registry associated
     * with the current context
     * @static
     * @param ContextInterface $ctx
     * @param bool $useCache Whether to cache the registry version in a memory cache.
     * @return \DOMXPath
     */
    private static function initXPath($ctx, $useCache = false)
    {
        $ctxId = $ctx->getStringIdentifier();
        if (!isSet(self::$xPathes[$ctxId])) {
            $registry = PluginsService::getInstance($ctx)->getFilteredXMLRegistry(false, false, $useCache);
            self::$xPathes[$ctxId] = new \DOMXPath($registry);
        }
        return self::$xPathes[$ctxId];
    }

    /**
     * API V1 : parse parameters based on the URL and their definitions in the manifest.
     * @param ServerRequestInterface $request
     * @return bool|\DOMElement
     * @throws ActionNotFoundException
     */
    public static function parseRestParameters(ServerRequestInterface &$request){

        $actionName = $request->getAttribute("action");
        $path = $request->getAttribute("rest_path");
        $ctx = $request->getAttribute("ctx");
        $reqParameters = $request->getParsedBody();

        $xPath = self::initXPath($ctx, true);
        $actions = $xPath->query("actions/action[@name='$actionName']");
        if (!$actions->length) {
            throw new ActionNotFoundException($actionName);
        }
        $action = $actions->item(0);
        $restPathList = $xPath->query("processing/serverCallback/@restParams", $action);
        if (!$restPathList->length) {
            throw new ActionNotFoundException($actionName);
        }
        $restPath = $restPathList->item(0)->nodeValue;
        $paramNames = explode("/", trim($restPath, "/"));
        $exploded = explode("?", $path);
        $path = array_shift($exploded);
        //$paramValues = array_map("urldecode", explode("/", trim($path, "/"), count($paramNames)));
        $paramValues = explode("/", trim($path, "/"), count($paramNames));
        foreach ($paramNames as $i => $pName) {
            if (strpos($pName, "+") !== false) {
                $paramNames[$i] = str_replace("+", "", $pName);
                $paramValues[$i] = "/" . $paramValues[$i];
            }
        }
        if (count($paramValues) < count($paramNames)) {
            $paramNames = array_slice($paramNames, 0, count($paramValues));
        }
        $paramValues = array_map(array("Pydio\\Core\\Utils\\TextEncoder", "toUTF8"), $paramValues);

        $reqParameters = array_merge($reqParameters, array_combine($paramNames, $paramValues));
        $request = $request->withParsedBody($reqParameters);
        return $action;

    }

    /**
     * Middleware entry point
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable|null $nextCallable
     * @return ResponseInterface
     * @throws AuthRequiredException
     */
    public static function registryActionMiddleware(ServerRequestInterface $request, ResponseInterface $response, callable $nextCallable = null){
        $action = null;
        if($request->getAttribute("api") == "v1"){
            $action = Controller::parseRestParameters($request);
        }
        $response = Controller::run($request, $action);
        if($nextCallable != null){
            $response = call_user_func_array($nextCallable, array(&$request, &$response));
        }
        return $response;
    }

    /**
     * Check if mandatory parameters as defined in the manifests are correct.
     *
     * @static
     * @param ServerRequestInterface $request
     * @param \DOMNode $callbackNode
     * @param \DOMXPath $xPath
     * @throws \Exception
     */
    public static function checkParams(&$request, $callbackNode, $xPath)
    {
        if (!$callbackNode->attributes->getNamedItem('checkParams') || $callbackNode->attributes->getNamedItem('checkParams')->nodeValue != "true") {
            return;
        }
        $inputParams = $xPath->query("input_param", $callbackNode);
        $declaredParams = array();
        $parameters = $request->getParsedBody();
        $changes = 0;
        foreach ($inputParams as $param) {
            $name = $param->attributes->getNamedItem("name")->nodeValue;
            $type = $param->attributes->getNamedItem("type")->nodeValue;
            $defaultNode = $param->attributes->getNamedItem("default");
            $mandatory = ($param->attributes->getNamedItem("mandatory")->nodeValue == "true");
            if ($mandatory && !isSet($parameters[$name])) {
                throw new \Exception("Missing parameter '".$name."' of type '$type'");
            }
            if ($defaultNode != null && !isSet($parameters[$name])) {
                $parameters[$name] = $defaultNode->nodeValue;
                $changes ++;
            }
            $declaredParams[] = $name;
        }
        foreach ($parameters as $k => $n) {
            if(!in_array($k, $declaredParams)) {
                $changes ++;
                unset($parameters[$k]);
            }
        }
        if($changes){
            $request = $request->withParsedBody($parameters);
        }
    }

    /**
     * Main method for querying the XML registry, find an action and all its associated processors,
     * and apply all the callbacks.
     *
     * @static
     * @param ServerRequestInterface $request
     * @param \DOMNode $actionNode
     * @return ResponseInterface
     * @throws \Exception
     */
    public static function run(ServerRequestInterface $request, &$actionNode = null)
    {
        $actionName = $request->getAttribute("action");
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");

        $xPath = self::initXPath($ctx, true);
        if ($actionNode == null) {
            $actions = $xPath->query("actions/action[@name='$actionName']");
            if (!$actions->length) {
                throw new ActionNotFoundException($actionName);
            }
            $actionNode = $actions->item(0);
        }
        //Check Rights
        if (UsersService::usersEnabled()) {
            $loggedUser = $ctx->getUser();
            if( $actionName != "logout" && Controller::actionNeedsRight($actionNode, $xPath, "userLogged", "only") && $loggedUser == null){
                    throw new AuthRequiredException();
                }
            if( Controller::actionNeedsRight($actionNode, $xPath, "adminOnly") &&
                ($loggedUser == null || !$loggedUser->isAdmin())){
                    throw new AuthRequiredException("207");
                }
            if( Controller::actionNeedsRight($actionNode, $xPath, "read") &&
                ($loggedUser == null || !$loggedUser->canRead($ctx->getRepositoryId().""))){
                    if($actionName == "ls" & $loggedUser!=null
                        && $loggedUser->canWrite($ctx->getRepositoryId()."")){
                        // Special case of "write only" right : return empty listing, no auth error.
                        $response = new Response();
                        $response = $response->withHeader("Content-type", "text/xml");
                        $response->getBody()->write(XMLHelper::wrapDocument(""));
                        return $response;
                    }else{
                        throw new AuthRequiredException("208");
                    }
                }
            if( Controller::actionNeedsRight($actionNode, $xPath, "write") &&
                ($loggedUser == null || !$loggedUser->canWrite($ctx->getRepositoryId().""))){
                    throw new AuthRequiredException("207");
                }
        }

        $queries = [
            'pre_processing/serverCallback' => true,
            'processing/serverCallback' => false,
            'post_processing/serverCallback[not(@capture="true")]' => true,
            'post_processing/serverCallback[@capture="true"]' => true
        ];

        $response = new Response();

        foreach ($queries as $cbQuery => $multiple){
            $calls = self::getCallbackNode($xPath, $actionNode, $cbQuery, $actionName, $request->getParsedBody(), $_FILES, $multiple);
            if(!$multiple && count($calls)){
                self::checkParams($request, $calls[0], $xPath);
            }
            foreach ($calls as $call){
                self::handleRequest($call, $request, $response);
            }
        }

        self::applyHook("response.send", array($ctx, &$response));

        return $response;
    }

    /**
     * @param ContextInterface $context
     * @param string $action
     * @param array $parameters
     * @return ServerRequestInterface
     */
    public static function executableRequest(ContextInterface $context, $action, $parameters = []){
        $request = ServerRequestFactory::fromGlobals();
        $request = $request
            ->withAttribute("ctx", $context)
            ->withAttribute("action", $action)
            ->withParsedBody($parameters);
        return $request;
    }


    /**
     * Find a callback node by its xpath query, filtering with the applyCondition if the xml attribute exists.
     * @static
     * @param \DOMXPath $xPath
     * @param \DOMNode $actionNode
     * @param string $query
     * @param string $actionName
     * @param array $httpVars
     * @param array $fileVars
     * @param bool $multiple
     * @return \DOMElement[]
     */
    private static function getCallbackNode($xPath, $actionNode, $query ,$actionName, $httpVars, $fileVars, $multiple = true)
    {
        $callbacks = $xPath->query($query, $actionNode);
        if(!$callbacks->length) return [];
        if ($multiple) {
            $cbArray = array();
            foreach ($callbacks as $callback) {
                if (self::appliesCondition($callback, $actionName, $httpVars, $fileVars)) {
                    $cbArray[] = $callback;
                }
            }
            if(!count($cbArray)) return [];
            return $cbArray;
        } else {
            $callback=$callbacks->item(0);
            if(!self::appliesCondition($callback, $actionName, $httpVars, $fileVars)) return [];
            return [$callback];
        }
    }

    /**
     * Check in the callback node if an applyCondition XML attribute exists, and eval its content.
     * The content must set an $apply boolean as result
     * @static
     * @param \DOMElement|\DOMNode $callback
     * @param string $actionName
     * @param array $httpVars
     * @param array $fileVars
     * @return bool
     */
    private static function appliesCondition($callback, $actionName, $httpVars, $fileVars)
    {
        if ($callback->getAttribute("applyCondition")!="") {
            $apply = false;
            eval($callback->getAttribute("applyCondition"));
            if(!$apply) return false;
        }
        return true;
    }

    /**
     * Applies a callback node
     * @static
     * @param ContextInterface $context
     * @param \DOMElement|array $callback The DOM Node or directly an array of attributes
     * @param null $variableArgs
     * @param bool $defer
     * @throws PydioException
     * @return mixed
     */
    private static function applyCallback($context, $callback, &$variableArgs, $defer = false)
    {
        //Processing
        if(is_array($callback)){
            $plugId = $callback["pluginId"];
            $methodName = $callback["methodName"];
        }else{
            $plugId = $callback->getAttribute("pluginId");
            $methodName = $callback->getAttribute("methodName");
        }
        $plugInstance = PluginsService::getInstance($context)->getPluginById($plugId);
        //return call_user_func(array($plugInstance, $methodName), $actionName, $httpVars, $fileVars);
        // Do not use call_user_func, it cannot pass parameters by reference.
        if (method_exists($plugInstance, $methodName)) {
            if ($defer == true) {
                ShutdownScheduler::getInstance()->registerShutdownEvent(array($plugInstance, $methodName), $variableArgs);
            } else {
                call_user_func_array(array($plugInstance, $methodName), $variableArgs);
            }
        } else {
            throw new PydioException("Cannot find method $methodName for plugin $plugId!");
        }
        return null;
    }

    /**
     * @param $callback
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws PydioException
     */
    private static function handleRequest($callback, ServerRequestInterface &$request, ResponseInterface &$response){

        list($plugInstance, $methodName) = self::parseCallback($request->getAttribute("ctx"), $callback);

        $reflectMethod = new \ReflectionMethod($plugInstance, $methodName);
        $reflectParams = $reflectMethod->getParameters();
        $supportPSR = false;
        foreach ($reflectParams as $reflectParam){
            if($reflectParam->getClass() !== null && $reflectParam->getClass()->getName() == "Psr\\Http\\Message\\ServerRequestInterface"){
                $supportPSR = true;
                break;
            }
        }
        if($supportPSR){

            $plugInstance->$methodName($request, $response);

        }else{

            $httpVars = $request->getParsedBody();
            $result = $plugInstance->$methodName($request->getAttribute("action"), $httpVars, $_FILES, $request->getAttribute("ctx"));
            // May have been modified
            $request = $request->withParsedBody($httpVars);

            if(!empty($result)){
                $response->getBody()->write(XMLHelper::wrapDocument($result));
                $response = $response->withHeader("Content-type", "text/xml; charset=UTF-8");
            }
        }

    }

    /**
     * @param \DOMElement|array $callback
     * @throws PydioException
     * @return array
     */
    private static function parseCallback(ContextInterface $ctx, $callback){
        if(is_array($callback)){
            $plugId = $callback["pluginId"];
            $methodName = $callback["methodName"];
        }else{
            $plugId = $callback->getAttribute("pluginId");
            $methodName = $callback->getAttribute("methodName");
        }
        $plugInstance = PluginsService::getInstance($ctx)->getPluginById($plugId);
        if(empty($plugInstance) || !method_exists($plugInstance, $methodName)){
            throw new PydioException("Cannot find method $methodName for plugin $plugId!");
        }
        return [$plugInstance, $methodName];
    }

    /**
     * Find all callbacks registered for a given hook and apply them. Caches the hooks locally, on a
     * per-context basis
     *
     * @static
     * @param string $hookName
     * @param array $args
     * @param bool $forceNonDefer
     * @throws PydioException
     * @throws \Exception
     */
    public static function applyHook($hookName, $args, $forceNonDefer = false)
    {
        $findContext = null;
        foreach ($args as $arg){
            if($arg instanceof ContextInterface) {
                $findContext = $arg;
                break;
            }else if($arg instanceof ContextProviderInterface){
                $findContext = $arg->getContext();
                break;
            }
        }
        if($findContext == null){
            Logger::error("Controller", "applyHook", "Applying hook $hookName without context");
            throw new \Exception("No context found for hook $hookName: please make sure to pass at list one ContextInterface or Context Provider object");
        }

        $contextId = $findContext->getStringIdentifier();
        if(isSet(self::$hooksCaches[$contextId][$hookName])){
            $hooks = self::$hooksCaches[$contextId][$hookName];
            foreach($hooks as $hook){
                if (isSet($hook["applyCondition"]) && $hook["applyCondition"]!="") {
                    $apply = false;
                    eval($hook["applyCondition"]);
                    if(!$apply) continue;
                }
                $defer = $hook["defer"];
                if($defer && $forceNonDefer) $defer = false;
                self::applyCallback($findContext, $hook, $args, $defer);
            }
            return;
        }
        $xPath = self::initXPath($findContext, true);
        $callbacks = $xPath->query("hooks/serverCallback[@hookName='$hookName']");
        if(!$callbacks->length) return ;
        if(!isSet(self::$hooksCaches[$contextId])){
            self::$hooksCaches[$contextId] = [];
        }
        self::$hooksCaches[$contextId][$hookName] = [];
        /**
         * @var $callback \DOMElement
         */
        foreach ($callbacks as $callback) {
            $defer = ($callback->getAttribute("defer") === "true");
            $applyCondition = $callback->getAttribute("applyCondition");
            $plugId = $callback->getAttribute("pluginId");
            $methodName = $callback->getAttribute("methodName");
            $dontBreakOnExceptionAtt = $callback->getAttribute("dontBreakOnException");
            $dontBreakOnException = !empty($dontBreakOnExceptionAtt) && $dontBreakOnExceptionAtt == "true";
            $hookCallback = array(
                "defer" => $defer,
                "applyCondition" => $applyCondition,
                "pluginId"    => $plugId,
                "methodName"    => $methodName
            );
            self::$hooksCaches[$contextId][$hookName][] = $hookCallback;
            if (!empty($applyCondition)) {
                $apply = false;
                eval($applyCondition);
                if(!$apply) continue;
            }
            if($defer && $forceNonDefer) $defer = false;
            if($dontBreakOnException){
                try{
                    self::applyCallback($findContext, $hookCallback, $args, $defer);
                }catch(\Exception $e){
                    Logger::error("[Hook $hookName]", "[Callback ".$plugId.".".$methodName."]", $e->getMessage());
                }
            }else{
                self::applyCallback($findContext, $hookCallback, $args, $defer);
            }
        }
    }

    /**
     * Find the statically defined callbacks for a given hook and apply them
     * @static
     * @param $hookName
     * @param $args
     * @return void
     */
    public static function applyIncludeHook($hookName, &$args)
    {
        if(!isSet(self::$includeHooks[$hookName])) return;
        foreach (self::$includeHooks[$hookName] as $callback) {
            call_user_func_array($callback, $args);
        }
    }

    /**
     * Register a hook statically when it must be defined before the XML registry construction.
     * @static
     * @param $hookName
     * @param $callback
     * @return void
     */
    public static function registerIncludeHook($hookName, $callback)
    {
        if (!isSet(self::$includeHooks[$hookName])) {
            self::$includeHooks[$hookName] = array();
        }
        self::$includeHooks[$hookName][] = $callback;
    }

    /**
     * Check the rightsContext node of an action.
     * @static
     * @param \DOMNode $actionNode
     * @param \DOMXPath $xPath
     * @param string $right
     * @return bool
     */
    public static function actionNeedsRight($actionNode, $xPath, $right, $expectedValue="true")
    {
        $rights = $xPath->query("rightsContext", $actionNode);
        if(!$rights->length) return false;
        $rightNode =  $rights->item(0);
        $rightAttr = $xPath->query("@".$right, $rightNode);
        if ($rightAttr->length && $rightAttr->item(0)->value == $expectedValue) {
            return true;
        }
        return false;
    }

}
