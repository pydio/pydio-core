<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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

defined('AJXP_EXEC') or die('Access not allowed');
define('AJXP_SHARED_META_NAMESPACE', 'ajxp_shared');

class ShareMetaManager
{
    /**
     * @var ShareStore
     */
    var $shareStore;

    /**
     * ShareMetaManager constructor.
     * @param ShareStore $shareStore
     */
    public function __construct($shareStore){
        $this->shareStore = $shareStore;
    }

    /**
     * @param AJXP_Node $node
     * @return array mixed
     */
    public function getNodeMeta($node){
        $private = $node->retrieveMetadata(AJXP_SHARED_META_NAMESPACE, true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        $shared  = $node->retrieveMetadata(AJXP_SHARED_META_NAMESPACE, false, AJXP_METADATA_SCOPE_REPOSITORY, true);
        return array_merge_recursive($private, $shared);
    }

    /**
     * @param AJXP_Node $node
     * @param array $meta
     * @param bool $private
     */
    public function setNodeMeta($node, $meta, $private = true){
        $otherScope = ! $private;
        $otherScopeMeta = $node->retrieveMetadata(AJXP_SHARED_META_NAMESPACE, $otherScope, AJXP_METADATA_SCOPE_REPOSITORY, true);
        // The scope has changed. Let's clear the old value
        if(count($otherScopeMeta)){
            $node->removeMetadata(AJXP_SHARED_META_NAMESPACE, $otherScope, AJXP_METADATA_SCOPE_REPOSITORY, true);
        }
        $node->setMetadata(AJXP_SHARED_META_NAMESPACE, $meta, $private, AJXP_METADATA_SCOPE_REPOSITORY, true);
    }

    /**
     * @param AJXP_Node $node
     */
    public function clearNodeMeta($node){
        // Try to remove both scopes
        $node->removeMetadata(AJXP_SHARED_META_NAMESPACE, true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        $node->removeMetadata(AJXP_SHARED_META_NAMESPACE, false, AJXP_METADATA_SCOPE_REPOSITORY, true);
    }


    /**
     * @param AJXP_Node $node
     * @param $shareType
     * @param $shareId
     * @param $publicScope
     * @param string|null $originalShareId
     */
    public function addShareInMeta($node, $shareType, $shareId, $publicScope, $originalShareId=null){
        $shares = array();
        $this->shareStore->getMetaManager()->getSharesFromMeta($node, $shares, false);
        if(empty($shares)){
            $shares = array();
        }
        if(!empty($shares) && $originalShareId !== null && isSet($shares[$originalShareId])){
            unset($shares[$originalShareId]);
        }
        $shares[$shareId] = array("type" => $shareType);
        $this->setNodeMeta($node, array("shares" => $shares), !$publicScope);
    }


    /**
     * @param AJXP_Node $node
     * @param $shareId
     */
    public function removeShareFromMeta($node, $shareId){
        $shares = array();
        $this->shareStore->getMetaManager()->getSharesFromMeta($node, $shares);
        if(!empty($shares) && isSet($shares[$shareId])){
            unset($shares[$shareId]);
            if(count($shares)){
                $this->setNodeMeta($node, array("shares" => $shares));
            }else{
                $this->clearNodeMeta($node);
            }
        }

    }

    /**
     * @param AJXP_Node $node
     * @param $metas
     */
    public function collectSharesInParent($node, &$metas){
        $node->collectMetadataInParents(AJXP_SHARED_META_NAMESPACE, AJXP_METADATA_ALLUSERS, AJXP_METADATA_SCOPE_REPOSITORY, false, $metas);
    }

    /**
     * @param AJXP_Node $node
     * @return array
     */
    public function collectSharesIncludingChildren($node){
        return $node->collectRepositoryMetadatasInChildren(AJXP_SHARED_META_NAMESPACE, AJXP_METADATA_ALLUSERS);
    }

    /**
     * @param AJXP_Node $node
     * @param array $shares
     * @param bool $clearIfEmpty
     */
    public function getSharesFromMeta($node, &$shares, $clearIfEmpty = false){

        $meta = $this->getNodeMeta($node);

        // NEW FORMAT
        if(isSet($meta["shares"])){
            $shares = array();
            $update = false;
            foreach($meta["shares"] as $hashOrRepoId => $shareData){
                $type = $shareData["type"];
                if(is_array($type)) {
                    $shareData["type"] = $type[0];
                }
                if(!$clearIfEmpty || $this->shareStore->shareExists($shareData["type"],$hashOrRepoId)){
                    $shares[$hashOrRepoId] = $shareData;
                }else{
                    $update = true;
                }
            }
            if($update && !count($shares)){
                $this->clearNodeMeta($node);
            }
            return;
        }

        // OLD FORMAT
        if(isSet($meta["minisite"])){
            $type = "minisite";
        }else{
            $type = "detect";
        }
        $els = array();
        if(is_string($meta["element"])) $els[] = $meta["element"];
        else if (is_array($meta["element"])) $els = $meta["element"];
        if($clearIfEmpty){
            $update = false;
            $shares = array();
            foreach($els as $hashOrRepoId => $additionalData){
                if(is_string($additionalData)) {
                    $hashOrRepoId = $additionalData;
                    $additionalData = array();
                }
                if($type == "detect"){
                    if(ConfService::getRepositoryById($hashOrRepoId) != null) $type = "repository";
                    else $type = "file";
                }
                if($this->shareStore->shareExists($type,$hashOrRepoId)){
                    $shares[$hashOrRepoId] = array_merge($additionalData, array("type" => $type));
                }else{
                    $update = true;
                }
            }
            if($update && !count($shares)){
                $this->clearNodeMeta($node);
            }
        }else{
            $shares = $els;
        }

    }

}