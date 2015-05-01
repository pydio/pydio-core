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
 * XML output Generator
 * @package Pydio
 * @subpackage Core
 */
class AJXP_XMLWriter
{
    public static $headerSent = false;

    /**
     * Output Headers, XML <?xml version...?> tag and a root node
     * @static
     * @param string $docNode
     * @param array $attributes
     */
    public static function header($docNode="tree", $attributes=array())
    {
        if(self::$headerSent !== false && self::$headerSent == $docNode) return ;
        header('Content-Type: text/xml; charset=UTF-8');
        header('Cache-Control: no-cache');
        print('<?xml version="1.0" encoding="UTF-8"?>');
        $attString = "";
        if (count($attributes)) {
            foreach ($attributes as $name=>$value) {
                $attString.="$name=\"$value\" ";
            }
        }
        self::$headerSent = $docNode;
        print("<$docNode $attString>");

    }
    /**
     * Outputs a closing root not (</tree>)
     * @static
     * @param string $docNode
     * @return void
     */
    public static function close($docNode="tree")
    {
        print("</$docNode>");
    }

    /**
     * @static
     * @param string $data
     * @param bool $print
     * @return string
     */
    public static function write($data, $print)
    {
        if ($print) {
            print($data);
            return "";
        } else {
            return $data;
        }
    }

    /**
     * Ouput the <pagination> tag
     * @static
     * @param integer $count
     * @param integer $currentPage
     * @param integer $totalPages
     * @param integer $dirsCount
     * @return void
     */
    public static function renderPaginationData($count, $currentPage, $totalPages, $dirsCount = -1, $remoteSortAttributes = null)
    {
        $remoteSortString = "";
        if (is_array($remoteSortAttributes)) {
            foreach($remoteSortAttributes as $k => $v) $remoteSortString .= " $k='$v'";
        }
        $string = '<pagination count="'.$count.'" total="'.$totalPages.'" current="'.$currentPage.'" overflowMessage="'.$currentPage."/".$totalPages.'" icon="folder.png" openicon="folder_open.png" dirsCount="'.$dirsCount.'"'.$remoteSortString.'/>';
        AJXP_XMLWriter::write($string, true);
    }
    /**
     * Prints out the XML headers and preamble, then an open node
     * @static
     * @param $nodeName
     * @param $nodeLabel
     * @param $isLeaf
     * @param array $metaData
     * @return void
     */
    public static function renderHeaderNode($nodeName, $nodeLabel, $isLeaf, $metaData = array())
    {
        header('Content-Type: text/xml; charset=UTF-8');
        header('Cache-Control: no-cache');
        print('<?xml version="1.0" encoding="UTF-8"?>');
        self::$headerSent = "tree";
        AJXP_XMLWriter::renderNode($nodeName, $nodeLabel, $isLeaf, $metaData, false);
    }

    /**
     * @static
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public static function renderAjxpHeaderNode($ajxpNode)
    {
        header('Content-Type: text/xml; charset=UTF-8');
        header('Cache-Control: no-cache');
        print('<?xml version="1.0" encoding="UTF-8"?>');
        self::$headerSent = "tree";
        self::renderAjxpNode($ajxpNode, false);
    }

    /**
     * The basic node
     * @static
     * @param string $nodeName
     * @param string $nodeLabel
     * @param bool $isLeaf
     * @param array $metaData
     * @param bool $close
     * @param bool $print
     * @return void|string
     */
    public static function renderNode($nodeName, $nodeLabel, $isLeaf, $metaData = array(), $close=true, $print = true)
    {
        $string = "<tree";
        $metaData["filename"] = $nodeName;
        if(AJXP_Utils::detectXSS($nodeName)) $metaData["filename"] = "/XSS Detected - Please contact your admin";
        if (!isSet($metaData["text"])) {
            if(AJXP_Utils::detectXSS($nodeLabel)) $nodeLabel = "XSS Detected - Please contact your admin";
            $metaData["text"] = $nodeLabel;
        }else{
            if(AJXP_Utils::detectXSS($metaData["text"])) $metaData["text"] = "XSS Detected - Please contact your admin";
        }
        $metaData["is_file"] = ($isLeaf?"true":"false");

        foreach ($metaData as $key => $value) {
            if(AJXP_Utils::detectXSS($value)) $value = "XSS Detected!";
            $value = AJXP_Utils::xmlEntities($value, true);
            $string .= " $key=\"$value\"";
        }
        if ($close) {
            $string .= "/>";
        } else {
            $string .= ">";
        }
        return AJXP_XMLWriter::write($string, $print);
    }

    /**
     * @static
     * @param AJXP_Node $ajxpNode
     * @param bool $close
     * @param bool $print
     * @return void|string
     */
    public static function renderAjxpNode($ajxpNode, $close = true, $print = true)
    {
        return AJXP_XMLWriter::renderNode(
            $ajxpNode->getPath(),
            $ajxpNode->getLabel(),
            $ajxpNode->isLeaf(),
            $ajxpNode->metadata,
            $close,
            $print);
    }

    /**
     * Render a node with arguments passed as array
     * @static
     * @param $array
     * @return void
     */
    public static function renderNodeArray($array)
    {
        self::renderNode($array[0],$array[1],$array[2],$array[3]);
    }
    /**
     * Error Catcher for PHP errors. Depending on the SERVER_DEBUG config
     * shows the file/line info or not.
     * @static
     * @param $code
     * @param $message
     * @param $fichier
     * @param $ligne
     * @param $context
     * @return
     */
    public static function catchError($code, $message, $fichier, $ligne, $context)
    {
        if(error_reporting() == 0) {
            return ;
        }
        AJXP_Logger::error(basename($fichier), "error l.$ligne", array("message" => $message));
        $loggedUser = AuthService::getLoggedUser();
        if (ConfService::getConf("SERVER_DEBUG")) {
            $stack = debug_backtrace();
            $stackLen = count($stack);
            for ($i = 1; $i < $stackLen; $i++) {
                $entry = $stack[$i];

                $func = $entry['function'] . '(';
                $argsLen = count($entry['args']);
                for ($j = 0; $j < $argsLen; $j++) {
                    $s = $entry['args'][$j];
                    if(is_string($s)){
                        $func .= $s;
                    }else if (is_object($s)){
                        $func .= get_class($s);
                    }
                    if ($j < $argsLen - 1) $func .= ', ';
                }
                $func .= ')';

                $message .= "\n". str_replace(dirname(__FILE__), '', $entry['file']) . ':' . $entry['line'] . ' - ' . $func . PHP_EOL;
            }
        }
        if(!headers_sent()) AJXP_XMLWriter::header();
        if(!empty($context) && is_object($context) && is_a($context, "AJXP_PromptException")){
            AJXP_XMLWriter::write("<prompt type=\"".$context->getPromptType()."\"><message>".$message."</message><data><![CDATA[".json_encode($context->getPromptData())."]]></data></prompt>", true);
        }else{
            AJXP_XMLWriter::sendMessage(null, SystemTextEncoding::toUTF8($message), true);
        }
        AJXP_XMLWriter::close();
        exit(1);
    }

    /**
     * Catch exceptions, @see catchError
     * @param Exception $exception
     */
    public static function catchException($exception)
    {
        try {
            AJXP_XMLWriter::catchError($exception->getCode(), SystemTextEncoding::fromUTF8($exception->getMessage()), $exception->getFile(), $exception->getLine(), $exception);
        } catch (Exception $innerEx) {
            error_log(get_class($innerEx)." thrown within the exception handler!");
            error_log("Original exception was: ".$innerEx->getMessage()." in ".$innerEx->getFile()." on line ".$innerEx->getLine());
            error_log("New exception is: ".$innerEx->getMessage()." in ".$innerEx->getFile()." on line ".$innerEx->getLine()." ".$innerEx->getTraceAsString());
            print("Error");
        }
    }
    /**
     * Dynamically replace XML keywords with their live values.
     * AJXP_SERVER_ACCESS, AJXP_MIMES_*,AJXP_ALL_MESSAGES, etc.
     * @static
     * @param string $xml
     * @param bool $stripSpaces
     * @return mixed
     */
    public static function replaceAjxpXmlKeywords($xml, $stripSpaces = false)
    {
        $messages = ConfService::getMessages();
        $confMessages = ConfService::getMessagesConf();
        $matches = array();
        if (isSet($_SESSION["AJXP_SERVER_PREFIX_URI"])) {
            //$xml = str_replace("AJXP_THEME_FOLDER", $_SESSION["AJXP_SERVER_PREFIX_URI"].AJXP_THEME_FOLDER, $xml);
            $xml = str_replace("AJXP_SERVER_ACCESS", $_SESSION["AJXP_SERVER_PREFIX_URI"].AJXP_SERVER_ACCESS, $xml);
        } else {
            //$xml = str_replace("AJXP_THEME_FOLDER", AJXP_THEME_FOLDER, $xml);
            $xml = str_replace("AJXP_SERVER_ACCESS", AJXP_SERVER_ACCESS, $xml);
        }
        $xml = str_replace("AJXP_APPLICATION_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $xml);
        $xml = str_replace("AJXP_MIMES_EDITABLE", AJXP_Utils::getAjxpMimes("editable"), $xml);
        $xml = str_replace("AJXP_MIMES_IMAGE", AJXP_Utils::getAjxpMimes("image"), $xml);
        $xml = str_replace("AJXP_MIMES_AUDIO", AJXP_Utils::getAjxpMimes("audio"), $xml);
        $xml = str_replace("AJXP_MIMES_ZIP", AJXP_Utils::getAjxpMimes("zip"), $xml);
        $authDriver = ConfService::getAuthDriverImpl();
        if ($authDriver != NULL) {
            $loginRedirect = $authDriver->getLoginRedirect();
            $xml = str_replace("AJXP_LOGIN_REDIRECT", ($loginRedirect!==false?"'".$loginRedirect."'":"false"), $xml);
        }
        $xml = str_replace("AJXP_REMOTE_AUTH", "false", $xml);
        $xml = str_replace("AJXP_NOT_REMOTE_AUTH", "true", $xml);
        $xml = str_replace("AJXP_ALL_MESSAGES", "MessageHash=".json_encode(ConfService::getMessages()).";", $xml);

        if (preg_match_all("/AJXP_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messId = str_replace("]", "", str_replace("[", "", $match[1]));
                $xml = str_replace("AJXP_MESSAGE[$messId]", $messages[$messId], $xml);
            }
        }
        if (preg_match_all("/CONF_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messId = str_replace(array("[", "]"), "", $match[1]);
                $message = $messId;
                if (array_key_exists($messId, $confMessages)) {
                    $message = $confMessages[$messId];
                }
                $xml = str_replace("CONF_MESSAGE[$messId]", AJXP_Utils::xmlEntities($message), $xml);
            }
        }
        if (preg_match_all("/MIXIN_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messId = str_replace(array("[", "]"), "", $match[1]);
                $message = $messId;
                if (array_key_exists($messId, $confMessages)) {
                    $message = $confMessages[$messId];
                }
                $xml = str_replace("MIXIN_MESSAGE[$messId]", AJXP_Utils::xmlEntities($message), $xml);
            }
        }
        if ($stripSpaces) {
            $xml = preg_replace("/[\n\r]?/", "", $xml);
            $xml = preg_replace("/\t/", " ", $xml);
        }
        $xml = str_replace(array('xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"','xsi:noNamespaceSchemaLocation="file:../core.ajaxplorer/ajxp_registry.xsd"'), "", $xml);
        $tab = array(&$xml);
        AJXP_Controller::applyIncludeHook("xml.filter", $tab);
        return $xml;
    }
    /**
     * Send a <reload> XML instruction for refreshing the list
     * @static
     * @param string $nodePath
     * @param string $pendingSelection
     * @param bool $print
     * @return string
     */
    public static function reloadDataNode($nodePath="", $pendingSelection="", $print = true)
    {
        $nodePath = AJXP_Utils::xmlEntities($nodePath, true);
        $pendingSelection = AJXP_Utils::xmlEntities($pendingSelection, true);
        return AJXP_XMLWriter::write("<reload_instruction object=\"data\" node=\"$nodePath\" file=\"$pendingSelection\"/>", $print);
    }


    /**
     * Send a <reload> XML instruction for refreshing the list
     * @static
     * @param string $nodePath
     * @param string $pendingSelection
     * @param bool $print
     * @return string
     */
    public static function writeNodesDiff($diffNodes, $print = false)
    {
        $mess = ConfService::getMessages();
        $buffer = "<nodes_diff>";
        if (isSet($diffNodes["REMOVE"]) && count($diffNodes["REMOVE"])) {
            $buffer .= "<remove>";
            foreach ($diffNodes["REMOVE"] as $nodePath) {
                $nodePath = AJXP_Utils::xmlEntities($nodePath, true);
                $buffer .= "<tree filename=\"$nodePath\"/>";
            }
            $buffer .= "</remove>";
        }
        if (isSet($diffNodes["ADD"]) && count($diffNodes["ADD"])) {
            $buffer .= "<add>";
            foreach ($diffNodes["ADD"] as $ajxpNode) {
                $ajxpNode->loadNodeInfo(false, false, "all");
                if (!empty($ajxpNode->metaData["mimestring_id"]) && array_key_exists($ajxpNode->metaData["mimestring_id"], $mess)) {
                    $ajxpNode->mergeMetadata(array("mimestring" =>  $mess[$ajxpNode->metaData["mimestring_id"]]));
                }
                $buffer .=  self::renderAjxpNode($ajxpNode, true, false);
            }
            $buffer .= "</add>";
        }
        if (isSet($diffNodes["UPDATE"]) && count($diffNodes["UPDATE"])) {
            $buffer .= "<update>";
            foreach ($diffNodes["UPDATE"] as $originalPath => $ajxpNode) {
                $ajxpNode->loadNodeInfo(false, false, "all");
                if (!empty($ajxpNode->metaData["mimestring_id"]) && array_key_exists($ajxpNode->metaData["mimestring_id"], $mess)) {
                    $ajxpNode->mergeMetadata(array("mimestring" =>  $mess[$ajxpNode->metaData["mimestring_id"]]));
                }
                $ajxpNode->original_path = $originalPath;
                $buffer .= self::renderAjxpNode($ajxpNode, true, false);
            }
            $buffer .= "</update>";
        }
        $buffer .= "</nodes_diff>";
        return AJXP_XMLWriter::write($buffer, $print);

        /*
        $nodePath = AJXP_Utils::xmlEntities($nodePath, true);
        $pendingSelection = AJXP_Utils::xmlEntities($pendingSelection, true);
        return AJXP_XMLWriter::write("<reload_instruction object=\"data\" node=\"$nodePath\" file=\"$pendingSelection\"/>", $print);
        */
    }


    /**
     * Send a <reload> XML instruction for refreshing the repositories list
     * @static
     * @param bool $print
     * @return string
     */
    public static function reloadRepositoryList($print = true)
    {
        return AJXP_XMLWriter::write("<reload_instruction object=\"repository_list\"/>", $print);
    }
    /**
     * Outputs a <require_auth/> tag
     * @static
     * @param bool $print
     * @return string
     */
    public static function requireAuth($print = true)
    {
        return AJXP_XMLWriter::write("<require_auth/>", $print);
    }
    /**
     * Triggers a background action client side
     * @static
     * @param $actionName
     * @param $parameters
     * @param $messageId
     * @param bool $print
     * @param int $delay
     * @return string
     */
    public static function triggerBgAction($actionName, $parameters, $messageId, $print=true, $delay = 0)
    {
        $messageId = AJXP_Utils::xmlEntities($messageId);
        $data = AJXP_XMLWriter::write("<trigger_bg_action name=\"$actionName\" messageId=\"$messageId\" delay=\"$delay\">", $print);
        foreach ($parameters as $paramName=>$paramValue) {
            $paramValue = AJXP_Utils::xmlEntities($paramValue);
            $data .= AJXP_XMLWriter::write("<param name=\"$paramName\" value=\"$paramValue\"/>", $print);
        }
        $data .= AJXP_XMLWriter::write("</trigger_bg_action>", $print);
        return $data;
    }

    public static function triggerBgJSAction($jsCode, $messageId, $print=true, $delay = 0)
    {
           $data = AJXP_XMLWriter::write("<trigger_bg_action name=\"javascript_instruction\" messageId=\"$messageId\" delay=\"$delay\">", $print);
        $data .= AJXP_XMLWriter::write("<clientCallback><![CDATA[".$jsCode."]]></clientCallback>", $print);
           $data .= AJXP_XMLWriter::write("</trigger_bg_action>", $print);
           return $data;
       }

    /**
     * List all bookmmarks as XML
     * @static
     * @param $allBookmarks
     * @param bool $print
     * @param string $format legacy|node_list
     * @return string
     */
    public static function writeBookmarks($allBookmarks, $print = true, $format = "legacy")
    {
        if ($format == "node_list") {
            $driver = ConfService::loadRepositoryDriver();
            if (!is_a($driver, "AjxpWrapperProvider")) {
                $driver = false;
            }
        }
        $buffer = "";
        foreach ($allBookmarks as $bookmark) {
            $path = ""; $title = "";
            if (is_array($bookmark)) {
                $path = $bookmark["PATH"];
                $title = $bookmark["TITLE"];
            } else if (is_string($bookmark)) {
                $path = $bookmark;
                $title = basename($bookmark);
            }
            if ($format == "node_list") {
                if ($driver) {
                    $node = new AJXP_Node($driver->getResourceUrl($path));
                    $buffer .= AJXP_XMLWriter::renderAjxpNode($node, true, false);
                } else {
                    $buffer .= AJXP_XMLWriter::renderNode($path, $title, false, array('icon' => "mime_empty.png"), true, false);
                }
            } else {
                $buffer .= "<bookmark path=\"".AJXP_Utils::xmlEntities($path, true)."\" title=\"".AJXP_Utils::xmlEntities($title, true)."\"/>";
            }
        }
        if($print) print $buffer;
        else return $buffer;
    }
    /**
     * Utilitary for generating a <component_config> tag for the FilesList component
     * @static
     * @param $config
     * @return void
     */
    public static function sendFilesListComponentConfig($config)
    {
        if (is_string($config)) {
            print("<client_configs><component_config className=\"FilesList\">$config</component_config></client_configs>");
        }
    }
    /**
     * Send a success or error message to the client.
     * @static
     * @param $logMessage
     * @param $errorMessage
     * @param bool $print
     * @return string
     */
    public static function sendMessage($logMessage, $errorMessage, $print = true)
    {
        $messageType = "";
        $message = "";
        if ($errorMessage == null) {
            $messageType = "SUCCESS";
            $message = AJXP_Utils::xmlContentEntities($logMessage);
        } else {
            $messageType = "ERROR";
            $message = AJXP_Utils::xmlContentEntities($errorMessage);
        }
        return AJXP_XMLWriter::write("<message type=\"$messageType\">".$message."</message>", $print);
    }

    /**
     * Extract all the user data and put it in XML
     * @static
     * @param null $userObject * @internal param bool $details
     * @return string
     */
    public static function getUserXML($userObject = null)
    {
        $buffer = "";
        $loggedUser = AuthService::getLoggedUser();
        $confDriver = ConfService::getConfStorageImpl();
        if($userObject != null) $loggedUser = $userObject;
        if (!AuthService::usersEnabled()) {
            $buffer.="<user id=\"shared\">";
            $buffer.="<active_repo id=\"".ConfService::getCurrentRepositoryId()."\" write=\"1\" read=\"1\"/>";
            $buffer.= AJXP_XMLWriter::writeRepositoriesData(null);
            $buffer.="</user>";
        } else if ($loggedUser != null) {
            $lock = $loggedUser->getLock();
            $buffer.="<user id=\"".$loggedUser->id."\">";
            $buffer.="<active_repo id=\"".ConfService::getCurrentRepositoryId()."\" write=\"".($loggedUser->canWrite(ConfService::getCurrentRepositoryId())?"1":"0")."\" read=\"".($loggedUser->canRead(ConfService::getCurrentRepositoryId())?"1":"0")."\"/>";
            $buffer.= AJXP_XMLWriter::writeRepositoriesData($loggedUser);
            $buffer.="<preferences>";
            $preferences = $confDriver->getExposedPreferences($loggedUser);
            foreach ($preferences as $prefName => $prefData) {
                $atts = "";
                if (isSet($prefData["exposed"]) && $prefData["exposed"] == true) {
                    foreach ($prefData as $k => $v) {
                        if($k=="name") continue;
                        if($k == "value") $k = "default";
                        $atts .= "$k='$v' ";
                    }
                }
                if (isset($prefData["pluginId"])) {
                    $atts .=  "pluginId='".$prefData["pluginId"]."' ";
                }
                if ($prefData["type"] == "string") {
                    $buffer.="<pref name=\"$prefName\" value=\"".$prefData["value"]."\" $atts/>";
                } else if ($prefData["type"] == "json") {
                    $buffer.="<pref name=\"$prefName\" $atts><![CDATA[".$prefData["value"]."]]></pref>";
                }
            }
            $buffer.="</preferences>";
            $buffer.="<special_rights is_admin=\"".($loggedUser->isAdmin()?"1":"0")."\"  ".($lock!==false?"lock=\"$lock\"":"")."/>";
            /*
            $bMarks = $loggedUser->getBookmarks();
            if (count($bMarks)) {
                $buffer.= "<bookmarks>".AJXP_XMLWriter::writeBookmarks($bMarks, false)."</bookmarks>";
            }
            */
            $buffer.="</user>";
        }
        return $buffer;
    }

    /**
     * Write the repositories access rights in XML format
     * @static
     * @param AbstractAjxpUser|null $loggedUser * @internal param bool $details
     * @return string
     */
    public static function writeRepositoriesData($loggedUser)
    {
        $st = "<repositories>";
        $streams = ConfService::detectRepositoryStreams(false);

        $exposed = array();
        $cacheHasExposed = AJXP_PluginsService::getInstance()->loadFromPluginQueriesCache("//server_settings/param[contains(@scope,'repository') and @expose='true']");
        if ($cacheHasExposed !== null && is_array($cacheHasExposed)) {
            $exposed = $cacheHasExposed;
        } else {
            $exposed_props = AJXP_PluginsService::searchAllManifests("//server_settings/param[contains(@scope,'repository') and @expose='true']", "node", false, false, true);
            foreach($exposed_props as $exposed_prop){
                $pluginId = $exposed_prop->parentNode->parentNode->getAttribute("id");
                $paramName = $exposed_prop->getAttribute("name");
                $paramDefault = $exposed_prop->getAttribute("default");
                $exposed[] = array("PLUGIN_ID" => $pluginId, "NAME" => $paramName, "DEFAULT" => $paramDefault);
            }
            AJXP_PluginsService::getInstance()->storeToPluginQueriesCache("//server_settings/param[contains(@scope,'repository') and @expose='true']", $exposed);
        }

        foreach (ConfService::getAccessibleRepositories($loggedUser, false, false) as $repoId => $repoObject) {
            $toLast = false;
            if ($repoObject->getAccessType()=="ajxp_conf") {
                if(AuthService::usersEnabled() && !$loggedUser->isAdmin())continue;
                $toLast = true;
            }
            $rightString = "";
            $streamString = "";
            if (in_array($repoObject->accessType, $streams)) {
                $streamString = "allowCrossRepositoryCopy=\"true\"";
            }
            if ($repoObject->getUniqueUser()) {
                $streamString .= " user_editable_repository=\"true\" ";
            }
            $slugString = "";
            $slug = $repoObject->getSlug();
            if (!empty($slug)) {
                $slugString = "repositorySlug=\"$slug\"";
            }
            $isSharedString = "";
            $ownerLabel = null;
            if ($repoObject->hasOwner()) {
                $uId = $repoObject->getOwner();
                $uObject = ConfService::getConfStorageImpl()->createUserObject($uId);
                $label = $uObject->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, $uId);
                if(empty($label)) $label = $uId;
                $ownerLabel = $label;
                $isSharedString =  'owner="'.AJXP_Utils::xmlEntities($label).'"';
            }
            $descTag = "";
            $public = false;
            if(!empty($_SESSION["CURRENT_MINISITE"])) $public = true;
            $description = $repoObject->getDescription($public);
            if (!empty($description)) {
                $descTag = '<description>'.AJXP_Utils::xmlEntities($description, true).'</description>';
            }
            $roleString="";
            if($loggedUser != null){
                $merged = $loggedUser->mergedRole;
                $params = array();
                foreach($exposed as $exposed_prop){
                    $metaOptions = $repoObject->getOption("META_SOURCES");
                    if(!isSet($metaOptions[$exposed_prop["PLUGIN_ID"]])){
                        continue;
                    }
                    $value = $exposed_prop["DEFAULT"];
                    if(isSet($metaOptions[$exposed_prop["PLUGIN_ID"]][$exposed_prop["NAME"]])){
                        $value = $metaOptions[$exposed_prop["PLUGIN_ID"]][$exposed_prop["NAME"]];
                    }
                    $value = $merged->filterParameterValue($exposed_prop["PLUGIN_ID"], $exposed_prop["NAME"], $repoId, $value);
                    if($value !== null){
                        if($value === true  || $value === false) $value = ($value === true ?"true":"false");
                        $params[] = '<repository_plugin_param plugin_id="'.$exposed_prop["PLUGIN_ID"].'" name="'.$exposed_prop["NAME"].'" value="'.AJXP_Utils::xmlEntities($value).'"/>';
                        $roleString .= str_replace(".", "_",$exposed_prop["PLUGIN_ID"])."_".$exposed_prop["NAME"].'="'.AJXP_Utils::xmlEntities($value).'" ';
                    }
                }
                $roleString.='acl="'.$merged->getAcl($repoId).'"';
            }
            $xmlString = "<repo access_type=\"".$repoObject->accessType."\" id=\"".$repoId."\"$rightString $streamString $slugString $isSharedString $roleString><label>".SystemTextEncoding::toUTF8(AJXP_Utils::xmlEntities($repoObject->getDisplay()))."</label>".$descTag.$repoObject->getClientSettings()."</repo>";
            if ($toLast) {
                $lastString = $xmlString;
            } else {
                $st .= $xmlString;
            }
        }

        if (isSet($lastString)) {
            $st.= $lastString;
        }
        $st .= "</repositories>";
        return $st;
    }
    /**
     * Writes a <logging_result> tag
     * @static
     * @param integer $result
     * @param string $rememberLogin
     * @param string $rememberPass
     * @param string $secureToken
     * @return void
     */
    public static function loggingResult($result, $rememberLogin="", $rememberPass = "", $secureToken="")
    {
        $remString = "";
        if ($rememberPass != "" && $rememberLogin!= "") {
            $remString = " remember_login=\"$rememberLogin\" remember_pass=\"$rememberPass\"";
        }
        if ($secureToken != "") {
            $remString .= " secure_token=\"$secureToken\"";
        }
        print("<logging_result value=\"$result\"$remString/>");
    }

    /**
     * Create plain PHP associative array from XML.
     *
     * Example usage:
     *   $xmlNode = simplexml_load_file('example.xml');
     *   $arrayData = xmlToArray($xmlNode);
     *   echo json_encode($arrayData);
     *
     * @param \DOMNode $domXml The dom node to load
     * @param array $options Associative array of options
     * @return array
     * @link http://outlandishideas.co.uk/blog/2012/08/xml-to-json/ More info
     * @author Tamlyn Rhodes <http://tamlyn.org>
     * @license http://creativecommons.org/publicdomain/mark/1.0/ Public Domain
     */
    public static function xmlToArray($domXml, $options = array()) {
        $xml = simplexml_import_dom($domXml);
        $defaults = array(
            'namespaceSeparator' => ':',//you may want this to be something other than a colon
            'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
            'alwaysArray' => array(),   //array of xml tag names which should always become arrays
            'autoArray' => true,        //only create arrays for tags which appear more than once
            'textContent' => '$',       //key used for the text content of elements
            'autoText' => true,         //skip textContent key if node has no attributes or child nodes
            'keySearch' => false,       //optional search and replace on tag and attribute names
            'keyReplace' => false       //replace values for above search values (as passed to str_replace())
        );
        $options = array_merge($defaults, $options);
        $namespaces = $xml->getDocNamespaces();
        $namespaces[''] = null; //add base (empty) namespace

        //get attributes from all namespaces
        $attributesArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }

        //get child nodes from all namespaces
        $tagsArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = self::xmlToArray($childXml, $options);
                list($childTagName, $childProperties) = each($childArray);

                //replace characters in tag name
                if ($options['keySearch']) $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                //add namespace prefix, if any
                if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;

                if (!isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                            ? array($childProperties) : $childProperties;
                } elseif (
                    is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                    === range(0, count($tagsArray[$childTagName]) - 1)
                ) {
                    //key already exists and is integer indexed array
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
                }
            }
        }

        //get text content of node
        $textContentArray = array();
        $plainText = trim((string)$xml);
        if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;

        //stick it all together
        $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

        //return node as array
        return array(
            $xml->getName() => $propertiesArray
        );
    }

}
