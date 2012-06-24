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
    * @var MetaStoreProvider
    */
    protected $metaStore;

    public static function rsyncEnabled(){
        return function_exists("rsync_generate_signature");
    }

    public function parseSpecificContributions(&$contribNode){
        parent::parseSpecificContributions($contribNode);
        if(!self::rsyncEnabled()){
            // REMOVE rsync actions get_filesignature
        }
    }

   	public function initMeta($accessDriver){
   		$this->accessDriver = $accessDriver;
        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        //if($store === false){
        //   throw new Exception("The 'meta.simple_lock' plugin requires at least one active 'metastore' plugin");
        //}
        $this->metaStore = $store;
        $this->metaStore->initMeta($accessDriver);
    }

    public function switchActions($actionName, $httpVars, $fileVars){
        //$urlBase = $this->accessDriver
        $repository = ConfService::getRepository();
        if(!$repository->detectStreamWrapper(true)){
            return false;
        }
        if(!isSet($this->pluginConf)){
            $this->pluginConf = array("GENERATE_THUMBNAIL"=>false);
        }
        $streamData = $repository->streamData;
        $this->streamData = $streamData;
        $destStreamURL = $streamData["protocol"]."://".$repository->getId();
        switch($actionName){
            case "filehasher_signature":
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                if(!file_exists($destStreamURL.$file)) break;
                $cacheItem = AJXP_Cache::getItem("signatures", $destStreamURL.$file, array($this, "generateSignature"));
                $data = $cacheItem->getData();
                header("Content-Type:application/octet-stream");
                header("Content-Length", strlen($data));
                echo($data);
            break;
            case "filehasher_delta":
            case "filehasher_patch":
                // HANDLE UPLOAD DATA
                if(!isSet($fileVars) && !is_array($fileVars["userfile_0"])) {
                    throw new Exception("These action should find uploaded data");
                }
                $uploadedData = tempnam(AJXP_Utils::getAjxpTmpDir(), $actionName."-sig");
                move_uploaded_file($fileVars["userfile_0"]["tmp_name"], $uploadedData);

                $fileUrl = $destStreamURL.AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $file = call_user_func(array($this->streamData["classname"], "getRealFSReference"), $fileUrl, true);
                if($actionName == "filehasher_delta"){
                    $signatureFile = $uploadedData;
                    $deltaFile = tempnam(AJXP_Utils::getAjxpTmpDir(), $actionName."-delta");
                    rsync_generate_delta($signatureFile, $file, $deltaFile);
                    header("Content-Type:application/octet-stream");
                    header("Content-Length:".filesize($deltaFile));
                    readfile($deltaFile);
                    unlink($signatureFile);
                    unlink($deltaFile);
                }else{
                    $patched = $file.".rdiff_patched";
                    $deltaFile = $uploadedData;
                    rsync_patch_file($file, $deltaFile, $patched);
                    rename($patched, $file);
                    header("Content-Type:text/plain");
                    echo md5_file($file);
                }

            break;
        }
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
                $mtime = filemtime($node->getUrl());
                if(is_array($hashMeta)
                    && array_key_exists("md5", $hashMeta)
                    && array_key_exists("md5_mtime", $hashMeta)
                    && $hashMeta["md5_mtime"] >= $mtime){
                    $md5 = $hashMeta["md5"];
                }
                if($md5 == null){
                    $md5 = md5_file($node->getUrl());
                    $hashMeta = array(
                        "md5" => $md5,
                        "md5_mtime" => $mtime
                    );
                    $this->metaStore->setMetadata($node, FileHasher::METADATA_HASH_NAMESPACE, $hashMeta, false, AJXP_METADATA_SCOPE_GLOBAL);
                }

            }else{

                $md5 = md5_file($node->getUrl());

            }
            $node->mergeMetadata(array("md5" => $md5));
        }
    }

    public function invalidateHash($oldNode = null, $newNode = null, $copy = false){
        if($this->metaStore == false) return;
        if($oldNode == null) return;
        $this->metaStore->removeMetadata($oldNode, FileHasher::METADATA_HASH_NAMESPACE, false, AJXP_METADATA_SCOPE_GLOBAL);
    }


    public function generateSignature($masterFile, $targetFile){
        rsync_generate_signature($masterFile, $targetFile);
    }
}
