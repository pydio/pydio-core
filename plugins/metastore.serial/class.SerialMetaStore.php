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
define('AJXP_METADATA_SHAREDUSER', 'AJXP_METADATA_SHAREDUSER');

define('AJXP_METADATA_SCOPE_GLOBAL', 1);
define('AJXP_METADATA_SCOPE_REPOSITORY', 2);
/**
 * @package info.ajaxplorer.plugins
 * Simple metadata implementation, stored in hidden files inside the
 * folders
 */
class SerialMetaStore extends AJXP_Plugin {
	
	private static $currentMetaName;
	private static $metaCache;
	private static $fullMetaCache;

	public $accessDriver;

	public function init($options){
		$this->options = $options;
        $this->loadRegistryContributions();
	}

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
    }


    public function setMetadata($ajxpNode, $nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
        if(!isSet(self::$metaCache[$nameSpace])){
            self::$metaCache[$nameSpace] = array();
        }
        self::$metaCache[$nameSpace] = array_merge(self::$metaCache[$nameSpace], $metaData);
        $this->saveMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
    }

    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
        if(!isSet(self::$metaCache[$nameSpace])) return;
        unset(self::$metaCache[$nameSpace]);
        $this->saveMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
    }

    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
        if(!isSet(self::$metaCache[$nameSpace])) return array();
        else return self::$metaCache[$nameSpace];
    }



    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
	public function enrichNode(&$ajxpNode){
        // Try both
        $all = array();
        $this->loadMetaFileData($ajxpNode, AJXP_METADATA_SCOPE_GLOBAL, AJXP_METADATA_SHAREDUSER);
        $all[] = self::$metaCache;
        $this->loadMetaFileData($ajxpNode, AJXP_METADATA_SCOPE_GLOBAL, AuthService::getLoggedUser()->getId());
        $all[] = self::$metaCache;
        $this->loadMetaFileData($ajxpNode, AJXP_METADATA_SCOPE_REPOSITORY, AJXP_METADATA_SHAREDUSER);
        $all[] = self::$metaCache;
        $this->loadMetaFileData($ajxpNode, AJXP_METADATA_SCOPE_REPOSITORY, AuthService::getLoggedUser()->getId());
        $all[] = self::$metaCache;
        $allMeta = array();
        foreach($all as $metadata){
            foreach($metadata as $namespace => $meta){
                foreach( $meta as $key => $value){
                    $allMeta[$namespace."-".$key] = $value;
                }
            }
        }
        $ajxpNode->mergeMetadata($allMeta);
	}
	

    
    /**
     * @param AJXP_Node $ajxpNode
     * @param String $scope
     * @param String $userId
     * @return void
     */
	protected function loadMetaFileData($ajxpNode, $scope, $userId){
        $currentFile = $ajxpNode->getUrl();
        $fileKey = $ajxpNode->getPath();
        if($scope == AJXP_METADATA_SCOPE_GLOBAL){
            $metaFile = dirname($currentFile)."/".$this->pluginConf["METADATA_FILE"];
            //if(self::$currentMetaName == $metaFile && is_array(self::$metaCache))return;
            // Cannot store metadata inside zips...
            if(preg_match("/\.zip\//",$currentFile)){
                self::$fullMetaCache = array();
                self::$metaCache = array();
                return ;
            }
            $fileKey = basename($fileKey);
        }else{
            // already loaded?
            //if(is_array(self::$fullMetaCache)) return;
            $metaFile = AJXP_DATA_PATH."/plugins/metastore.serial/".$this->pluginConf["METADATA_FILE"]."_".$ajxpNode->getRepositoryId();
        }
		if(is_file($metaFile) && is_readable($metaFile)){
            self::$currentMetaName = $metaFile;
			$rawData = file_get_contents($metaFile);
            self::$fullMetaCache = unserialize($rawData);
            if(isSet(self::$fullMetaCache[$fileKey][$userId])){
                self::$metaCache = self::$fullMetaCache[$fileKey][$userId];
            }else{
                self::$metaCache = array();
            }
		}else{
            self::$fullMetaCache = array();
			self::$metaCache = array();
		}
	}

    /**
     * @param AJXP_Node $ajxpNode
     * @param String $scope
     * @param String $userId
     */
	protected function saveMetaFileData($ajxpNode, $scope, $userId){
        $currentFile = $ajxpNode->getUrl();
        $repositoryId = $ajxpNode->getRepositoryId();
        $fileKey = $ajxpNode->getPath();
        if($scope == AJXP_METADATA_SCOPE_GLOBAL){
            $metaFile = dirname($currentFile)."/".$this->pluginConf["METADATA_FILE"];
            $fileKey = basename($fileKey);
        }else{
            if(!is_dir(AJXP_DATA_PATH."/plugins/metastore.serial/")){
                mkdir(AJXP_DATA_PATH."/plugins/metastore.serial/", 0666, true);
            }
            $metaFile = AJXP_DATA_PATH."/plugins/metastore.serial/".$this->pluginConf["METADATA_FILE"]."_".$repositoryId;
        }
		if((is_file($metaFile) && call_user_func(array($this->accessDriver, "isWriteable"), $metaFile)) || call_user_func(array($this->accessDriver, "isWriteable"), dirname($metaFile)) || ($scope=="repository") ){
            if(!isset(self::$fullMetaCache[$fileKey])){
                self::$fullMetaCache[$fileKey] = array();
            }
            if(!isset(self::$fullMetaCache[$fileKey][$userId])){
                self::$fullMetaCache[$fileKey][$userId] = array();
            }
            self::$fullMetaCache[$fileKey][$userId] = self::$metaCache;
			$fp = fopen($metaFile, "w");
            if($fp !== false){
                @fwrite($fp, serialize(self::$fullMetaCache), strlen(serialize(self::$fullMetaCache)));
                @fclose($fp);
            }
			AJXP_Controller::applyHook("version.commit_file", $metaFile);
		}
	}
	
}

?>