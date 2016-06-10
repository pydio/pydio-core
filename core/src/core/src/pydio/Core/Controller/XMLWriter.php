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
namespace Pydio\Core\Controller;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\IAjxpWrapperProvider;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Services;
use Pydio\Conf\Core\AbstractAjxpUser;
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\TextEncoder;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * XML output Generator
 * @package Pydio
 * @subpackage Core
 */
class XMLWriter
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
        if(!ConfService::currentContextIsCommandLine()){
            header('Content-Type: text/xml; charset=UTF-8');
            header('Cache-Control: no-cache');
        }
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
     * @param bool $print
     * @return void|string
     */
    public static function close($docNode="tree", $print = true)
    {
        if($print){
            print("</$docNode>");
        }else{
            return "</$docNode>";
        }
    }

    public static function wrapDocument($content, $docNode = "tree", $attributes = array()){

        if(self::$headerSent !== false && self::$headerSent == $docNode) {
            return $content;
        }
        //header('Content-Type: text/xml; charset=UTF-8');
        //header('Cache-Control: no-cache');
        $buffer = '<?xml version="1.0" encoding="UTF-8"?>';
        $attString = "";
        if (count($attributes)) {
            foreach ($attributes as $name=>$value) {
                $attString.="$name=\"$value\" ";
            }
        }
        self::$headerSent = $docNode;
        $buffer .= "<$docNode $attString>";
        $buffer .= $content;
        $buffer .= "</$docNode>";
        return $buffer;

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
     * @param null $remoteSortAttributes
     * @param bool $print
     * @return void|string
     */
    public static function renderPaginationData($count, $currentPage, $totalPages, $dirsCount = -1, $remoteSortAttributes = null, $print = true)
    {
        $remoteSortString = "";
        if (is_array($remoteSortAttributes)) {
            foreach($remoteSortAttributes as $k => $v) $remoteSortString .= " $k='$v'";
        }
        $string = '<pagination count="'.$count.'" total="'.$totalPages.'" current="'.$currentPage.'" overflowMessage="'.$currentPage."/".$totalPages.'" icon="folder.png" openicon="folder_open.png" dirsCount="'.$dirsCount.'"'.$remoteSortString.'/>';
        return XMLWriter::write($string, $print);
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
        if(!ConfService::currentContextIsCommandLine()) {
            header('Content-Type: text/xml; charset=UTF-8');
            header('Cache-Control: no-cache');
        }
        print('<?xml version="1.0" encoding="UTF-8"?>');
        self::$headerSent = "tree";
        XMLWriter::renderNode($nodeName, $nodeLabel, $isLeaf, $metaData, false);
    }

    /**
     * @static
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @return void
     */
    public static function renderAjxpHeaderNode($ajxpNode)
    {
        if(!ConfService::currentContextIsCommandLine()) {
            header('Content-Type: text/xml; charset=UTF-8');
            header('Cache-Control: no-cache');
        }
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
        if(Utils::detectXSS($nodeName)) $metaData["filename"] = "/XSS Detected - Please contact your admin";
        if (!isSet($metaData["text"])) {
            if(Utils::detectXSS($nodeLabel)) $nodeLabel = "XSS Detected - Please contact your admin";
            $metaData["text"] = $nodeLabel;
        }else{
            if(Utils::detectXSS($metaData["text"])) $metaData["text"] = "XSS Detected - Please contact your admin";
        }
        $metaData["is_file"] = ($isLeaf?"true":"false");
        $metaData["ajxp_im_time"] = time();
        foreach ($metaData as $key => $value) {
            if(Utils::detectXSS($value)) $value = "XSS Detected!";
            $value = Utils::xmlEntities($value, true);
            $string .= " $key=\"$value\"";
        }
        if ($close) {
            $string .= "/>";
        } else {
            $string .= ">";
        }
        return XMLWriter::write($string, $print);
    }

    /**
     * @static
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @param bool $close
     * @param bool $print
     * @return void|string
     */
    public static function renderAjxpNode($ajxpNode, $close = true, $print = true)
    {
        return XMLWriter::renderNode(
            $ajxpNode->getPath(),
            $ajxpNode->getLabel(),
            $ajxpNode->isLeaf(),
            $ajxpNode->metadata,
            $close,
            $print);
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
        $messages = LocaleService::getMessages();
        $confMessages = LocaleService::getConfigMessages();
        $matches = array();
        if (isSet($_SESSION["AJXP_SERVER_PREFIX_URI"])) {
            //$xml = str_replace("AJXP_THEME_FOLDER", $_SESSION["AJXP_SERVER_PREFIX_URI"].AJXP_THEME_FOLDER, $xml);
            $xml = str_replace("AJXP_SERVER_ACCESS", $_SESSION["AJXP_SERVER_PREFIX_URI"].AJXP_SERVER_ACCESS, $xml);
        } else {
            //$xml = str_replace("AJXP_THEME_FOLDER", AJXP_THEME_FOLDER, $xml);
            $xml = str_replace("AJXP_SERVER_ACCESS", AJXP_SERVER_ACCESS, $xml);
        }
        $xml = str_replace("AJXP_APPLICATION_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $xml);
        $xml = str_replace("AJXP_MIMES_EDITABLE", Utils::getAjxpMimes("editable"), $xml);
        $xml = str_replace("AJXP_MIMES_IMAGE", Utils::getAjxpMimes("image"), $xml);
        $xml = str_replace("AJXP_MIMES_AUDIO", Utils::getAjxpMimes("audio"), $xml);
        $xml = str_replace("AJXP_MIMES_ZIP", Utils::getAjxpMimes("zip"), $xml);
        $authDriver = ConfService::getAuthDriverImpl();
        if ($authDriver != NULL) {
            $loginRedirect = $authDriver->getLoginRedirect();
            $xml = str_replace("AJXP_LOGIN_REDIRECT", ($loginRedirect!==false?"'".$loginRedirect."'":"false"), $xml);
        }
        $xml = str_replace("AJXP_REMOTE_AUTH", "false", $xml);
        $xml = str_replace("AJXP_NOT_REMOTE_AUTH", "true", $xml);
        $xml = str_replace("AJXP_ALL_MESSAGES", "MessageHash=".json_encode(LocaleService::getMessages()).";", $xml);

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
                $xml = str_replace("CONF_MESSAGE[$messId]", Utils::xmlEntities($message), $xml);
            }
        }
        if (preg_match_all("/MIXIN_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messId = str_replace(array("[", "]"), "", $match[1]);
                $message = $messId;
                if (array_key_exists($messId, $confMessages)) {
                    $message = $confMessages[$messId];
                }
                $xml = str_replace("MIXIN_MESSAGE[$messId]", Utils::xmlEntities($message), $xml);
            }
        }
        if ($stripSpaces) {
            $xml = preg_replace("/[\n\r]?/", "", $xml);
            $xml = preg_replace("/\t/", " ", $xml);
        }
        $xml = str_replace(array('xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"','xsi:noNamespaceSchemaLocation="file:../core.ajaxplorer/ajxp_registry.xsd"'), "", $xml);
        $tab = array(&$xml);
        Controller::applyIncludeHook("xml.filter", $tab);
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
        $nodePath = Utils::xmlEntities($nodePath, true);
        $pendingSelection = Utils::xmlEntities($pendingSelection, true);
        return XMLWriter::write("<reload_instruction object=\"data\" node=\"$nodePath\" file=\"$pendingSelection\"/>", $print);
    }


    /**
     * Send a <reload> XML instruction for refreshing the list
     * @static
     * @param $diffNodes
     * @param bool $print
     * @return string
     */
    public static function writeNodesDiff($diffNodes, $print = false)
    {
        /**
         * @var $ajxpNode \Pydio\Access\Core\Model\AJXP_Node
         */
        $mess = LocaleService::getMessages();
        $buffer = "<nodes_diff>";
        if (isSet($diffNodes["REMOVE"]) && count($diffNodes["REMOVE"])) {
            $buffer .= "<remove>";
            foreach ($diffNodes["REMOVE"] as $nodePath) {
                $nodePath = Utils::xmlEntities($nodePath, true);
                $buffer .= "<tree filename=\"$nodePath\" ajxp_im_time=\"".time()."\"/>";
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
        return XMLWriter::write($buffer, $print);
    }


    /**
     * Send a <reload> XML instruction for refreshing the repositories list
     * @static
     * @param bool $print
     * @return string
     */
    public static function reloadRepositoryList($print = true)
    {
        return XMLWriter::write("<reload_instruction object=\"repository_list\"/>", $print);
    }
    /**
     * Outputs a <require_auth/> tag
     * @static
     * @param bool $print
     * @return string
     */
    public static function requireAuth($print = true)
    {
        return XMLWriter::write("<require_auth/>", $print);
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
        $messageId = Utils::xmlEntities($messageId);
        $data = XMLWriter::write("<trigger_bg_action name=\"$actionName\" messageId=\"$messageId\" delay=\"$delay\">", $print);
        foreach ($parameters as $paramName=>$paramValue) {
            $paramValue = Utils::xmlEntities($paramValue);
            $data .= XMLWriter::write("<param name=\"$paramName\" value=\"$paramValue\"/>", $print);
        }
        $data .= XMLWriter::write("</trigger_bg_action>", $print);
        return $data;
    }

    public static function triggerBgJSAction($jsCode, $messageId, $print=true, $delay = 0)
    {
           $data = XMLWriter::write("<trigger_bg_action name=\"javascript_instruction\" messageId=\"$messageId\" delay=\"$delay\">", $print);
        $data .= XMLWriter::write("<clientCallback><![CDATA[".$jsCode."]]></clientCallback>", $print);
           $data .= XMLWriter::write("</trigger_bg_action>", $print);
           return $data;
       }

    /**
     * List all bookmmarks as XML
     * @static
     * @param $allBookmarks
     * @param ContextInterface $context
     * @param bool $print
     * @param string $format legacy|node_list
     * @return string
     */
    public static function writeBookmarks($allBookmarks, $context, $print = true, $format = "legacy")
    {
        $driver = false;
        $repository = $context->getRepository();
        if ($format == "node_list") {
            $driver = $repository->getDriverInstance();
            if (!($driver instanceof IAjxpWrapperProvider)) {
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
                    $node = new AJXP_Node($context->getUrlBase().$path);
                    $buffer .= XMLWriter::renderAjxpNode($node, true, false);
                } else {
                    $buffer .= XMLWriter::renderNode($path, $title, false, array('icon' => "mime_empty.png"), true, false);
                }
            } else {
                $buffer .= "<bookmark path=\"".Utils::xmlEntities($path, true)."\" title=\"".Utils::xmlEntities($title, true)."\"/>";
            }
        }
        if($print) {
            print $buffer;
            return null;
        } else {
            return $buffer;
        }
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
        if ($errorMessage == null) {
            $messageType = "SUCCESS";
            $message = Utils::xmlContentEntities($logMessage);
        } else {
            $messageType = "ERROR";
            $message = Utils::xmlContentEntities($errorMessage);
        }
        return XMLWriter::write("<message type=\"$messageType\">".$message."</message>", $print);
    }

    /**
     * Extract all the user data and put it in XML
     * @static
     * @param ContextInterface $ctx
     * @param AbstractAjxpUser|null $userObject
     * @return string
     */
    public static function getUserXML(ContextInterface $ctx, $userObject = null)
    {
        $buffer = "";
        $loggedUser = $ctx->getUser();
        $currentRepoId = $ctx->getRepositoryId();
        $confDriver = ConfService::getConfStorageImpl();
        if($userObject != null) $loggedUser = $userObject;
        if (!UsersService::usersEnabled()) {
            $buffer.="<user id=\"shared\">";
            $buffer.="<active_repo id=\"".$currentRepoId."\" write=\"1\" read=\"1\"/>";
            $buffer.= XMLWriter::writeRepositoriesData($ctx);
            $buffer.="</user>";
        } else if ($loggedUser != null) {
            $lock = $loggedUser->getLock();
            $buffer.="<user id=\"".$loggedUser->id."\">";
            $buffer.="<active_repo id=\"".$currentRepoId."\" write=\"".($loggedUser->canWrite($currentRepoId)?"1":"0")."\" read=\"".($loggedUser->canRead($currentRepoId)?"1":"0")."\"/>";
            $buffer.= XMLWriter::writeRepositoriesData($ctx);
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
     * @param ContextInterface $ctx
     * @return string
     */
    public static function writeRepositoriesData(ContextInterface $ctx)
    {
        $loggedUser = $ctx->getUser();
        $st = "<repositories>";
        $streams = ConfService::detectRepositoryStreams($loggedUser, false);
        
        $exposed = PluginsService::searchManifestsWithCache("//server_settings/param[contains(@scope,'repository') and @expose='true']", function($nodes){
            $exposedNodes = [];
            foreach($nodes as $exposed_prop){
                $pluginId = $exposed_prop->parentNode->parentNode->getAttribute("id");
                $paramName = $exposed_prop->getAttribute("name");
                $paramDefault = $exposed_prop->getAttribute("default");
                $exposedNodes[] = array("PLUGIN_ID" => $pluginId, "NAME" => $paramName, "DEFAULT" => $paramDefault);
            }
            return $exposedNodes;
        });

        $accessible = UsersService::getRepositoriesForUser($loggedUser);

        $inboxStatus = 0;
        foreach($accessible as $repoId => $repoObject){
            if(!$repoObject->hasContentFilter()) {
                continue;
            }
            $accessStatus = $repoObject->getAccessStatus();
            if(empty($accessStatus) && $loggedUser != null){
                $lastConnected = $loggedUser->getArrayPref("repository_last_connected", $repoId);
                if(empty($lastConnected)){
                    $accessStatus = 1;
                }
            }
            if(!empty($accessStatus)){
                $inboxStatus ++;
            }
        }

        foreach ($accessible as $repoId => $repoObject) {
            if(!isSet($_SESSION["CURRENT_MINISITE"]) && $repoObject->hasContentFilter()){
                continue;
            }
            $accessStatus = '';
            if($repoObject->getAccessType() == "inbox"){
                $accessStatus = $inboxStatus;
            }
            $xmlString = self::repositoryToXML($repoId, $repoObject, $exposed, $streams, $loggedUser, $accessStatus);
            $st .= $xmlString;
        }

        $st .= "</repositories>";
        return $st;
    }

    /**
     * @param string $repoId
     * @param \Pydio\Access\Core\Model\Repository $repoObject
     * @param array $exposed
     * @param array $streams
     * @param AbstractAjxpUser $loggedUser
     * @param string $accessStatus
     * @return string
     * @throws \Exception
     */
    public static function repositoryToXML($repoId, $repoObject, $exposed, $streams, $loggedUser, $accessStatus = ""){


        $statusString = " repository_type=\"".$repoObject->getRepositoryType()."\"";
        if(empty($accessStatus)){
            $accessStatus = $repoObject->getAccessStatus();
        }
        if(!empty($accessStatus)){
            $statusString .= " access_status=\"$accessStatus\" ";
        }else if($loggedUser != null){
            $lastConnected = $loggedUser->getArrayPref("repository_last_connected", $repoId);
            if(!empty($lastConnected)) $statusString .= " last_connection=\"$lastConnected\" ";
        }

        $streamString = "";
        if (in_array($repoObject->accessType, $streams)) {
            $streamString = "allowCrossRepositoryCopy=\"true\"";
        }
        if ($repoObject->getUniqueUser()) {
            $streamString .= " user_editable_repository=\"true\" ";
        }
        if ($repoObject->hasContentFilter()){
            $streamString .= " hasContentFilter=\"true\"";
        }
        $slugString = "";
        $slug = $repoObject->getSlug();
        if (!empty($slug)) {
            $slugString = "repositorySlug=\"$slug\"";
        }
        $isSharedString = "";
        $currentUserIsOwner = false;
        $ownerLabel = null;
        if ($repoObject->hasOwner()) {
            $uId = $repoObject->getOwner();
            if($loggedUser != null && $loggedUser->getId() == $uId){
                $currentUserIsOwner = true;
            }
            $label = ConfService::getUserPersonalParameter("USER_DISPLAY_NAME", $uId, "core.conf", $uId);
            $ownerLabel = $label;
            $isSharedString =  'owner="'.Utils::xmlEntities($label).'"';
        }
        if ($repoObject->securityScope() == "USER" || $currentUserIsOwner){
            $streamString .= " userScope=\"true\"";
        }

        $descTag = "";
        $public = false;
        if(!empty($_SESSION["CURRENT_MINISITE"])) $public = true;
        $description = $repoObject->getDescription($public, $ownerLabel);
        if (!empty($description)) {
            $descTag = '<description>'.Utils::xmlEntities($description, true).'</description>';
        }
        $ctx = Context::contextWithObjects($loggedUser, $repoObject);
        $roleString="";
        if($loggedUser != null){
            $merged = $loggedUser->mergedRole;
            $params = array();
            foreach($exposed as $exposed_prop){
                $metaOptions = $repoObject->getContextOption($ctx, "META_SOURCES");
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
                    $params[] = '<repository_plugin_param plugin_id="'.$exposed_prop["PLUGIN_ID"].'" name="'.$exposed_prop["NAME"].'" value="'.Utils::xmlEntities($value).'"/>';
                    $roleString .= str_replace(".", "_",$exposed_prop["PLUGIN_ID"])."_".$exposed_prop["NAME"].'="'.Utils::xmlEntities($value).'" ';
                }
            }
            $roleString.='acl="'.$merged->getAcl($repoId).'"';
            if($merged->hasMask($repoId)){
                $roleString.= ' hasMask="true" ';
            }
        }
        return "<repo access_type=\"".$repoObject->accessType."\" id=\"".$repoId."\"$statusString $streamString $slugString $isSharedString $roleString><label>".TextEncoder::toUTF8(Utils::xmlEntities($repoObject->getDisplay()))."</label>".$descTag.$repoObject->getClientSettings($ctx)."</repo>";

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
