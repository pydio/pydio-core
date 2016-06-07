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
namespace Pydio\Access\Driver\StreamProvider\Imap;

use DOMNode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Driver\StreamProvider\FS\fsAccessDriver;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Utils\Utils;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to browse a mailbox content (IMAP OR POP)
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class imapAccessDriver extends fsAccessDriver
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

        $this->detectStreamWrapper(true);
        $uId = $contextInterface->hasUser() ? $contextInterface->getUser() : "shared";
        $this->urlBase = "pydio://".$uId."@".$contextInterface->getRepositoryId();

    }

    public function performChecks()
    {
        if (!function_exists("imap_createmailbox")) {
            throw new \Exception("PHP Imap extension must be loaded to use this driver!");
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
        $metadata = $ajxpNode->metadata;
        $parsed = parse_url($currentNode);
        if ( isSet($parsed["fragment"]) && strpos($parsed["fragment"], "attachments") === 0) {
            list(, $attachmentId) = explode("/", $parsed["fragment"]);
            $meta = imapAccessWrapper::getCurrentAttachmentsMetadata();
            if ($meta != null) {
                foreach ($meta as $attach) {
                    if ($attach["x-attachment-id"] == $attachmentId) {
                        $metadata["text"] = $attach["filename"];
                        $metadata["icon"] = Utils::mimetype($attach["filename"], "image", false);
                        $metadata["mimestring"] = Utils::mimetype($attach["filename"], "text", false);
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
     * @inheritdoc
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    public function filterNodeName(ContextInterface $contextInterface, $nodePath, $nodeName, &$isLeaf, $lsOptions)
    {
        return true;
    }

    public function countChildren($dirName, $foldersOnly = false, $nonEmptyCheckOnly = false, $dirHandle = null)
    {
        if($foldersOnly) return 0;
        $count = 0;
        if($tmpHandle = opendir($dirName)){
            // WILL USE IMAP FUNCTIONS TO COUNT;
            $this->logDebug("COUNT : ".imapAccessWrapper::getCurrentDirCount());
            $count = imapAccessWrapper::getCurrentDirCount();
            closedir($tmpHandle);
        }
        return $count;
    }

}
