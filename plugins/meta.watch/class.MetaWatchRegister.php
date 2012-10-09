<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

class MetaWatchRegister extends AJXP_Plugin{

    public static $META_WATCH_CHANGE = "META_WATCH_CHANGE";
    public static $META_WATCH_READ = "META_WATCH_READ";
    public static $META_WATCH_BOTH = "META_WATCH_BOTH";
    public static $META_WATCH_NAMESPACE = "META_WATCH";

    public static $META_WATCH_USERS = "META_WATCH_USERS";
    public static $META_WATCH_USERS_NAMESPACE = "META_WATCH_USERS";

    /**
     * @var MetaStoreProvider
     */
    protected $metaStore;

    /**
     * @var AbstractAccessDriver
     */
    protected $accessDriver;

    /**
     * @var AJXP_NotificationCenter
     */
    protected $notificationCenter;

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
        $this->notificationCenter = AJXP_PluginsService::findPluginById("core.notifications");
        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if($store === false){
            throw new Exception("The 'meta.watch' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->initMeta($accessDriver);
    }

    /**
     * @param AJXP_Node $node
     * @param string $userId
     * @param string $watchType
     * @param array $targetUsers Optional list of specific users to watch
     */
    public function setWatchOnFolder($node, $userId, $watchType, $targetUsers = array()){

        if($watchType == self::$META_WATCH_USERS && count($targetUsers)){
            $usersMeta = $this->metaStore->retrieveMetadata($node, self::$META_WATCH_USERS_NAMESPACE);
            if(is_array($usersMeta) && is_array($usersMeta[$userId])){
                $usersMeta[$userId] = array_merge($usersMeta[$userId], $targetUsers);
            }else{
                if(!is_array($usersMeta)) $usersMeta = array();
                $usersMeta[$userId] = $targetUsers;
            }
            $this->metaStore->setMetadata(
                $node,
                self::$META_WATCH_USERS_NAMESPACE,
                $usersMeta,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }else{
            $meta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
            if(isSet($meta) && isSet($meta[$userId])){
                unset($meta[$userId]);
                $this->metaStore->removeMetadata(
                    $node,
                    self::$META_WATCH_NAMESPACE,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
            }
            $meta[$userId] = $watchType;
            if(count($meta)){
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
     * @param AJXP_Node $node
     * @param $userId
     * @param bool $clearUsers
     */
    public function removeWatchFromFolder($node, $userId, $clearUsers = false){

        if($clearUsers){
            $usersMeta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_USERS_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
            if(isSet($usersMeta) && isSet($usersMeta[$userId])){
                $this->metaStore->removeMetadata(
                    $node,
                    self::$META_WATCH_USERS_NAMESPACE,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY);
            }
        }else{

            $meta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
            if(isSet($meta) && isSet($meta[$userId])){
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
     * @param AJXP_Node $node
     * @param $userId
     * @param string $ns Watch namespace
     * @return string|bool the type of watch
     */
    public function hasWatchOnNode($node, $userId, $ns = "META_WATCH"){

        $meta = $this->metaStore->retrieveMetadata(
            $node,
            $ns,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        if(isSet($meta) && isSet($meta[$userId])){
            return $meta[$userId];
        }else{
            return false;
        }

    }

    public function getWatchesOnNode($node, $watchType){

        $IDS = array();
        $meta = $this->metaStore->retrieveMetadata(
            $node,
            self::$META_WATCH_NAMESPACE,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        if(AuthService::getLoggedUser() != null){
            $usersMeta = $this->metaStore->retrieveMetadata(
                $node,
                self::$META_WATCH_USERS_NAMESPACE,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }
        if(isSet($meta) && is_array($meta)){
            foreach($meta as $id => $type){
                if($type == $watchType || $type == self::$META_WATCH_BOTH){
                    $IDS[] = $id;
                }
            }
        }
        if(isSet($usersMeta) && is_array($usersMeta)){
            foreach($usersMeta as $id => $targetUsers){
                if(in_array(AuthService::getLoggedUser()->getId(), $targetUsers)){
                    $IDS[] = $id;
                }
            }
        }
        if(count($IDS)){
            $changes = false;
            foreach($IDS as $index => $id){
                if(!AuthService::userExists($id)){
                    $changes = true;
                    unset($meta[$id]);
                    unset($IDS[$index]);
                }
            }
            if($changes){
                $this->metaStore->setMetadata(
                    $node,
                    self::$META_WATCH_NAMESPACE,
                    $meta,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
            }
        }
        return $IDS;

    }

    public function switchActions($actionName, $httpVars, $fileVars){


        switch ($actionName){

            case "toggle_watch":

                $us = new UserSelection();
                $us->initFromHttpVars($httpVars);
                $node = $us->getUniqueNode($this->accessDriver);
                $node->loadNodeInfo();
                $cmd = $httpVars["watch_action"];
                $stopWatch = false;

                $meta = $this->metaStore->retrieveMetadata(
                    $node,
                    self::$META_WATCH_NAMESPACE,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                $userId = AuthService::getLoggedUser()!= null ? AuthService::getLoggedUser()->getId() : "shared";

                if($cmd == "watch_stop" && isSet($meta) && isSet($meta[$userId])){
                    unset($meta[$userId]);
                    $this->metaStore->removeMetadata(
                        $node,
                        self::$META_WATCH_NAMESPACE,
                        false,
                        AJXP_METADATA_SCOPE_REPOSITORY
                    );
                }else{
                    switch($cmd){
                        case "watch_change": $type = self::$META_WATCH_CHANGE;break;
                        case "watch_read": $type = self::$META_WATCH_READ; break;
                        case "watch_both": $type = self::$META_WATCH_BOTH; break;
                        default: break;
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

                AJXP_XMLWriter::header();
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();

                break;

            default:
            break;

        }
    }


    public function processChangeHook(AJXP_Node $oldNode=null, AJXP_Node $newNode=null, $copy = false){

        $newNotif = $this->notificationCenter->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "new");
        if($newNotif !== false && $newNotif->getNode() !== false){
            $ids = $this->getWatchesOnNode($newNode, self::$META_WATCH_CHANGE);
            if(count($ids)){
                foreach($ids as $id) $this->notificationCenter->postNotification($newNotif, $id);
            }
            $parentNode = new AJXP_Node(dirname($newNode->getUrl()));
            $ids = $this->getWatchesOnNode($parentNode, self::$META_WATCH_CHANGE);
            if(count($ids)){
                // POST NOW : PARENT FOLDER IS AFFECTED
                $parentNotif = new AJXP_Notification();
                $parentNotif->setNode($parentNode);
                $parentNotif->setAction(AJXP_NOTIF_NODE_CHANGE);
                $parentNotif->setRelatedNotification($newNotif);
                foreach($ids as $id) $this->notificationCenter->postNotification($parentNotif, $id);
            }
        }
        if($oldNode != null && $newNode != null && $oldNode->getUrl() == $newNode->getUrl()) return;
        $oldNotif =  $this->notificationCenter->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "old");
        if($oldNotif !== false && $oldNotif->getNode() !== false){
            $ids = $this->getWatchesOnNode($oldNode, self::$META_WATCH_CHANGE);
            if(count($ids)){
                foreach($ids as $id) $this->notificationCenter->postNotification($oldNotif, $id);
            }
            $parentNode = new AJXP_Node(dirname($oldNode->getUrl()));
            $ids = $this->getWatchesOnNode($parentNode, self::$META_WATCH_CHANGE);
            if(count($ids)){
                // POST NOW : PARENT FOLDER IS AFFECTED
                $parentNotif = new AJXP_Notification();
                $parentNotif->setNode($parentNode);
                $parentNotif->setAction(AJXP_NOTIF_NODE_CHANGE);
                $parentNotif->setRelatedNotification($oldNotif);
                foreach($ids as $id) $this->notificationCenter->postNotification($parentNotif, $id);
            }
        }

        $this->updateMetaLocation($oldNode, $newNode, $copy);

    }

    public function processReadHook(AJXP_Node $node){

        $ids = $this->getWatchesOnNode($node, self::$META_WATCH_READ);
        $notif = new AJXP_Notification();
        $notif->setAction(AJXP_NOTIF_NODE_VIEW);
        $notif->setNode($node);
        if(count($ids)){
            foreach($ids as $id) $this->notificationCenter->postNotification($notif, $id);
        }
        $parentNode = new AJXP_Node(dirname($node->getUrl()));
        $ids = $this->getWatchesOnNode($parentNode, self::$META_WATCH_READ);
        if(count($ids)){
            // POST NOW : PARENT FOLDER IS AFFECTED
            $parentNotif = new AJXP_Notification();
            $parentNotif->setNode($parentNode);
            $parentNotif->setAction(AJXP_NOTIF_NODE_VIEW);
            $parentNotif->setRelatedNotification($notif);
            foreach($ids as $id) $this->notificationCenter->postNotification($parentNotif, $id);
        }

    }

    /**
     * @param AJXP_Node $node
     */
    public function enrichNode($node){
        if(AuthService::getLoggedUser() == null) return;
        $meta = $this->metaStore->retrieveMetadata(
            $node,
            self::$META_WATCH_NAMESPACE,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY);
        if(is_array($meta)
            && array_key_exists(AuthService::getLoggedUser()->getId(), $meta)){
            $node->mergeMetadata(array(
                "meta_watched" => $meta[AuthService::getLoggedUser()->getId()],
                "overlay_icon" => "meta.watch/ICON_SIZE/watch.png"
            ), true);
        }
    }

    /**
     *
     * @param AJXP_Node $oldFile
     * @param AJXP_Node $newFile
     * @param Boolean $copy
     */
    public function updateMetaLocation($oldFile, $newFile = null, $copy = false){
        if($oldFile == null) return;
        if(!$copy && $this->metaStore->inherentMetaMove()) return;

        $oldMeta = $this->metaStore->retrieveMetadata($oldFile, self::$META_WATCH_NAMESPACE, false, AJXP_METADATA_SCOPE_REPOSITORY);
        if(count($oldMeta)){
            // If it's a move or a delete, delete old data
            if(!$copy){
                $this->metaStore->removeMetadata($oldFile, self::$META_WATCH_NAMESPACE, false, AJXP_METADATA_SCOPE_REPOSITORY);
            }
            // If copy or move, copy data.
            if($newFile != null){
                $this->metaStore->setMetadata($newFile, self::$META_WATCH_NAMESPACE, $oldMeta, false, AJXP_METADATA_SCOPE_REPOSITORY);
            }
        }

    }

}