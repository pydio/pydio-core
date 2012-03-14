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

defined('AJXP_EXEC') or die('Access not allowed');

class FileHasher extends AJXP_Plugin
{
    protected $accessDriver;
    const METADATA_HASH_NAMESPACE = "file_hahser";
    /**
    * @var SerialMetaStore
    */
    protected $metaStore;

   	public function initMeta($accessDriver){
   		$this->accessDriver = $accessDriver;
        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        //if($store === false){
        //   throw new Exception("The 'meta.simple_lock' plugin requires at least one active 'metastore' plugin");
        //}
        $this->metaStore = $store;
        $this->metaStore->accessDriver = $accessDriver;
    }

    /**
     * @param AJXP_Node $node
     */
    public function getFileHash($node){
        if($node->isLeaf()){
            $md5 = null;
            if($this->metaStore != false){

                $hashMeta = $this->metaStore->retrieveMetadata(
                   $node,
                   FileHasher::METADATA_HASH_NAMESPACE,
                   false,
                   AJXP_METADATA_SCOPE_GLOBAL);
                if(is_array($hashMeta)
                    && array_key_exists("md5", $hashMeta)){
                    $md5 = $hashMeta["md5"];
                }
                if($md5 == null){
                    $md5 = md5_file($node->getUrl());
                    $hashMeta = array("md5" => $md5);
                    $this->metaStore->setMetadata($node, FileHasher::METADATA_HASH_NAMESPACE, $hashMeta, false, AJXP_METADATA_SCOPE_GLOBAL);
                }

            }else{

                $md5 = md5_file($node->getUrl());

            }
            $node->mergeMetadata(array("md5" => $md5));
        }
    }

    public function invalidateHash($oldNode, $newNode, $copy){
        if($this->metaStore == false) return;
        if($oldNode == null) return;
        $this->metaStore->removeMetadata($oldNode, FileHasher::METADATA_HASH_NAMESPACE, false, AJXP_METADATA_SCOPE_GLOBAL);
    }

    /**
     * @param AJXP_Node $node
     */
    public function processLockMeta($node){
        // Transform meta into overlay_icon
        // AJXP_Logger::debug("SHOULD PROCESS METADATA FOR ", $node->getLabel());
        $lock = $this->metaStore->retrieveMetadata(
           $node,
           SimpleLockManager::METADATA_HASH_NAMESPACE,
           false,
           AJXP_METADATA_SCOPE_GLOBAL);
        if(is_array($lock)
            && array_key_exists("lock_user", $lock)){
            if($lock["lock_user"] != AuthService::getLoggedUser()->getId()){
                $node->mergeMetadata(array(
                    "sl_locked" => "true",
                    "overlay_icon" => "meta_simple_lock/ICON_SIZE/lock.png"
                ), true);
            }else{
                $node->mergeMetadata(array(
                    "sl_locked" => "true",
                    "sl_mylock" => "true",
                    "overlay_icon" => "meta_simple_lock/ICON_SIZE/lock_my.png"
                ), true);
            }
        }
    }

    /**
     * @param AJXP_Node $node
     */
    public function checkFileLock($node){
        AJXP_Logger::debug("SHOULD CHECK LOCK METADATA FOR ", $node->getLabel());
        $lock = $this->metaStore->retrieveMetadata(
           $node,
           SimpleLockManager::METADATA_HASH_NAMESPACE,
           false,
           AJXP_METADATA_SCOPE_GLOBAL);
        if(is_array($lock)
            && array_key_exists("lock_user", $lock)
            && $lock["lock_user"] != AuthService::getLoggedUser()->getId()){
            $mess = ConfService::getMessages();
            throw new Exception($mess["meta.simple_lock.5"]);
        }
    }
}
