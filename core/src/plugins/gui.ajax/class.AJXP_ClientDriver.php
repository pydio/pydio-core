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
defined('AJXP_EXEC') or die( 'Access not allowed');

class AJXP_ClientDriver extends AJXP_Plugin 
{	
	function switchAction($action, $httpVars, $fileVars)
	{
		if(!isSet($this->actions[$action])) return;
		foreach($httpVars as $getName=>$getValue){
			$$getName = AJXP_Utils::securePath($getValue);
		}
		if(isSet($dir) && $action != "upload") $dir = SystemTextEncoding::fromUTF8($dir);
		$mess = ConfService::getMessages();
		
		switch ($action){			
			//------------------------------------
			//	GET AN HTML TEMPLATE
			//------------------------------------
			case "get_template":
			
				HTMLWriter::charsetHeader();
				$folder = CLIENT_RESOURCES_FOLDER."/html";
				if(isSet($httpVars["pluginName"])){
					$folder = "plugins/".$httpVars["pluginName"];
					if(isSet($httpVars["pluginPath"])){
						$folder.= "/".$httpVars["pluginPath"];
					}
				}
				if(isset($template_name) && is_file($folder."/".$template_name))
				{
					include($folder."/".$template_name);
				}
				
			break;
						
			//------------------------------------
			//	GET I18N MESSAGES
			//------------------------------------
			case "get_i18n_messages":
			
				HTMLWriter::charsetHeader('text/javascript');
				HTMLWriter::writeI18nMessagesClass(ConfService::getMessages());
				
			break;
			
			//------------------------------------
			//	SEND XML REGISTRY
			//------------------------------------
			case "get_xml_registry" :
				
				$regDoc = AJXP_PluginsService::getXmlRegistry();
				if(isSet($_GET["xPath"])){
					$regPath = new DOMXPath($regDoc);
					$nodes = $regPath->query($_GET["xPath"]);
					AJXP_XMLWriter::header("ajxp_registry_part", array("xPath"=>$_GET["xPath"]));
					if($nodes->length){
						print(AJXP_XMLWriter::replaceAjxpXmlKeywords($regDoc->saveXML($nodes->item(0))));
					}
					AJXP_XMLWriter::close("ajxp_registry_part");
				}else{
					header('Content-Type: application/xml; charset=UTF-8');
					print(AJXP_XMLWriter::replaceAjxpXmlKeywords($regDoc->saveXML()));
				}
				
			break;
									
			//------------------------------------
			//	DISPLAY DOC
			//------------------------------------
			case "display_doc":
			
				HTMLWriter::charsetHeader();
				echo HTMLWriter::getDocFile(htmlentities($_GET["doc_file"]));
				
			break;
			
			//------------------------------------
			//	CHECK UPDATE
			//------------------------------------
			case "check_software_update":
			
				$content = @file_get_contents(SOFTWARE_UPDATE_SITE."ajxp.version");
				$message = $mess["345"];
				if(isSet($content) && $content != ""){
					if(strstr($content, "::URL::")!== false){
						list($version, $downloadUrl) = explode("::URL::", $content);
					}else{
						$version = $content;
						$downloadUrl = "http://www.ajaxplorer.info/";
					}
					$compare = version_compare(AJXP_VERSION, $content);
					if($compare >= 0){
						$message = $mess["346"];
					}else{
						$link = '<a target="_blank" href="'.$downloadUrl.'">'.$downloadUrl.'</a>';
						$message = sprintf($mess["347"], $version, $link);
					}
				}
				HTMLWriter::charsetHeader("text/plain");
				print($message);
				
			break;
			
			//------------------------------------
			//	GET CONFIG FOR BOOT
			//------------------------------------
			case "get_boot_conf":
				
				if(isSet($_GET["server_prefix_uri"])){
					$_SESSION["AJXP_SERVER_PREFIX_URI"] = $_GET["server_prefix_uri"];
				}
				$config = array();
				$config["ajxpResourcesFolder"] = AJXP_THEME_FOLDER;
				$config["ajxpServerAccess"] = SERVER_ACCESS;
				$config["zipEnabled"] = ConfService::zipEnabled();
				$config["multipleFilesDownloadEnabled"] = !DISABLE_ZIP_CREATION;
				$config["flashUploaderEnabled"] = ConfService::getConf("UPLOAD_ENABLE_FLASH");
				$welcomeCustom = ConfService::getConf("WELCOME_CUSTOM_MSG");
				if($welcomeCustom != ""){
					$config["customWelcomeMessage"] = $welcomeCustom;
				}
				if(!ConfService::getConf("UPLOAD_ENABLE_FLASH")){
				    $UploadMaxSize = AJXP_Utils::convertBytes(ini_get('upload_max_filesize'));
				    $confMaxSize = ConfService::getConf("UPLOAD_MAX_FILE");
				    if($confMaxSize != 0 &&  $confMaxSize < $UploadMaxSize) $UploadMaxSize = $confMaxSize;
				    $confTotalNumber = ConfService::getConf("UPLOAD_MAX_NUMBER");				
					$config["htmlMultiUploaderOptions"] = array("282"=>$UploadMaxSize,"284"=>$confTotalNumber);
				}
				$config["usersEnabled"] = AuthService::usersEnabled();
				$config["loggedUser"] = (AuthService::getLoggedUser()!=null);
				$config["currentLanguage"] = ConfService::getLanguage();
				$config["session_timeout"] = intval(ini_get("session.gc_maxlifetime"));
				$config["client_timeout"] = ConfService::getConf("CLIENT_TIMEOUT_TIME");
				$config["client_timeout_warning"] = ConfService::getConf("CLIENT_TIMEOUT_WARNING");
				$config["availableLanguages"] = ConfService::getConf("AVAILABLE_LANG");
				$config["ajxpVersion"] = AJXP_VERSION;
				$config["ajxpVersionDate"] = AJXP_VERSION_DATE;				
				if(stristr($_SERVER["HTTP_USER_AGENT"], "msie 6")){
					$config["cssResources"] = array("css/pngHack/pngHack.css");
				}
				if(defined("GOOGLE_ANALYTICS_ID") && GOOGLE_ANALYTICS_ID != "") {
					$config["googleAnalyticsData"] = array(
						"id"=>GOOGLE_ANALYTICS_ID,
						"domain" => GOOGLE_ANALYTICS_DOMAIN,
						"event" => GOOGLE_ANALYTICS_EVENT);
				}
				$config["i18nMessages"] = ConfService::getMessages();
				header("Content-type:application/json;charset=UTF-8");
				print(json_encode($config));
				
			break;
					
			default;
			break;
		}
		
		return false;		
	}
}

?>