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
defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * Core controller for dispatching the actions.
 * It uses the XML Registry (simple version, not extended) to search all the <action> tags and find the action.
 * @package Pydio
 * @subpackage Core
 */
class AJXP_Controller
{
    /**
     * @var DOMXPath
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
     * @return DOMXPath
     */
    private static function initXPath()
    {
        if (!isSet(self::$xPath)) {

            $registry = AJXP_PluginsService::getXmlRegistry( false );
            $changes = self::filterRegistryFromRole($registry);
            if($changes) AJXP_PluginsService::updateXmlRegistry($registry);
            self::$xPath = new DOMXPath($registry);
        }
        return self::$xPath;
    }

    public static function registryReset(){
        self::$xPath = null;
        self::$hooksCache = array();
    }

    /**
     * Check the current user "specificActionsRights" and filter the full registry actions with these.
     * @static
     * @param DOMDocument $registry
     * @return bool
     */
    public static function filterRegistryFromRole(&$registry)
    {
        if(!AuthService::usersEnabled()) return false ;
        $loggedUser = AuthService::getLoggedUser();
        if($loggedUser == null) return false;
        $crtRepo = ConfService::getRepository();
        $crtRepoId = AJXP_REPO_SCOPE_ALL; // "ajxp.all";
        if ($crtRepo != null && is_a($crtRepo, "Repository")) {
            $crtRepoId = $crtRepo->getId();
        }
        $actionRights = $loggedUser->mergedRole->listActionsStatesFor($crtRepo);
        $changes = false;
        $xPath = new DOMXPath($registry);
        foreach ($actionRights as $pluginName => $actions) {
            foreach ($actions as $actionName => $enabled) {
                if($enabled !== false) continue;
                $actions = $xPath->query("actions/action[@name='$actionName']");
                if (!$actions->length) {
                    continue;
                }
                $action = $actions->item(0);
                $action->parentNode->removeChild($action);
                $changes = true;
            }
        }
        $parameters = $loggedUser->mergedRole->listParameters();
        foreach ($parameters as $scope => $paramsPlugs) {
            if ($scope == AJXP_REPO_SCOPE_ALL || $scope == $crtRepoId || ($crtRepo!=null && $crtRepo->hasParent() && $scope == AJXP_REPO_SCOPE_SHARED)) {
                foreach ($paramsPlugs as $plugId => $params) {
                    foreach ($params as $name => $value) {
                        // Search exposed plugin_configs, replace if necessary.
                        $searchparams = $xPath->query("plugins/*[@id='$plugId']/plugin_configs/property[@name='$name']");
                        if(!$searchparams->length) continue;
                        $param = $searchparams->item(0);
                        $newCdata = $registry->createCDATASection(json_encode($value));
                        $param->removeChild($param->firstChild);
                        $param->appendChild($newCdata);
                    }
                }
            }
        }
        return $changes;
    }


    /**
     * @param $actionName
     * @param $path
     * @return bool
     */
    public static function findRestActionAndApply($actionName, $path)
    {
        $xPath = self::initXPath();
        $actions = $xPath->query("actions/action[@name='$actionName']");
        if (!$actions->length) {
            self::$lastActionNeedsAuth = true;
            return false;
        }
        $action = $actions->item(0);
        $restPathList = $xPath->query("processing/serverCallback/@restParams", $action);
        if (!$restPathList->length) {
            self::$lastActionNeedsAuth = true;
            return false;
        }
        $restPath = $restPathList->item(0)->nodeValue;
        $paramNames = explode("/", trim($restPath, "/"));
        $path = array_shift(explode("?", $path));
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
        $httpVars = array_merge($_GET, $_POST, array_combine($paramNames, $paramValues));
        return self::findActionAndApply($actionName, $httpVars, $_FILES, $action);

    }

    /**
     * @static
     * @param Array $parameters
     * @param DOMNode $callbackNode
     * @param DOMXPath $xPath
     * @throws Exception
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
                throw new Exception("Missing parameter '".$name."' of type '$type'");
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
     * @param String $actionName
     * @param array $httpVars
     * @param array $fileVars
     * @param DOMNode $action
     * @return mixed
     */
    public static function findActionAndApply($actionName, $httpVars, $fileVars, &$action = null)
    {
        $actionName = AJXP_Utils::sanitize($actionName, AJXP_SANITIZE_EMAILCHARS);
        if ($actionName == "cross_copy") {
            $pService = AJXP_PluginsService::getInstance();
            $actives = $pService->getActivePlugins();
            $accessPlug = $pService->getPluginsByType("access");
            if (count($accessPlug)) {
                foreach ($accessPlug as $key=>$objbect) {
                    if ($actives[$objbect->getId()] === true) {
                        call_user_func(array($pService->getPluginById($objbect->getId()), "crossRepositoryCopy"), $httpVars);
                        break;
                    }
                }
            }
            self::$lastActionNeedsAuth = true;
            return null;
        }
        $xPath = self::initXPath();
        if ($action == null) {
            $actions = $xPath->query("actions/action[@name='$actionName']");
            if (!$actions->length) {
                self::$lastActionNeedsAuth = true;
                return false;
            }
            $action = $actions->item(0);
        }
        //Check Rights
        if (AuthService::usersEnabled()) {
            $loggedUser = AuthService::getLoggedUser();
            if( AJXP_Controller::actionNeedsRight($action, $xPath, "adminOnly") &&
                ($loggedUser == null || !$loggedUser->isAdmin())){
                    $mess = ConfService::getMessages();
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess[207]);
                    AJXP_XMLWriter::requireAuth();
                    AJXP_XMLWriter::close();
                    exit(1);
                }
            if( AJXP_Controller::actionNeedsRight($action, $xPath, "read") &&
                ($loggedUser == null || !$loggedUser->canRead(ConfService::getCurrentRepositoryId().""))){
                    AJXP_XMLWriter::header();
                    if($actionName == "ls" & $loggedUser!=null
                        && $loggedUser->canWrite(ConfService::getCurrentRepositoryId()."")){
                        // Special case of "write only" right : return empty listing, no auth error.
                        AJXP_XMLWriter::close();
                        exit(1);
                    }
                    $mess = ConfService::getMessages();
                    AJXP_XMLWriter::sendMessage(null, $mess[208]);
                    AJXP_XMLWriter::requireAuth();
                    AJXP_XMLWriter::close();
                    exit(1);
                }
            if( AJXP_Controller::actionNeedsRight($action, $xPath, "write") &&
                ($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRepositoryId().""))){
                    $mess = ConfService::getMessages();
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess[207]);
                    AJXP_XMLWriter::requireAuth();
                    AJXP_XMLWriter::close();
                    exit(1);
                }
        }

        $preCalls = self::getCallbackNode($xPath, $action, 'pre_processing/serverCallback', $actionName, $httpVars, $fileVars, true);
        $postCalls = self::getCallbackNode($xPath, $action, 'post_processing/serverCallback[not(@capture="true")]', $actionName, $httpVars, $fileVars, true);
        $captureCalls = self::getCallbackNode($xPath, $action, 'post_processing/serverCallback[@capture="true"]', $actionName, $httpVars, $fileVars, true);
        $mainCall = self::getCallbackNode($xPath, $action, "processing/serverCallback",$actionName, $httpVars, $fileVars, false);
        if ($mainCall != null) {
            self::checkParams($httpVars, $mainCall, $xPath);
        }

        if ($captureCalls !== false) {
            // Make sure the ShutdownScheduler has its own OB started BEFORE, as it will presumabily be
            // executed AFTER the end of this one.
            AJXP_ShutdownScheduler::getInstance();
            ob_start();
            $params = array("pre_processor_results" => array(), "post_processor_results" => array());
        }
        if ($preCalls !== false) {
            foreach ($preCalls as $preCall) {
                // A Preprocessing callback can modify its input arguments (passed by ref)
                $preResult = self::applyCallback($preCall, $actionName, $httpVars, $fileVars);
                if (isSet($params)) {
                    $params["pre_processor_results"][$preCall->getAttribute("pluginId")] = $preResult;
                }
            }
        }
        if ($mainCall) {
            $result = self::applyCallback($mainCall, $actionName, $httpVars, $fileVars);
            if (isSet($params)) {
                $params["processor_result"] = $result;
            }
        }
        if ($postCalls !== false) {
            foreach ($postCalls as $postCall) {
                // A Preprocessing callback can modify its input arguments (passed by ref)
                $postResult = self::applyCallback($postCall, $actionName, $httpVars, $fileVars);
                if (isSet($params)) {
                    $params["post_processor_results"][$postCall->getAttribute("pluginId")] = $postResult;
                }
            }
        }
        if ($captureCalls !== false) {
            $params["ob_output"] = ob_get_contents();
            ob_end_clean();
            foreach ($captureCalls as $captureCall) {
                self::applyCallback($captureCall, $actionName, $httpVars, $params);
            }
        } else {
            if(isSet($result)) return $result;
        }
        return null;
    }

    /**
     * Launch a command-line version of the framework by passing the actionName & parameters as arguments.
     * @static
     * @param String $currentRepositoryId
     * @param String $actionName
     * @param Array $parameters
     * @param string $user
     * @param string $statusFile
     * @return null|UnixProcess
     */
    public static function applyActionInBackground($currentRepositoryId, $actionName, $parameters, $user ="", $statusFile = "")
    {
        $token = md5(time());
        $logDir = AJXP_CACHE_DIR."/cmd_outputs";
        if(!is_dir($logDir)) mkdir($logDir, 0755);
        $logFile = $logDir."/".$token.".out";
        if (empty($user)) {
            if(AuthService::usersEnabled() && AuthService::getLoggedUser() !== null) $user = AuthService::getLoggedUser()->getId();
            else $user = "shared";
        }
        if (AuthService::usersEnabled()) {
            $cKey = ConfService::getCoreConf("AJXP_CLI_SECRET_KEY", "conf");
            if(empty($cKey)){
                $cKey = "\1CDAFx¨op#";
            }
            $user = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($token.$cKey), $user, MCRYPT_MODE_ECB));
        }
        $robustInstallPath = str_replace("/", DIRECTORY_SEPARATOR, AJXP_INSTALL_PATH);
        $cmd = ConfService::getCoreConf("CLI_PHP")." ".$robustInstallPath.DIRECTORY_SEPARATOR."cmd.php -u=$user -t=$token -a=$actionName -r=$currentRepositoryId";
        /* Inserted next 3 lines to quote the command if in windows - rmeske*/
        if (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") {
            $cmd = ConfService::getCoreConf("CLI_PHP")." ".chr(34).$robustInstallPath.DIRECTORY_SEPARATOR."cmd.php".chr(34)." -u=$user -t=$token -a=$actionName -r=$currentRepositoryId";
        }
        if ($statusFile != "") {
            $cmd .= " -s=".$statusFile;
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

        $repoObject = ConfService::getRepository();
        $clearEnv = false;
        if($repoObject->getOption("USE_SESSION_CREDENTIALS")){
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
        } else {
            $process = new UnixProcess($cmd, (AJXP_SERVER_DEBUG?$logFile:null));
            AJXP_Logger::debug("Starting process and sending output dev null");
            return $process;
        }
    }

    /**
     * Find a callback node by its xpath query, filtering with the applyCondition if the xml attribute exists.
     * @static
     * @param DOMXPath $xPath
     * @param DOMNode $actionNode
     * @param string $query
     * @param string $actionName
     * @param array $httpVars
     * @param array $fileVars
     * @param bool $multiple
     * @return DOMElement|bool
     */
    private static function getCallbackNode($xPath, $actionNode, $query ,$actionName, $httpVars, $fileVars, $multiple = true)
    {
        $callbacks = $xPath->query($query, $actionNode);
        if(!$callbacks->length) return false;
        if ($multiple) {
            $cbArray = array();
            foreach ($callbacks as $callback) {
                if (self::appliesCondition($callback, $actionName, $httpVars, $fileVars)) {
                    $cbArray[] = $callback;
                }
            }
            if(!count($cbArray)) return  false;
            return $cbArray;
        } else {
            $callback=$callbacks->item(0);
            if(!self::appliesCondition($callback, $actionName, $httpVars, $fileVars)) return false;
            return $callback;
        }
    }

    /**
     * Check in the callback node if an applyCondition XML attribute exists, and eval its content.
     * The content must set an $apply boolean as result
     * @static
     * @param DOMElement $callback
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
     * @param DOMElement|Array $callback The DOM Node or directly an array of attributes
     * @param String $actionName
     * @param Array $httpVars
     * @param Array $fileVars
     * @param null $variableArgs
     * @param bool $defer
     * @throws AJXP_Exception* @internal param \DOMXPath $xPath
     * @return mixed
     */
    private static function applyCallback($callback, &$actionName, &$httpVars, &$fileVars, &$variableArgs = null, $defer = false)
    {
        //Processing
        if(is_array($callback)){
            $plugId = $callback["pluginId"];
            $methodName = $callback["methodName"];
        }else{
            $plugId = $callback->getAttribute("pluginId");
            $methodName = $callback->getAttribute("methodName");
        }
        $plugInstance = AJXP_PluginsService::findPluginById($plugId);
        //return call_user_func(array($plugInstance, $methodName), $actionName, $httpVars, $fileVars);
        // Do not use call_user_func, it cannot pass parameters by reference.
        if (method_exists($plugInstance, $methodName)) {
            if ($variableArgs == null) {
                return $plugInstance->$methodName($actionName, $httpVars, $fileVars);
            } else {
                if ($defer == true) {
                    AJXP_ShutdownScheduler::getInstance()->registerShutdownEventArray(array($plugInstance, $methodName), $variableArgs);
                } else {
                    call_user_func_array(array($plugInstance, $methodName), $variableArgs);
                }
            }
        } else {
            throw new AJXP_Exception("Cannot find method $methodName for plugin $plugId!");
        }
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
                self::applyCallback($hook, $fake1, $fake2, $fake3, $args, $defer);
            }
            return;
        }
        $xPath = self::initXPath();
        $callbacks = $xPath->query("hooks/serverCallback[@hookName='$hookName']");
        if(!$callbacks->length) return ;
        self::$hooksCache[$hookName] = array();
        foreach ($callbacks as $callback) {
            $defer = ($callback->getAttribute("defer") === "true");
            $applyCondition = $callback->getAttribute("applyCondition");
            $plugId = $callback->getAttribute("pluginId");
            $methodName = $callback->getAttribute("methodName");
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
            self::applyCallback($hookCallback, $fake1, $fake2, $fake3, $args, $defer);
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
     * @param DOMNode $actionNode
     * @param DOMXPath $xPath
     * @param string $right
     * @return bool
     */
    public static function actionNeedsRight($actionNode, $xPath, $right)
    {
        $rights = $xPath->query("rightsContext", $actionNode);
        if(!$rights->length) return false;
        $rightNode =  $rights->item(0);
        $rightAttr = $xPath->query("@".$right, $rightNode);
        if ($rightAttr->length && $rightAttr->item(0)->value == "true") {
            self::$lastActionNeedsAuth = true;
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
