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
namespace Pydio\Access\Meta\UserGenerated;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;

use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Access\Meta\Core\AbstractMetaSource;
use Pydio\Access\Metastore\Core\IMetaStoreProvider;
use Pydio\Core\Utils\XMLHelper;
use Pydio\Notification\Core\IFeedStore;
use Pydio\Core\Controller\Controller;

define("AJXP_META_SPACE_COMMENTS", "AJXP_META_SPACE_COMMENTS");

/**
 * Class CommentsMetaManager
 * Manage use defined comments
 * @package Pydio\Meta\Comment
 */
class CommentsMetaManager extends AbstractMetaSource
{
    /**
     * @var IMetaStoreProvider
     */
    private $metaStore;
    /**
     * @var IFeedStore
     */
    private $feedStore;

    private $storageMode;

    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     * @throws \Exception
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {
        parent::initMeta($ctx, $accessDriver);
        $pService = PluginsService::getInstance($ctx);
        $feed = $pService->getUniqueActivePluginForType("feed");
        if ($feed) {
            $this->storageMode = "FEED";
            $this->feedStore = $feed;
        } else {
            $store = $pService->getUniqueActivePluginForType("metastore");
            if ($store === false) {
                throw new \Exception("The 'meta.comments' plugin requires at least one active 'metastore' plugin");
            }
            $this->metaStore = $store;
            $this->storageMode = "METASTORE";
        }
    }
    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     */
    public function mergeMeta($ajxpNode)
    {
        $ajxpNode->mergeMetadata(array("ajxp_has_comments_feed" => "true"));
        if ($ajxpNode->retrieveMetadata(AJXP_META_SPACE_COMMENTS, false) != null) {
        }

    }

    /**
     *
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldFile
     * @param \Pydio\Access\Core\Model\AJXP_Node $newFile
     * @param Boolean $copy
     */
    public function moveMeta($oldFile, $newFile = null, $copy = false)
    {
        if($oldFile == null) return;
        $feedStore = PluginsService::getInstance($oldFile->getContext())->getUniqueActivePluginForType("feed");
        if ($feedStore !== false) {
            $feedStore->updateMetaObject($oldFile->getRepositoryId(), $oldFile->getPath(), ($newFile!=null?$newFile->getPath():null), $copy);
            return;
        }

        if(!$copy && $this->metaStore->inherentMetaMove()) return;

        $oldMeta = $this->metaStore->retrieveMetadata($oldFile, AJXP_META_SPACE_COMMENTS);
        if (!count($oldMeta)) {
            return;
        }
        // If it's a move or a delete, delete old data
        if (!$copy) {
            $this->metaStore->removeMetadata($oldFile, AJXP_META_SPACE_COMMENTS);
        }
        // If copy or move, copy data.
        if ($newFile != null) {
            $this->metaStore->setMetadata($newFile, AJXP_META_SPACE_COMMENTS, $oldMeta);
        }
    }


    /**
     * @param String $actionName
     * @param array $httpVars
     * @param array $fileVars
     * @param ContextInterface $ctx
     */
    public function switchActions($actionName, $httpVars, $fileVars, ContextInterface $ctx)
    {
        $userSelection = UserSelection::fromContext($ctx, $httpVars);
        $uniqNode = $userSelection->getUniqueNode();
        /** @var IFeedStore $feedStore */
        $feedStore = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("feed");
        $existingFeed = $uniqNode->retrieveMetadata(AJXP_META_SPACE_COMMENTS, false);
        if ($existingFeed == null) {
            $existingFeed = array();
        }
        $mess = LocaleService::getMessages();
        switch ($actionName) {

            case "post_comment":

                $uId = $ctx->getUser()->getId();
                $limit = $this->getContextualOption($ctx, "COMMENT_SIZE_LIMIT");
                if (!empty($limit)) {
                    $content = substr(InputFilter::decodeSecureMagic($httpVars["content"], InputFilter::SANITIZE_HTML), 0, $limit);
                } else {
                    $content = InputFilter::decodeSecureMagic($httpVars["content"], InputFilter::SANITIZE_HTML);
                }
                $com = array(
                    "date"      => time(),
                    "author"    => $uId,
                    "content"   => $content
                );
                $existingFeed[] = $com;
                if ($feedStore !== false) {
                    $com['uuid'] = $feedStore->persistMetaObject(
                        $uniqNode->getPath(),
                        base64_encode($content),
                        $uniqNode->getRepositoryId(),
                        $uniqNode->getRepository()->securityScope(),
                        $uniqNode->getRepository()->getOwner(),
                        $ctx->getUser()->getId(),
                        $ctx->getUser()->getGroupPath());

                } else {
                    $uniqNode->removeMetadata(AJXP_META_SPACE_COMMENTS, false);
                    $uniqNode->setMetadata(AJXP_META_SPACE_COMMENTS, $existingFeed, false);
                }
                HTMLWriter::charsetHeader("application/json");
                $com["hdate"] = StatHelper::relativeDate($com["date"], $mess);
                $com["path"] = $uniqNode->getPath();
                echo json_encode($com);
                $imMessage = '<![CDATA[' . json_encode($com) . ']]>';
                $imMessage = XMLHelper::toXmlElement("metacomments", ["event" => "newcomment", "path" => $uniqNode->getPath()], $imMessage);
                Controller::applyHook("msg.instant", array($uniqNode->getContext(), $imMessage));

                break;

            case "load_comments_feed":

                SessionService::close();
                HTMLWriter::charsetHeader("application/json");
                if ($feedStore !== false) {
                    $sortBy = isSet($httpVars["sort_by"])? InputFilter::decodeSecureMagic($httpVars["sort_by"]) :"date";
                    $sortDir = isSet($httpVars["sort_dir"])? InputFilter::decodeSecureMagic($httpVars["sort_dir"]) :"asc";
                    $offset = isSet($httpVars["offset"]) ? intval($httpVars["offset"]) : 0;
                    $limit = isSet($httpVars["limit"]) ? intval($httpVars["limit"]) : 100;
                    $uniqNode->loadNodeInfo();
                    $data = $feedStore->findMetaObjectsByIndexPath(
                        $ctx->getRepositoryId(),
                        $uniqNode->getPath(),
                        $ctx->getUser()->getId(),
                        $ctx->getUser()->getGroupPath(),
                        $offset,
                        $limit,
                        $sortBy,
                        $sortDir,
                        !$uniqNode->isLeaf()
                    );
                    $theFeed = array();
                    foreach ($data as $stdObject) {
                        $rPath = substr($stdObject->path, strlen($uniqNode->getPath()));
                        if($rPath == false && $stdObject->path == $uniqNode->getPath()) $rPath = "";
                        $rPath = ltrim($rPath, "/");
                        $newItem = array(
                            "date"      =>$stdObject->date,
                            "hdate"     => StatHelper::relativeDate($stdObject->date, $mess),
                            "author"    => $stdObject->author,
                            "content"   => base64_decode($stdObject->content),
                            "path"      => $stdObject->path,
                            "rpath"     => $rPath,
                            "uuid"      => $stdObject->uuid
                        );
                        if (isSet($previous) && $previous["author"] == $newItem["author"] &&  $previous["path"] == $newItem["path"] && $previous["hdate"] == $newItem["hdate"] ) {
                            $theFeed[count($theFeed) - 1]["content"].= '<br>'.$newItem["content"];

                        } else {
                            $theFeed[] = $newItem;
                        }
                        $previous = $newItem;
                    }
                    echo json_encode($theFeed);
                } else {
                    foreach ($existingFeed as &$item) {
                        $item["hdate"] = StatHelper::relativeDate($item["date"], $mess);
                    }
                    echo json_encode($existingFeed);
                }

                break;

            case "delete_comment":

                $data = json_decode($httpVars["comment_data"], true);
                if ($feedStore === false) {
                    $reFeed = array();
                    if($data["author"] != $ctx->getUser()->getId()) break;
                    foreach ($existingFeed as $fElement) {
                        if ($fElement["date"] == $data["date"] && $fElement["author"] == $data["author"] && $fElement["content"] == $data["content"]) {
                            continue;
                        }
                        $fElement["hdate"] = StatHelper::relativeDate($fElement["date"], $mess);
                        $reFeed[] = $fElement;
                    }
                    $uniqNode->removeMetadata(AJXP_META_SPACE_COMMENTS, false);
                    $uniqNode->setMetadata(AJXP_META_SPACE_COMMENTS, $reFeed, false);
                    HTMLWriter::charsetHeader("application/json");
                    echo json_encode($reFeed);
                } else {
                    $feedStore->dismissMetaObjectById($ctx, $data["uuid"]);
                }
                $imMessage = XMLHelper::toXmlElement("metacomments", ["event" => "deletecomment", "path" => $uniqNode->getPath()]);
                Controller::applyHook("msg.instant", array($uniqNode->getContext(), $imMessage));

                break;

            default:
                break;
        }

    }

}
