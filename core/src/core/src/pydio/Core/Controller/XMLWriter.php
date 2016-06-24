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
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\Services;
use Pydio\Core\Services\ConfService;


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
            return "";
        }else{
            return "</$docNode>";
        }
    }

    /**
     * Wrap xml inside a <tree>...</tree> document, including <?xml> declaration.
     * @param $content
     * @param string $docNode
     * @param array $attributes
     * @return string
     */
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
        if(InputFilter::detectXSS($nodeName)) $metaData["filename"] = "/XSS Detected - Please contact your admin";
        if (!isSet($metaData["text"])) {
            if(InputFilter::detectXSS($nodeLabel)) $nodeLabel = "XSS Detected - Please contact your admin";
            $metaData["text"] = $nodeLabel;
        }else{
            if(InputFilter::detectXSS($metaData["text"])) $metaData["text"] = "XSS Detected - Please contact your admin";
        }
        $metaData["is_file"] = ($isLeaf?"true":"false");
        $metaData["ajxp_im_time"] = time();
        foreach ($metaData as $key => $value) {
            if(InputFilter::detectXSS($value)) $value = "XSS Detected!";
            $value = StringHelper::xmlEntities($value, true);
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
        $xml = str_replace("AJXP_APPLICATION_TITLE", ConfService::getGlobalConf("APPLICATION_TITLE"), $xml);
        $xml = str_replace("AJXP_MIMES_EDITABLE", StatHelper::getAjxpMimes("editable"), $xml);
        $xml = str_replace("AJXP_MIMES_IMAGE", StatHelper::getAjxpMimes("image"), $xml);
        $xml = str_replace("AJXP_MIMES_AUDIO", StatHelper::getAjxpMimes("audio"), $xml);
        $xml = str_replace("AJXP_MIMES_ZIP", StatHelper::getAjxpMimes("zip"), $xml);
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
                $xml = str_replace("CONF_MESSAGE[$messId]", StringHelper::xmlEntities($message), $xml);
            }
        }
        if (preg_match_all("/MIXIN_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messId = str_replace(array("[", "]"), "", $match[1]);
                $message = $messId;
                if (array_key_exists($messId, $confMessages)) {
                    $message = $confMessages[$messId];
                }
                $xml = str_replace("MIXIN_MESSAGE[$messId]", StringHelper::xmlEntities($message), $xml);
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
        $nodePath = StringHelper::xmlEntities($nodePath, true);
        $pendingSelection = StringHelper::xmlEntities($pendingSelection, true);
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
                $nodePath = StringHelper::xmlEntities($nodePath, true);
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
        $messageId = StringHelper::xmlEntities($messageId);
        $data = XMLWriter::write("<trigger_bg_action name=\"$actionName\" messageId=\"$messageId\" delay=\"$delay\">", $print);
        foreach ($parameters as $paramName=>$paramValue) {
            $paramValue = StringHelper::xmlEntities($paramValue);
            $data .= XMLWriter::write("<param name=\"$paramName\" value=\"$paramValue\"/>", $print);
        }
        $data .= XMLWriter::write("</trigger_bg_action>", $print);
        return $data;
    }

    /**
     * Send directly JavaScript code to the client
     * @param $jsCode
     * @param $messageId
     * @param bool $print
     * @param int $delay
     * @return string
     */
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
                $buffer .= "<bookmark path=\"". StringHelper::xmlEntities($path, true) ."\" title=\"". StringHelper::xmlEntities($title, true) ."\"/>";
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
     * Simple XML element build from associative array. Can pass specific $children for nested elements.
     * @param string $tagName
     * @param array $attributes
     * @param string $xmlChildren
     * @return string
     */
    public static function toXmlElement($tagName, $attributes, $xmlChildren = ""){
        $buffer = "<$tagName ";
        foreach ($attributes as $attName => $attValue){
            $buffer.= "$attName=\"". StringHelper::xmlEntities($attValue) ."\" ";
        }
        if(!strlen($xmlChildren)) {
            $buffer .= "/>";
        } else{
            $buffer .= ">".$xmlChildren."</$tagName>";
        }
        return $buffer;
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
            $message = StringHelper::xmlContentEntities($logMessage);
        } else {
            $messageType = "ERROR";
            $message = StringHelper::xmlContentEntities($errorMessage);
        }
        return XMLWriter::write("<message type=\"$messageType\">".$message."</message>", $print);
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
