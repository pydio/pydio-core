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
class AJXP_Controller{
	public static function findActionAndApply($actionName, $httpVars, $fileVars){
		$registry = AJXP_PluginsService::getXmlRegistry();
		$xPath = new DOMXPath($registry);
		$actions = $xPath->query("actions/action[@name='$actionName']");		
		if(!$actions->length) return false;
		$action = $actions->item(0);
		//Check Rights
		if(AuthService::usersEnabled()){
			$loggedUser = AuthService::getLoggedUser();
			if( AJXP_Controller::actionNeedsRight($action, $xPath, "read") && 
				($loggedUser == null || !$loggedUser->canRead(ConfService::getCurrentRootDirIndex().""))){
					AJXP_XMLWriter::header();
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
		
		//Processing
		$callbacks = $xPath->query("processing/serverCallback", $action);
		if(!$callbacks->length) return false;
		$callback=$callbacks->item(0);
		$plugId = $xPath->query("@pluginId", $callback)->item(0)->value;
		$methodName = $xPath->query("@methodName", $callback)->item(0)->value;
		
		$plugInstance = AJXP_PluginsService::findPluginById($plugId);
		try{
			return call_user_func(array($plugInstance, $methodName), $actionName, $httpVars, $fileVars);
		}catch (Exception $e){
			return AJXP_XMLWriter::sendMessage(null, SystemTextEncoding::toUTF8($e->getMessage())." (".basename($e->getFile())." - L.".$e->getLine().")", false);
		}		
		
	}
	
	public static function actionNeedsRight($actionNode, $xPath, $right){
		$rights = $xPath->query("rights", $actionNode);
		if(!$rights->length) return false;
		$rightNode =  $rights->item(0);
		$rightAttr = $xPath->query("@".$right);
		if($rightAttr->length && $rightAttr->item(0)->value == "true"){
			return true;
		}
		return false;
	}
}
?>