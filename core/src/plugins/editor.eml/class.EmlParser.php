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
 * Parses an EML file and return the result as XML
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class EmlParser extends AJXP_Plugin
{
    public static $currentListingOnlyEmails;

    public function performChecks()
    {
        if (!AJXP_Utils::searchIncludePath("Mail/mimeDecode.php")) {
            throw new Exception("Cannot find Mail/mimeDecode PEAR library");
        }
    }

    public function switchAction($action, $httpVars, $filesVars)
    {
        if(!isSet($this->actions[$action])) return false;

        $repository = ConfService::getRepository();
        if (!$repository->detectStreamWrapper(true)) {
            return false;
        }

        $streamData = $repository->streamData;
        $destStreamURL = $streamData["protocol"]."://".$repository->getId();
        $wrapperClassName = $streamData["classname"];

        $selection = new UserSelection($repository, $httpVars);

        if($selection->isEmpty()) return;

        $file = $destStreamURL.$selection->getUniqueFile();
        $mess = ConfService::getMessages();

        $node = new AJXP_Node($file);
        AJXP_Controller::applyHook("node.read", array($node));


        switch ($action) {
            case "eml_get_xml_structure":
                $params = array(
                    'include_bodies' => false,
                    'decode_bodies' => false,
                    'decode_headers' => 'UTF-8'
                );
                $decoder = $this->getStructureDecoder($file, ($wrapperClassName == "imapAccessWrapper"));
                $xml = $decoder->getXML($decoder->decode($params));
                if (function_exists("imap_mime_header_decode")) {
                    $doc = new DOMDocument();
                    $doc->loadXML($xml);
                    $xPath = new DOMXPath($doc);
                    $headers = $xPath->query("//headername");
                    $changes = false;
                    foreach ($headers as $headerNode) {
                        if ($headerNode->firstChild->nodeValue == "Subject") {
                            $headerValueNode = $headerNode->nextSibling->nextSibling;
                            $value = $headerValueNode->nodeValue;
                            $elements = imap_mime_header_decode($value);
                            $decoded = "";
                            foreach ($elements as $element) {
                                $decoded.=$element->text;
                                $charset = $element->charset;
                            }
                            if ($decoded != $value) {
                                $value = SystemTextEncoding::changeCharset($charset, "UTF-8", $decoded);
                                $node = $doc->createElement("headervalue", $value);
                                $res = $headerNode->parentNode->replaceChild($node, $headerValueNode);
                                $changes = true;
                            }
                        }
                    }
                    if($changes) $xml = $doc->saveXML();
                }
                print $xml;
            break;
            case "eml_get_bodies":
                require_once("Mail/mimeDecode.php");
                $params = array(
                    'include_bodies' => true,
                    'decode_bodies' => true,
                    'decode_headers' => false
                );
                if ($wrapperClassName == "imapAccessWrapper") {
                    $cache = AJXP_Cache::getItem("eml_remote", $file, null, array("EmlParser", "computeCacheId"));
                    $content = $cache->getData();
                } else {
                    $content = file_get_contents($file);
                }

                $decoder = new Mail_mimeDecode($content);
                $structure = $decoder->decode($params);
                $html = $this->_findPartByCType($structure, "text", "html");
                $text = $this->_findPartByCType($structure, "text", "plain");
                if ($html != false && isSet($html->ctype_parameters) && isSet($html->ctype_parameters["charset"])) {
                    $charset = $html->ctype_parameters["charset"];
                }
                if (isSet($charset)) {
                    header('Content-Type: text/xml; charset='.$charset);
                    header('Cache-Control: no-cache');
                    print('<?xml version="1.0" encoding="'.$charset.'"?>');
                    print('<email_body>');
                } else {
                    AJXP_XMLWriter::header("email_body");
                }
                if ($html!==false) {
                    print('<mimepart type="html"><![CDATA[');
                    $text = $html->body;
                    print($text);
                    print("]]></mimepart>");
                }
                if ($text!==false) {
                    print('<mimepart type="plain"><![CDATA[');
                    print($text->body);
                    print("]]></mimepart>");
                }
                AJXP_XMLWriter::close("email_body");

            break;
            case "eml_dl_attachment":
                $attachId = $httpVars["attachment_id"];
                if(!isset($attachId)) break;

                require_once("Mail/mimeDecode.php");
                $params = array(
                    'include_bodies' => true,
                    'decode_bodies' => true,
                    'decode_headers' => false
                );
                if ($wrapperClassName == "imapAccessWrapper") {
                    $cache = AJXP_Cache::getItem("eml_remote", $file, null, array("EmlParser", "computeCacheId"));
                    $content = $cache->getData();
                } else {
                    $content = file_get_contents($file);
                }
                $decoder = new Mail_mimeDecode($content);
                $structure = $decoder->decode($params);
                $part = $this->_findAttachmentById($structure, $attachId);
                if ($part !== false) {
                    $fake = new fsAccessDriver("fake", "");
                    $fake->readFile($part->body, "file", $part->d_parameters['filename'], true);
                    exit();
                } else {
                    //var_dump($structure);
                }
            break;
            case "eml_cp_attachment":
                $attachId = $httpVars["attachment_id"];
                $destRep = AJXP_Utils::decodeSecureMagic($httpVars["destination"]);
                if (!isset($attachId)) {
                    AJXP_XMLWriter::sendMessage(null, "Wrong Parameters");
                    break;
                }

                require_once("Mail/mimeDecode.php");
                $params = array(
                    'include_bodies' => true,
                    'decode_bodies' => true,
                    'decode_headers' => false
                );
                if ($wrapperClassName == "imapAccessWrapper") {
                    $cache = AJXP_Cache::getItem("eml_remote", $file, null, array("EmlParser", "computeCacheId"));
                    $content = $cache->getData();
                } else {
                    $content = file_get_contents($file);
                }

                $decoder = new Mail_mimeDecode($content);
                $structure = $decoder->decode($params);
                $part = $this->_findAttachmentById($structure, $attachId);
                AJXP_XMLWriter::header();
                if ($part !== false) {
                    if (isSet($httpVars["dest_repository_id"])) {
                        $destRepoId = $httpVars["dest_repository_id"];
                        if (AuthService::usersEnabled()) {
                            $loggedUser = AuthService::getLoggedUser();
                            if(!$loggedUser->canWrite($destRepoId)) throw new Exception($mess[364]);
                        }
                        $destRepoObject = ConfService::getRepositoryById($destRepoId);
                        $destRepoAccess = $destRepoObject->getAccessType();
                        $plugin = AJXP_PluginsService::findPlugin("access", $destRepoAccess);
                        $destWrapperData = $plugin->detectStreamWrapper(true);
                        $destStreamURL = $destWrapperData["protocol"]."://$destRepoId";
                    }
                    $destFile = $destStreamURL.$destRep."/".$part->d_parameters['filename'];
                    $fp = fopen($destFile, "w");
                    if ($fp !== false) {
                        fwrite($fp, $part->body, strlen($part->body));
                        fclose($fp);
                        AJXP_XMLWriter::sendMessage(sprintf($mess["editor.eml.7"], $part->d_parameters["filename"], $destRep), NULL);
                    } else {
                        AJXP_XMLWriter::sendMessage(null, $mess["editor.eml.8"]);
                    }
                } else {
                    AJXP_XMLWriter::sendMessage(null, $mess["editor.eml.9"]);
                }
                AJXP_XMLWriter::close();
            break;

            default:
            break;
        }
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @param Boolean $isParent
     */
    public function extractMimeHeaders(&$ajxpNode, $isParent = false)
    {
        if($isParent) return;
        $currentNode = $ajxpNode->getUrl();
        $metadata = $ajxpNode->metadata;
        $wrapperClassName = $ajxpNode->wrapperClassName;

        $noMail = true;
        if ($metadata["is_file"] && ($wrapperClassName == "imapAccessWrapper" || preg_match("/\.eml$/i",$currentNode))) {
            $noMail = false;
        }
        if ($wrapperClassName == "imapAccessWrapper" && !$metadata["is_file"]) {
            $metadata["mimestring"] = "Mailbox";
        }
        $parsed = parse_url($currentNode);
        if ( $noMail || ( isSet($parsed["fragment"]) && strpos($parsed["fragment"], "attachments") === 0 ) ) {
            EmlParser::$currentListingOnlyEmails = FALSE;
            return;
        }
        if (EmlParser::$currentListingOnlyEmails === NULL) {
            EmlParser::$currentListingOnlyEmails = true;
        }
        if ($wrapperClassName == "imapAccessWrapper") {
            $cachedFile = AJXP_Cache::getItem("eml_remote", $currentNode, null, array("EmlParser", "computeCacheId"));
            $realFile = $cachedFile->getId();
            if (!is_file($realFile)) {
                $cachedFile->getData();// trigger loading!
            }
        } else {
            $realFile = $ajxpNode->getRealFile();
        }
        $cacheItem = AJXP_Cache::getItem("eml_mimes", $realFile, array($this, "mimeExtractorCallback"));
        $data = unserialize($cacheItem->getData());
        $data["ajxp_mime"] = "eml";
        $data["mimestring"] = "Email";
        $metadata = array_merge($metadata, $data);
        if ($wrapperClassName == "imapAccessWrapper" && $metadata["eml_attachments"]!= "0" && (strpos($_SERVER["HTTP_USER_AGENT"], "ajaxplorer-ios") !== false)) {
            $metadata["is_file"] = false;
            $metadata["nodeName"] = basename($currentNode)."#attachments";
        }
        $ajxpNode->metadata = $metadata;
    }

    public function mimeExtractorCallback($masterFile, $targetFile)
    {
        $metadata = array();
        require_once("Mail/mimeDecode.php");
        $params = array(
            'include_bodies' => true,
            'decode_bodies' => false,
            'decode_headers' => 'UTF-8'
        );
        $mess = ConfService::getMessages();
        $content = file_get_contents($masterFile);
        $decoder = new Mail_mimeDecode($content);
        $structure = $decoder->decode($params);
        $allowedHeaders = array("to", "from", "subject", "message-id", "mime-version", "date", "return-path");
        foreach ($structure->headers as $hKey => $hValue) {
            if(!in_array($hKey, $allowedHeaders)) continue;
            if (is_array($hValue)) {
                $hValue = implode(", ", $hValue);
            }
            if ($hKey == "date") {
                $date = strtotime($hValue);
                $metadata["eml_time"] = $date;
            }
            $metadata["eml_".$hKey] = AJXP_Utils::xmlEntities(@htmlentities($hValue, ENT_COMPAT, "UTF-8"));
            //$this->logDebug($hKey." - ".$hValue. " - ".$metadata["eml_".$hKey]);
            if ($metadata["eml_".$hKey] == "") {
                $metadata["eml_".$hKey] = AJXP_Utils::xmlEntities(@htmlentities($hValue));
                if (!SystemTextEncoding::isUtf8($metadata["eml_".$hKey])) {
                    $metadata["eml_".$hKey] = SystemTextEncoding::toUTF8($metadata["eml_".$hKey]);
                }
            }
            $metadata["eml_".$hKey] = str_replace("&amp;", "&", $metadata["eml_".$hKey]);
        }
        $metadata["eml_attachments"] = 0;
        $parts = $structure->parts;
        if (!empty($parts)) {
            foreach ($parts as $mimePart) {
                if (!empty($mimePart->disposition) && $mimePart->disposition == "attachment") {
                    $metadata["eml_attachments"]++;
                }
            }
        }
        $metadata["icon"] = "eml_images/ICON_SIZE/mail_mime.png";
        file_put_contents($targetFile, serialize($metadata));
    }

    public function lsPostProcess($action, $httpVars, $outputVars)
    {
        if (!EmlParser::$currentListingOnlyEmails) {
            if(isSet($httpVars["playlist"])) return;
            header('Content-Type: text/xml; charset=UTF-8');
            header('Cache-Control: no-cache');
            print($outputVars["ob_output"]);
            return;
        }

        $config = '<columns template_name="eml.list">
            <column messageId="editor.eml.1" attributeName="ajxp_label" sortType="String"/>
            <column messageId="editor.eml.2" attributeName="eml_to" sortType="String"/>
            <column messageId="editor.eml.3" attributeName="eml_subject" sortType="String"/>
            <column messageId="editor.eml.4" attributeName="ajxp_modiftime" sortType="MyDate"/>
            <column messageId="2" attributeName="filesize" sortType="NumberKo"/>
            <column messageId="editor.eml.5" attributeName="eml_attachments" sortType="Number" modifier="EmlViewer.prototype.attachmentCellRenderer" fixedWidth="30"/>
        </columns>';

        $dom = new DOMDocument("1.0", "UTF-8");
        $dom->loadXML($outputVars["ob_output"]);
        $mobileAgent = AJXP_Utils::userAgentIsIOS() || AJXP_Utils::userAgentIsNativePydioApp();
        $this->logDebug("MOBILE AGENT DETECTED?".$mobileAgent, $_SERVER["HTTP_USER_AGENT"]);
        if (EmlParser::$currentListingOnlyEmails === true) {
            // Replace all text attributes by the "from" value
            $index = 1;
            foreach ($dom->documentElement->childNodes as $child) {
                if ($mobileAgent) {
                    $from = $child->getAttribute("eml_from");
                    $ar = explode("&lt;", $from);
                    $from = trim(array_shift($ar));
                    $text = ($index < 10?"0":"").$index.". ".$from." &gt; ".$child->getAttribute("eml_subject");
                    if (AJXP_Utils::userAgentIsNativePydioApp()) {
                        $text = html_entity_decode($text, ENT_COMPAT, "UTF-8");
                    }
                    $index ++;
                } else {
                    $text = $child->getAttribute("eml_from");
                }
                $child->setAttribute("text", $text);
                $child->setAttribute("ajxp_modiftime", $child->getAttribute("eml_time"));
            }
        }

        // Add the columns template definition
        $insert = new DOMDocument("1.0", "UTF-8");
        $config = "<client_configs><component_config className=\"FilesList\" local=\"true\">$config</component_config></client_configs>";
        $insert->loadXML($config);
        $imported = $dom->importNode($insert->documentElement, true);
        $dom->documentElement->appendChild($imported);
        header('Content-Type: text/xml; charset=UTF-8');
        header('Cache-Control: no-cache');
        print($dom->saveXML());
    }

    /**
     *
     * Enter description here ...
     * @param string $file url
     * @param boolean $cacheRemoteContent
     * @return Mail_mimeDecode
     */
    public function getStructureDecoder($file, $cacheRemoteContent = false)
    {
        require_once ("Mail/mimeDecode.php");
        if ($cacheRemoteContent) {
            $cache = AJXP_Cache::getItem ( "eml_remote", $file , null, array("EmlParser", "computeCacheId"));
            $content = $cache->getData ();
        } else {
            $content = file_get_contents ( $file );
        }
        $decoder = new Mail_mimeDecode ( $content );

        header ( 'Content-Type: text/xml; charset=UTF-8' );
        header ( 'Cache-Control: no-cache' );
        return $decoder;
    }

    // $cacheRemoteContent = false
    public function listAttachments($file, $cacheRemoteContent, &$attachments,  $structure = null)
    {
        if ($structure == null) {
            $decoder = $this->getStructureDecoder($file, $cacheRemoteContent);
               $params = array(
                   'include_bodies' => false,
                   'decode_bodies' => false,
                   'decode_headers' => 'UTF-8'
               );
            $structure = $decoder->decode($params);
        }
        if (isSet($structure->disposition) && $structure->disposition == "attachment") {
            $attachments[] = array(
                "filename" => $structure->d_parameters['filename'],
                "content-type" => $structure->ctype_primary."/".$structure->ctype_secondary,
                "x-attachment-id" => (isSet($structure->headers["x-attachment-id"])?$structure->headers["x-attachment-id"]:count($attachments))
            );
        } else if (isset($structure->parts)) {
            foreach ($structure->parts as $partObject) {
                $this->listAttachments($file, true, $attachments, $partObject);
            }
        }
    }

    public function getAttachmentBody($file, $attachmentId, $cacheRemoteContent = false, &$metadata = array())
    {
        $decoder = $this->getStructureDecoder($file, $cacheRemoteContent);
           $params = array(
               'include_bodies' => true,
               'decode_bodies' => true,
               'decode_headers' => 'UTF-8'
           );
        $structure = $decoder->decode($params);
        $part = $this->_findAttachmentById($structure, $attachmentId);
        if($part == false) return false;
        $metadata = array(
            "filename" => $part->d_parameters['filename'],
            "content-type" => $part->ctype_primary."/".$part->ctype_secondary,
            "x-attachment-id" => $attachmentId
        );
        return $part->body;
    }

    protected function _findPartByCType($structure, $primary, $secondary)
    {
        if ($structure->ctype_primary == $primary && $structure->ctype_secondary == $secondary) {
            return $structure;
        }
        if(empty($structure->parts)) return false;
        foreach ($structure->parts as $part) {
            $res = $this->_findPartByCType($part, $primary, $secondary);
            if ($res !== false) {
                return $res;
            }
        }
        return false;
    }

    protected function _findAttachmentById($structure, $attachId)
    {
        if (is_numeric($attachId)) {
            $attachId = intval($attachId);
            if(empty($structure->parts)) return false;
            $index = 0;
            foreach ($structure->parts as $part) {
                if (!empty($part->disposition) &&  $part->disposition == "attachment") {
                    if($index == $attachId) return $part;
                    $index++;
                }
            }
            return false;
        } else {
            if(!empty($structure->disposition) &&  $structure->disposition == "attachment"
                && ($structure->headers["x-attachment-id"] == $attachId || $attachId == "0" )){
                return $structure;
            }
            if(empty($structure->parts)) return false;
            foreach ($structure->parts as $part) {
                $res = $this->_findAttachmentById($part, $attachId);
                if ($res !== false) {
                    return $res;
                }
            }
            return false;
        }
    }

    public static function computeCacheId($mailPath)
    {
        $header = file_get_contents($mailPath."#header");
        //$this->logDebug("Headers ", $header);
        return md5($header);
    }

}
