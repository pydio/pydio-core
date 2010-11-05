<?php 
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Controller
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class AJXP_Controller{
	
	private static $xPath;
	public static $lastActionNeedsAuth = false;
	
	private static function initXPath(){
		if(!isSet(self::$xPath)){
			$registry = AJXP_PluginsService::getXmlRegistry();
			self::$xPath = new DOMXPath($registry);		
		}
		return self::$xPath;
	}
	
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
		$mess = ConfService::getMessages();
		if(AuthService::usersEnabled()){
			$loggedUser = AuthService::getLoggedUser();
			if( AJXP_Controller::actionNeedsRight($action, $xPath, "adminOnly") && 
				($loggedUser == null || !$loggedUser->isAdmin())){
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
					AJXP_XMLWriter::sendMessage(null, $mess[208]);
					AJXP_XMLWriter::requireAuth();
					AJXP_XMLWriter::close();
					exit(1);
				}
			if( AJXP_Controller::actionNeedsRight($action, $xPath, "write") && 
				($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex().""))){
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
	
	private function getCallbackNode($xPath, $actionNode, $query ,$actionName, $httpVars, $fileVars, $multiple = true){		
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
	
	private function appliesCondition($callback, $actionName, $httpVars, $fileVars){
		if($callback->getAttribute("applyCondition")!=""){
			$apply = false;
			eval($callback->getAttribute("applyCondition"));
			if(!$apply) return false;			
		}
		return true;
	}
	
	private function applyCallback($xPath, $callback, &$actionName, &$httpVars, &$fileVars, &$variableArgs = null){
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
	
	public static function applyHook($hookName, $args){
		$xPath = self::initXPath();
		$callbacks = $xPath->query("hooks/serverCallback[@hookName='$hookName']");
		if(!$callbacks->length) return ;		
		foreach ($callbacks as $callback){
			$fake1; $fake2; $fake3;
			self::applyCallback($xPath, $callback, $fake1, $fake2, $fake3, $args);
		}
	}
	
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