<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Editor\EML;

use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Services\LocalCache;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;

use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\Utils\Http\UserAgent;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Core\Utils\Vars\UrlUtils;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Parses an EML file and return the result as XML
 * @package Pydio\Editor\EML
 */
class EmlParser extends Plugin
{
    public static $currentListingOnlyEmails;

    public function performChecks()
    {
        if (!ApplicationState::searchIncludePath("Mail/mimeDecode.php")) {
            throw new \Exception("Cannot find Mail/mimeDecode PEAR library");
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws \Exception
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchAction(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $httpVars = $requestInterface->getParsedBody();
        $action = $requestInterface->getAttribute("action");
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");

        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($x);

        $selection = UserSelection::fromContext($ctx, $httpVars);
        if($selection->isEmpty()) return;
        $node = $selection->getUniqueNode();
        $file = $node->getUrl();
        Controller::applyHook("node.read", array($node));

        $wrapperIsImap = $this->wrapperIsImap($node);

        $mess = LocaleService::getMessages();
        switch ($action) {
            case "eml_get_xml_structure":
                $params = array(
                    'include_bodies' => false,
                    'decode_bodies' => false,
                    'decode_headers' => 'UTF-8'
                );
                $decoder = $this->getStructureDecoder($file, $wrapperIsImap);
                $xml = $decoder->getXML($decoder->decode($params));
                $doc = new \Pydio\Core\Http\Message\XMLDocMessage($xml);
                if (function_exists("imap_mime_header_decode")) {
                    $xPath = new \DOMXPath($doc);
                    $headers = $xPath->query("//headername");
                    $charset = "UTF-8";
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
                                $value = TextEncoder::changeCharset($charset, "UTF-8", $decoded);
                                $node = $doc->createElement("headervalue", $value);
                                $headerNode->parentNode->replaceChild($node, $headerValueNode);
                            }
                        }
                    }
                }
                $x->addChunk($doc);
            break;
            case "eml_get_bodies":
                require_once("Mail/mimeDecode.php");
                $params = array(
                    'include_bodies' => true,
                    'decode_bodies' => true,
                    'decode_headers' => false
                );
                if ($wrapperIsImap) {
                    $cache = LocalCache::getItem("eml_remote", $file, null, array($this, "computeCacheId"));
                    $content = $cache->getData();
                } else {
                    $content = file_get_contents($file);
                }

                $decoder = new \Mail_mimeDecode($content);
                $structure = $decoder->decode($params);
                $html = $this->_findPartByCType($structure, "text", "html");
                $text = $this->_findPartByCType($structure, "text", "plain");
                $charset = 'UTF-8';
                if ($html != false && isSet($html->ctype_parameters) && isSet($html->ctype_parameters["charset"])) {
                    $charset = $html->ctype_parameters["charset"];
                }
                require_once "EmlXmlMessage.php";
                $x->addChunk(new EmlXmlMessage($charset, $html, $text));

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
                if ($wrapperIsImap) {
                    $cache = LocalCache::getItem("eml_remote", $file, null, array($this, "computeCacheId"));
                    $content = $cache->getData();
                } else {
                    $content = file_get_contents($file);
                }
                $decoder = new \Mail_mimeDecode($content);
                $structure = $decoder->decode($params);
                $part = $this->_findAttachmentById($structure, $attachId);

                $fileReader = new \Pydio\Core\Http\Response\FileReaderResponse(null, $part->body);
                $fileReader->setLocalName($part->d_parameters['filename']);
                $responseInterface = $responseInterface->withBody($fileReader);

            break;

            case "eml_cp_attachment":

                $attachId = $httpVars["attachment_id"];
                $destRep = InputFilter::decodeSecureMagic($httpVars["destination"]);
                if (!isset($attachId)) {
                    throw new \Pydio\Core\Exception\PydioException("Wrong Parameters");
                }

                require_once("Mail/mimeDecode.php");
                $params = array(
                    'include_bodies' => true,
                    'decode_bodies' => true,
                    'decode_headers' => false
                );
                if ($wrapperIsImap) {
                    $cache = LocalCache::getItem("eml_remote", $file, null, array($this, "computeCacheId"));
                    $content = $cache->getData();
                } else {
                    $content = file_get_contents($file);
                }

                $decoder = new \Mail_mimeDecode($content);
                $structure = $decoder->decode($params);
                $part = $this->_findAttachmentById($structure, $attachId);
                if ($part !== false) {
                    $destStreamURL = $selection->currentBaseUrl();
                    if (isSet($httpVars["dest_repository_id"])) {
                        $destRepoId = $httpVars["dest_repository_id"];
                        if (UsersService::usersEnabled()) {
                            $loggedUser = $ctx->getUser();
                            if(!$loggedUser->canWrite($destRepoId)) throw new \Exception($mess[364]);
                        }
                        $user = $ctx->getUser()->getId();
                        $destStreamURL = "pydio://$user@$destRepoId";
                        MetaStreamWrapper::detectWrapperForNode(new AJXP_Node($destStreamURL), true);
                    }
                    $destFile = $destStreamURL.$destRep."/".$part->d_parameters['filename'];
                    $fp = fopen($destFile, "w");
                    if ($fp !== false) {
                        fwrite($fp, $part->body, strlen($part->body));
                        fclose($fp);
                        $x->addChunk(new UserMessage(sprintf($mess["editor.eml.7"], $part->d_parameters["filename"], $destRep)));
                    } else {
                        $x->addChunk(new UserMessage($mess["editor.eml.8"], LOG_LEVEL_ERROR));
                    }
                } else {
                    $x->addChunk(new UserMessage($mess["editor.eml.9"], LOG_LEVEL_ERROR));
                }
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
        $wrapperIsImap = $this->wrapperIsImap($ajxpNode);

        $noMail = true;
        if ($metadata["is_file"] && ($wrapperIsImap || preg_match("/\.eml$/i",$currentNode))) {
            $noMail = false;
        }
        if ($wrapperIsImap && !$metadata["is_file"]) {
            $metadata["mimestring"] = "Mailbox";
        }
        $parsed = UrlUtils::mbParseUrl($currentNode);
        if ( $noMail || ( isSet($parsed["fragment"]) && strpos($parsed["fragment"], "attachments") === 0 ) ) {
            EmlParser::$currentListingOnlyEmails = FALSE;
            return;
        }
        if (EmlParser::$currentListingOnlyEmails === NULL) {
            EmlParser::$currentListingOnlyEmails = true;
        }
        if ($wrapperIsImap) {
            $cachedFile = LocalCache::getItem("eml_remote", $currentNode, null, array($this, "computeCacheId"));
            $realFile = $cachedFile->getId();
            if (!is_file($realFile)) {
                $cachedFile->getData();// trigger loading!
            }
        } else {
            $realFile = $ajxpNode->getRealFile();
        }
        $cacheItem = LocalCache::getItem("eml_mimes", $realFile, array($this, "mimeExtractorCallback"));
        $data = unserialize($cacheItem->getData());
        $data["ajxp_mime"] = "eml";
        $data["mimestring"] = "Email";
        $metadata = array_merge($metadata, $data);
        if ($wrapperIsImap && $metadata["eml_attachments"]!= "0" && (strpos($_SERVER["HTTP_USER_AGENT"], "ajaxplorer-ios") !== false)) {
            $metadata["is_file"] = false;
            $metadata["nodeName"] = basename($currentNode)."#attachments";
        }
        $ajxpNode->metadata = $metadata;
    }

    /**
     * @param AJXP_Node $node
     * @return bool
     */
    protected function wrapperIsImap($node){
        $refClassName = "Pydio\\Access\\Driver\\StreamProvider\\Imap\\ImapAccessWrapper";
        $wrapperClassName = MetaStreamWrapper::actualRepositoryWrapperClass($node);
        return $wrapperClassName === $refClassName;
    }

    /**
     * @param $masterFile
     * @param $targetFile
     */
    public function mimeExtractorCallback($masterFile, $targetFile)
    {
        $metadata = array();
        require_once("Mail/mimeDecode.php");
        $params = array(
            'include_bodies' => true,
            'decode_bodies' => false,
            'decode_headers' => 'UTF-8'
        );
        $content = file_get_contents($masterFile);
        $decoder = new \Mail_mimeDecode($content);
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
            $metadata["eml_".$hKey] = StringHelper::xmlEntities(@htmlentities($hValue, ENT_COMPAT, "UTF-8"));
            //$this->logDebug($hKey." - ".$hValue. " - ".$metadata["eml_".$hKey]);
            if ($metadata["eml_".$hKey] == "") {
                $metadata["eml_".$hKey] = StringHelper::xmlEntities(@htmlentities($hValue));
                if (!TextEncoder::isUtf8($metadata["eml_".$hKey])) {
                    $metadata["eml_".$hKey] = TextEncoder::toUTF8($metadata["eml_".$hKey]);
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

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return \Psr\Http\Message\ResponseInterface|\Zend\Diactoros\Response|static
     */
    public function lsPostProcess(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        if (!EmlParser::$currentListingOnlyEmails) {
            return $responseInterface;
        }

        $config = '<columns template_name="eml.list">
            <column messageId="editor.eml.2" attributeName="ajxp_label" sortType="String"/>
            <column messageId="editor.eml.1" attributeName="eml_from" sortType="String"/>
            <column messageId="editor.eml.2" attributeName="eml_to" sortType="String"/>
            <column messageId="editor.eml.4" attributeName="ajxp_modiftime" sortType="MyDate"/>
            <column messageId="2" attributeName="filesize" sortType="NumberKo"/>
            <column messageId="editor.eml.5" attributeName="eml_attachments" sortType="Number" modifier="EmlViewer.prototype.attachmentCellRenderer" fixedWidth="30"/>
        </columns>';

        $responseData = $responseInterface->getBody()->getContents();

        $dom = new \DOMDocument("1.0", "UTF-8");
        $dom->loadXML($responseData);
        $mobileAgent = UserAgent::userAgentIsIOS() || UserAgent::userAgentIsNativePydioApp();
        $this->logDebug("MOBILE AGENT DETECTED?".$mobileAgent, $_SERVER["HTTP_USER_AGENT"]);
        if (EmlParser::$currentListingOnlyEmails === true) {
            // Replace all text attributes by the "from" value
            $index = 1;
            /** @var \DOMElement $child */
            foreach ($dom->documentElement->childNodes as $child) {
                if ($mobileAgent) {
                    $from = $child->getAttribute("eml_from");
                    $ar = explode("&lt;", $from);
                    $from = trim(array_shift($ar));
                    $text = ($index < 10?"0":"").$index.". ".$from." &gt; ".$child->getAttribute("eml_subject");
                    if (UserAgent::userAgentIsNativePydioApp()) {
                        $text = html_entity_decode($text, ENT_COMPAT, "UTF-8");
                    }
                    $index ++;
                } else {
                    $text = $child->getAttribute("eml_subject");
                }
                $child->setAttribute("text", $text);
                $child->setAttribute("ajxp_modiftime", $child->getAttribute("eml_time"));
            }
        }

        // Add the columns template definition
        $insert = new \DOMDocument("1.0", "UTF-8");
        $config = "<client_configs><component_config component=\"FilesList\" local=\"true\">$config</component_config></client_configs>";
        $insert->loadXML($config);
        $imported = $dom->importNode($insert->documentElement, true);
        $dom->documentElement->appendChild($imported);
        $responseInterface = new \Zend\Diactoros\Response();
        $responseInterface = $responseInterface->withHeader("Content-Type", "text/xml");
        $responseInterface = $responseInterface->withHeader("Cache-Control", "no-cache");
        $responseInterface->getBody()->write($dom->saveXML());
        return $responseInterface;
    }

    /**
     *
     * Enter description here ...
     * @param string $file url
     * @param boolean $cacheRemoteContent
     * @return \Mail_mimeDecode
     */
    public function getStructureDecoder($file, $cacheRemoteContent = false)
    {
        require_once ("Mail/mimeDecode.php");
        if ($cacheRemoteContent) {
            $cache = LocalCache::getItem ( "eml_remote", $file , null, array($this, "computeCacheId"));
            $content = $cache->getData ();
        } else {
            $content = file_get_contents ( $file );
        }
        $decoder = new \Mail_mimeDecode ( $content );

        header ( 'Content-Type: text/xml; charset=UTF-8' );
        header ( 'Cache-Control: no-cache' );
        return $decoder;
    }

    // $cacheRemoteContent = false
    /**
     * @param $file
     * @param $cacheRemoteContent
     * @param $attachments
     * @param null $structure
     */
    public function listAttachments($file, $cacheRemoteContent, &$attachments, $structure = null)
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

    /**
     * @param $file
     * @param $attachmentId
     * @param bool $cacheRemoteContent
     * @param array $metadata
     * @return bool
     */
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

    /**
     * @param $structure
     * @param $primary
     * @param $secondary
     * @return bool
     */
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

    /**
     * @param $structure
     * @param $attachId
     * @return bool
     */
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

    /**
     * @param $mailPath
     * @return string
     */
    public function computeCacheId($mailPath)
    {
        $header = file_get_contents($mailPath."#header");
        //$this->logDebug("Headers ", $header);
        return md5($header);
    }

}
