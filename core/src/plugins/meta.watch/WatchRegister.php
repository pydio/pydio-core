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
namespace Pydio\Access\Meta\Watch;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\UsersService;
use Pydio\Access\Meta\Core\AbstractMetaSource;
use Pydio\Access\Metastore\Core\IMetaStoreProvider;
use Pydio\Notification\Core\Notification;
use Pydio\Notification\Core\NotificationCenter;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Keep an eye on a folder to be alerted when something changes inside it
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class WatchRegister extends AbstractMetaSource
{
    public static $META_WATCH_CHANGE = "META_WATCH_CHANGE";
    public static $META_WATCH_READ = "META_WATCH_READ";
    public static $META_WATCH_BOTH = "META_WATCH_BOTH";
    public static $META_WATCH_NAMESPACE = "META_WATCH";

    public static $META_WATCH_USERS_READ = "META_WATCH_USERS_READ";
    public static $META_WATCH_USERS_CHANGE = "META_WATCH_USERS_CHANGE";
    public static $META_WATCH_USERS_NAMESPACE = "META_WATCH_USERS";

    /**
     * @var IMetaStoreProvider
     */
    protected $metaStore;

    /**
     * @var NotificationCenter
     */
    protected $notificationCenter;

    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     * @throws PydioException
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {
        parent::initMeta($ctx, $accessDriver);
        $this->notificationCenter = PluginsService::getInstance($ctx)->getPluginById("core.notifications");
        $store = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("metastore");
        if ($store === false) {
            throw new PydioException("The 'meta.watch' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->initMeta($ctx, $accessDriver);
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param string $userId
     * @param string $watchType
     * @param array $targetUsers Optional list of specific users to watch
     */
    public function setWatchOnFolder($node, $userId, $watchType, $targetUsers = array())
    {
        if ( ($watchType == self::$META_WATCH_USERS_READ || $watchType == self::$META_WATCH_USERS_CHANGE ) && count($targetUsers)) {
            $usersMeta = $this->metaStore->retrieveMetadata($node, self::$META_WATCH_USERS_NAMESPACE);
            if (is_array($usersMeta) && is_array($usersMeta[$watchType]) && is_array($usersMeta[$watchType][$userId])) {
                $usersMeta[$watchType][$userId] = array_merge($usersMeta[$watchType][$userId], $targetUsers);
            } else {
                if(!is_array($usersMeta)) $usersMeta = array();
                if(!is_array($usersMeta[$watchType])) $usersMeta[$watchType] = array();
                $usersMeta[$watchType][$userId] = $targetUsers;
            }
            $this->metaStore->setMetadata(
                $node,
                self::$META_WATCH_USERS_NAMESPACE,
                $usersMeta,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        } else {
            $meta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
            if (isSet($meta) && isSet($meta[$userId])) {
                unset($meta[$userId]);
                $this->metaStore->removeMetadata(
                    $node,
                    self::$META_WATCH_NAMESPACE,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
            }
            $meta[$userId] = $watchType;
            if (count($meta)) {
                $this->metaStore->setMetadata(
                    $node,
                    self::$META_WATCH_NAMESPACE,
                    $meta,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
            }
        }
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param $userId
     * @param bool $clearUsers
     * @param bool $targetUserId
     */
    public function removeWatchFromFolder($node, $userId, $clearUsers = false, $targetUserId = false)
    {
        if ($clearUsers) {
            $usersMeta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_USERS_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );

            // WEIRD / WILL IT REMOVE OTHER PEOPLE WATCHES??
            if (isSet($usersMeta) && (isSet($usersMeta[self::$META_WATCH_USERS_CHANGE][$userId]) || isSet($usersMeta[self::$META_WATCH_USERS_READ][$userId]))) {

                if ($targetUserId != false) {
                    if (isSet($usersMeta[self::$META_WATCH_USERS_CHANGE][$userId])) {
                        $c = $usersMeta[self::$META_WATCH_USERS_CHANGE][$userId];
                        if(in_array($targetUserId, $c)) $c = array_diff($c, array($targetUserId));
                        if(count($c)) $usersMeta[self::$META_WATCH_USERS_CHANGE][$userId] = $c;
                        else unset($usersMeta[self::$META_WATCH_USERS_CHANGE][$userId]);
                    }
                    if (isSet($usersMeta[self::$META_WATCH_USERS_READ][$userId])) {
                        $c = $usersMeta[self::$META_WATCH_USERS_READ][$userId];
                        if(in_array($targetUserId, $c)) $c = array_diff($c, array($targetUserId));
                        if(count($c)) $usersMeta[self::$META_WATCH_USERS_READ][$userId] = $c;
                        else unset($usersMeta[self::$META_WATCH_USERS_READ][$userId]);
                    }
                    $this->metaStore->setMetadata($node, self::$META_WATCH_USERS_NAMESPACE, $usersMeta, false, AJXP_METADATA_SCOPE_REPOSITORY);

                } else {

                    $this->metaStore->removeMetadata(
                        $node,
                        self::$META_WATCH_USERS_NAMESPACE,
                        false,
                        AJXP_METADATA_SCOPE_REPOSITORY);

                }

            }
        } else {

            $meta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
            if (isSet($meta) && isSet($meta[$userId])) {
                $this->metaStore->removeMetadata(
                    $node,
                    self::$META_WATCH_NAMESPACE,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
            }

        }

    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param $userId
     * @param string $ns Watch namespace
     * @param array $result
     * @return string|bool the type of watch
     */
    public function hasWatchOnNode($node, $userId, $ns = "META_WATCH", &$result = array())
    {
        $meta = $this->metaStore->retrieveMetadata(
            $node,
            $ns,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        if ($ns == self::$META_WATCH_USERS_NAMESPACE) {
            if (isSet($meta[self::$META_WATCH_USERS_READ]) && isSet($meta[self::$META_WATCH_USERS_READ][$userId])) {
                $result = $meta[self::$META_WATCH_USERS_READ][$userId];
                return self::$META_WATCH_USERS_READ;
            }
            if (isSet($meta[self::$META_WATCH_USERS_CHANGE]) && isSet($meta[self::$META_WATCH_USERS_CHANGE][$userId])) {
                $result = $meta[self::$META_WATCH_USERS_CHANGE][$userId];
                return self::$META_WATCH_USERS_CHANGE;
            }
            return false;
        } else if (isSet($meta) && isSet($meta[$userId])) {
            return $meta[$userId];
        } else {
            return false;
        }

    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param String $watchType
     * @return array
     */
    public function collectWatches($node, $watchType)
    {
        $currentUserId = "shared";
        $ctx = $node->getContext();
        if ($ctx->hasUser()) {
            $currentUserId = $ctx->getUser()->getId();
        }
        $result = array();
        $result["node"] = $this->getWatchesOnNode($node, $watchType, $currentUserId);

        $ancestors = array();
        $node->collectMetadatasInParents(
            array(self::$META_WATCH_NAMESPACE,self::$META_WATCH_USERS_NAMESPACE),
            false,
            AJXP_METADATA_SCOPE_REPOSITORY,
            false,
            $ancestors
        );

        $result["ancestors"] = array();
        foreach($ancestors as $nodeMeta){
            $source = $nodeMeta["SOURCE_NODE"];
            $watchMeta = isSet($nodeMeta[self::$META_WATCH_NAMESPACE]) ? $nodeMeta[self::$META_WATCH_NAMESPACE] : false;
            $usersMeta = isSet($nodeMeta[self::$META_WATCH_USERS_NAMESPACE]) ? $nodeMeta[self::$META_WATCH_USERS_NAMESPACE] : false;
            $ids = $this->loadWatchesFromMeta($watchType, $currentUserId, $source, $watchMeta, $usersMeta);
            foreach($ids as $id){
                // Do not send notification to myself!
                if($id !== $currentUserId){
                    $result["ancestors"][] = array("node" => $source, "id" => $id);
                }
            }
        }

        return $result;
    }
    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param String $watchType
     * @param String $userId
     * @return array
     */
    public function getWatchesOnNode($node, $watchType, $userId = null)
    {
        $ctx = $node->getContext();
        if($userId == null){
            $currentUserId = "shared";
            if ($ctx->hasUser()) {
                $currentUserId = $ctx->getUser()->getId();
            }
        }else{
            $currentUserId = $userId;
        }
        $meta = $node->retrieveMetadata(
            self::$META_WATCH_NAMESPACE,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        $usersMeta = false;
        if ($currentUserId != 'shared') {
            $usersMeta = $node->retrieveMetadata(
                self::$META_WATCH_USERS_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }
        return $this->loadWatchesFromMeta($watchType, $currentUserId, $node, $meta, $usersMeta);
    }

    /**
     * @param String $watchType
     * @param String $currentUserId
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param array|bool $watchMeta
     * @param array|bool $usersMeta
     * @return array
     */
    private function loadWatchesFromMeta($watchType, $currentUserId, $node, $watchMeta = false, $usersMeta = false){
        $IDS = array();
        if($usersMeta !== false){
            if ($watchType == self::$META_WATCH_CHANGE && isSet($usersMeta[self::$META_WATCH_USERS_CHANGE])) {
                $usersMeta = $usersMeta[self::$META_WATCH_USERS_CHANGE];
            } else if ($watchType == self::$META_WATCH_READ && isSet($usersMeta[self::$META_WATCH_USERS_READ])) {
                $usersMeta = $usersMeta[self::$META_WATCH_USERS_READ];
            } else {
                $usersMeta = null;
            }
        }

        if (!empty($watchMeta) && is_array($watchMeta)) {
            foreach ($watchMeta as $id => $type) {
                if ($type == $watchType || $type == self::$META_WATCH_BOTH) {
                    $IDS[] = $id;
                }
            }
        }
        if (!empty($usersMeta) && is_array($usersMeta)) {
            foreach ($usersMeta as $id => $targetUsers) {
                if (in_array($currentUserId, $targetUsers)) {
                    $IDS[] = $id;
                }
            }
        }
        if (count($IDS)) {
            $changes = false;
            foreach ($IDS as $index => $id) {
                if ($currentUserId == $id && !AJXP_SERVER_DEBUG) {
                    // In non-debug mode, do not send notifications to watcher!
                    unset($IDS[$index]);
                    continue;
                }
                if (!UsersService::userExists($id)) {
                    unset($IDS[$index]);
                    if(is_array($watchMeta)){
                        $changes = true;
                        $watchMeta[$id] = AJXP_VALUE_CLEAR;
                    }
                }else{
                    // Make sure the user is still authorized on this node, otherwise remove it.
                    $clear = false;
                    $uObject = UsersService::getUserById($id, false);
                    $acl = $uObject->getMergedRole()->getAcl($node->getRepositoryId());
                    $isOwner = ($node->getRepository()->getOwner() === $uObject->getId());
                    if((empty($acl) || strpos($acl, "r") === FALSE) && !$isOwner){
                        // Maybe another user has registered a watch because link is "publicly" visible, check parent acl
                        $isPublic = $node->getRepository()->getSafeOption("SHARE_ACCESS") === 'public';
                        if($isPublic){
                            $acl = $uObject->getMergedRole()->getAcl($node->getRepository()->getParentId());
                            if(empty($acl) || strpos($acl, "r") === FALSE){
                                $clear = true;
                            }
                        }else{
                            $clear = true;
                        }
                    }
                    if($clear){
                        unset($IDS[$index]);
                        if(is_array($watchMeta)){
                            $changes = true;
                            $watchMeta[$id] = AJXP_VALUE_CLEAR;
                        }
                    }
                }
            }
            if ($changes) {
                $node->setMetadata(
                    self::$META_WATCH_NAMESPACE,
                    $watchMeta,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
            }
        }
        return $IDS;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     */
    public function switchActions(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $actionName = $requestInterface->getAttribute("action");
        $httpVars = $requestInterface->getParsedBody();
        if($actionName !== "toggle_watch") return;
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");

        $us = UserSelection::fromContext($ctx, $httpVars);
        $node = $us->getUniqueNode();
        $node->loadNodeInfo();
        $cmd = $httpVars["watch_action"];

        $meta = $this->metaStore->retrieveMetadata(
            $node,
            self::$META_WATCH_NAMESPACE,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        $userId = $ctx->hasUser() ? $ctx->getUser()->getId() : "shared";

        if($node->isRoot() && $node->getRepository()->hasParent() && $cmd == "watch_stop" && !(isSet($meta) && isSet($meta[$userId]))){
            $usersMeta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_USERS_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY);
            if(is_array($usersMeta) && array_key_exists(self::$META_WATCH_USERS_CHANGE, $usersMeta) && array_key_exists($userId,$usersMeta[self::$META_WATCH_USERS_CHANGE])) {
                unset($usersMeta[self::$META_WATCH_USERS_CHANGE][$userId]);
                $this->metaStore->setMetadata(
                    $node,
                    self::$META_WATCH_USERS_NAMESPACE,
                    $usersMeta,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
            }
        } else if ($cmd == "watch_stop" && isSet($meta) && isSet($meta[$userId])) {
            unset($meta[$userId]);
            $this->metaStore->removeMetadata(
                $node,
                self::$META_WATCH_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        } else {
            switch ($cmd) {
                case "watch_change": $type = self::$META_WATCH_CHANGE;break;
                case "watch_read": $type = self::$META_WATCH_READ; break;
                case "watch_both": $type = self::$META_WATCH_BOTH; break;
                default: $type = self::$META_WATCH_BOTH; break;
            }
            $meta[$userId] = $type;
            $this->metaStore->setMetadata(
                $node,
                self::$META_WATCH_NAMESPACE,
                $meta,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }

        $node->metadata = array();
        $node->loadNodeInfo(true, false, "all");
        $this->enrichNode($node);
        $nodesDiff = new \Pydio\Access\Core\Model\NodesDiff();
        $nodesDiff->update($node);
        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $x->addChunk($nodesDiff);
        $responseInterface = $responseInterface->withBody($x);

    }


    /**
     * @param AJXP_Node|null $oldNode
     * @param AJXP_Node|null $newNode
     * @param bool $copy
     */
    public function processChangeHook(AJXP_Node $oldNode=null, AJXP_Node $newNode=null, $copy = false)
    {
        $newNotif = $this->notificationCenter->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "new");
        if ($newNotif !== false && $newNotif->getNode() !== false) {
            $this->processActiveHook($newNotif, self::$META_WATCH_CHANGE, AJXP_NOTIF_NODE_CHANGE);
        }
        if($oldNode != null && $newNode != null && $oldNode->getUrl() == $newNode->getUrl()) return;
        $oldNotif =  $this->notificationCenter->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "old");
        if ($oldNotif !== false && $oldNotif->getNode() !== false) {
            $this->processActiveHook($oldNotif, self::$META_WATCH_CHANGE, AJXP_NOTIF_NODE_CHANGE);
        }

        $this->updateMetaLocation($oldNode, $newNode, $copy);

    }

    /**
     * @param AJXP_Node $node
     */
    public function processReadHook(AJXP_Node $node)
    {
        $notification = new Notification();
        $notification->setAction(AJXP_NOTIF_NODE_VIEW);
        $notification->setNode($node);
        $this->processActiveHook($notification, self::$META_WATCH_READ);

    }

    /**
     * @param Notification $notification
     * @param $namespace
     * @param null $parentActionType
     */
    private function processActiveHook(Notification $notification, $namespace, $parentActionType = null){
        $origNode = $notification->getNode();
        $node = new AJXP_Node($origNode->getUrl());
        $all = $this->collectWatches($node, $namespace);
        if($node->isRoot() && $node->getRepository() !== null && $node->getRepository()->hasContentFilter()){
            $node->setLeaf(true);
        }else{
            $node->setLeaf(false);
        }
        foreach($all["node"] as $id) {
            $this->notificationCenter->postNotification($notification, $id);
        }
        foreach($all["ancestors"] as $pair){
            $parentNotification = new Notification();
            /**
             * @var \Pydio\Access\Core\Model\AJXP_Node $parentNode
             */
            $parentNode = $pair["node"];
            if($parentNode->isRoot() && $parentNode->getRepository() !== null && $parentNode->getRepository()->hasContentFilter()){
                $parentNode->setLeaf(true);
            }else{
                $parentNode->setLeaf(false);
            }
            $parentNotification->setNode($parentNode);
            if($parentActionType == null){
                $parentNotification->setAction($notification->getAction());
            }else{
                $parentNotification->setAction($parentActionType);
            }
            $this->notificationCenter->prepareNotification($notification);
            $parentNotification->addRelatedNotification($notification);
            $this->notificationCenter->postNotification($parentNotification, $pair["id"]);
        }
    }

    /**
     * @param AJXP_Node $node
     */
    public function enrichNode(&$node)
    {
        if(!$node->getContext()->hasUser()) {
            return;
        }
        $meta = $this->metaStore->retrieveMetadata(
            $node,
            self::$META_WATCH_NAMESPACE,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY);
        $userId = $node->getContext()->getUser()->getId();
        if(is_array($meta) && array_key_exists($userId, $meta)){
            $node->mergeMetadata(array(
                "meta_watched" => $meta[$userId],
                "overlay_icon" => "meta.watch/ICON_SIZE/watch.png",
                "overlay_class" => "icon-eye-open"
            ), true);
        }else if($node->isRoot() && $node->getRepository()->hasParent()){
            $usersMeta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_USERS_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY);
            if(is_array($usersMeta) && array_key_exists(self::$META_WATCH_USERS_CHANGE, $usersMeta) && array_key_exists($userId,$usersMeta[self::$META_WATCH_USERS_CHANGE])) {
                $node->mergeMetadata(array(
                    "meta_watched" => self::$META_WATCH_CHANGE,
                    "overlay_icon" => "meta.watch/ICON_SIZE/watch.png",
                    "overlay_class" => "icon-eye-open"
                ), true);
            }
        }
    }

    /**
     *
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldFile
     * @param \Pydio\Access\Core\Model\AJXP_Node $newFile
     * @param Boolean $copy
     */
    public function updateMetaLocation($oldFile, $newFile = null, $copy = false)
    {
        if($oldFile == null) return;
        if(!$copy && $this->metaStore->inherentMetaMove()) return;

        $oldMeta = $this->metaStore->retrieveMetadata($oldFile, self::$META_WATCH_NAMESPACE, false, AJXP_METADATA_SCOPE_REPOSITORY);
        if (count($oldMeta)) {
            // If it's a move or a delete, delete old data
            if (!$copy) {
                $this->metaStore->removeMetadata($oldFile, self::$META_WATCH_NAMESPACE, false, AJXP_METADATA_SCOPE_REPOSITORY);
            }
            // If copy or move, copy data.
            if ($newFile != null) {
                $this->metaStore->setMetadata($newFile, self::$META_WATCH_NAMESPACE, $oldMeta, false, AJXP_METADATA_SCOPE_REPOSITORY);
            }
        }

    }

}
