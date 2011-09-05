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
        if(!defined("AJXP_THEME_FOLDER")){
            define("AJXP_THEME_FOLDER", "plugins/gui.ajax/res/themes/".$this->pluginConf["GUI_THEME"]);
        }
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
                $crtTheme = $this->pluginConf["GUI_THEME"];
                $thFolder = CLIENT_RESOURCES_FOLDER."/themes/$crtTheme/html";
				if(isset($template_name))
				{
                    if(is_file($thFolder."/".$template_name)){
                        include($thFolder."/".$template_name);
                    }else if(is_file($folder."/".$template_name)){
    					include($folder."/".$template_name);
                    }
				}
				
			break;
						
			//------------------------------------
			//	GET I18N MESSAGES
			//------------------------------------
			case "get_i18n_messages":

                $refresh = false;
                if(AuthService::getLoggedUser() == null && isSet($httpVars["lang"])){
                    ConfService::setLanguage($httpVars["lang"]);
                    $refresh = true;
                }
				HTMLWriter::charsetHeader('text/javascript');
				HTMLWriter::writeI18nMessagesClass(ConfService::getMessages($refresh));
				
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
			//	GET BOOT GUI
			//------------------------------------
			case "get_boot_gui":
				
				header("X-UA-Compatible: chrome=1");			
				HTMLWriter::charsetHeader();
				
				if(!is_file(TESTS_RESULT_FILE)){
					$outputArray = array();
					$testedParams = array();
					$passed = AJXP_Utils::runTests($outputArray, $testedParams);
					if(!$passed && !isset($_GET["ignore_tests"])){
						die(AJXP_Utils::testResultsToTable($outputArray, $testedParams));
					}else{
						AJXP_Utils::testResultsToFile($outputArray, $testedParams);
					}
				}
				
				$START_PARAMETERS = array("BOOTER_URL"=>"index.php?get_action=get_boot_conf", "MAIN_ELEMENT" => "ajxp_desktop");
				if(AuthService::usersEnabled())
				{
					AuthService::preLogUser((isSet($httpVars["remote_session"])?$httpVars["remote_session"]:""));
					AuthService::bootSequence($START_PARAMETERS);
					if(AuthService::getLoggedUser() != null || AuthService::logUser(null, null) == 1)
					{
						if(AuthService::getDefaultRootId() == -1){
							AuthService::disconnect();
						}else{
							$loggedUser = AuthService::getLoggedUser();
							if(!$loggedUser->canRead(ConfService::getCurrentRootDirIndex()) 
									&& AuthService::getDefaultRootId() != ConfService::getCurrentRootDirIndex())
							{
								ConfService::switchRootDir(AuthService::getDefaultRootId());
							}
						}
					}
				}
				
				AJXP_Utils::parseApplicationGetParameters($_GET, $START_PARAMETERS, $_SESSION);
				
				$confErrors = ConfService::getErrors();
				if(count($confErrors)){
					$START_PARAMETERS["ALERT"] = implode(", ", array_values($confErrors));
				}
				
				if(isSet($_COOKIE["AJXP_LAST_KNOWN_VERSION"]) && $_COOKIE["AJXP_LAST_KNOWN_VERSION"] != AJXP_VERSION){
					$mess = ConfService::getMessages();
					$START_PARAMETERS["ALERT"] = sprintf($mess[392], AJXP_VERSION);
				}
				setcookie("AJXP_LAST_KNOWN_VERSION", AJXP_VERSION, time() + 3600*24*365, "/");
				
				$JSON_START_PARAMETERS = json_encode($START_PARAMETERS);
				if(ConfService::getConf("JS_DEBUG")){
					if(!isSet($mess)){
						$mess = ConfService::getMessages();
					}
					include_once(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/html/gui_debug.html");
				}else{
					$content = file_get_contents(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/html/gui.html");	
					$content = AJXP_XMLWriter::replaceAjxpXmlKeywords($content, false);
					if($JSON_START_PARAMETERS){
						$content = str_replace("//AJXP_JSON_START_PARAMETERS", "startParameters = ".$JSON_START_PARAMETERS.";", $content);
					}
					print($content);
				}				
			break;
			//------------------------------------
			//	GET CONFIG FOR BOOT
			//------------------------------------
			case "get_boot_conf":
				
				if(isSet($_GET["server_prefix_uri"])){
					$_SESSION["AJXP_SERVER_PREFIX_URI"] = $_GET["server_prefix_uri"];
				}
				$config = array();
				$config["ajxpResourcesFolder"] = "plugins/gui.ajax/res/themes/".$this->pluginConf["GUI_THEME"];
				$config["ajxpServerAccess"] = AJXP_SERVER_ACCESS;
				$config["zipEnabled"] = ConfService::zipEnabled();
				$config["multipleFilesDownloadEnabled"] = ConfService::getCoreConf("ZIP_CREATION");
				$config["customWording"] = array(
		        	"welcomeMessage" => $this->pluginConf["CUSTOM_WELCOME_MESSAGE"],
		        	"title"			 => ConfService::getCoreConf("APPLICATION_TITLE"),
		        	"icon"			 => $this->pluginConf["CUSTOM_ICON"],
		        	"iconWidth"		 => $this->pluginConf["CUSTOM_ICON_WIDTH"],
		        	"titleFontSize"	 => $this->pluginConf["CUSTOM_FONT_SIZE"]
		        );
				
			    $confMaxSize = AJXP_Utils::convertBytes(ConfService::getCoreConf("UPLOAD_MAX_SIZE", "uploader"));
		        $UploadMaxSize = min(AJXP_Utils::convertBytes(ini_get('upload_max_filesize')), AJXP_Utils::convertBytes(ini_get('post_max_size')));
		        if($confMaxSize != 0) $UploadMaxSize = min ($UploadMaxSize, $confMaxSize);
			    $confTotalNumber = ConfService::getCoreConf("UPLOAD_MAX_NUMBER", "uploader");
				$config["htmlMultiUploaderOptions"] = array("282"=>$UploadMaxSize,"284"=>$confTotalNumber);
					
				$config["filenamesMaxLength"] = intval(ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
				$config["usersEnabled"] = AuthService::usersEnabled();
				$config["loggedUser"] = (AuthService::getLoggedUser()!=null);
				$config["currentLanguage"] = ConfService::getLanguage();
				$config["session_timeout"] = intval(ini_get("session.gc_maxlifetime"));				
				if(!isSet($this->pluginConf["CLIENT_TIMEOUT_TIME"]) || $this->pluginConf["CLIENT_TIMEOUT_TIME"] == ""){
					$to = $config["session_timeout"]; 
				}else{
					$to = $to = $this->pluginConf["CLIENT_TIMEOUT_TIME"];
				}
				$config["client_timeout"] = $to;
				$config["client_timeout_warning"] = $this->pluginConf["CLIENT_TIMEOUT_WARN"];
				$config["availableLanguages"] = ConfService::getConf("AVAILABLE_LANG");
				$config["usersEditable"] = ConfService::getAuthDriverImpl()->usersEditable();
				$config["ajxpVersion"] = AJXP_VERSION;
				$config["ajxpVersionDate"] = AJXP_VERSION_DATE;				
				if(stristr($_SERVER["HTTP_USER_AGENT"], "msie 6")){
					$config["cssResources"] = array("css/pngHack/pngHack.css");
				}
				if(!empty($this->pluginConf['GOOGLE_ANALYTICS_ID'])) {
					$config["googleAnalyticsData"] = array(
						"id"=> 		$this->pluginConf['GOOGLE_ANALYTICS_ID'],
						"domain" => $this->pluginConf['GOOGLE_ANALYTICS_DOMAIN'],
						"event" => 	$this->pluginConf['GOOGLE_ANALYTICS_EVENT']);
				}
				$config["i18nMessages"] = ConfService::getMessages();
				$config["password_min_length"] = ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth");
				$config["SECURE_TOKEN"] = AuthService::generateSecureToken();
				$config["streaming_supported"] = "true";
                $config["theme"] = $this->pluginConf["GUI_THEME"];
				header("Content-type:application/json;charset=UTF-8");
				print(json_encode($config));
				
			break;
					
			default;
			break;
		}
		
		return false;		
	}

    static function filterXml($value){
        $instance = AJXP_PluginsService::getInstance()->findPlugin("gui", "ajax");
        if($instance === false) return ;
        $confs = $instance->getConfigs();
        $theme = $confs["GUI_THEME"];
        if(isSet($_SESSION["AJXP_SERVER_PREFIX_URI"])){
            $value = str_replace("AJXP_THEME_FOLDER", $_SESSION["AJXP_SERVER_PREFIX_URI"]."plugins/gui.ajax/res/themes/".$theme, $value);
        }else{
            $value = str_replace("AJXP_THEME_FOLDER", "plugins/gui.ajax/res/themes/".$theme, $value);
        }
    }
}

AJXP_Controller::registerIncludeHook("xml.filter", array("AJXP_ClientDriver", "filterXml"));

?>