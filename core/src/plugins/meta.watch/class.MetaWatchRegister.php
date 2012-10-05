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

    static $META_NAMESPACE_WATCH_CHANGE = "META_WATCH_CHANGE";
    static $META_NAMESPACE_WATCH_READ = "META_WATCH_READ";

    /**
     * @var MetaStoreProvider
     */
    protected $metaStore;

    /**
     * @var AbstractAccessDriver
     */
    protected $accessDriver;

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if($store === false){
            throw new Exception("The 'meta.watch' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->initMeta($accessDriver);
    }

    /**
     * @param AJXP_Node $node
     * @param $userId
     * @param $namespace
     */
    public function setWatchOnFolder($node, $userId, $namespace){

        $meta = $this->metaStore->retrieveMetadata(
            $node,
            $namespace,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        if(isSet($meta) && isSet($meta[$userId])){
            unset($meta[$userId]);
            $this->metaStore->removeMetadata($node, $namespace, false, AJXP_METADATA_SCOPE_REPOSITORY);
        }
        $meta[$userId] = true;
        if(count($meta)){
            $this->metaStore->setMetadata(
                $node,
                $namespace,
                $meta,
                false,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }

    }

    /**
     * @param AJXP_Node $node
     * @param $userId
     * @param $namespace
     */
    public function removeWatchFromFolder($node, $userId, $namespace){

        $meta = $this->metaStore->retrieveMetadata(
            $node,
            $namespace,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        if(isSet($meta) && isSet($meta[$userId])){
            unset($meta[$userId]);
            $this->metaStore->removeMetadata($node, $namespace, false, AJXP_METADATA_SCOPE_REPOSITORY);
        }

    }

    /**
     * @param AJXP_Node $node
     * @param $userId
     * @param $namespace
     * @param bool $checkUserExists
     * @return bool
     */
    public function hasWatchOnNode($node, $userId, $namespace){

        $meta = $this->metaStore->retrieveMetadata(
            $node,
            $namespace,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        return (isSet($meta) && isSet($meta[$userId]));

    }

    public function getWatchesOnNode($node, $namespace){

        $IDS = array();
        $meta = $this->metaStore->retrieveMetadata(
            $node,
            $namespace,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        if(isSet($meta)){
            $IDS = array_keys($meta);
        }
        if(count($IDS)){
            $changes = false;
            foreach($IDS as $id){
                if(!AuthService::userExists($id)){
                    $changes = true;
                    unset($meta[$id]);
                }
            }
            if($changes){
                $this->metaStore->setMetadata(
                    $node,
                    $namespace,
                    $meta,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                $IDS = array_keys($meta);
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
                if(strpos($cmd, "watch_stop_") === 0){
                    $namespace = ($cmd == "watch_stop_change" ? self::$META_NAMESPACE_WATCH_CHANGE : self::$META_NAMESPACE_WATCH_READ);
                }else{
                    $namespace = ($cmd == "watch_change" ? self::$META_NAMESPACE_WATCH_CHANGE : self::$META_NAMESPACE_WATCH_READ);
                }
                $userId = AuthService::getLoggedUser()!= null ? AuthService::getLoggedUser()->getId() : "shared";

                $meta = $this->metaStore->retrieveMetadata(
                    $node,
                    $namespace,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                if(isSet($meta) && isSet($meta[$userId])){
                    unset($meta[$userId]);
                    $this->metaStore->removeMetadata($node, $namespace, false, AJXP_METADATA_SCOPE_REPOSITORY);
                }else if(strpos($cmd, "watch_stop_") === false){
                    $meta[$userId] = true;
                }

                if(count($meta)){
                    $this->metaStore->setMetadata(
                        $node,
                        $namespace,
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

        $newNotif = AJXP_NotificationCenter::getInstance()->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "new");
        if($newNotif->getNode() !== false){
            $ids = $this->getWatchesOnNode($newNode, self::$META_NAMESPACE_WATCH_CHANGE);
            if(count($ids)){
                foreach($ids as $id) AJXP_NotificationCenter::getInstance()->postNotification($newNotif, $id);
            }
            $parentNode = new AJXP_Node(dirname($newNode->getUrl()));
            $ids = $this->getWatchesOnNode($parentNode, self::$META_NAMESPACE_WATCH_CHANGE);
            if(count($ids)){
                // POST NOW : PARENT FOLDER IS AFFECTED
                $parentNotif = new AJXP_Notification();
                $parentNotif->setNode($parentNode);
                $parentNotif->setAction(AJXP_NOTIF_NODE_CHANGE);
                $parentNotif->setRelatedNotification($newNotif);
                foreach($ids as $id) AJXP_NotificationCenter::getInstance()->postNotification($parentNotif, $id);
            }
        }
        if($oldNode != null && $newNode != null && $oldNode->getUrl() == $newNode->getUrl()) return;
        $oldNotif =  AJXP_NotificationCenter::getInstance()->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "old");
        if($oldNotif->getNode() !== false){
            $ids = $this->getWatchesOnNode($oldNode, self::$META_NAMESPACE_WATCH_CHANGE);
            if(count($ids)){
                foreach($ids as $id) AJXP_NotificationCenter::getInstance()->postNotification($oldNotif, $id);
            }
            $parentNode = new AJXP_Node(dirname($oldNode->getUrl()));
            $ids = $this->getWatchesOnNode($parentNode, self::$META_NAMESPACE_WATCH_CHANGE);
            if(count($ids)){
                // POST NOW : PARENT FOLDER IS AFFECTED
                $parentNotif = new AJXP_Notification();
                $parentNotif->setNode($parentNode);
                $parentNotif->setAction(AJXP_NOTIF_NODE_CHANGE);
                $parentNotif->setRelatedNotification($oldNode);
                foreach($ids as $id) AJXP_NotificationCenter::getInstance()->postNotification($parentNotif, $id);
            }
        }

        $this->updateMetaLocation($oldNode, $newNode, $copy);

    }

    public function processReadHook(AJXP_Node $node){

        $ids = $this->getWatchesOnNode($node, self::$META_NAMESPACE_WATCH_READ);
        $notif = new AJXP_Notification();
        $notif->setAction(AJXP_NOTIF_NODE_VIEW);
        $notif->setNode($node);
        if(count($ids)){
            foreach($ids as $id) AJXP_NotificationCenter::getInstance()->postNotification($notif, $id);
        }
        $parentNode = new AJXP_Node(dirname($node->getUrl()));
        $ids = $this->getWatchesOnNode($parentNode, self::$META_NAMESPACE_WATCH_READ);
        if(count($ids)){
            // POST NOW : PARENT FOLDER IS AFFECTED
            $parentNotif = new AJXP_Notification();
            $parentNotif->setNode($parentNode);
            $parentNotif->setAction(AJXP_NOTIF_NODE_VIEW);
            $parentNotif->setRelatedNotification($notif);
            foreach($ids as $id) AJXP_NotificationCenter::getInstance()->postNotification($parentNotif, $id);
        }

    }

    /**
     * @param AJXP_Node $node
     */
    public function enrichNode($node){
        if(AuthService::getLoggedUser() == null) return;
        $meta = $this->metaStore->retrieveMetadata(
            $node,
            self::$META_NAMESPACE_WATCH_CHANGE,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY);
        if(is_array($meta)
            && array_key_exists(AuthService::getLoggedUser()->getId(), $meta)){
            $node->mergeMetadata(array(
                "meta_watched" => "change",
                "overlay_icon" => "meta.watch/ICON_SIZE/watch.png"
            ), true);
        }
        $meta = $this->metaStore->retrieveMetadata(
            $node,
            self::$META_NAMESPACE_WATCH_READ,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY);
        if(is_array($meta)
            && array_key_exists(AuthService::getLoggedUser()->getId(), $meta)){
            $node->mergeMetadata(array(
                "meta_watched" => "read",
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

        $oldMeta = $this->metaStore->retrieveMetadata($oldFile, self::$META_NAMESPACE_WATCH_CHANGE, false, AJXP_METADATA_SCOPE_REPOSITORY);
        if(count($oldMeta)){
            // If it's a move or a delete, delete old data
            if(!$copy){
                $this->metaStore->removeMetadata($oldFile, self::$META_NAMESPACE_WATCH_CHANGE, false, AJXP_METADATA_SCOPE_REPOSITORY);
            }
            // If copy or move, copy data.
            if($newFile != null){
                $this->metaStore->setMetadata($newFile, self::$META_NAMESPACE_WATCH_CHANGE, $oldMeta, false, AJXP_METADATA_SCOPE_REPOSITORY);
            }
        }

        $oldMeta = $this->metaStore->retrieveMetadata($oldFile, self::$META_NAMESPACE_WATCH_READ, false, AJXP_METADATA_SCOPE_REPOSITORY);
        if(count($oldMeta)){
            // If it's a move or a delete, delete old data
            if(!$copy){
                $this->metaStore->removeMetadata($oldFile, self::$META_NAMESPACE_WATCH_READ, false, AJXP_METADATA_SCOPE_REPOSITORY);
            }
            // If copy or move, copy data.
            if($newFile != null){
                $this->metaStore->setMetadata($newFile, self::$META_NAMESPACE_WATCH_READ, $oldMeta, false, AJXP_METADATA_SCOPE_REPOSITORY);
            }
        }

    }


    //protected function hasWatch

    protected function checkAndNotifyIfNecessary($nameSpace, AJXP_Node $node, $checkParent = false, AJXP_Node $oldNode = null, $copy = false){

        $url = $node->getUrl();
        $metaNode = $node;
        if(!$checkParent){
            if($nameSpace == self::$META_NAMESPACE_WATCH_CHANGE){
                $this->checkAndNotifyIfNecessary($nameSpace, $node, true);
                if($oldNode != null){
                    $this->checkAndNotifyIfNecessary($nameSpace, $oldNode, true);
                }
            }
        }else{
            // We are checking parent, it cannot be a leaf.
            $parent = dirname($url);
            $metaNode = new AJXP_Node($parent);
        }

        $meta = $this->metaStore->retrieveMetadata(
            $metaNode,
            $nameSpace,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );

        if($meta != null && is_array($meta) && count($meta)){
            $changes = false;
            $currentId = (AuthService::getLoggedUser()!=null ? AuthService::getLoggedUser()->getId() : "share");
            foreach(array_keys($meta) as $userId){
                if($currentId == $userId) continue;
                $notification = new AJXP_Notification();
                $notification->setTarget($userId);
                $notification->setNode($metaNode);
                if($checkParent){
                    // IT'S A PARENT FOLDER CHANGE/READ
                    $notification->setAction( ($nameSpace == self::$META_NAMESPACE_WATCH_CHANGE) ? AJXP_NOTIF_NODE_CHANGE : AJXP_NOTIF_NODE_VIEW);
                }else{
                    if($nameSpace == self::$META_NAMESPACE_WATCH_READ){
                        $notification->setAction(AJXP_NOTIF_NODE_VIEW, $metaNode);
                    }
                }
                AJXP_Logger::debug("SHOULD TRIGGER A NOTIFICATION FOR ".$userId." on ITEM ".$metaNode->getUrl());
                if(!AuthService::userExists($userId)){
                    unset($meta[$userId]);
                    $changes = true;
                }
            }
            if($changes){
                $this->metaStore->setMetadata(
                    $metaNode,
                    $nameSpace,
                    $meta,
                    false,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
            }
        }
    }

}