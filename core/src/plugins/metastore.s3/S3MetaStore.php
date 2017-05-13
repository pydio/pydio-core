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
namespace Pydio\Access\Metastore\Implementation;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Core\Model\ContextInterface;

use Pydio\Access\Meta\Core\AbstractMetaSource;
use Pydio\Access\Metastore\Core\IMetaStoreProvider;

defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * Simple metadata implementation, coupled with an S3 repository, stores
 * the metadata in the s3 bucket
 */
class S3MetaStore extends AbstractMetaSource implements IMetaStoreProvider
{
    private static $metaCache;
    protected $bucketName;
    
    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {
        parent::initMeta($ctx, $accessDriver);
        $this->bucketName = $ctx->getRepository()->getContextOption($ctx, "CONTAINER");
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
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @return string
     */
    protected function getUserId($node)
    {
        if($node->hasUser()) return $node->getUserId();
        return "shared";
    }

    /**
     * @return \aws\S3\S3Client
     */
    protected function getAwsService(ContextInterface $ctx)
    {
        if(method_exists($ctx->getRepository()->getDriverInstance($ctx), "getS3Service")){
            return $ctx->getRepository()->getDriverInstance($ctx)->getS3Service();
        }
        return null;
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @param boolean $create
     * @return String
     */
    private function updateNodeMetaPath($ajxpNode, $create = false){
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

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param array $metaData
     * @param bool $private
     * @param int $scope
     */
    public function setMetadata($ajxpNode, $nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $aws = $this->getAwsService($ajxpNode->getContext());
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

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     */
    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $aws = $this->getAwsService($ajxpNode->getContext());
        if($aws == null) return;
        $user = ($private?$this->getUserId($ajxpNode):AJXP_METADATA_SHAREDUSER);
        $pathName = $this->updateNodeMetaPath($ajxpNode, false);
        if($pathName != null){
            $aws->copyObject(
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

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     * @return array|mixed
     */
    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $aws = $this->getAwsService($ajxpNode->getContext());
        if($aws == null) return array();

        if (isSet(self::$metaCache[$ajxpNode->getPath()])) {
            $data = self::$metaCache[$ajxpNode->getPath()];
        } else {
            $pathName = $this->updateNodeMetaPath($ajxpNode, false);
            if($pathName == null) return [];
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

    /**
     * @param $namespace
     * @param $scope
     * @param $user
     * @return string
     */
    private function getMetaKey($namespace, $scope, $user)
    {
        return strtolower($namespace."-".$scope."-".$user);
    }


    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @return void
     */
    public function enrichNode(&$ajxpNode)
    {
        // Try both
        $aws = $this->getAwsService($ajxpNode->getContext());
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
