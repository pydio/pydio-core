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

defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * Simple metadata implementation, coupled with an S3 repository, stores
 * the metadata in the s3 bucket
 * @package AjaXplorer_Plugins
 * @subpackage Metastore
 */
class s3MetaStore extends AJXP_AbstractMetaSource implements MetaStoreProvider
{
    private static $currentMetaName;
    private static $metaCache;
    private static $fullMetaCache;

    protected $globalMetaFile;
    protected $bucketName;


    public function init($options)
    {
        $this->options = $options;
        $this->loadRegistryContributions();
        $this->globalMetaFile = AJXP_DATA_PATH."/plugins/metastore.serial/ajxp_meta";
    }

    public function initMeta($accessDriver)
    {
        parent::initMeta($accessDriver);
        $this->bucketName = $this->accessDriver->repository->getOption("CONTAINER");
    }

    /**
     * @abstract
     * @return bool
     */
    public function inherentMetaMove()
    {
        return true;
    }

    /**
     * @param AJXP_Node $node
     * @return string
     */
    protected function getUserId($node)
    {
        if($node->hasUser()) return $node->getUser();
        if(AuthService::usersEnabled()) return AuthService::getLoggedUser()->getId();
        return "shared";
    }

    /**
     * @return \aws\S3\S3Client
     */
    protected function getAwsService()
    {
        if(method_exists($this->accessDriver, "getS3Service")){
            return $this->accessDriver->getS3Service();
        }
        return null;
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @param boolean $create
     * @return String
     */
    private function updateNodeMetaPath($ajxpNode, $create = false){
        $folder = false;
        $trim = trim($ajxpNode->getPath(), "/");
        if($ajxpNode->is_file !== null){
            $folder = !$ajxpNode->isLeaf();
        }else{
            $folder = !is_file($ajxpNode->getUrl());
        }
        if(!$folder) return $trim;
        $meta = is_file(rtrim($ajxpNode->getUrl(), "/")."/.meta");
        if(!$meta){
            if($create) file_put_contents(rtrim($ajxpNode->getUrl(), "/")."/.meta", "meta");
            else return null;
        }
        return $trim."/.meta";
    }

    public function setMetadata($ajxpNode, $nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $aws = $this->getAwsService();
        if($aws == null) return;
        $user = ($private?$this->getUserId($ajxpNode):AJXP_METADATA_SHAREDUSER);
        $pathName = $this->updateNodeMetaPath($ajxpNode, true);
        $response = $aws->copyObject(
            array(
                'Bucket' => $this->bucketName,
                'Key' => $pathName,
                'CopySource' => $this->bucketName."/".rawurlencode($pathName),
                'MetadataDirective' => 'REPLACE',
                'Metadata' => array($this->getMetaKey($nameSpace,$scope,$user) => base64_encode(serialize($metaData)))
            )
        );
        $this->logDebug("UPDATE RESPONSE", $response);
    }

    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $aws = $this->getAwsService();
        if($aws == null) return;
        $user = ($private?$this->getUserId($ajxpNode):AJXP_METADATA_SHAREDUSER);
        $pathName = $this->updateNodeMetaPath($ajxpNode, false);
        if($pathName != null){
            $response = $aws->copyObject(
                array(
                    'Bucket' => $this->bucketName,
                    'Key' => $pathName,
                    'CopySource' => $this->bucketName."/".rawurlencode($pathName),
                    'MetadataDirective' => 'REPLACE',
                    'Metadata' => array($this->getMetaKey($nameSpace,$scope,$user) => "")
                )
            );
        }
    }

    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $aws = $this->getAwsService();
        if($aws == null) return array();

        if (isSet(self::$metaCache[$ajxpNode->getPath()])) {
            $data = self::$metaCache[$ajxpNode->getPath()];
        } else {
            $pathName = $this->updateNodeMetaPath($ajxpNode, false);
            if($pathName == null) return;
            try{
                $response = $aws->headObject(array("Bucket" => $this->bucketName, "Key" => $pathName));
                $metadata = $response["Metadata"];
                if($metadata == null){
                    $metadata = array();
                }
            }catch(Aws\S3\Exception\S3Exception $e){
                $metadata = array();
            }
            self::$metaCache[$ajxpNode->getPath()] = $metadata;
            $data = self::$metaCache[$ajxpNode->getPath()];
        }
        if($private === AJXP_METADATA_ALLUSERS){
            $startKey = $this->getMetaKey($nameSpace, $scope, "");
            $arrMeta = array();
            foreach($data as $k => $mData){
                if(strpos($k, $startKey) === 0){
                    $decData = unserialize(base64_decode($mData));
                    if(is_array($decData)) $arrMeta = array_merge_recursive($arrMeta, $decData);
                }
            }
            return $arrMeta;
        }else{
            $user = ($private?$this->getUserId($ajxpNode):AJXP_METADATA_SHAREDUSER);
            $mKey = $this->getMetaKey($nameSpace,$scope,$user);
            if (isSet($data[$mKey])) {
                $arrMeta =  unserialize(base64_decode($data[$mKey]));
                if(is_array($arrMeta)) return $arrMeta;
            }
        }
        return array();
    }

    private function getMetaKey($namespace, $scope, $user)
    {
        return strtolower($namespace."-".$scope."-".$user);
    }


    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function enrichNode(&$ajxpNode)
    {
        // Try both
        $aws = $this->getAwsService();
        if($aws == null) return;

        if (isSet(self::$metaCache[$ajxpNode->getPath()])) {
            $data = self::$metaCache[$ajxpNode->getPath()];
        } else {
            $this->logDebug("Should retrieve metadata for ".$ajxpNode->getPath());
            $pathName = $this->updateNodeMetaPath($ajxpNode, false);
            if($pathName == null) return;
            try{
                $response = $aws->headObject(array("Bucket" => $this->bucketName, "Key" => $pathName));
                $metadata = $response["Metadata"];
                if($metadata == null){
                    $metadata = array();
                }
            }catch (Aws\S3\Exception\S3Exception $e){
                $metadata = array();
            }
            self::$metaCache[$ajxpNode->getPath()] = $metadata;
            $data = self::$metaCache[$ajxpNode->getPath()];
        }
        $allMeta = array();
        foreach ($data as $amzKey => $value) {
            $parts = explode("-", $amzKey);
            $all[$parts[0]] = $value;
        }

        $ajxpNode->mergeMetadata($allMeta);
    }


}
