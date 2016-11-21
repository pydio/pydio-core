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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Access\Driver\StreamProvider\Imap;

use DOMNode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Core\Utils\Vars\UrlUtils;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to browse a mailbox content (IMAP OR POP)
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class ImapAccessDriver extends FsAccessDriver
{
    /**
    * @var \Pydio\Access\Core\Model\Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        $this->urlBase = $contextInterface->getUrlBase();

    }

    public function performChecks()
    {
        if (!function_exists("imap_createmailbox")) {
            throw new \Exception("PHP Imap extension must be loaded to use this driver!");
        }
    }

    /**
     * @param $st1
     * @param $st2
     * @return int
     */
    public static function inverseSort($st1, $st2)
    {
        return strnatcasecmp($st2, $st1);
    }

    /**
     * @param $st1
     * @param $st2
     * @return int
     */
    public static function sortInboxFirst($st1, $st2)
    {
        if($st1 == "INBOX") return -1;
        if($st2 == "INBOX") return  1;
        return strcmp($st1, $st2);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws PydioException
     * @throws \Exception
     * @throws \Pydio\Access\Core\Exception\FileNotWriteableException
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response)
    {
        if ($request->getAttribute("action") ==  "ls") {
            $dir = $request->getParsedBody()["dir"];
            if ($dir == "/" || empty($dir)) {
                // MAILBOXES CASE
                $this->repository->addOption("PAGINATION_THRESHOLD", 500);
                $this->driverConf["SCANDIR_RESULT_SORTFONC"] = array("Pydio\\Access\\Driver\\StreamProvider\\Imap\\imapAccessDriver", "sortInboxFirst");
            } else {
                // MAILS LISTING CASE
                $this->driverConf["SCANDIR_RESULT_SORTFONC"] = array("Pydio\\Access\\Driver\\StreamProvider\\Imap\\imapAccessDriver", "inverseSort");
            }
        }
        parent::switchAction($request, $response);
    }

    /**
     *
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     */
    public function enrichMetadata(&$ajxpNode)//, &$metadata, $wrapperClassName, &$realFile)
    {
        $currentNode = $ajxpNode->getUrl();
        $baseUrl = $ajxpNode->getContext()->getUrlBase();
        $metadata = $ajxpNode->metadata;
        $parsed = UrlUtils::mbParseUrl($currentNode);
        if ( isSet($parsed["fragment"]) && strpos($parsed["fragment"], "attachments") === 0) {
            list(, $attachmentId) = explode("/", $parsed["fragment"]);
            $meta = ImapAccessWrapper::getCurrentAttachmentsMetadata();
            if ($meta != null) {
                foreach ($meta as $attach) {
                    if ($attach["x-attachment-id"] == $attachmentId) {
                        $metadata["text"] = $attach["filename"];
                        $fakeNode = new AJXP_Node($baseUrl."/".ltrim($attach["filename"], "/"));
                        $mimeData = StatHelper::getMimeInfo($fakeNode, false);
                        $metadata["mimestring_id"] = $mimeData[0];
                        $metadata["icon"] = $mimeData[1];
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

    /**
     * @param AJXP_Node $currentNode
     * @param string $localName
     * @param string $wrapperClassName
     */
    public function attachmentDLName($currentNode, &$localName, $wrapperClassName)
    {
        $parsed = UrlUtils::mbParseUrl($currentNode->getUrl());
        if ( isSet($parsed["fragment"]) && strpos($parsed["fragment"], "attachments") === 0) {
            list(, $attachmentId) = explode("/", $parsed["fragment"]);
            $meta = ImapAccessWrapper::getCurrentAttachmentsMetadata();
            if ($meta == null) {
                stat($currentNode);
                $meta = ImapAccessWrapper::getCurrentAttachmentsMetadata();
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
     * @inheritdoc
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    /**
     * @param ContextInterface $contextInterface
     * @param $nodePath
     * @param $nodeName
     * @param $isLeaf
     * @param $lsOptions
     * @return bool
     */
    public function filterNodeName(ContextInterface $contextInterface, $nodePath, $nodeName, &$isLeaf, $lsOptions)
    {
        return true;
    }

    /**
     * @param AJXP_Node $dirNode
     * @param bool $foldersOnly
     * @param bool $nonEmptyCheckOnly
     * @param null $dirHANDLE
     * @return int
     * @throws \Exception
     */
    public function countChildren(AJXP_Node $dirNode, $foldersOnly = false, $nonEmptyCheckOnly = false, $dirHANDLE = null)
    {
        if($foldersOnly) return 0;
        $count = 0;
        if($dirHANDLE !== null){
            $tmpHandle = $dirHANDLE;
        }else{
            $tmpHandle = opendir($dirNode->getUrl());
        }
        if($tmpHandle !== false){
            // WILL USE IMAP FUNCTIONS TO COUNT;
            $this->logDebug("COUNT : ".ImapAccessWrapper::getCurrentDirCount());
            $count = ImapAccessWrapper::getCurrentDirCount();
            //closedir($tmpHandle);
        }
        return $count;
    }

}
