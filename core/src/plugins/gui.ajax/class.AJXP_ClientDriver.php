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
 * User Interface main implementation
 * @package AjaXplorer_Plugins
 * @subpackage Gui
 */
class AJXP_ClientDriver extends AJXP_Plugin
{
    private static $loadedBookmarks;

    public function isEnabled()
    {
        return true;
    }

    public function loadConfigs($configData)
    {
        parent::loadConfigs($configData);
        if (preg_match('/MSIE 7/',$_SERVER['HTTP_USER_AGENT'])) {
            // Force legacy theme for the moment
             $this->pluginConf["GUI_THEME"] = "oxygen";
        }
        if (!defined("AJXP_THEME_FOLDER")) {
            define("CLIENT_RESOURCES_FOLDER", AJXP_PLUGINS_FOLDER."/gui.ajax/res");
            define("AJXP_THEME_FOLDER", CLIENT_RESOURCES_FOLDER."/themes/".$this->pluginConf["GUI_THEME"]);
        }
        if (!isSet($configData["CLIENT_TIMEOUT_TIME"])) {
            $this->pluginConf["CLIENT_TIMEOUT_TIME"] = intval(ini_get("session.gc_maxlifetime"));
        }
    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return null;
        if (preg_match('/MSIE 7/',$_SERVER['HTTP_USER_AGENT'])) {
            // Force legacy theme for the moment
            $this->pluginConf["GUI_THEME"] = "oxygen";
        }
        if (!defined("AJXP_THEME_FOLDER")) {
            define("CLIENT_RESOURCES_FOLDER", AJXP_PLUGINS_FOLDER."/gui.ajax/res");
            define("AJXP_THEME_FOLDER", CLIENT_RESOURCES_FOLDER."/themes/".$this->pluginConf["GUI_THEME"]);
        }
        foreach ($httpVars as $getName=>$getValue) {
            $$getName = AJXP_Utils::securePath($getValue);
        }
        $mess = ConfService::getMessages();

        switch ($action) {
            //------------------------------------
            //	GET AN HTML TEMPLATE
            //------------------------------------
            case "get_template":

                HTMLWriter::charsetHeader();
                $folder = CLIENT_RESOURCES_FOLDER."/html";
                if (isSet($httpVars["pluginName"])) {
                    $folder = AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/".AJXP_Utils::securePath($httpVars["pluginName"]);
                    if (isSet($httpVars["pluginPath"])) {
                        $folder.= "/".AJXP_Utils::securePath($httpVars["pluginPath"]);
                    }
                }
                $thFolder = AJXP_THEME_FOLDER."/html";
                if (isset($template_name)) {
                    if (is_file($thFolder."/".$template_name)) {
                        include($thFolder."/".$template_name);
                    } else if (is_file($folder."/".$template_name)) {
                        include($folder."/".$template_name);
                    }
                }

            break;

            //------------------------------------
            //	GET I18N MESSAGES
            //------------------------------------
            case "get_i18n_messages":

                $refresh = false;
                if (isSet($httpVars["lang"])) {
                    ConfService::setLanguage($httpVars["lang"]);
                    $refresh = true;
                }
                HTMLWriter::charsetHeader('text/javascript');
                HTMLWriter::writeI18nMessagesClass(ConfService::getMessages($refresh));

            break;

            //------------------------------------
            //	DISPLAY DOC
            //------------------------------------
            case "display_doc":

                HTMLWriter::charsetHeader();
                echo HTMLWriter::getDocFile(AJXP_Utils::securePath(htmlentities($httpVars["doc_file"])));

            break;


            //------------------------------------
            //	GET BOOT GUI
            //------------------------------------
            case "get_boot_gui":

                HTMLWriter::internetExplorerMainDocumentHeader();
                HTMLWriter::charsetHeader();

                if (!is_file(TESTS_RESULT_FILE)) {
                    $outputArray = array();
                    $testedParams = array();
                    $passed = AJXP_Utils::runTests($outputArray, $testedParams);
                    if (!$passed && !isset($httpVars["ignore_tests"])) {
                        AJXP_Utils::testResultsToTable($outputArray, $testedParams);
                        die();
                    } else {
                        AJXP_Utils::testResultsToFile($outputArray, $testedParams);
                    }
                }

                $root = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $configUrl = ConfService::getCoreConf("SERVER_URL");
                if(!empty($configUrl)){
                    $root = '/'.ltrim(parse_url($configUrl, PHP_URL_PATH), '/');
                    if(strlen($root) > 1) $root = rtrim($root, '/').'/';
                }else{
                    preg_match ('/ws-(.)*\/|settings|dashboard|welcome|user/', $root, $matches, PREG_OFFSET_CAPTURE);
                    if(count($matches)){
                        $capture = $matches[0][1];
                        $root = substr($root, 0, $capture);
                    }
                }
                $START_PARAMETERS = array(
                    "BOOTER_URL"        =>"index.php?get_action=get_boot_conf",
                    "MAIN_ELEMENT"      => "ajxp_desktop",
                    "APPLICATION_ROOT"  => $root,
                    "REBASE"            => $root
                );
                if (AuthService::usersEnabled()) {
                    AuthService::preLogUser((isSet($httpVars["remote_session"])?$httpVars["remote_session"]:""));
                    AuthService::bootSequence($START_PARAMETERS);
                    if (AuthService::getLoggedUser() != null || AuthService::logUser(null, null) == 1) {
                        if (AuthService::getDefaultRootId() == -1) {
                            AuthService::disconnect();
                        } else {
                            $loggedUser = AuthService::getLoggedUser();
                            if(!$loggedUser->canRead(ConfService::getCurrentRepositoryId())
                                    && AuthService::getDefaultRootId() != ConfService::getCurrentRepositoryId())
                            {
                                ConfService::switchRootDir(AuthService::getDefaultRootId());
                            }
                        }
                    }
                }

                AJXP_Utils::parseApplicationGetParameters($_GET, $START_PARAMETERS, $_SESSION);

                $confErrors = ConfService::getErrors();
                if (count($confErrors)) {
                    $START_PARAMETERS["ALERT"] = implode(", ", array_values($confErrors));
                }
                // PRECOMPUTE BOOT CONF
                if (!preg_match('/MSIE 7/',$_SERVER['HTTP_USER_AGENT']) && !preg_match('/MSIE 8/',$_SERVER['HTTP_USER_AGENT'])) {
                    $preloadedBootConf = $this->computeBootConf();
                    AJXP_Controller::applyHook("loader.filter_boot_conf", array(&$preloadedBootConf));
                    $START_PARAMETERS["PRELOADED_BOOT_CONF"] = $preloadedBootConf;
                }

                // PRECOMPUTE REGISTRY
                if (!isSet($START_PARAMETERS["FORCE_REGISTRY_RELOAD"])) {
                    $regDoc = AJXP_PluginsService::getXmlRegistry();
                    $changes = AJXP_Controller::filterRegistryFromRole($regDoc);
                    if($changes) AJXP_PluginsService::updateXmlRegistry($regDoc);
                    $clone = $regDoc->cloneNode(true);
                    $clonePath = new DOMXPath($clone);
                    $serverCallbacks = $clonePath->query("//serverCallback|hooks");
                    foreach ($serverCallbacks as $callback) {
                        $callback->parentNode->removeChild($callback);
                    }
                    $START_PARAMETERS["PRELOADED_REGISTRY"] = AJXP_XMLWriter::replaceAjxpXmlKeywords($clone->saveXML());
                }

                $JSON_START_PARAMETERS = json_encode($START_PARAMETERS);
                $crtTheme = $this->pluginConf["GUI_THEME"];
                $additionalFrameworks = $this->getFilteredOption("JS_RESOURCES_BEFORE");
                $ADDITIONAL_FRAMEWORKS = "";
                if( !empty($additionalFrameworks) ){
                    $frameworkList = explode(",", $additionalFrameworks);
                    foreach($frameworkList as $index => $framework){
                        $frameworkList[$index] = '<script language="javascript" type="text/javascript" src="'.$framework.'"></script>'."\n";
                    }
                    $ADDITIONAL_FRAMEWORKS = implode("", $frameworkList);
                }
                if (ConfService::getConf("JS_DEBUG")) {
                    if (!isSet($mess)) {
                        $mess = ConfService::getMessages();
                    }
                    if (is_file(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui_debug.html")) {
                        include(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui_debug.html");
                    } else {
                        include(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/html/gui_debug.html");
                    }
                } else {
                    if (is_file(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui.html")) {
                        $content = file_get_contents(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui.html");
                    } else {
                        $content = file_get_contents(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/html/gui.html");
                    }
                    if (preg_match('/MSIE 7/',$_SERVER['HTTP_USER_AGENT'])) {
                        $content = str_replace("ajaxplorer_boot.js", "ajaxplorer_boot_protolegacy.js", $content);
                    }
                    $content = str_replace("AJXP_ADDITIONAL_JS_FRAMEWORKS", $ADDITIONAL_FRAMEWORKS, $content);
                    $content = AJXP_XMLWriter::replaceAjxpXmlKeywords($content, false);
                    $content = str_replace("AJXP_REBASE", isSet($START_PARAMETERS["REBASE"])?'<base href="'.$START_PARAMETERS["REBASE"].'"/>':"", $content);
                    if ($JSON_START_PARAMETERS) {
                        $content = str_replace("//AJXP_JSON_START_PARAMETERS", "startParameters = ".$JSON_START_PARAMETERS.";", $content);
                    }
                    print($content);
                }
            break;
            //------------------------------------
            //	GET CONFIG FOR BOOT
            //------------------------------------
            case "get_boot_conf":

                $out = array();
                AJXP_Utils::parseApplicationGetParameters($_GET, $out, $_SESSION);
                $config = $this->computeBootConf();
                header("Content-type:application/json;charset=UTF-8");
                print(json_encode($config));

            break;

            default;
            break;
        }

        return false;
    }

    public function computeBootConf()
    {
        if (isSet($_GET["server_prefix_uri"])) {
            $_SESSION["AJXP_SERVER_PREFIX_URI"] = str_replace("_UP_", "..", $_GET["server_prefix_uri"]);
        }
        $currentIsMinisite = (strpos(session_name(), "AjaXplorer_Shared") === 0);
        $config = array();
        $config["ajxpResourcesFolder"] = "plugins/gui.ajax/res";
        if ($currentIsMinisite) {
            $config["ajxpServerAccess"] = "index_shared.php";
        } else {
            $config["ajxpServerAccess"] = AJXP_SERVER_ACCESS;
        }
        $config["zipEnabled"] = ConfService::zipBrowsingEnabled();
        $config["multipleFilesDownloadEnabled"] = ConfService::zipCreationEnabled();
        $customIcon = $this->getFilteredOption("CUSTOM_ICON");
        self::filterXml($customIcon);
        $config["customWording"] = array(
            "welcomeMessage" => $this->getFilteredOption("CUSTOM_WELCOME_MESSAGE"),
            "title"			 => ConfService::getCoreConf("APPLICATION_TITLE"),
            "icon"			 => $customIcon,
            "iconWidth"		 => $this->getFilteredOption("CUSTOM_ICON_WIDTH"),
            "iconHeight"     => $this->getFilteredOption("CUSTOM_ICON_HEIGHT"),
            "iconOnly"       => $this->getFilteredOption("CUSTOM_ICON_ONLY"),
            "titleFontSize"	 => $this->getFilteredOption("CUSTOM_FONT_SIZE")
        );
        $cIcBin = $this->getFilteredOption("CUSTOM_ICON_BINARY");
        if (!empty($cIcBin)) {
            $config["customWording"]["icon_binary_url"] = "get_action=get_global_binary_param&binary_id=".$cIcBin;
        }
        $config["usersEnabled"] = AuthService::usersEnabled();
        $config["loggedUser"] = (AuthService::getLoggedUser()!=null);
        $config["currentLanguage"] = ConfService::getLanguage();
        $config["session_timeout"] = intval(ini_get("session.gc_maxlifetime"));
        $timeoutTime = $this->getFilteredOption("CLIENT_TIMEOUT_TIME");
        if (empty($timeoutTime)) {
            $to = $config["session_timeout"];
        } else {
            $to = $timeoutTime;
        }
        if($currentIsMinisite) $to = -1;
        $config["client_timeout"] = intval($to);
        $config["client_timeout_warning"] = floatval($this->getFilteredOption("CLIENT_TIMEOUT_WARN"));
        $config["availableLanguages"] = ConfService::getConf("AVAILABLE_LANG");
        $config["usersEditable"] = ConfService::getAuthDriverImpl()->usersEditable();
        $config["ajxpVersion"] = AJXP_VERSION;
        $config["ajxpVersionDate"] = AJXP_VERSION_DATE;
        $analytic = $this->getFilteredOption('GOOGLE_ANALYTICS_ID');
        if (!empty($analytic)) {
            $config["googleAnalyticsData"] = array(
                "id"=> 		$analytic,
                "domain" => $this->getFilteredOption('GOOGLE_ANALYTICS_DOMAIN'),
                "event" => 	$this->getFilteredOption('GOOGLE_ANALYTICS_EVENT')
            );
        }
        $config["i18nMessages"] = ConfService::getMessages();
        $config["SECURE_TOKEN"] = AuthService::generateSecureToken();
        $config["streaming_supported"] = "true";
        $config["theme"] = $this->pluginConf["GUI_THEME"];
        return $config;
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function nodeBookmarkMetadata(&$ajxpNode)
    {
        $user = AuthService::getLoggedUser();
        if($user == null) return;
        $metadata = $ajxpNode->retrieveMetadata("ajxp_bookmarked", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        if (is_array($metadata) && count($metadata)) {
            $ajxpNode->mergeMetadata(array(
                     "ajxp_bookmarked" => "true",
                     "overlay_icon"  => "bookmark.png",
                     "overlay_class" => "icon-bookmark"
                ), true);
            return;
        }
        if (!isSet(self::$loadedBookmarks)) {
            self::$loadedBookmarks = $user->getBookmarks();
        }
        foreach (self::$loadedBookmarks as $bm) {
            if ($bm["PATH"] == $ajxpNode->getPath()) {
                $ajxpNode->mergeMetadata(array(
                         "ajxp_bookmarked" => "true",
                         "overlay_icon"  => "bookmark.png",
                        "overlay_class" => "icon-bookmark"
                    ), true);
                $ajxpNode->setMetadata("ajxp_bookmarked", array("ajxp_bookmarked"=> "true"), true, AJXP_METADATA_SCOPE_REPOSITORY, true);
            }
        }
    }

    /**
     * @param AJXP_Node $fromNode
     * @param AJXP_Node $toNode
     * @param bool $copy
     */
    public function nodeChangeBookmarkMetadata($fromNode=null, $toNode=null, $copy=false){
        if($copy || $fromNode == null) return;
        $user = AuthService::getLoggedUser();
        if($user == null) return;
        if (!isSet(self::$loadedBookmarks)) {
            self::$loadedBookmarks = $user->getBookmarks();
        }
        if($toNode == null) {
            $fromNode->removeMetadata("ajxp_bookmarked", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        } else {
            $toNode->copyOrMoveMetadataFromNode($fromNode, "ajxp_bookmarked", "move", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        }
        AJXP_Controller::applyHook("msg.instant", array("<reload_bookmarks/>", $fromNode->getRepositoryId()));
    }

    public static function filterXml(&$value)
    {
        $instance = AJXP_PluginsService::getInstance()->findPlugin("gui", "ajax");
        if($instance === false) return null;
        $confs = $instance->getConfigs();
        $theme = $confs["GUI_THEME"];
        if (!defined("AJXP_THEME_FOLDER")) {
            define("CLIENT_RESOURCES_FOLDER", AJXP_PLUGINS_FOLDER."/gui.ajax/res");
            define("AJXP_THEME_FOLDER", CLIENT_RESOURCES_FOLDER."/themes/".$theme);
        }
        $value = str_replace(array("AJXP_CLIENT_RESOURCES_FOLDER", "AJXP_CURRENT_VERSION"), array(CLIENT_RESOURCES_FOLDER, AJXP_VERSION), $value);
        if (isSet($_SESSION["AJXP_SERVER_PREFIX_URI"])) {
            $value = str_replace("AJXP_THEME_FOLDER", $_SESSION["AJXP_SERVER_PREFIX_URI"]."plugins/gui.ajax/res/themes/".$theme, $value);
        } else {
            $value = str_replace("AJXP_THEME_FOLDER", "plugins/gui.ajax/res/themes/".$theme, $value);
        }
        return $value;
    }
}

AJXP_Controller::registerIncludeHook("xml.filter", array("AJXP_ClientDriver", "filterXml"));
