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
     * @return bool
     */
    public function hasWatchOnFolder($node, $userId, $namespace){

        $meta = $this->metaStore->retrieveMetadata(
            $node,
            $namespace,
            false,
            AJXP_METADATA_SCOPE_REPOSITORY
        );
        return (isSet($meta) && isSet($meta[$userId]));

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

        if($oldNode != null){
            $this->checkAndNotifyIfNecessary(self::$META_NAMESPACE_WATCH_CHANGE, $oldNode);
        }
        if($newNode != null){
            if($oldNode == null || dirname($newNode->getUrl()) != dirname($oldNode->getUrl())){
                $this->checkAndNotifyIfNecessary(self::$META_NAMESPACE_WATCH_CHANGE,$newNode);
            }
        }

    }

    public function processReadHook(AJXP_Node $node){

        //AJXP_Logger::debug("READ TRIGGER ON NODE ".$node->getUrl());
        $this->checkAndNotifyIfNecessary(self::$META_NAMESPACE_WATCH_READ,$node);

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


    protected function checkAndNotifyIfNecessary($nameSpace, AJXP_Node $node, $checkParent = false){

        $url = $node->getUrl();
        $metaNode = $node;
        if(!$checkParent){
            $isLeaf = $node->isLeaf();
            if($nameSpace == self::$META_NAMESPACE_WATCH_CHANGE){
                $this->checkAndNotifyIfNecessary($nameSpace, $node, true);
            }
        }else{
            // We are checking parent, it cannot be a leaf.
            $isLeaf = false;
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