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
 * Description : Basic implementation of the AbstractDriver, handle low level actions (docs, templates, etc).
 */
class AJXP_ClientDriver extends AbstractDriver 
{
	
	function AJXP_ClientDriver($repository) {
		//parent::AbstractDriver("ajxp_actions");
		$this->initXmlActionsFile(CLIENT_RESOURCES_FOLDER."/xml/ajxpclient_actions.xml");
		unset($this->actions["get_driver_actions"]);
		unset($this->actions["get_driver_info_panels"]);
		$this->actions["get_ajxp_actions"] = array();
		$this->actions["get_ajxp_info_panels"] = array();
	}
	
	function applyAction($actionName, $httpVars, $filesVar){
		if($actionName == "get_ajxp_actions"){
			AJXP_XMLWriter::header();
			$this->sendActionsToClient(false, null, null);
			$authDriver = ConfService::getAuthDriverImpl();
			$authDriver->sendActionsToClient(false, null, null);
			$confDriver = ConfService::getConfStorageImpl();
			$confDriver->sendActionsToClient(false, null, null);
			AJXP_XMLWriter::close();
			exit(1);
		}else{
			parent::applyAction($actionName, $httpVars, $filesVar);
		}
	}
	
	function switchAction($action, $httpVars, $fileVars)
	{
		if(!isSet($this->actions[$action])) return;
		$xmlBuffer = "";
		foreach($httpVars as $getName=>$getValue){
			$$getName = Utils::securePath($getValue);
		}
		if(isSet($dir) && $action != "upload") $dir = SystemTextEncoding::fromUTF8($dir);
		$mess = ConfService::getMessages();
		
		switch ($action){			
			//------------------------------------
			//	GET AN HTML TEMPLATE
			//------------------------------------
			case "get_template":
			
				HTMLWriter::charsetHeader();
				if(isset($template_name) && is_file(CLIENT_RESOURCES_FOLDER."/html/".$template_name))
				{
					include(CLIENT_RESOURCES_FOLDER."/html/".$template_name);
				}
				exit(0);	
				
			break;
						
			//------------------------------------
			//	GET I18N MESSAGES
			//------------------------------------
			case "get_i18n_messages":
			
				HTMLWriter::charsetHeader('text/javascript');
				HTMLWriter::writeI18nMessagesClass(ConfService::getMessages());
				exit(0);	
				
			break;
			
			case "get_editors_registry":
				
				$plugService = AJXP_PluginsService::getInstance();
				$plugins = $plugService->getPluginsByType("editor");
				AJXP_XMLWriter::header("editors");
				foreach ($plugins as $plugin){
					print(AJXP_XMLWriter::replaceAjxpXmlKeywords($plugin->getManifestRawContent()));
				}
				AJXP_XMLWriter::close("editors");
				exit(0);
					
			break;
						
			//------------------------------------
			//	DISPLAY DOC
			//------------------------------------
			case "display_doc":
			
				HTMLWriter::charsetHeader();
				echo HTMLWriter::getDocFile($_GET["doc_file"]);
				exit(1);
				
			break;
			
					
			default;
			break;
		}
		if(isset($logMessage) || isset($errorMessage))
		{
			$xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);			
		}
		
		if(isset($requireAuth))
		{
			$xmlBuffer .= AJXP_XMLWriter::requireAuth(false);
		}
		
		if(isset($reload_current_node) && $reload_current_node == "true")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadCurrentNode(false);
		}
		
		if(isset($reload_dest_node) && $reload_dest_node != "")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadNode($reload_dest_node, false);
		}
		
		if(isset($reload_file_list))
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadFileList($reload_file_list, false);
		}
		
		return $xmlBuffer;		
	}
}

?>