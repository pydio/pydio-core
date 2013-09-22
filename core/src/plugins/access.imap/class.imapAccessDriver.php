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
 * AJXP_Plugin to browse a mailbox content (IMAP OR POP)
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class imapAccessDriver extends fsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    public function initRepository()
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        $wrapperData = $this->detectStreamWrapper(true);
        $this->wrapperClassName = $wrapperData["classname"];
        $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
        if ($this->repository->getOption("MAILBOX") != "") {
            //$this->urlBase .= "/INBOX";
        }
        /*
        if (!file_exists($this->urlBase)) {
            throw new AJXP_Exception("Cannot find base path for your repository! Please check the configuration!");
        }
        */
    }

    public function performChecks()
    {
        if (!function_exists("imap_createmailbox")) {
            throw new Exception("PHP Imap extension must be loaded to use this driver!");
        }
    }

    public static function inverseSort($st1, $st2)
    {
        return strnatcasecmp($st2, $st1);
    }

    public static function sortInboxFirst($st1, $st2)
    {
        if($st1 == "INBOX") return -1;
        if($st2 == "INBOX") return  1;
        return strcmp($st1, $st2);
    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if ($action == "ls") {
            $dir = $httpVars["dir"];
            if ($dir == "/" || empty($dir)) {
                // MAILBOXES CASE
                $this->repository->addOption("PAGINATION_THRESHOLD", 500);
                $this->driverConf["SCANDIR_RESULT_SORTFONC"] = array("imapAccessDriver", "sortInboxFirst");
            } else {
                // MAILS LISTING CASE
                //$httpVars["dir"] = mb_convert_encoding($httpVars["dir"], "UTF7-IMAP", SystemTextEncoding::getEncoding());
                $this->driverConf["SCANDIR_RESULT_SORTFONC"] = array("imapAccessDriver", "inverseSort");
            }
        }
        parent::switchAction($action, $httpVars, $fileVars);
    }

    /**
     *
     * @param AJXP_Node $ajxpNode
     */
    public function enrichMetadata(&$ajxpNode)//, &$metadata, $wrapperClassName, &$realFile)
    {
        $currentNode = $ajxpNode->getUrl();
        $metadata = $ajxpNode->metadata;
        $parsed = parse_url($currentNode);
        if ( isSet($parsed["fragment"]) && strpos($parsed["fragment"], "attachments") === 0) {
            list(, $attachmentId) = explode("/", $parsed["fragment"]);
            $meta = imapAccessWrapper::getCurrentAttachmentsMetadata();
            if ($meta != null) {
                foreach ($meta as $attach) {
                    if ($attach["x-attachment-id"] == $attachmentId) {
                        $metadata["text"] = $attach["filename"];
                        $metadata["icon"] = AJXP_Utils::mimetype($attach["filename"], "image", false);
                        $metadata["mimestring"] = AJXP_Utils::mimetype($attach["filename"], "text", false);
                    }
                }
            }
        }

        if (!$metadata["is_file"] && $currentNode != "") {
            $metadata["icon"] = "imap_images/ICON_SIZE/mail_folder_sent.png";
        }
        if (basename($currentNode) == "INBOX") {
            $metadata["text"] = "Incoming Mails";
        }
        if (strstr($currentNode, "__delim__")!==false) {
            $parts = explode("/", $currentNode);
            $metadata["text"] = str_replace("__delim__", "/", array_pop($parts));
        }
        $ajxpNode->metadata = $metadata;
    }

    public function attachmentDLName($currentNode, &$localName, $wrapperClassName)
    {
        $parsed = parse_url($currentNode);
        if ( isSet($parsed["fragment"]) && strpos($parsed["fragment"], "attachments") === 0) {
            list(, $attachmentId) = explode("/", $parsed["fragment"]);
            $meta = imapAccessWrapper::getCurrentAttachmentsMetadata();
            if ($meta == null) {
                stat($currentNode);
                $meta = imapAccessWrapper::getCurrentAttachmentsMetadata();
            }
            if ($meta != null) {
                foreach ($meta as $attach) {
                    if ($attach["x-attachment-id"] == $attachmentId) {
                        $localName = $attach["filename"];
                    }
                }
            }
        } else {
            $localName = basename($currentNode).".eml";
        }
    }

    /**
     * Parse
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    public function filterNodeName($nodePath, $nodeName, &$isLeaf, $lsOptions)
    {
        return true;
    }

    public function countFiles($dirName,  $foldersOnly = false, $nonEmptyCheckOnly = false)
    {
        if($foldersOnly) return 0;
        // WILL USE IMAP FUNCTIONS TO COUNT;
        $tmpHandle = opendir($dirName);
        $this->logDebug("COUNT : ".imapAccessWrapper::getCurrentDirCount());
        return imapAccessWrapper::getCurrentDirCount();
    }

}
