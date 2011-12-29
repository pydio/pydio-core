<?php 
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * @package info.ajaxplorer.core
 */
/**
 * Core controller for dispatching the actions.
 * It uses the XML Registry (simple version, not extended) to search all the <action> tags and find the action.
 */
class AJXP_Controller{

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

    /**
     * Initialize the queryable xPath object
     * @static
     * @return DOMXPath
     */
	private static function initXPath(){		
		if(!isSet(self::$xPath)){
			
			$registry = AJXP_PluginsService::getXmlRegistry( false );
			$changes = self::filterActionsRegistry($registry);
			if($changes) AJXP_PluginsService::updateXmlRegistry($registry);
			self::$xPath = new DOMXPath($registry);		
		}
		return self::$xPath;
	}

    /**
     * Check the current user "specificActionsRights" and filter the full registry actions with these.
     * @static
     * @param DOMDocument $registry
     * @return bool
     */
	public static function filterActionsRegistry(&$registry){
		if(!AuthService::usersEnabled()) return false ;
		$loggedUser = AuthService::getLoggedUser();
		if($loggedUser == null) return false;
		$crtRepo = ConfService::getRepository();
		$crtRepoId = "ajxp.all";
		if($crtRepo != null && is_a($crtRepo, "Repository")){
			$crtRepoId = $crtRepo->getId();
		}
		$actionRights = $loggedUser->getSpecificActionsRights($crtRepoId);
		$changes = false;
		$xPath = new DOMXPath($registry);
		foreach ($actionRights as $actionName => $enabled){
			if($enabled !== false) continue;
			$actions = $xPath->query("actions/action[@name='$actionName']");		
			if(!$actions->length){
				continue;
			}
			$action = $actions->item(0);
			$action->parentNode->removeChild($action);
			$changes = true;
		}
		return $changes;
	}

    /**
     * Main method for querying the XML registry, find an action and all its associated processors,
     * and apply all the callbacks.
     * @static
     * @param $actionName
     * @param $httpVars
     * @param $fileVars
     * @return bool
     */
	public static function findActionAndApply($actionName, $httpVars, $fileVars){
		if($actionName == "cross_copy"){
			$pService = AJXP_PluginsService::getInstance();
			$actives = $pService->getActivePlugins();
			$accessPlug = $pService->getPluginsByType("access");
			if(count($accessPlug)){
				foreach($accessPlug as $key=>$objbect){
					if($actives[$objbect->getId()] === true){
						call_user_func(array($pService->getPluginById($objbect->getId()), "crossRepositoryCopy"), $httpVars);
						break;
					}
				}
			}
			self::$lastActionNeedsAuth = true;
			return ;
		}
		$xPath = self::initXPath();
		$actions = $xPath->query("actions/action[@name='$actionName']");		
		if(!$actions->length){
			self::$lastActionNeedsAuth = true;
			return false;
		}
		$action = $actions->item(0);
		//Check Rights
		if(AuthService::usersEnabled()){
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
				($loggedUser == null || !$loggedUser->canRead(ConfService::getCurrentRootDirIndex().""))){
					AJXP_XMLWriter::header();
					if($actionName == "ls" & $loggedUser!=null 
						&& $loggedUser->canWrite(ConfService::getCurrentRootDirIndex()."")){
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
				($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex().""))){
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
		
		if($captureCalls !== false){
			ob_start();
			$params = array("pre_processor_results" => array(), "post_processor_results" => array());
		}
		if($preCalls !== false){
			foreach ($preCalls as $preCall){
				// A Preprocessing callback can modify its input arguments (passed by ref)
				$preResult = self::applyCallback($xPath, $preCall, $actionName, $httpVars, $fileVars);
				if(isSet($params)){
					$params["pre_processor_results"][$preCall->getAttribute("pluginId")] = $preResult;
				}
			}
		}		
		if($mainCall){
			$result = self::applyCallback($xPath, $mainCall, $actionName, $httpVars, $fileVars);
			if(isSet($params)){
				$params["processor_result"] = $result;
			}			
		}		
		if($postCalls !== false){
			foreach ($postCalls as $postCall){
				// A Preprocessing callback can modify its input arguments (passed by ref)
				$postResult = self::applyCallback($xPath, $postCall, $actionName, $httpVars, $fileVars);
				if(isSet($params)){
					$params["post_processor_results"][$postCall->getAttribute("pluginId")] = $postResult;
				}
			}
		}		
		if($captureCalls !== false){
			$params["ob_output"] = ob_get_contents();
			ob_end_clean();
			foreach ($captureCalls as $captureCall){
				self::applyCallback($xPath, $captureCall, $actionName, $httpVars, $params);
			}
		}else{
			if(isSet($result)) return $result;
		}
	}

    /**
     * Launch a command-line version of the framework by passing the actionName & parameters as arguments.
     * @static
     * @param String $currentRepositoryId
     * @param String $actionName
     * @param Array $parameters
     * @return null|UnixProcess
     */
	public static function applyActionInBackground($currentRepositoryId, $actionName, $parameters){
		$token = md5(time());
        $logDir = AJXP_CACHE_DIR."/cmd_outputs";
        if(!is_dir($logDir)) mkdir($logDir, 755);
        $logFile = $logDir."/".$token.".out";
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
        $user = "shared";
        if(AuthService::usersEnabled()){
            $user = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($token."\1CDAFx¨op#"), AuthService::getLoggedUser()->getId(), MCRYPT_MODE_ECB, $iv));
        }
		$cmd = ConfService::getCoreConf("CLI_PHP")." ".AJXP_INSTALL_PATH.DIRECTORY_SEPARATOR."cmd.php -u=$user -t=$token -a=$actionName -r=$currentRepositoryId";
		foreach($parameters as $key=>$value){
            if($key == "action" || $key == "get_action") continue;
			$cmd .= " --$key=".escapeshellarg($value);
		}
		if (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows"){
			$tmpBat = implode(DIRECTORY_SEPARATOR, array(AJXP_INSTALL_PATH, "data","tmp", md5(time()).".bat"));
            $cmd .= " > ".$logFile;
			$cmd .= "\n DEL $tmpBat";
			AJXP_Logger::debug("Writing file $cmd to $tmpBat");
			file_put_contents($tmpBat, $cmd);
			pclose(popen("start /b ".$tmpBat, 'r'));
		}else{
			$process = new UnixProcess($cmd, $logFile);
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
     * @return array|bool
     */
	private static function getCallbackNode($xPath, $actionNode, $query ,$actionName, $httpVars, $fileVars, $multiple = true){		
		$callbacks = $xPath->query($query, $actionNode);
		if(!$callbacks->length) return false;
		if($multiple){
			$cbArray = array();
			foreach ($callbacks as $callback) {
				if(self::appliesCondition($callback, $actionName, $httpVars, $fileVars)){
					$cbArray[] = $callback;
				}
			}
			if(!count($cbArray)) return  false;
			return $cbArray;
		}else{
			$callback=$callbacks->item(0);
			if(!self::appliesCondition($callback, $actionName, $httpVars, $fileVars)) return false;
			return $callback;
		}
	}

    /**
     * Check in the callback node if an applyCondition XML attribute exists, and eval its content.
     * The content must set an $apply boolean as result
     * @static
     * @param DOMNode $callback
     * @param string $actionName
     * @param array $httpVars
     * @param array $fileVars
     * @return bool
     */
	private static function appliesCondition($callback, $actionName, $httpVars, $fileVars){
		if($callback->getAttribute("applyCondition")!=""){
			$apply = false;
			eval($callback->getAttribute("applyCondition"));
			if(!$apply) return false;			
		}
		return true;
	}

    /**
     * Applies a callback node
     * @static
     * @param DOMXPath $xPath
     * @param DOMNode $callback
     * @param String $actionName
     * @param Array $httpVars
     * @param Array $fileVars
     * @param null $variableArgs
     * @throw AJXP_Exception
     * @return void
     */
	private static function applyCallback($xPath, $callback, &$actionName, &$httpVars, &$fileVars, &$variableArgs = null){
		//Processing
		$plugId = $xPath->query("@pluginId", $callback)->item(0)->value;
		$methodName = $xPath->query("@methodName", $callback)->item(0)->value;		
		$plugInstance = AJXP_PluginsService::findPluginById($plugId);
		//return call_user_func(array($plugInstance, $methodName), $actionName, $httpVars, $fileVars);	
		// Do not use call_user_func, it cannot pass parameters by reference.	
		if(method_exists($plugInstance, $methodName)){
			if($variableArgs == null){
				return $plugInstance->$methodName($actionName, $httpVars, $fileVars);
			}else{
				call_user_func_array(array($plugInstance, $methodName), $variableArgs);
			}
		}else{
			throw new AJXP_Exception("Cannot find method $methodName for plugin $plugId!");
		}
	}

    /**
     * Find all callbacks registered for a given hook and apply them
     * @static
     * @param string $hookName
     * @param array $args
     * @return
     */
	public static function applyHook($hookName, $args){
		$xPath = self::initXPath();
		$callbacks = $xPath->query("hooks/serverCallback[@hookName='$hookName']");
		if(!$callbacks->length) return ;		
		foreach ($callbacks as $callback){
			$fake1; $fake2; $fake3;
			self::applyCallback($xPath, $callback, $fake1, $fake2, $fake3, $args);
		}
	}	

    /**
     * Find the statically defined callbacks for a given hook and apply them
     * @static
     * @param $hookName
     * @param $args
     * @return
     */
	public static function applyIncludeHook($hookName, $args){
		if(!isSet(self::$includeHooks[$hookName])) return;
		foreach(self::$includeHooks[$hookName] as $callback){
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
	public static function registerIncludeHook($hookName, $callback){
		if(!isSet(self::$includeHooks[$hookName])){
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
	public static function actionNeedsRight($actionNode, $xPath, $right){
		$rights = $xPath->query("rightsContext", $actionNode);
		if(!$rights->length) return false;
		$rightNode =  $rights->item(0);
		$rightAttr = $xPath->query("@".$right, $rightNode);
		if($rightAttr->length && $rightAttr->item(0)->value == "true"){
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
	public static function passProcessDataThrough($postProcessData){
		if(isSet($postProcessData["pre_processor_results"]) && is_array($postProcessData["pre_processor_results"])){
			print(implode("", $postProcessData["pre_processor_results"]));
		}
		if(isSet($postProcessData["processor_result"])){
			print($postProcessData["processor_result"]);
		}
		if(isSet($postProcessData["ob_output"])) print($postProcessData["ob_output"]);
	}
}
?>