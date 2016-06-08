<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Core\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\ActionNotFoundException;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Exception\PydioException;
use Pydio\Auth\Core\AJXP_Safe;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\UnixProcess;
use Pydio\Log\Core\AJXP_Logger;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

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
     * @var \DOMXPath
     */
    private static $xPath;
    /**
     * @var bool
     */
    public static $lastActionNeedsAuth = false;
    /**
     * @var array
     */
    private static $includeHooks = array();

    private static $hooksCache = array();

    /**
     * Initialize the queryable xPath object
     * @static
     * @param bool $useCache Whether to cache the registry version in a memory cache.
     * @return \DOMXPath
     */
    private static function initXPath($useCache = false)
    {
        if (!isSet(self::$xPath)) {
            $ctx = Context::fromGlobalServices();
            $registry = PluginsService::getInstance($ctx)->getFilteredXMLRegistry(false, false, $useCache);
            self::$xPath = new \DOMXPath($registry);
        }
        return self::$xPath;
    }

    public static function registryReset(){
        self::$xPath = null;
        self::$hooksCache = array();
    }


    /**
     * @param ServerRequestInterface $request
     * @return bool|\DOMElement
     * @throws ActionNotFoundException
     */
    public static function parseRestParameters(ServerRequestInterface &$request){
        $actionName = $request->getAttribute("action");
        $path = $request->getAttribute("rest_path");
        $reqParameters = $request->getParsedBody();

        $xPath = self::initXPath(true);
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
        $paramValues = array_map("urldecode", explode("/", trim($path, "/"), count($paramNames)));
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
     * @static
     * @param array $parameters
     * @param \DOMNode $callbackNode
     * @param \DOMXPath $xPath
     * @throws \Exception
     */
    public static function checkParams(&$parameters, $callbackNode, $xPath)
    {
        if (!$callbackNode->attributes->getNamedItem('checkParams') || $callbackNode->attributes->getNamedItem('checkParams')->nodeValue != "true") {
            return;
        }
        $inputParams = $xPath->query("input_param", $callbackNode);
        $declaredParams = array();
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
            }
            $declaredParams[] = $name;
        }
        foreach ($parameters as $k => $n) {
            if(!in_array($k, $declaredParams)) unset($parameters[$k]);
        }
    }

    /**
     * Main method for querying the XML registry, find an action and all its associated processors,
     * and apply all the callbacks.
     * @static
     * @param ServerRequestInterface $request
     * @param \DOMNode $actionNode
     * @return ResponseInterface
     * @throws \Exception
     */
    public static function run(ServerRequestInterface $request, &$actionNode = null)
    {
        $actionName = $request->getAttribute("action");
        $xPath = self::initXPath(true);
        if ($actionNode == null) {
            $actions = $xPath->query("actions/action[@name='$actionName']");
            if (!$actions->length) {
                throw new ActionNotFoundException($actionName);
            }
            $actionNode = $actions->item(0);
        }
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
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
                ($loggedUser == null || !$loggedUser->canRead(ConfService::getCurrentRepositoryId().""))){
                    if($actionName == "ls" & $loggedUser!=null
                        && $loggedUser->canWrite(ConfService::getCurrentRepositoryId()."")){
                        // Special case of "write only" right : return empty listing, no auth error.
                        $response = new Response();
                        $response->getBody()->write(XMLWriter::wrapDocument(""));
                        return $response;
                    }else{
                        throw new AuthRequiredException("208");
                    }
                }
            if( Controller::actionNeedsRight($actionNode, $xPath, "write") &&
                ($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRepositoryId().""))){
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
                self::checkParams($httpVars, $calls[0], $xPath);
            }
            foreach ($calls as $call){
                self::handleRequest($call, $request, $response);
            }
        }

        self::applyHook("response.send", array(&$response));

        return $response;
    }

    /**
     * @param Task $task
     */
    public static function applyTaskInBackground(Task $task){

        $parameters = $task->getParameters();
        $task->setStatus(Task::STATUS_RUNNING);
        TaskService::getInstance()->updateTask($task);
        self::applyActionInBackground($task->getContext(), $task->getAction(), $parameters, "", $task->getId());

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
     * Launch a command-line version of the framework by passing the actionName & parameters as arguments.
     * @static
     * @param ContextInterface $ctx
     * @param String $actionName
     * @param array $parameters
     * @param string $statusFile
     * @param string $taskId
     * @return null|UnixProcess
     */
    public static function applyActionInBackground(ContextInterface $ctx, $actionName, $parameters, $statusFile = "", $taskId = null)
    {
        $repositoryId = $ctx->getRepositoryId();
        $user = $ctx->hasUser() ? $ctx->getUser()->getId() : "shared";

        $token = md5(time());
        $logDir = AJXP_CACHE_DIR."/cmd_outputs";
        if(!is_dir($logDir)) mkdir($logDir, 0755);
        $logFile = $logDir."/".$token.".out";

        if (UsersService::usersEnabled()) {
            $cKey = ConfService::getCoreConf("AJXP_CLI_SECRET_KEY", "conf");
            if(empty($cKey)){
                $cKey = "\1CDAFx¨op#";
            }
            $user = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($token.$cKey), $user, MCRYPT_MODE_ECB));
        }
        $robustInstallPath = str_replace("/", DIRECTORY_SEPARATOR, AJXP_INSTALL_PATH);
        $cmd = ConfService::getCoreConf("CLI_PHP")." ".$robustInstallPath.DIRECTORY_SEPARATOR."cmd.php -u=$user -t=$token -a=$actionName -r=$repositoryId";
        /* Inserted next 3 lines to quote the command if in windows - rmeske*/
        if (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") {
            $cmd = ConfService::getCoreConf("CLI_PHP")." ".chr(34).$robustInstallPath.DIRECTORY_SEPARATOR."cmd.php".chr(34)." -u=$user -t=$token -a=$actionName -r=$repositoryId";
        }
        if (!empty($statusFile)) {
            $cmd .= " -s=".$statusFile;
        }
        if (!empty($taskId)) {
            $cmd .= " -k=".$taskId;
        }
        foreach ($parameters as $key=>$value) {
            if($key == "action" || $key == "get_action") continue;
            if(is_array($value)){
                $index = 0;
                foreach($value as $v){
                    $cmd .= " --file_".$index."=".escapeshellarg($v);
                    $index++;
                }
            }else{
                $cmd .= " --$key=".escapeshellarg($value);
            }
        }

        $repoObject = $ctx->getRepository();
        $clearEnv = false;
        if($repoObject->getContextOption($ctx, "USE_SESSION_CREDENTIALS")){
            $encodedCreds = AJXP_Safe::getEncodedCredentialString();
            if(!empty($encodedCreds)){
                putenv("AJXP_SAFE_CREDENTIALS=".$encodedCreds);
                $clearEnv = "AJXP_SAFE_CREDENTIALS";
            }
        }

        $res = self::runCommandInBackground($cmd, $logFile);
        if(!empty($clearEnv)){
            putenv($clearEnv);
        }
        return $res;
    }

    /**
     * @param $cmd
     * @param $logFile
     * @return UnixProcess|null
     */
    public static function runCommandInBackground($cmd, $logFile)
    {
        if (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") {
              if(AJXP_SERVER_DEBUG) $cmd .= " > ".$logFile;
              if (class_exists("COM") && ConfService::getCoreConf("CLI_USE_COM")) {
                  $WshShell   = new COM("WScript.Shell");
                  $oExec      = $WshShell->Run("cmd /C $cmd", 0, false);
              } else {
                  $basePath = str_replace("/", DIRECTORY_SEPARATOR, AJXP_INSTALL_PATH);
                  $tmpBat = implode(DIRECTORY_SEPARATOR, array( $basePath, "data","tmp", md5(time()).".bat"));
                  $cmd = "@chcp 1252 > nul \r\n".$cmd;
                  $cmd .= "\n DEL ".chr(34).$tmpBat.chr(34);
                  AJXP_Logger::debug("Writing file $cmd to $tmpBat");
                  file_put_contents($tmpBat, $cmd);
                  pclose(popen('start /b "CLI" "'.$tmpBat.'"', 'r'));
              }
            return null;
        } else {
            $process = new UnixProcess($cmd, (AJXP_SERVER_DEBUG?$logFile:null));
            AJXP_Logger::debug("Starting process and sending output dev null");
            return $process;
        }
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
     * @param \DOMElement|array $callback The DOM Node or directly an array of attributes
     * @param null $variableArgs
     * @param bool $defer
     * @throws PydioException
     * @return mixed
     */
    private static function applyCallback($callback, &$variableArgs, $defer = false)
    {
        //Processing
        if(is_array($callback)){
            $plugId = $callback["pluginId"];
            $methodName = $callback["methodName"];
        }else{
            $plugId = $callback->getAttribute("pluginId");
            $methodName = $callback->getAttribute("methodName");
        }
        $plugInstance = PluginsService::findPluginById($plugId);
        //return call_user_func(array($plugInstance, $methodName), $actionName, $httpVars, $fileVars);
        // Do not use call_user_func, it cannot pass parameters by reference.
        if (method_exists($plugInstance, $methodName)) {
            if ($defer == true) {
                ShutdownScheduler::getInstance()->registerShutdownEventArray(array($plugInstance, $methodName), $variableArgs);
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

        list($plugInstance, $methodName) = self::parseCallback($callback);

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
                error_log("Action has result " . $request->getAttribute("action").", wrapping in XML Doc");
                $response->getBody()->write(XMLWriter::wrapDocument($result));
                $response = $response->withHeader("Content-type", "text/xml; charset=UTF-8");
            }
        }

    }

    /**
     * @param \DOMElement|array $callback
     * @throws PydioException
     * @return array
     */
    private static function parseCallback($callback){
        if(is_array($callback)){
            $plugId = $callback["pluginId"];
            $methodName = $callback["methodName"];
        }else{
            $plugId = $callback->getAttribute("pluginId");
            $methodName = $callback->getAttribute("methodName");
        }
        $plugInstance = PluginsService::findPluginById($plugId);
        if(empty($plugInstance) || !method_exists($plugInstance, $methodName)){
            throw new PydioException("Cannot find method $methodName for plugin $plugId!");
        }
        return [$plugInstance, $methodName];
    }

    /**
     * Find all callbacks registered for a given hook and apply them
     * @static
     * @param string $hookName
     * @param array $args
     * @param bool $forceNonDefer
     * @return void
     */
    public static function applyHook($hookName, $args, $forceNonDefer = false)
    {
        if(isSet(self::$hooksCache[$hookName])){
            $hooks = self::$hooksCache[$hookName];
            foreach($hooks as $hook){
                if (isSet($hook["applyCondition"]) && $hook["applyCondition"]!="") {
                    $apply = false;
                    eval($hook["applyCondition"]);
                    if(!$apply) continue;
                }
                $defer = $hook["defer"];
                if($defer && $forceNonDefer) $defer = false;
                self::applyCallback($hook, $args, $defer);
            }
            return;
        }
        $xPath = self::initXPath(true);
        $callbacks = $xPath->query("hooks/serverCallback[@hookName='$hookName']");
        if(!$callbacks->length) return ;
        self::$hooksCache[$hookName] = array();
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
            self::$hooksCache[$hookName][] = $hookCallback;
            if (!empty($applyCondition)) {
                $apply = false;
                eval($applyCondition);
                if(!$apply) continue;
            }
            if($defer && $forceNonDefer) $defer = false;
            if($dontBreakOnException){
                try{
                    self::applyCallback($hookCallback, $args, $defer);
                }catch(\Exception $e){
                    AJXP_Logger::error("[Hook $hookName]", "[Callback ".$plugId.".".$methodName."]", $e->getMessage());
                }
            }else{
                self::applyCallback($hookCallback, $args, $defer);
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
            //self::$lastActionNeedsAuth = true;
            return true;
        }
        return false;
    }

    /**
     * Utilitary used by the postprocesors to forward previously computed data
     * @static
     * @param array $postProcessData
     * @return void
     */
    public static function passProcessDataThrough($postProcessData)
    {
        if (isSet($postProcessData["pre_processor_results"]) && is_array($postProcessData["pre_processor_results"])) {
            print(implode("", $postProcessData["pre_processor_results"]));
        }
        if (isSet($postProcessData["processor_result"])) {
            print($postProcessData["processor_result"]);
        }
        if(isSet($postProcessData["ob_output"])) print($postProcessData["ob_output"]);
    }
}
