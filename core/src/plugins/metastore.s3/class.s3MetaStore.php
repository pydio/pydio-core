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
/**
 * @package info.ajaxplorer.plugins
 * Simple metadata implementation, stored in hidden files inside the
 * folders
 */
class s3MetaStore extends AJXP_Plugin implements MetaStoreProvider {
	
	private static $currentMetaName;
	private static $metaCache;
	private static $fullMetaCache;

    protected $globalMetaFile;
    /**
     * @var AbstractAccessDriver
     */
    protected $accessDriver;
    protected $bucketName;


	public function init($options){
		$this->options = $options;
        $this->loadRegistryContributions();
        $this->globalMetaFile = AJXP_DATA_PATH."/plugins/metastore.serial/ajxp_meta";
	}

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
        $this->bucketName = $this->accessDriver->repository->getOption("CONTAINER");
    }


    protected function getUserId(){
        if(AuthService::usersEnabled()) return AuthService::getLoggedUser()->getId();
        return "shared";
    }

    /**
     * @return AmazonS3
     */
    protected function getAwsService(){
        return aS3StreamWrapper::getAWSServiceForProtocol("s3");
    }

    public function setMetadata($ajxpNode, $nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $aws = $this->getAwsService();
        if($aws == null) return;
        $user = ($private?$this->getUserId():AJXP_METADATA_SHAREDUSER);
        $pathName = ltrim($ajxpNode->getPath(), "/");
        $response = $aws->copy_object(
            array('bucket' => $this->bucketName, 'filename' => $pathName),
            array('bucket' => $this->bucketName, 'filename' => $pathName),
            array(
                'metadataDirective' => 'REPLACE',
                'meta' => array($this->getMetaKey($nameSpace,$scope,$user) => base64_encode(serialize($metaData)))
            )
        );
        AJXP_Logger::debug("UPDATE RESPONSE", $response);
    }

    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $aws = $this->getAwsService();
        if($aws == null) return;
        $user = ($private?$this->getUserId():AJXP_METADATA_SHAREDUSER);
        $aws->update_object(
            $this->bucketName,
            ltrim($ajxpNode->getPath(), "/"),
            array("x-amz-meta-".$this->getMetaKey($nameSpace,$scope,$user) => "")
        );
    }

    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $aws = $this->getAwsService();
        if($aws == null) return;
        $user = ($private?$this->getUserId():AJXP_METADATA_SHAREDUSER);

        if(isSet(self::$metaCache[$ajxpNode->getPath()])){
            $data = self::$metaCache[$ajxpNode->getPath()];
        }else{
            $response = $aws->get_object_metadata($this->bucketName, ltrim($ajxpNode->getPath(), "/"));
            self::$metaCache[$ajxpNode->getPath()] = $response["Headers"];
            $data = self::$metaCache[$ajxpNode->getPath()];
        }
        $mKey = $this->getMetaKey($nameSpace,$scope,$user);
        if(isSet($data["x-amz-meta-".$mKey])){
            $arrMeta =  unserialize(base64_decode($data["x-amz-meta-".$mKey]));
            if(is_array($arrMeta)) return $arrMeta;
        }
    }

    private function getMetaKey($namespace, $scope, $user){
        return strtolower($namespace."-".$scope."-".$user);
    }


    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
	public function enrichNode(&$ajxpNode){
        // Try both
        $aws = $this->getAwsService();
        if($aws == null) return;

        if(isSet(self::$metaCache[$ajxpNode->getPath()])){
            $data = self::$metaCache[$ajxpNode->getPath()];
        }else{
            AJXP_Logger::debug("Should retrieve metadata for ".$ajxpNode->getPath());
            $response = $aws->get_object_metadata($this->bucketName, ltrim($ajxpNode->getPath(), "/"));
            self::$metaCache[$ajxpNode->getPath()] = $response["Headers"];
            $data = self::$metaCache[$ajxpNode->getPath()];
        }
        $allMeta = array();
        foreach($data as $amzKey => $value){
            $parts = explode("-", $amzKey);
            $all[$parts[0]] = $value;
        }

        $ajxpNode->mergeMetadata($allMeta);
	}

	
}

?>