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
 * @package info.ajaxplorer.plugins
 * User Interface main implementation
 */
class AJXP_ClientDriver extends AJXP_Plugin 
{
    private static $loadedBookmarks;

    public function loadConfigs($configData){
        parent::loadConfigs($configData);
        if(preg_match('/MSIE 7/',$_SERVER['HTTP_USER_AGENT']) || preg_match('/MSIE 8/',$_SERVER['HTTP_USER_AGENT'])){
            // Force legacy theme for the moment
            $this->pluginConf["GUI_THEME"] = "oxygen";
        }
        if(!defined("AJXP_THEME_FOLDER")){
            define("CLIENT_RESOURCES_FOLDER", AJXP_PLUGINS_FOLDER."/gui.ajax/res");
            define("AJXP_THEME_FOLDER", CLIENT_RESOURCES_FOLDER."/themes/".$this->pluginConf["GUI_THEME"]);
        }
        if(!isSet($configData["CLIENT_TIMEOUT_TIME"])){
            $this->pluginConf["CLIENT_TIMEOUT_TIME"] = intval(ini_get("session.gc_maxlifetime"));
        }
    }

	function switchAction($action, $httpVars, $fileVars)
	{
		if(!isSet($this->actions[$action])) return;
        if(preg_match('/MSIE 7/',$_SERVER['HTTP_USER_AGENT']) || preg_match('/MSIE 8/',$_SERVER['HTTP_USER_AGENT'])){
            // Force legacy theme for the moment
            $this->pluginConf["GUI_THEME"] = "oxygen";
        }
        if(!defined("AJXP_THEME_FOLDER")){
            define("CLIENT_RESOURCES_FOLDER", AJXP_PLUGINS_FOLDER."/gui.ajax/res");
            define("AJXP_THEME_FOLDER", CLIENT_RESOURCES_FOLDER."/themes/".$this->pluginConf["GUI_THEME"]);
        }		
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
					$folder = AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/".AJXP_Utils::securePath($httpVars["pluginName"]);
					if(isSet($httpVars["pluginPath"])){
						$folder.= "/".AJXP_Utils::securePath($httpVars["pluginPath"]);
					}
				}
                $crtTheme = $this->pluginConf["GUI_THEME"];
                $thFolder = AJXP_THEME_FOLDER."/html";
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
                if(isSet($httpVars["lang"])){
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
                $changes = AJXP_Controller::filterActionsRegistry($regDoc);
                if($changes) AJXP_PluginsService::updateXmlRegistry($regDoc);
				if(isSet($_GET["xPath"])){
					$regPath = new DOMXPath($regDoc);
					$nodes = $regPath->query($_GET["xPath"]);
					AJXP_XMLWriter::header("ajxp_registry_part", array("xPath"=>$_GET["xPath"]));
					if($nodes->length){
						print(AJXP_XMLWriter::replaceAjxpXmlKeywords($regDoc->saveXML($nodes->item(0))));
					}
					AJXP_XMLWriter::close("ajxp_registry_part");
				}else{
                    AJXP_Utils::safeIniSet("zlib.output_compression", "4096");
					header('Content-Type: application/xml; charset=UTF-8');
                    print(AJXP_XMLWriter::replaceAjxpXmlKeywords($regDoc->saveXML()));
				}
				
			break;
									
			//------------------------------------
			//	DISPLAY DOC
			//------------------------------------
			case "display_doc":
			
				HTMLWriter::charsetHeader();
				echo HTMLWriter::getDocFile(AJXP_Utils::securePath(htmlentities($_GET["doc_file"])));
				
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

				$JSON_START_PARAMETERS = json_encode($START_PARAMETERS);
                $crtTheme = $this->pluginConf["GUI_THEME"];
				if(ConfService::getConf("JS_DEBUG")){
					if(!isSet($mess)){
						$mess = ConfService::getMessages();
					}
                    if(is_file(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui_debug.html")){
                        include(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui_debug.html");
                    }else{
                        include(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/html/gui_debug.html");
                    }
				}else{
                    if(is_file(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui.html")){
                        $content = file_get_contents(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui.html");
                    }else{
                        $content = file_get_contents(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/html/gui.html");
                    }
                    if(preg_match('/MSIE 7/',$_SERVER['HTTP_USER_AGENT']) || preg_match('/MSIE 8/',$_SERVER['HTTP_USER_AGENT'])){
                        $content = str_replace("ajaxplorer_boot.js", "ajaxplorer_boot_protolegacy.js", $content);
                    }
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
				$config["ajxpResourcesFolder"] = "plugins/gui.ajax/res";
				$config["ajxpServerAccess"] = AJXP_SERVER_ACCESS;
				$config["zipEnabled"] = ConfService::zipEnabled();
				$config["multipleFilesDownloadEnabled"] = ConfService::getCoreConf("ZIP_CREATION");
				$config["customWording"] = array(
		        	"welcomeMessage" => $this->pluginConf["CUSTOM_WELCOME_MESSAGE"],
		        	"title"			 => ConfService::getCoreConf("APPLICATION_TITLE"),
		        	"icon"			 => $this->pluginConf["CUSTOM_ICON"],
		        	"iconWidth"		 => $this->pluginConf["CUSTOM_ICON_WIDTH"],
		        	"iconHeight"     => $this->pluginConf["CUSTOM_ICON_HEIGHT"],
                    "iconOnly"       => $this->pluginConf["CUSTOM_ICON_ONLY"],
		        	"titleFontSize"	 => $this->pluginConf["CUSTOM_FONT_SIZE"]
		        );
				$config["usersEnabled"] = AuthService::usersEnabled();
				$config["loggedUser"] = (AuthService::getLoggedUser()!=null);
				$config["currentLanguage"] = ConfService::getLanguage();
				$config["session_timeout"] = intval(ini_get("session.gc_maxlifetime"));				
				if(!isSet($this->pluginConf["CLIENT_TIMEOUT_TIME"]) || $this->pluginConf["CLIENT_TIMEOUT_TIME"] == ""){
					$to = $config["session_timeout"]; 
				}else{
					$to = $this->pluginConf["CLIENT_TIMEOUT_TIME"];
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

    /**
     * @param AJXP_Node $ajxpNode
     * @return
     */
    function nodeBookmarkMetadata(&$ajxpNode){
        $user = AuthService::getLoggedUser();
        if($user == null) return;
        if(!isSet(self::$loadedBookmarks)){
            self::$loadedBookmarks = $user->getBookmarks();
        }
        foreach(self::$loadedBookmarks as $bm){
            if($bm["PATH"] == $ajxpNode->getPath()){
                $ajxpNode->mergeMetadata(array(
                         "ajxp_bookmarked" => "true",
                         "overlay_icon"  => "bookmark.png"
                    ), true);
                /*
                 * TESTING MULTIPLE OVERLAYS
                $ajxpNode->mergeMetadata(array(
                         "overlay_icon"  => "shared.png"
                    ), true);
                */
            }
        }
    }

    static function filterXml(&$value){
        $instance = AJXP_PluginsService::getInstance()->findPlugin("gui", "ajax");
        if($instance === false) return ;
        $confs = $instance->getConfigs();
        $theme = $confs["GUI_THEME"];
        if(!defined("AJXP_THEME_FOLDER")){
            define("CLIENT_RESOURCES_FOLDER", AJXP_PLUGINS_FOLDER."/gui.ajax/res");
            define("AJXP_THEME_FOLDER", CLIENT_RESOURCES_FOLDER."/themes/".$theme);
        }
        $value = str_replace(array("AJXP_CLIENT_RESOURCES_FOLDER", "AJXP_CURRENT_VERSION"), array(CLIENT_RESOURCES_FOLDER, AJXP_VERSION), $value);
        if(isSet($_SESSION["AJXP_SERVER_PREFIX_URI"])){
            $value = str_replace("AJXP_THEME_FOLDER", $_SESSION["AJXP_SERVER_PREFIX_URI"]."plugins/gui.ajax/res/themes/".$theme, $value);
        }else{
            $value = str_replace("AJXP_THEME_FOLDER", "plugins/gui.ajax/res/themes/".$theme, $value);
        }
        return $value;
    }
}

AJXP_Controller::registerIncludeHook("xml.filter", array("AJXP_ClientDriver", "filterXml"));

?>